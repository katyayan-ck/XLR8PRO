<?php

namespace App\Http\Scopes;

use App\Services\IAM\DataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * DataScopeFilter — Eloquent Global Scope.
 *
 * Apply to any model via ScopedQuery trait.
 * Reads $scopeColumn (default: 'branch_code') from the model.
 * Reads $scopeType  (default: 'branch') from the model.
 */
class DataScopeFilter implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Skip during testing without auth, console commands, or queue workers
        if (!Auth::check()) return;

        $user = Auth::user();

        // SuperAdmin always bypasses
        if ($user->isSuperAdmin()) return;

        /** @var DataScopeService $service */
        $service = app(DataScopeService::class);

        $scopeType   = $model->scopeType   ?? 'branch';
        $scopeColumn = $model->scopeColumn ?? 'branch_code';
        $scopeGroup  = $model->scopeGroup  ?? 'org'; // 'org' or 'vehicle'

        $codes = $scopeGroup === 'vehicle'
            ? $service->getVehicleScope($user, $scopeType)
            : $service->getOrgScope($user, $scopeType);

        if ($codes === null) return;      // wildcard — no filter needed
        if (empty($codes)) {
            // No access — return zero rows
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->whereIn($scopeColumn, $codes);
    }
}
