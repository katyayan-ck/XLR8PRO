<?php

namespace App\Services\Org;

use App\Models\Admin\Employee;
use App\Models\Admin\Vertical;
use Illuminate\Http\Request;

/**
 * Single source of truth for Vertical business logic (create/update, code
 * immutability, dependency-checked disable, media). The controller only
 * handles HTTP concerns and delegates everything else here.
 */
class VerticalService
{
    /**
     * Vertical is standalone — Employee (via its direct vertical_code column) is its only
     * dependent. `Vertical::employees()`/`employeeAssignments()` point at
     * xlr8_admin_emp_vertical_pivot, which doesn't exist (see BUG-081) — using the real,
     * existing employee.vertical_code column instead, same pattern as the other org entities.
     */
    private const DEPENDENTS = [
        [Employee::class, 'vertical_code', 'employee', 'employment_status', 'active'],
    ];

    public function create(array $validated, Request $request): Vertical
    {
        $vertical = Vertical::create($validated);

        $this->syncMedia($request, $vertical);

        return $vertical;
    }

    /**
     * @return array{ok: true, vertical: Vertical}|array{ok: false, blockers: array<int, string>}
     */
    public function update(Vertical $vertical, array $validated, Request $request): array
    {
        // Code is the real primary key every relation points at by string — never editable.
        unset($validated['code']);

        $wasActive = $vertical->is_active;
        $willBeActive = (bool) ($validated['is_active'] ?? false);

        $blockers = OrgEntityGuard::blockersForDisabling($wasActive, $willBeActive, $vertical->code, self::DEPENDENTS);
        if ($blockers) {
            return ['ok' => false, 'blockers' => $blockers];
        }

        $vertical->update($validated);
        $this->syncMedia($request, $vertical);

        return ['ok' => true, 'vertical' => $vertical->fresh()];
    }

    private function syncMedia(Request $request, Vertical $vertical): void
    {
        if ($request->boolean('remove_image')) {
            $vertical->clearMediaCollection('vertical_image');
        }

        if ($request->hasFile('vertical_image')) {
            $vertical->addMediaFromRequest('vertical_image')->toMediaCollection('vertical_image');
        }

        if ($request->hasFile('documents')) {
            foreach ((array) $request->file('documents') as $file) {
                $vertical->addMedia($file)->toMediaCollection('documents');
            }
        }

        foreach ((array) $request->input('remove_documents', []) as $mediaId) {
            $vertical->media()->where('id', $mediaId)->where('collection_name', 'documents')->first()?->delete();
        }
    }
}
