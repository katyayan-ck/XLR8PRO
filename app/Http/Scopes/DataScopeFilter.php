<?php

namespace App\Http\Scopes;

use App\Models\User;
use App\Services\IAM\DataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * DataScopeFilter — Eloquent Global Scope, applied via the ScopedQuery trait.
 *
 * Reads from the model:
 *   $scopeType   — a DataScopeService::TYPE_MODELS key (default 'branch')
 *   $scopeColumn — the model's column holding that entity's *id* (default 'branch_id')
 *   $scopeGroup  — 'org' | 'vehicle' (default 'org')
 *
 * The user's scope codes are translated to ids by DataScopeService, so
 * $scopeColumn must be an id column, not a code column.
 */
class DataScopeFilter implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return;
        }

        $service = app(DataScopeService::class);

        $scopeType = $model->scopeType ?? 'branch';
        $scopeColumn = $model->scopeColumn ?? 'branch_id';
        $scopeGroup = $model->scopeGroup ?? 'org';

        $ids = $scopeGroup === 'vehicle'
            ? $service->getVehicleScope($user, $scopeType)
            : $service->getOrgScope($user, $scopeType);

        if ($ids === null) {
            return;
        }

        if ($ids === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereIn($model->qualifyColumn($scopeColumn), $ids);
    }
}
