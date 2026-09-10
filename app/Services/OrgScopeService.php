<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrgScopeService
{
    /**
     * Complete hierarchy definition for both Org and Vehicle trees.
     * This is the single source of truth.
     */
    protected static array $hierarchy = [
        // === ORGANIZATION HIERARCHY ===
        'branch' => [
            'table'      => 'xlr8_admin_branch',
            'code'       => 'code',
            'name'       => 'name',
            'children'   => ['location'],
        ],
        'location' => [
            'table'      => 'xlr8_admin_location',
            'code'       => 'code',
            'name'       => 'name',
            'parent_col' => 'branch_code',
            'children'   => [],
        ],

        'department' => [
            'table'      => 'xlr8_admin_department',
            'code'       => 'code',
            'name'       => 'name',
            'children'   => ['division'],
        ],
        'division' => [
            'table'      => 'xlr8_admin_division',
            'code'       => 'code',
            'name'       => 'name',
            'parent_col' => 'dept_code',
            'children'   => [],
        ],

        // === VEHICLE HIERARCHY (Segment → SubSegment → Model → Variant) ===
        'segment' => [
            'table'      => 'xlr8_vehicle_segment',
            'code'       => 'code',
            'name'       => 'name',
            'children'   => ['sub_segment'],
        ],
        'sub_segment' => [
            'table'      => 'xlr8_vehicle_subsegment',
            'code'       => 'code',
            'name'       => 'name',
            'parent_col' => 'segment_code',
            'children'   => ['model'],
        ],
        'model' => [
            'table'      => 'xlr8_vehicle_model',
            'code'       => 'code',
            'name'       => 'name',
            'parent_col' => 'segment_code', // or sub_segment_code if you have it
            'children'   => ['variant'],
        ],
        'variant' => [
            'table'      => 'xlr8_vehicle_variant',
            'code'       => 'code',
            'name'       => 'name',
            'parent_col' => 'model_code',
            'children'   => [], // Color can be added later
        ],
    ];

    /**
     * Resolve a value (code or name) to canonical code (case-insensitive)
     */
    public static function resolveCode(string $type, ?string $value): ?string
    {
        if (!$value) return null;

        $value = trim($value);
        $upper = strtoupper($value);

        if (in_array($upper, ['ALL', 'ANY', 'NULL', 'N/A', '-'])) {
            return 'ALL';
        }

        $type = strtolower($type);
        if (!isset(self::$hierarchy[$type])) {
            return strtoupper(Str::slug($value, '_'));
        }

        $cfg = self::$hierarchy[$type];

        // Try code
        $code = DB::table($cfg['table'])
            ->whereRaw("UPPER(`{$cfg['code']}`) = ?", [$upper])
            ->value($cfg['code']);

        if ($code) return strtoupper($code);

        // Try name
        $code = DB::table($cfg['table'])
            ->whereRaw("UPPER(`{$cfg['name']}`) = ?", [$upper])
            ->value($cfg['code']);

        return $code ? strtoupper($code) : null;
    }

    /**
     * Full hierarchical expansion supporting ALL at any level.
     */
    public static function expandCodes(string $type, ?string $value, array $context = []): array
    {
        if (!$value) return [];

        $type = strtolower($type);
        if (!isset(self::$hierarchy[$type])) return [];

        $cfg = self::$hierarchy[$type];
        $upper = strtoupper(trim($value));

        // === Handle ALL / ANY with hierarchical expansion ===
        if (in_array($upper, ['ALL', 'ANY'])) {
            $query = DB::table($cfg['table'])->where('is_active', 1);

            // Apply parent filter if context has the parent code
            if (!empty($cfg['parent_col']) && isset($context[$cfg['parent_col']])) {
                $query->where($cfg['parent_col'], $context[$cfg['parent_col']]);
            }

            $codes = $query->pluck($cfg['code'])->map(fn($c) => strtoupper($c))->toArray();

            // If this level has children and we want deep expansion, we can recurse here later
            return $codes;
        }

        // === Normal values (comma separated) ===
        $parts = array_filter(array_map('trim', explode(',', $value)));
        $codes = [];

        foreach ($parts as $part) {
            $resolved = self::resolveCode($type, $part);
            if ($resolved && $resolved !== 'ALL') {
                $codes[] = $resolved;
            }
        }

        return array_unique($codes);
    }

    public static function firstCode(string $type, ?string $value): ?string
    {
        $codes = self::expandCodes($type, $value);
        return $codes[0] ?? null;
    }
}