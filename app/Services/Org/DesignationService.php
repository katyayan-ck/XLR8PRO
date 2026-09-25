<?php

namespace App\Services\Org;

use App\Models\Admin\Designation;
use App\Models\Admin\Employee;
use App\Services\IAM\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for Designation business logic (create/update, code
 * immutability, dependency-checked disable, media, and its role-permission
 * assignment — Designation doubles as a Spatie role). The controller only
 * handles HTTP concerns (auth checks, request validation, redirects) and
 * delegates everything else here.
 */
class DesignationService
{
    /**
     * Designation is a standalone org entity — Employee is its only dependent.
     * Employee has no is_active column; "active" there is employment_status = 'active'.
     */
    private const DEPENDENTS = [
        [Employee::class, 'designation_code', 'employee', 'employment_status', 'active'],
    ];

    public function __construct(private RolePermissionService $rolePermissions) {}

    public function create(array $validated, Request $request): Designation
    {
        $validated['rank'] = $validated['rank'] ?? 0;
        $validated['guard_name'] = 'web';

        $this->validateReportsTo($validated['parent_desig_code'] ?? null, (int) $validated['rank']);

        $designation = Designation::create($validated);

        $this->syncMedia($request, $designation);

        return $designation;
    }

    /**
     * @return array{ok: true, designation: Designation}|array{ok: false, blockers: array<int, string>}
     */
    public function update(Designation $designation, array $validated, Request $request): array
    {
        // Code is the real primary key every relation points at by string — never editable.
        unset($validated['code']);

        $validated['rank'] = $validated['rank'] ?? 0;
        $validated['guard_name'] = 'web';

        $this->validateReportsTo($validated['parent_desig_code'] ?? null, (int) $validated['rank']);

        $wasActive = $designation->is_active;
        $willBeActive = (bool) ($validated['is_active'] ?? false);

        $blockers = OrgEntityGuard::blockersForDisabling($wasActive, $willBeActive, $designation->code, self::DEPENDENTS);
        if ($blockers) {
            return ['ok' => false, 'blockers' => $blockers];
        }

        $designation->update($validated);
        $this->syncMedia($request, $designation);

        return ['ok' => true, 'designation' => $designation->fresh()];
    }

    /** @param  array<int, string>  $permissionCodes */
    public function syncPermissions(Designation $designation, array $permissionCodes): void
    {
        $this->rolePermissions->syncRolePermissions($designation, $permissionCodes);
    }

    /** @return array<int, string> */
    public function currentPermissionCodes(Designation $designation): array
    {
        return $this->rolePermissions->currentPermissionCodes($designation);
    }

    /**
     * A designation can only report to an existing designation of the same or
     * higher rank (rank 1/A is the highest, rank 5/E the lowest — so "same or
     * higher" means the parent's rank number must be <= this designation's).
     */
    private function validateReportsTo(?string $parentCode, int $rank): void
    {
        if (! $parentCode || $rank <= 0) {
            return;
        }

        $parent = Designation::where('code', $parentCode)->first();

        if ($parent && (int) $parent->rank > 0 && (int) $parent->rank > $rank) {
            throw ValidationException::withMessages([
                'parent_desig_code' => 'The designation reported to must be of the same or higher rank (A is highest, E is lowest).',
            ]);
        }
    }

    private function syncMedia(Request $request, Designation $designation): void
    {
        if ($request->boolean('remove_image')) {
            $designation->clearMediaCollection('designation_image');
        }

        if ($request->hasFile('designation_image')) {
            $designation->addMediaFromRequest('designation_image')->toMediaCollection('designation_image');
        }

        if ($request->hasFile('documents')) {
            foreach ((array) $request->file('documents') as $file) {
                $designation->addMedia($file)->toMediaCollection('documents');
            }
        }

        foreach ((array) $request->input('remove_documents', []) as $mediaId) {
            $designation->media()->where('id', $mediaId)->where('collection_name', 'documents')->first()?->delete();
        }
    }
}
