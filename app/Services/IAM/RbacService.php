<?php

namespace App\Services\IAM;

use App\Models\IAM\Module;
use App\Models\IAM\Process;
use App\Models\IAM\Permission;
use Exception;

class RbacService
{
    /**
     * Get all currently active Modules.
     */
    public function getActiveModules()
    {
        return Module::where('is_active', 1)->orderBy('name')->get();
    }

    /**
     * Get all currently active Processes for a specific Module.
     */
    public function getActiveProcessesByModule(string $moduleCode)
    {
        return Process::where('module_code', $moduleCode)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['code', 'name']);
    }

    /**
     * Get names of active Processes by Module Code (UI Helper).
     */
    public function getActiveProcessNamesByModule(string $moduleCode): array
    {
        return Process::where('module_code', $moduleCode)
            ->where('is_active', 1)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get names of all Permissions linked to a Process Code (UI Helper).
     */
    public function getPermissionNamesByProcess(string $processCode): array
    {
        return Permission::where('process_code', $processCode)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Extract the unique suffix from a permission name.
     */
    public function extractPermissionSuffix(string $permissionName): string
    {
        $parts = explode('_', $permissionName);

        if (count($parts) > 2) {
            return implode('_', array_slice($parts, 2));
        }

        return '';
    }

    /**
     * Update a Module and enforce deactivation business constraints.
     * 
     * @throws Exception if constraints are violated.
     */
    public function updateModule(Module $module, array $attributes): bool
    {
        $isDeactivating = $module->is_active == 1 && empty($attributes['is_active']);

        if ($isDeactivating) {
            $activeProcessCount = Process::where('module_code', $module->code)
                ->where('is_active', 1)
                ->count();

            if ($activeProcessCount > 0) {
                throw new Exception("Cannot deactivate Module. {$activeProcessCount} active Process(es) exist.");
            }
        }

        return $module->update($attributes);
    }

    /**
     * Update a Process and enforce deactivation business constraints.
     * 
     * @throws Exception if constraints are violated.
     */
    public function updateProcess(Process $process, array $attributes): bool
    {
        $isDeactivating = $process->is_active == 1 && empty($attributes['is_active']);

        if ($isDeactivating) {
            $permissionCount = Permission::where('process_code', $process->code)->count();

            if ($permissionCount > 0) {
                throw new Exception("Cannot deactivate Process. {$permissionCount} Permission(s) exist.");
            }
        }

        return $process->update($attributes);
    }
}