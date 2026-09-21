<?php

namespace App\Services\Org;

/**
 * Shared guard for the "code is the real primary key" org reference-data
 * entities (Designation, Department, Division, Vertical, Branch, Location,
 * ...) — every relation in this app points at these entities by `code`, not
 * `id`, so:
 *  1. `code` must never change after creation (would silently break every
 *     existing FK-by-code reference elsewhere in the app).
 *  2. An entity can't be disabled (is_active -> false) while it still has
 *     active dependent records — those must be disabled first, otherwise
 *     they'd be left pointing at a "disabled" parent with no warning.
 *
 * Not every dependent model uses an is_active boolean (e.g. Employee has no
 * is_active column at all — "active" is employment_status = 'active'), so
 * each dependent entry can override which column/value counts as "active".
 */
class OrgEntityGuard
{
    /**
     * @param  array<int, array{0: class-string, 1: string, 2?: string, 3?: string, 4?: mixed}>  $dependents  Each entry:
     *                                                                                                        [ModelClass, foreignCodeColumn, humanLabel?, activeColumn?, activeValue?]
     *                                                                                                        — humanLabel defaults to the class basename; activeColumn/activeValue default to 'is_active'/true.
     * @return array<int, string> Human-readable blocker descriptions; empty means safe to disable.
     */
    public static function activeDependents(string $code, array $dependents): array
    {
        $blockers = [];

        foreach ($dependents as $dependent) {
            [$modelClass, $column] = $dependent;
            $label = $dependent[2] ?? class_basename($modelClass);
            $activeColumn = $dependent[3] ?? 'is_active';
            $activeValue = $dependent[4] ?? true;

            $count = $modelClass::where($column, $code)->where($activeColumn, $activeValue)->count();

            if ($count > 0) {
                $blockers[] = "{$count} active {$label}".($count === 1 ? '' : 's');
            }
        }

        return $blockers;
    }

    /**
     * Convenience: throws-style check via return value, for the common
     * "about to set is_active=false, was it previously true?" case.
     *
     * @param  array<int, array{0: class-string, 1: string, 2?: string, 3?: string, 4?: mixed}>  $dependents
     */
    public static function blockersForDisabling(bool $wasActive, bool $willBeActive, string $code, array $dependents): array
    {
        if (! $wasActive || $willBeActive) {
            return [];
        }

        return self::activeDependents($code, $dependents);
    }
}
