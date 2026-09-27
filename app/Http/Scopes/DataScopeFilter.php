<?php

namespace App\Http\Scopes;

use App\Services\IAM\DataScope\DataScopeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope added by App\Models\Traits\HasDataScope (DEC-071): filters a business model by the signed-in user's
 * data scope, using the entity's columns from config/data_scope.php. All logic lives in DataScopeManager; this class
 * only hands the query over. Remove it for one query with `Model::withoutDataScope()`.
 */
class DataScopeFilter implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        app(DataScopeManager::class)->applyToEloquent($builder, $model);
    }
}
