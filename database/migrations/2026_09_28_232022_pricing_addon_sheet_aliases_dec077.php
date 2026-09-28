<?php

use App\Services\Vehicle\Pricing\SheetHeaderService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-077: the reference Addon-N-Discounts.xlsx labels for the Exchange and Corporate sheets (the registry only knew
 * "Model / Scheme Type / OEM Share / Dealer Share / Total"). Aliases only; down() removes exactly what was added.
 */
return new class extends Migration
{
    private const TABLE = 'xlr8_vehicle_pricing_sheet_headers';

    /** sheet => field => aliases appended */
    private const ALIASES = [
        'EXCHANGE' => [
            'model' => ['OEM Model'], 'variant' => ['OEM Variant'], 'scheme_type' => ['Scheme'],
            'oem_share' => ['Bonus OEM'], 'dealer_share' => ['Bonus DLR'], 'total' => ['Bonus TOTAL'],
        ],
        'CORPORATE' => [
            'model' => ['OEM Model'], 'variant' => ['OEM Variant'], 'oem_share' => ['OEM'], 'dealer_share' => ['DLR'], 'total' => ['TOTAL'],
        ],
        'RSA' => ['model' => ['OEM Model']],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }
        foreach (self::ALIASES as $sheet => $fields) {
            foreach ($fields as $field => $add) {
                $this->row($sheet, $field)->update(['aliases' => $this->json(array_values(array_unique(array_merge($this->aliases($sheet, $field), $add)))), 'updated_at' => now()]);
            }
        }
        app(SheetHeaderService::class)->forgetCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }
        foreach (self::ALIASES as $sheet => $fields) {
            foreach ($fields as $field => $added) {
                $this->row($sheet, $field)->update(['aliases' => $this->json(array_values(array_diff($this->aliases($sheet, $field), $added)))]);
            }
        }
        app(SheetHeaderService::class)->forgetCache();
    }

    private function row(string $sheet, string $field): Builder
    {
        return DB::table(self::TABLE)->where('sheet_code', $sheet)->where('field_code', $field)->whereNull('deleted_at');
    }

    /** @return list<string> */
    private function aliases(string $sheet, string $field): array
    {
        return (array) json_decode((string) $this->row($sheet, $field)->value('aliases'), true);
    }

    /** @param list<string> $aliases */
    private function json(array $aliases): ?string
    {
        return $aliases === [] ? null : json_encode($aliases);
    }
};
