<?php

declare(strict_types=1);

namespace App\Services\IAM\DataScope;

use App\Models\Admin\UserScope;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turns a user's scope rows (xlr8_admin_user_scopes) into their effective ScopeSet (DEC-071).
 *
 * Rules (user, 28-09-2026):
 *  - no rows at any level of a tree → the whole tree is unrestricted;
 *  - a parent covers all of its children down to the last level unless the user holds codes at a child level;
 *  - a child restriction applies within the nearest assigned ancestor: PV + THAR → models {THAR} (not every
 *    other PV sub-segment's models); PV + CV + THAR → THAR under PV and every CV model;
 *  - "ALL" / "ANY" / "*" rows count as "no restriction at this level";
 *  - only active rows inside their from_date / to_date window count.
 *
 * Superadmins and users with bypass_data_scoping get ScopeSet::unrestricted(). Results are memoised per user for
 * the request; master hierarchies are cached for config('data_scope.master_cache_seconds').
 */
class ScopeResolver
{
    private const WILDCARDS = ['ALL', 'ANY', '*', 'NULL', ''];

    /** @var array<int, ScopeSet> */
    private array $memo = [];

    public function for(User $user): ScopeSet
    {
        return $this->memo[$user->id] ??= $this->resolve($user);
    }

    /** Forget memoised scopes (after a grant / revoke in the same request, and in tests). */
    public function flush(?int $userId = null): void
    {
        if ($userId === null) {
            $this->memo = [];
        } else {
            unset($this->memo[$userId]);
        }
    }

    /** Drop the cached master hierarchies (after master data changes, and in tests). */
    public function flushMasters(): void
    {
        foreach ((array) config('data_scope.trees', []) as $tree => $levels) {
            foreach (array_keys((array) $levels) as $level) {
                Cache::forget("data_scope:masters:{$tree}:{$level}");
            }
        }
        $this->memo = [];
    }

    private function resolve(User $user): ScopeSet
    {
        if ($user->isSuperAdmin() || $user->bypassesDataScoping()) {
            return ScopeSet::unrestricted();
        }

        $explicit = $this->explicitCodes($user->id);
        $levels = [];
        foreach (array_keys((array) config('data_scope.trees', [])) as $tree) {
            $levels += $this->resolveTree($tree, $explicit);
        }

        return new ScopeSet($levels);
    }

    /**
     * @param  array<string, list<string>>  $explicit  level => codes the user holds
     * @return array<string, list<string>|null>
     */
    private function resolveTree(string $tree, array $explicit): array
    {
        $levels = array_keys((array) config("data_scope.trees.{$tree}"));
        $allowed = [];

        foreach ($levels as $i => $level) {
            $own = $explicit[$level] ?? [];

            if ($i === 0) {
                $allowed[$level] = $own === [] ? null : $own;

                continue;
            }

            $parent = $levels[$i - 1];
            $base = $allowed[$parent] === null ? null : $this->descendants($tree, $level, $parent, $allowed[$parent]);

            if ($own === []) {
                $allowed[$level] = $base;

                continue;
            }

            $anchor = null;   // nearest level above with explicit codes
            for ($k = $i - 1; $k >= 0; $k--) {
                if (($explicit[$levels[$k]] ?? []) !== []) {
                    $anchor = $levels[$k];
                    break;
                }
            }

            if ($anchor === null) {
                $allowed[$level] = $own;

                continue;
            }

            $result = [];
            foreach ($allowed[$anchor] ?? [] as $ancestorCode) {
                $children = $this->descendants($tree, $level, $anchor, [$ancestorCode]);
                if ($base !== null) {
                    $children = array_values(array_intersect($children, $base));
                }
                $restricted = array_values(array_intersect($children, $own));
                $result = array_merge($result, $restricted !== [] ? $restricted : $children);
            }
            $allowed[$level] = array_values(array_unique($result));
        }

        return $allowed;
    }

    /**
     * Codes at $level whose $ancestorLevel code is one of $codes.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function descendants(string $tree, string $level, string $ancestorLevel, array $codes): array
    {
        $lookup = array_flip($codes);
        $out = [];
        foreach ($this->masters($tree, $level) as $code => $ancestors) {
            if (isset($ancestors[$ancestorLevel]) && isset($lookup[$ancestors[$ancestorLevel]])) {
                $out[] = (string) $code;
            }
        }

        return $out;
    }

    /** @return array<string, array<string, string>> code => [ancestor level => ancestor code] */
    private function masters(string $tree, string $level): array
    {
        $cfg = (array) config("data_scope.trees.{$tree}.{$level}");

        return Cache::remember(
            "data_scope:masters:{$tree}:{$level}",
            (int) config('data_scope.master_cache_seconds', 600),
            function () use ($cfg): array {
                $columns = array_values((array) ($cfg['ancestors'] ?? []));
                $rows = DB::table($cfg['table'])->whereNull('deleted_at')->get(array_merge(['code'], $columns));
                $map = [];
                foreach ($rows as $row) {
                    $ancestors = [];
                    foreach ((array) ($cfg['ancestors'] ?? []) as $ancestorLevel => $column) {
                        $value = strtoupper(trim((string) ($row->{$column} ?? '')));
                        if ($value !== '') {
                            $ancestors[$ancestorLevel] = $value;
                        }
                    }
                    $map[strtoupper(trim((string) $row->code))] = $ancestors;
                }

                return $map;
            }
        );
    }

    /** @return array<string, list<string>> level => codes, wildcards dropped */
    private function explicitCodes(int $userId): array
    {
        $today = now()->toDateString();

        $rows = UserScope::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('from_date')->orWhere('from_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $today))
            ->get(['scope_type', 'scope_code']);

        $out = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) $row->scope_code));
            if (in_array($code, self::WILDCARDS, true)) {
                continue;
            }
            $out[strtolower(trim((string) $row->scope_type))][] = $code;
        }

        return array_map(fn (array $codes) => array_values(array_unique($codes)), $out);
    }
}
