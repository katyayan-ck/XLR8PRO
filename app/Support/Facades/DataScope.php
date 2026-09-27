<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\IAM\DataScope\DataScopeManager;
use Illuminate\Support\Facades\Facade;

/**
 * User data scoping (DEC-071): DataScope::current(), off(fn, 'reason'), apply($rawQuery, Entity::class, 'alias'),
 * for($user), enabled(), unassignedVisible().
 *
 * @method static \App\Services\IAM\DataScope\ScopeSet current()
 * @method static \App\Services\IAM\DataScope\ScopeSet for(\App\Models\User $user)
 * @method static mixed off(callable $callback, string $reason = '')
 * @method static \Illuminate\Database\Query\Builder apply(\Illuminate\Database\Query\Builder $query, string $entity, ?string $alias = null)
 * @method static bool enabled()
 * @method static bool unassignedVisible()
 * @method static \App\Models\User|null user()
 *
 * @see DataScopeManager
 */
final class DataScope extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DataScopeManager::class;
    }
}
