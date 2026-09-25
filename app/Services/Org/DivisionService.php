<?php

namespace App\Services\Org;

use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for Division business logic (create/update, code
 * immutability, dependency-checked disable, media, and the pre-existing
 * "can't activate under an inactive Department" cross-entity rule). The
 * controller only handles HTTP concerns and delegates everything else here.
 */
class DivisionService
{
    /** Division is a child of Department — Employee (via its primary division) is its only dependent. */
    private const DEPENDENTS = [
        [Employee::class, 'primary_div_code', 'employee', 'employment_status', 'active'],
    ];

    public function create(array $validated, Request $request): Division
    {
        $this->validateParentDepartmentActive($validated['dept_code'] ?? null, (bool) ($validated['is_active'] ?? false));

        $division = Division::create($validated);

        $this->syncMedia($request, $division);

        return $division;
    }

    /**
     * @return array{ok: true, division: Division}|array{ok: false, blockers: array<int, string>}
     */
    public function update(Division $division, array $validated, Request $request): array
    {
        // Code is the real primary key every relation points at by string — never editable.
        unset($validated['code']);

        $this->validateParentDepartmentActive($validated['dept_code'] ?? null, (bool) ($validated['is_active'] ?? false));

        $wasActive = $division->is_active;
        $willBeActive = (bool) ($validated['is_active'] ?? false);

        $blockers = OrgEntityGuard::blockersForDisabling($wasActive, $willBeActive, $division->code, self::DEPENDENTS);
        if ($blockers) {
            return ['ok' => false, 'blockers' => $blockers];
        }

        $division->update($validated);
        $this->syncMedia($request, $division);

        return ['ok' => true, 'division' => $division->fresh()];
    }

    /** A Division can't be (re)activated while its parent Department is inactive. */
    private function validateParentDepartmentActive(?string $deptCode, bool $willBeActive): void
    {
        if (! $deptCode || ! $willBeActive) {
            return;
        }

        $department = Department::where('code', $deptCode)->first();

        if ($department && ! $department->is_active) {
            throw ValidationException::withMessages([
                'is_active' => 'Division cannot be activated because its Department is inactive.',
            ]);
        }
    }

    private function syncMedia(Request $request, Division $division): void
    {
        if ($request->boolean('remove_image')) {
            $division->clearMediaCollection('division_image');
        }

        if ($request->hasFile('division_image')) {
            $division->addMediaFromRequest('division_image')->toMediaCollection('division_image');
        }

        if ($request->hasFile('documents')) {
            foreach ((array) $request->file('documents') as $file) {
                $division->addMedia($file)->toMediaCollection('documents');
            }
        }

        foreach ((array) $request->input('remove_documents', []) as $mediaId) {
            $division->media()->where('id', $mediaId)->where('collection_name', 'documents')->first()?->delete();
        }
    }
}
