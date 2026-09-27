<?php

declare(strict_types=1);

namespace App\Services\IAM\DataScope;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies user data scoping (DEC-071). Reached through the DataScope facade and the HasDataScope global scope.
 *
 * When it applies: a user is signed in (Backpack admin, or the API's Sanctum user), `scope.enabled` is on and nothing
 * has switched it off. Jobs, console commands and importers run without a user and are never scoped.
 *
 * Opt-out (every use should say why — grep "withoutDataScope\|DataScope::off\|data-scope:off"):
 *   Model::withoutDataScope()->…                      one query
 *   DataScope::off(fn () => …, 'VOTF numbering')      everything inside the closure
 *   ->middleware('data-scope:off')                    a whole route / screen
 *
 * Filtering an entity (config/data_scope.php `entities`): per tree, the most specific level the table carries is
 * checked; when that column is empty on a row the next level up decides; a row empty on every level of a tree is
 * "unassigned" and passes only while `scope.unassigned_rows` is 'visible'. Trees combine with AND. Satellites
 * (`via`) keep rows whose parent passes the parent's scope.
 */
class DataScopeManager
{
    private int $suspended = 0;

    private ?string $routeOffReason = null;

    public function __construct(private readonly ScopeResolver $resolver) {}

    /** The signed-in user scoping applies to (Backpack guard first, then the default / API guard). */
    public function user(): ?User
    {
        try {
            $user = Auth::guard(backpack_guard_name())->user() ?? Auth::user();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    public function enabled(): bool
    {
        if ($this->suspended > 0 || $this->routeOffReason !== null) {
            return false;
        }

        return $this->settingEnabled ??= (bool) setting('scope.enabled', true);
    }

    /** Settings are read once per request (every scoped query asks). */
    private ?bool $settingEnabled = null;

    private ?bool $settingUnassignedVisible = null;

    /** Forget the per-request setting values (after changing scope.* settings in the same request, and in tests). */
    public function refreshSettings(): void
    {
        $this->settingEnabled = null;
        $this->settingUnassignedVisible = null;
    }

    /** The current user's effective scope, or unrestricted when scoping does not apply. */
    public function current(): ScopeSet
    {
        $user = $this->enabled() ? $this->user() : null;

        return $user ? $this->resolver->for($user) : ScopeSet::unrestricted();
    }

    public function for(User $user): ScopeSet
    {
        return $this->resolver->for($user);
    }

    /**
     * Run $callback with scoping switched off (numbering, duplicate checks, lookups that must see every row).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function off(callable $callback, string $reason = '')
    {
        $this->suspended++;
        Log::debug('Data scope off', ['reason' => $reason]);
        try {
            return $callback();
        } finally {
            $this->suspended--;
        }
    }

    /** Switch scoping off for the rest of this request (used by the data-scope:off middleware). */
    public function offForRequest(string $reason): void
    {
        $this->routeOffReason = $reason;
    }

    public function unassignedVisible(): bool
    {
        return $this->settingUnassignedVisible ??= strtolower((string) setting('scope.unassigned_rows', 'visible')) !== 'hidden';
    }

    /** Eloquent global-scope entry point (HasDataScope). */
    public function applyToEloquent(EloquentBuilder $builder, Model $model): void
    {
        $this->applyToQuery($builder->getQuery(), $model::class);
    }

    /**
     * Raw-query entry point: give a DB::table(...) query the same filter as the entity's model.
     *
     * @param  class-string<Model>  $entity
     */
    public function apply(QueryBuilder $query, string $entity, ?string $alias = null): QueryBuilder
    {
        $this->applyToQuery($query, $entity, $alias);

        return $query;
    }

    /** @param  class-string<Model>  $entity */
    private function applyToQuery(QueryBuilder $query, string $entity, ?string $alias = null): void
    {
        $config = config("data_scope.entities.{$entity}");
        if (! is_array($config)) {
            return;
        }

        $scope = $this->current();
        if ($scope->isUnrestricted()) {
            return;
        }

        $qualifier = $alias ?? $this->qualifier($query);
        $col = fn (string $column): string => $qualifier.'.'.$column;

        if (isset($config['via'])) {
            $this->applyVia($query, $config['via'], $col);

            return;
        }

        foreach (array_keys((array) config('data_scope.trees', [])) as $tree) {
            $pairs = [];
            foreach (array_reverse(array_keys((array) config("data_scope.trees.{$tree}"))) as $level) {
                if (isset($config[$level])) {
                    $pairs[] = [$level, $col($config[$level])];
                }
            }
            if ($pairs === [] || $this->treeUnrestricted($scope, $pairs)) {
                continue;
            }
            $query->where(fn (QueryBuilder $w) => $this->levelClause($w, $scope, $pairs, 0));
        }
    }

    /**
     * @param  array{column: string, parent: class-string<Model>, parent_key: string}  $via
     * @param  callable(string): string  $col
     */
    private function applyVia(QueryBuilder $query, array $via, callable $col): void
    {
        $parent = $via['parent'];
        $parentQuery = $parent::query()->select((new $parent)->qualifyColumn($via['parent_key']));
        $column = $col($via['column']);
        $visible = $this->unassignedVisible();

        $query->where(function (QueryBuilder $w) use ($column, $parentQuery, $visible) {
            $w->whereIn($column, $parentQuery);
            if ($visible) {
                $w->orWhereNull($column)->orWhere($column, '');
            }
        });
    }

    /** @param  list<array{0: string, 1: string}>  $pairs  most specific level first */
    private function treeUnrestricted(ScopeSet $scope, array $pairs): bool
    {
        foreach ($pairs as [$level]) {
            if ($scope->allowed($level) !== null) {
                return false;
            }
        }

        return true;
    }

    /** @param  list<array{0: string, 1: string}>  $pairs */
    private function levelClause(QueryBuilder $w, ScopeSet $scope, array $pairs, int $i): void
    {
        if ($i >= count($pairs)) {
            if (! $this->unassignedVisible()) {
                $w->whereRaw('1 = 0');
            }

            return;
        }

        [$level, $column] = $pairs[$i];
        $allowed = $scope->allowed($level);

        if ($allowed === null) {
            // unrestricted at this level although a more specific level is restricted: the row's own value here
            // says nothing about the restricted level, so treat it like the next level up
            $this->levelClause($w, $scope, $pairs, $i + 1);

            return;
        }

        $w->where(function (QueryBuilder $x) use ($scope, $pairs, $i, $column, $allowed) {
            if ($allowed === []) {
                $x->whereRaw('1 = 0');
            } else {
                $x->whereIn($column, $allowed);
            }
            $x->orWhere(function (QueryBuilder $empty) use ($scope, $pairs, $i, $column) {
                $empty->where(fn (QueryBuilder $e) => $e->whereNull($column)->orWhere($column, ''));
                $this->levelClause($empty, $scope, $pairs, $i + 1);
            });
        });
    }

    /** Table name or alias the query selects from ("xlr8_booking_master as bookings" → "bookings"). */
    private function qualifier(QueryBuilder $query): string
    {
        $from = is_string($query->from) ? $query->from : '';
        if (preg_match('/\s+as\s+(\S+)$/i', $from, $m)) {
            return $m[1];
        }

        return $from;
    }
}
