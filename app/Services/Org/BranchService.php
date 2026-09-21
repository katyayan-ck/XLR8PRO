<?php

namespace App\Services\Org;

use App\Models\Admin\Branch;
use App\Models\Admin\Employee;
use App\Models\Admin\Location;
use Illuminate\Http\Request;

/**
 * Single source of truth for Branch business logic (create/update, code
 * immutability, dependency-checked disable, media, single-head-office
 * enforcement). The controller only handles HTTP concerns and delegates
 * everything else here.
 */
class BranchService
{
    /**
     * Branch is the parent of Location and (via primary_branch_code) of Employee.
     * `Branch::primaryEmployees()` joins on the model's own `branch_code` column,
     * which is never populated (not fillable, always NULL in real data — see
     * BUG-082) — every real primary_branch_code value actually matches Branch's
     * `code` column instead, so the dependents list below uses `code` directly,
     * same as Location's own (correctly-written) relation already does.
     */
    private const DEPENDENTS = [
        [Location::class, 'branch_code', 'location'],
        [Employee::class, 'primary_branch_code', 'employee', 'employment_status', 'active'],
    ];

    public function create(array $validated, Request $request): Branch
    {
        if ($validated['is_head_office'] ?? false) {
            Branch::where('is_head_office', true)->update(['is_head_office' => false]);
        }

        $branch = Branch::create($validated);

        $this->syncMedia($request, $branch);

        return $branch;
    }

    /**
     * @return array{ok: true, branch: Branch}|array{ok: false, blockers: array<int, string>}
     */
    public function update(Branch $branch, array $validated, Request $request): array
    {
        // Code is the real primary key every relation points at by string — never editable.
        unset($validated['code']);

        $wasActive = $branch->is_active;
        $willBeActive = (bool) ($validated['is_active'] ?? false);

        $blockers = OrgEntityGuard::blockersForDisabling($wasActive, $willBeActive, $branch->code, self::DEPENDENTS);
        if ($blockers) {
            return ['ok' => false, 'blockers' => $blockers];
        }

        if ($validated['is_head_office'] ?? false) {
            Branch::where('id', '!=', $branch->id)->where('is_head_office', true)->update(['is_head_office' => false]);
        }

        $branch->update($validated);
        $this->syncMedia($request, $branch);

        return ['ok' => true, 'branch' => $branch->fresh()];
    }

    private function syncMedia(Request $request, Branch $branch): void
    {
        if ($request->boolean('remove_image')) {
            $branch->clearMediaCollection('branch_image');
        }

        if ($request->hasFile('branch_image')) {
            $branch->addMediaFromRequest('branch_image')->toMediaCollection('branch_image');
        }

        if ($request->hasFile('documents')) {
            foreach ((array) $request->file('documents') as $file) {
                $branch->addMedia($file)->toMediaCollection('documents');
            }
        }

        foreach ((array) $request->input('remove_documents', []) as $mediaId) {
            $branch->media()->where('id', $mediaId)->where('collection_name', 'documents')->first()?->delete();
        }
    }
}
