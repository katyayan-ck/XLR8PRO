<?php

namespace App\Services\Org;

use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use Illuminate\Http\Request;

/**
 * Single source of truth for Department business logic (create/update, code
 * immutability, dependency-checked disable, media). The controller only
 * handles HTTP concerns (auth checks, request validation, redirects) and
 * delegates everything else here.
 */
class DepartmentService
{
    /**
     * Department is the parent of Division and (via primary_dept_code) of
     * Employee. Employee has no is_active column — "active" there is
     * employment_status = 'active'.
     */
    private const DEPENDENTS = [
        [Division::class, 'dept_code', 'division'],
        [Employee::class, 'primary_dept_code', 'employee', 'employment_status', 'active'],
    ];

    public function create(array $validated, Request $request): Department
    {
        $department = Department::create($validated);

        $this->syncMedia($request, $department);

        // Every new Department starts with a same-coded default Division — pre-existing
        // behaviour, kept as-is; out of scope for the code-immutability/dependency-guard work.
        Division::create([
            'dept_code' => $department->code,
            'code' => $department->code,
            'name' => $department->name,
            'is_active' => true,
        ]);

        return $department;
    }

    /**
     * @return array{ok: true, department: Department}|array{ok: false, blockers: array<int, string>}
     */
    public function update(Department $department, array $validated, Request $request): array
    {
        // Code is the real primary key every relation points at by string — never editable.
        unset($validated['code']);

        $wasActive = $department->is_active;
        $willBeActive = (bool) ($validated['is_active'] ?? false);

        $blockers = OrgEntityGuard::blockersForDisabling($wasActive, $willBeActive, $department->code, self::DEPENDENTS);
        if ($blockers) {
            return ['ok' => false, 'blockers' => $blockers];
        }

        $department->update($validated);
        $this->syncMedia($request, $department);

        return ['ok' => true, 'department' => $department->fresh()];
    }

    private function syncMedia(Request $request, Department $department): void
    {
        if ($request->boolean('remove_image')) {
            $department->clearMediaCollection('department_image');
        }

        if ($request->hasFile('department_image')) {
            $department->addMediaFromRequest('department_image')->toMediaCollection('department_image');
        }

        if ($request->hasFile('documents')) {
            foreach ((array) $request->file('documents') as $file) {
                $department->addMedia($file)->toMediaCollection('documents');
            }
        }

        foreach ((array) $request->input('remove_documents', []) as $mediaId) {
            $department->media()->where('id', $mediaId)->where('collection_name', 'documents')->first()?->delete();
        }
    }
}
