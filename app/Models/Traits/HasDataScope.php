<?php

namespace App\Models\Traits;

use App\Http\Scopes\DataScopeFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Opts a business model into automatic user data scoping (DEC-071). The columns it is filtered on are declared in
 * config/data_scope.php `entities` — a model using this trait but missing from that list is not filtered.
 *
 *   Enquiry::query()->…                  scoped to the signed-in user
 *   Enquiry::withoutDataScope()->…       this query sees every row (say why in a comment)
 *   DataScope::off(fn () => …, 'why')    every scoped model inside the closure sees every row
 */
trait HasDataScope
{
    public static function bootHasDataScope(): void
    {
        static::addGlobalScope(new DataScopeFilter);
    }

    /** @return Builder<static> */
    public static function withoutDataScope(): Builder
    {
        return static::withoutGlobalScope(DataScopeFilter::class);
    }
}
