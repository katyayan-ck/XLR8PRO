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
        if (!Auth::check()) return;

        $user = Auth::user();

       
        if ($user->isSuperAdmin()) return;

        /** @var DataScopeService $service */
        $service = app(DataScopeService::class);

        $scopeType   = $model->scopeType   ?? 'branch';
        $scopeColumn = $model->scopeColumn ?? 'branch_code';
        $scopeGroup  = $model->scopeGroup  ?? 'org'; 

        $codes = $scopeGroup === 'vehicle'
            ? $service->getVehicleScope($user, $scopeType)
            : $service->getOrgScope($user, $scopeType);

        if ($codes === null) return;      
        if (empty($codes)) {
           
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->whereIn($scopeColumn, $codes);
    }
}
