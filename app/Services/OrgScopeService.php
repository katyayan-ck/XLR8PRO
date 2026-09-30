<?php

namespace App\Services;

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Location;
use App\Models\Admin\Vertical;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
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
            'model' => Branch::class,
            'code' => 'code',
            'name' => 'name',
            'children' => ['location'],
        ],
        'location' => [
            'model' => Location::class,
            'code' => 'code',
            'name' => 'name',
            'parent_col' => 'branch_code',
            'children' => [],
        ],

        'department' => [
            'model' => Department::class,
            'code' => 'code',
            'name' => 'name',
            'children' => ['division'],
        ],
        'division' => [
            'model' => Division::class,
            'code' => 'code',
            'name' => 'name',
            'parent_col' => 'dept_code',
            'children' => [],
        ],
        'vertical' => [
            'model' => Vertical::class,
            'code' => 'code',
            'name' => 'name',
            'children' => [],
        ],

        // === VEHICLE HIERARCHY (Segment → SubSegment → Model → Variant) ===
        'segment' => [
            'model' => Segment::class,
            'code' => 'code',
            'name' => 'name',
            'children' => ['sub_segment'],
        ],
        'sub_segment' => [
            'model' => SubSegment::class,
            'code' => 'code',
            'name' => 'name',
            'parent_col' => 'segment_code',
            'children' => ['model'],
        ],
        'model' => [
            'model' => VehicleModel::class,
            'code' => 'code',
            'name' => 'name',
            'parent_col' => 'segment_code', // or sub_segment_code if you have it
            'children' => ['variant'],
        ],
        'variant' => [
            'model' => Variant::class,
            'code' => 'code',
            // Variants have no `name` column; one code spans several colour rows (BUG-164).
            'name' => 'display_name',
            'parent_col' => 'model_code',
            'children' => [], // Color can be added later
        ],
    ];

    /**
     * Resolve a value (code or name) to canonical code (case-insensitive)
     */
    public static function resolveCode(string $type, ?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);
        $upper = strtoupper($value);

        if (in_array($upper, ['ALL', 'ANY', 'NULL', 'N/A', '-'])) {
            return 'ALL';
        }

        $type = strtolower($type);
        if (! isset(self::$hierarchy[$type])) {
            return strtoupper(Str::slug($value, '_'));
        }

        $cfg = self::$hierarchy[$type];

        // Try code
        $code = $cfg['model']::query()
            ->whereRaw("UPPER(`{$cfg['code']}`) = ?", [$upper])
            ->value($cfg['code']);

        if ($code) {
            return strtoupper($code);
        }

        // Try name
        $code = $cfg['model']::query()
            ->whereRaw("UPPER(`{$cfg['name']}`) = ?", [$upper])
            ->value($cfg['code']);

        return $code ? strtoupper($code) : null;
    }

    /**
     * Full hierarchical expansion supporting ALL at any level.
     */
    public static function expandCodes(string $type, ?string $value, array $context = []): array
    {
        if (! $value) {
            return [];
        }

        $type = strtolower($type);
        if (! isset(self::$hierarchy[$type])) {
            return [];
        }

        $cfg = self::$hierarchy[$type];
        $upper = strtoupper(trim($value));

        // === Handle ALL / ANY with hierarchical expansion ===
        if (in_array($upper, ['ALL', 'ANY'])) {
            $query = $cfg['model']::query()->where('is_active', 1);

            // Apply parent filter if context has the parent code
            if (! empty($cfg['parent_col']) && isset($context[$cfg['parent_col']])) {
                $query->where($cfg['parent_col'], $context[$cfg['parent_col']]);
            }

            $codes = $query->distinct()->pluck($cfg['code'])->map(fn ($c) => strtoupper($c))->unique()->values()->toArray();

            // If this level has children and we want deep expansion, we can recurse here later
            return $codes;
        }

        // === Normal values (comma separated) ===
        $parts = array_filter(array_map('trim', explode(',', $value)));
        $codes = [];

        foreach ($parts as $part) {
            $resolved = self::resolveLabel($type, $part);
            if ($resolved && $resolved !== 'ALL') {
                $codes[] = $resolved;
            }
        }

        return array_unique($codes);
    }

    /**
     * Resolve an export dropdown label `Name (CODE)` (or a plain code/name) to its code.
     * The trailing parenthesised part is tried as a code first, then the whole value.
     */
    public static function resolveLabel(string $type, ?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (preg_match('/\(([^()]+)\)\s*$/', trim($value), $m)) {
            $code = self::resolveCode($type, $m[1]);
            if ($code) {
                return $code;
            }
        }

        return self::resolveCode($type, $value);
    }

    /** @return list<string> the scope types this service can resolve */
    public static function types(): array
    {
        return array_keys(self::$hierarchy);
    }

    public static function firstCode(string $type, ?string $value): ?string
    {
        $codes = self::expandCodes($type, $value);

        return $codes[0] ?? null;
    }
}
