<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Converts vehicle model and sub-segment codes to the canonical hyphenated form (DEC-049):
 * "THAR ROXX" and its space-stripped twin "THARROXX" both become "THAR-ROXX", in the master
 * table and in every column that references it. Idempotent: a second run finds nothing to do.
 *
 * Families are grouped by the code with spaces and hyphens removed; a family is converted only
 * when one of its members is a master code, and only exact matches are rewritten.
 */
final class VehicleCodeNormaliser
{
    /** Free-text `model` columns that hold model codes (verified by value overlap, BUG-171). */
    private const MODEL_TEXT_COLUMNS = [
        ['xlr8_booking_accessories', 'model'],
        ['xlr8_booking_fee_collection', 'model'],
        ['xlr8_booking_insurance', 'model'],
        ['xlr8_crm_enquiries', 'model'],
        ['xlr8_crm_testdrive', 'model'],
        ['xlr8_spare_request', 'model'],
    ];

    /**
     * Old → new code maps, per kind.
     *
     * @return array{model: array<string, string>, sub_segment: array<string, string>}
     */
    public function plan(): array
    {
        return [
            'model' => $this->familyMap('xlr8_vehicle_model', $this->referenceColumns('model_code', self::MODEL_TEXT_COLUMNS)),
            'sub_segment' => $this->familyMap('xlr8_vehicle_subsegment', $this->referenceColumns('sub_segment_code')),
        ];
    }

    /**
     * Apply the plan in one transaction.
     *
     * @return array{plan: array{model: array<string, string>, sub_segment: array<string, string>}, updated: array<string, int>}
     */
    public function apply(bool $dryRun = false): array
    {
        $plan = $this->plan();
        $updated = [];

        if ($dryRun || ($plan['model'] === [] && $plan['sub_segment'] === [])) {
            return ['plan' => $plan, 'updated' => $updated];
        }

        DB::transaction(function () use ($plan, &$updated) {
            foreach (['model' => ['xlr8_vehicle_model', 'model_code', self::MODEL_TEXT_COLUMNS], 'sub_segment' => ['xlr8_vehicle_subsegment', 'sub_segment_code', []]] as $kind => [$master, $refColumn, $extra]) {
                $map = $plan[$kind];
                if ($map === []) {
                    continue;
                }
                $this->assertNoCollision($master, $map);

                $updated["{$master}.code"] = $this->remap($master, 'code', $map);
                foreach ($this->referenceColumns($refColumn, $extra) as [$table, $column]) {
                    $updated["{$table}.{$column}"] = $this->remap($table, $column, $map);
                }
                $updated["xlr8_admin_user_scopes.{$kind}"] = $this->remap('xlr8_admin_user_scopes', 'scope_code', $map, ['scope_type' => $kind]);
            }
        });

        $updated = array_filter($updated);
        $this->writeLog($plan, $updated);

        return ['plan' => $plan, 'updated' => $updated];
    }

    /**
     * Key-value lists whose codes are model identifiers (DEC-049). Other keywords with spaces are
     * labels/synonyms (pricing header mapping, spare bins, statuses) and must keep their text.
     */
    public const MODEL_KEYWORDS = ['CUSTOM-MODEL'];

    /**
     * Hyphenate the codes of MODEL_KEYWORDS and their exact references in enquiries/bookings.
     *
     * @return array{map: array<string, string>, updated: array<string, int>}
     */
    public function applyModelKeywords(bool $dryRun = false): array
    {
        $map = DB::table('xlr8_utils_keyvalue')->whereIn('keyword_code', self::MODEL_KEYWORDS)
            ->whereRaw("code REGEXP '[[:space:]]|[+]'")->pluck('code')->unique()
            ->mapWithKeys(fn ($c) => [(string) $c => self::canonical((string) $c)])->all();
        $updated = [];
        if ($dryRun || $map === []) {
            return ['map' => $map, 'updated' => $updated];
        }

        DB::transaction(function () use ($map, &$updated) {
            foreach (self::MODEL_KEYWORDS as $keyword) {
                $codes = DB::table('xlr8_utils_keyvalue')->where('keyword_code', $keyword)->pluck('code')->map(fn ($c) => $map[(string) $c] ?? (string) $c);
                $dupes = $codes->duplicates()->unique()->values()->all();
                if ($dupes !== []) {
                    throw new RuntimeException("Keyword {$keyword}: codes would collide after normalisation: ".implode(', ', $dupes));
                }
                $updated["xlr8_utils_keyvalue.{$keyword}"] = $this->remap('xlr8_utils_keyvalue', 'code', $map, ['keyword_code' => $keyword]);
            }
            foreach (array_merge(self::MODEL_TEXT_COLUMNS, [['xlr8_crm_enquiries', 'variant']]) as [$table, $column]) {
                if (Schema::hasColumn($table, $column)) {
                    $updated["{$table}.{$column}"] = $this->remap($table, $column, $map);
                }
            }
        });

        $updated = array_filter($updated);
        $db = (string) DB::connection()->getDatabaseName();
        File::put(storage_path("logs/model-keyword-normalisation-{$db}.json"), json_encode(
            ['ran_at' => now()->toIso8601String(), 'map' => $map, 'updated' => $updated], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));

        return ['map' => $map, 'updated' => $updated];
    }

