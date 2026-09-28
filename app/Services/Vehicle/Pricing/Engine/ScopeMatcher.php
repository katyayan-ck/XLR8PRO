<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Services\Vehicle\Pricing\Rules\RuleRange;
use Illuminate\Support\Collection;

/**
 * The locked spec's matching engine (§6, DEC-080). A row's scope cell passes when it is blank / ANY / ALL / *, or when
 * one of its comma-separated tokens equals one of the vehicle's values (case-insensitive); a range cell passes when
 * the vehicle's number is inside it. The most specific matching row wins (the count of non-ANY cells); on a tie the
 * later row (higher id) wins — "global first, overrides below".
 *
 *   ScopeMatcher::best($rows, ['segment' => ['PV'], 'model_code' => ['THAR-ROXX', 'THAR ROXX']], ['gvw_range' => 2500.0]);
 */
final class ScopeMatcher
{
    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  iterable<T>  $rows
     * @param  array<string, list<string|null>>  $tokens  column => the vehicle's values (null / [] = the vehicle has none)
     * @param  array<string, float|int|null>  $ranges  column => the vehicle's number
     * @return T|null
     */
    public static function best(iterable $rows, array $tokens, array $ranges = []): mixed
    {
        $best = null;
        $bestScore = -1;
        $bestId = -1;
        foreach ($rows as $row) {
            $score = self::score($row, $tokens, $ranges);
            if ($score === null) {
                continue;
            }
            $id = (int) $row->getKey();
            if ($score > $bestScore || ($score === $bestScore && $id > $bestId)) {
                [$best, $bestScore, $bestId] = [$row, $score, $id];
            }
        }

        return $best;
    }

    /**
     * Every matching row, most specific first.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  iterable<T>  $rows
     * @param  array<string, list<string|null>>  $tokens
     * @param  array<string, float|int|null>  $ranges
     * @return Collection<int, T>
     */
    public static function all(iterable $rows, array $tokens, array $ranges = []): Collection
    {
        $out = [];
        foreach ($rows as $row) {
            if (($score = self::score($row, $tokens, $ranges)) !== null) {
                $out[] = [$score, (int) $row->getKey(), $row];
            }
        }
        usort($out, fn ($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);

        return collect(array_column($out, 2));
    }

    /**
     * @param  array<string, list<string|null>>  $tokens
     * @param  array<string, float|int|null>  $ranges
     * @return int|null the specificity, or null when the row does not match
     */
    public static function score(object $row, array $tokens, array $ranges = []): ?int
    {
        $score = 0;
        foreach ($tokens as $column => $values) {
            $cell = self::cell($row->{$column} ?? null);
            if ($cell === null) {
                continue;
            }
            $values = array_filter(array_map(fn ($v) => $v === null ? null : strtoupper(trim((string) $v)), $values));
            $cellTokens = array_map(fn ($t) => strtoupper(trim($t)), explode(',', $cell));
            if (array_intersect($cellTokens, $values) === []) {
                return null;
            }
            $score++;
        }
        foreach ($ranges as $column => $number) {
            $cell = self::cell($row->{$column} ?? null);
            if ($cell === null) {
                continue;
            }
            try {
                if (! RuleRange::parse($cell)->contains($number)) {
                    return null;
                }
            } catch (\InvalidArgumentException) {
                return null;
            }
            $score++;
        }

        return $score;
    }

    /** The cell's text, or null when it means "any". */
    private static function cell(mixed $value): ?string
    {
        $t = trim((string) $value);

        return in_array(strtoupper($t), ['', 'ANY', 'ALL', '*'], true) ? null : $t;
    }
}
