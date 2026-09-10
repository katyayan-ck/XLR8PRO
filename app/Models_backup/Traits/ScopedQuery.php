<?php

namespace App\Models\Traits;

use App\Http\Scopes\DataScopeFilter;
use Illuminate\Support\Facades\Auth;

trait ScopedQuery
{
    public static function bootScopedQuery(): void
    {
        static::addGlobalScope(new DataScopeFilter());
    }

    /**
     * Query without data scope applied.
     * Use this when you need full data (e.g. for SuperAdmin or reports).
     */
    public static function withoutDataScope(): \Illuminate\Database\Eloquent\Builder
    {
        return static::withoutGlobalScope(DataScopeFilter::class);
    }

    /**
     * Check if current authenticated user should bypass data scoping
     */
    public static function shouldBypassDataScope(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return (bool) Auth::user()->bypass_data_scoping;
    }
}