    public static function canonical(string $code): string
    {
        $code = preg_replace('/\s*-\s*|\s+/', '-', trim(str_replace('+', ' PLUS ', $code)));

        return trim(strtoupper((string) preg_replace('/[^A-Za-z0-9\-_]/', '', $code)), '-');
    }

    /** Spelling-independent key: "THAR ROXX", "THARROXX", "Thar-Roxx" → THARROXX; "+" counts as PLUS. */
    private static function familyKey(string $code): string
    {
        return str_replace('-', '', self::canonical($code));
    }

    /**
     * @param  list<array{0: string, 1: string}>  $references
     * @return array<string, string>
     */
    private function familyMap(string $master, array $references): array
    {
        $masterCodes = DB::table($master)->pluck('code')->map(fn ($c) => (string) $c)->all();

        // Every distinct value in the master and its reference columns, with how often it is used.
        $usage = array_fill_keys($masterCodes, 1);
        foreach ($references as [$table, $column]) {
            DB::table($table)->whereNotNull($column)->where($column, '!=', '')
                ->select($column, DB::raw('COUNT(*) as n'))->groupBy($column)->get()
                ->each(function ($row) use ($column, &$usage) {
                    $usage[(string) $row->{$column}] = ($usage[(string) $row->{$column}] ?? 0) + (int) $row->n;
                });
        }

        $families = [];
        foreach ($usage as $code => $n) {
            $families[self::familyKey((string) $code)][(string) $code] = $n;
        }

        $map = [];
        foreach ($families as $members) {
            if (array_intersect(array_keys($members), $masterCodes) === []) {
                continue; // not a master code family (e.g. free text) — leave it alone
            }
            $separated = array_filter($members, fn ($n, $code) => preg_match('/[\s-]/', (string) $code) === 1, ARRAY_FILTER_USE_BOTH);
            if ($separated === []) {
                continue; // e.g. BOLERO — no spaced/hyphenated form anywhere
            }
            arsort($separated);
            $canonical = self::canonical((string) array_key_first($separated));
            foreach (array_keys($members) as $code) {
                if ((string) $code !== $canonical) {
                    $map[(string) $code] = $canonical;
                }
            }
        }
        ksort($map);

        return $map;
    }

    /**
     * Columns named $column in any table, plus extra free-text columns that exist.
     *
     * @param  list<array{0: string, 1: string}>  $extra
     * @return list<array{0: string, 1: string}>
     */
    private function referenceColumns(string $column, array $extra = []): array
    {
        // In the pricing tables `model_code` is the full OEM variant code, not a model code.
        $columns = collect(DB::select(
            "SELECT table_name AS t FROM information_schema.columns
             WHERE table_schema = DATABASE() AND column_name = ? AND table_name NOT LIKE 'xlr8\\_vehicle\\_pricing%'
             ORDER BY table_name",
            [$column]
        ))->map(fn ($r) => [$r->t, $column])->all();

        foreach ($extra as [$table, $col]) {
            if (Schema::hasColumn($table, $col)) {
                $columns[] = [$table, $col];
            }
        }

        return $columns;
    }

    /** @param array<string, string> $map */
    private function assertNoCollision(string $master, array $map): void
    {
        $codes = DB::table($master)->pluck('code')->map(fn ($c) => (string) $c)->all();
        $after = array_map(fn ($c) => $map[$c] ?? $c, $codes);
        $dupes = array_keys(array_filter(array_count_values($after), fn ($n) => $n > 1));
        if ($dupes !== []) {
            throw new RuntimeException("{$master}: codes would collide after normalisation: ".implode(', ', $dupes).'. Merge those rows first.');
        }
    }

    /**
     * @param  array<string, string>  $map
     * @param  array<string, string>  $where
     */
    private function remap(string $table, string $column, array $map, array $where = []): int
    {
        $count = 0;
        foreach ($map as $old => $new) {
            $count += DB::table($table)->where($where)->where($column, $old)->update([$column => $new]);
        }

        return $count;
    }

    /**
     * @param  array{model: array<string, string>, sub_segment: array<string, string>}  $plan
     * @param  array<string, int>  $updated
     */
    private function writeLog(array $plan, array $updated): void
    {
        $db = (string) DB::connection()->getDatabaseName();
        File::put(storage_path("logs/vehicle-code-normalisation-{$db}.json"), json_encode([
            'ran_at' => now()->toIso8601String(),
            'plan' => $plan,
            'updated' => $updated,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
