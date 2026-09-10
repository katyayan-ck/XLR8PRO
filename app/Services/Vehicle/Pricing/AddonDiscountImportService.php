<?php

/**
 * Path: app/Services/Vehicle/Pricing/AddonDiscountImportService.php
 *
 * Imports Addon-N-Discounts.xlsx:
 *   Dealer Charges - Segment Wise | Shield | RSA | Exchange | Corporate
 *
 * Writes into the LIVE column set:
 *   dealer_charges: one WIDE row (incidental/fastag/trc/rto_tape/cod)
 *   addons: RSA/SHIELD rows (segment/pack/trans/fuel when columns exist)
 *   discounts: name + discount_category + total_discount (+ scheme_name/category/amount if present)
 */

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Utils\SynonymService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AddonDiscountImportService
{
    public const SHEET_MAP = [
        'DEALER CHARGES' => 'DEALER_CHARGES',
        'DEALER CHARGES - SEGMENT WISE' => 'DEALER_CHARGES',
        'SHIELD' => 'SHIELD',
        'RSA' => 'RSA',
        'EXCHANGE' => 'EXCHANGE',
        'CORPORATE' => 'CORPORATE',
    ];

    public function __construct(
        protected SheetHeaderService $headers,
        protected SynonymService $synonyms
    ) {}

    /**
     * @param  list<string>  $selected
     * @return array<string, array{written:int,skipped:int,errors:list<string>}>
     */
    public function importFile(
        string $absolutePath,
        ImportSession $session,
        array $selected,
        ?string $wefDate = null,
        ?int $userId = null
    ): array {
        $userId = $userId ?? Auth::id();
        $wefDate = $wefDate ?? ($session->wef_date?->format('Y-m-d') ?? now()->toDateString());
        $selected = array_map('strtoupper', $selected);
        $plog = new PricingProcessLogger($session->id);
        $progressKey = 'pricing_addons_progress_' . $session->id;

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);

        $result = [];
        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $title = $ws->getTitle();
            $code = $this->sheetCodeFromTitle($title);
            if ($code === null || ! in_array($code, $selected, true)) {
                continue;
            }

            $this->push($progressKey, 'Importing ' . $title);
            $plog->info('Addon sheet begin', ['title' => $title, 'code' => $code]);

            $matrix = $ws->toArray(null, true, true, false);
            [$idx, $map] = $this->headers->findHeaderRow($code, $matrix, 20);
            if ($idx === null || $map === []) {
                $idx = 0;
                $map = $this->headers->mapHeaderRow($code, $matrix[0] ?? []);
            }
            if ($map === []) {
                $idx = 0;
                $map = $this->fallbackHeaderMap($matrix[0] ?? []);
            }
            if ($map === []) {
                $result[$code] = ['written' => 0, 'skipped' => 0, 'errors' => [$title . ': header not found']];
                continue;
            }
            $rows = array_slice($matrix, $idx + 1);

            $result[$code] = match ($code) {
                'DEALER_CHARGES' => $this->importDealerCharges($rows, $map, $session, $wefDate, $userId),
                'RSA'            => $this->importRsa($rows, $map, $session, $wefDate, $userId),
                'SHIELD'         => $this->importShield($rows, $map, $session, $wefDate, $userId),
                'EXCHANGE'       => $this->importDiscount($rows, $map, 'EXCHANGE', $session, $wefDate, $userId),
                'CORPORATE'      => $this->importDiscount($rows, $map, 'CORPORATE', $session, $wefDate, $userId),
                default          => ['written' => 0, 'skipped' => 0, 'errors' => ['unknown sheet']],
            };
        }

        $spreadsheet->disconnectWorksheets();
        Cache::put($progressKey, [
            'done'       => true,
            'result'     => $this->trimErrors($result),
            'message'    => 'Addons import finished',
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        Log::info('[AddonDiscountImport] done', $this->trimErrors($result));
        $plog->info('Addon import done', $this->trimErrors($result));

        return $this->trimErrors($result);
    }

    public function sheetCodeFromTitle(string $title): ?string
    {
        $t = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $title) ?? $title));
        if (isset(self::SHEET_MAP[$t])) {
            return self::SHEET_MAP[$t];
        }
        foreach (self::SHEET_MAP as $label => $code) {
            if (str_contains($t, $label)) {
                return $code;
            }
        }

        return null;
    }

    protected function importDealerCharges(array $rows, array $map, ImportSession $session, string $wef, ?int $userId): array
    {
        $written = 0;
        $skipped = 0;
        $errors = [];

        DealerCharge::query()->where('is_active', true)->update([
            'is_active'  => false,
            'expired_on' => $wef,
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);

        $wide = Schema::hasColumn('xlr8_vehicle_pricing_dealer_charges', 'incidental')
            || Schema::hasColumn('xlr8_vehicle_pricing_dealer_charges', 'fastag');

        foreach ($rows as $row) {
            $segment = $this->anyOrValue($this->synonyms->resolve('Segment', $this->str($row, $map, 'segment')));
            $permit = $this->anyOrValue($this->synonyms->resolve('Permit', $this->str($row, $map, 'permit')));
            $model = $this->anyOrValue($this->str($row, $map, 'model') ?? $this->str($row, $map, 'oem_model'));
            if ($segment === null && $model === null) {
                $skipped++;
                continue;
            }

            $inc = $this->dec($row, $map, 'incidental_charges') ?? 0;
            $ft = $this->dec($row, $map, 'fast_tag') ?? 0;
            $trc = $this->dec($row, $map, 'trc') ?? 0;
            $tape = $this->dec($row, $map, 'rto_tape') ?? 0;
            $cod = $this->dec($row, $map, 'cod_charges') ?? 0;
            if ($inc == 0 && $ft == 0 && $trc == 0 && $tape == 0 && $cod == 0) {
                $skipped++;
                continue;
            }

            try {
                $payload = [
                    'import_session_id' => $session->id,
                    'segment'           => $segment,
                    'permit'            => $permit,
                    'model_code'        => $model,
                    'is_active'         => true,
                    'wef_date'          => $wef,
                    'created_by'        => $userId,
                    'updated_by'        => $userId,
                ];
                if ($wide) {
                    $payload['incidental'] = $inc;
                    $payload['fastag'] = $ft;
                    $payload['trc'] = $trc;
                    $payload['rto_tape'] = $tape;
                    $payload['cod'] = $cod;
                } else {
                    $payload['charge_code'] = 'bundle';
                    $payload['charge_name'] = 'Dealer charges';
                    $payload['amount'] = $inc + $ft + $trc + $tape + $cod;
                }
                DealerCharge::query()->create($this->onlyFillable(DealerCharge::class, $payload));
                $written++;
            } catch (\Throwable $e) {
                $this->pushError($errors, $e->getMessage());
            }
        }

        return compact('written', 'skipped', 'errors');
    }

    protected function importRsa(array $rows, array $map, ImportSession $session, string $wef, ?int $userId): array
    {
        $written = 0;
        $skipped = 0;
        $errors = [];
        $this->expireAddons('RSA', $wef, $userId);

        $yearFields = [
            1 => 'std_plus_1',
            2 => 'std_plus_2',
            3 => 'std_plus_3',
            4 => 'std_plus_4',
            5 => 'std_plus_5',
        ];

        foreach ($rows as $row) {
            $segment = $this->anyOrValue($this->synonyms->resolve('Segment', $this->str($row, $map, 'segment')));
            $model = $this->anyOrValue($this->str($row, $map, 'model') ?? $this->str($row, $map, 'oem_model'));
            if ($segment === null && $model === null) {
                $skipped++;
                continue;
            }
            $std = $this->str($row, $map, 'std_coverage');
            $firstPaid = true;
            $any = false;
            foreach ($yearFields as $years => $field) {
                $amt = $this->dec($row, $map, $field);
                if ($amt === null) {
                    continue;
                }
                $any = true;
                try {
                    Addon::query()->create($this->onlyFillable(Addon::class, [
                        'import_session_id' => $session->id,
                        'addon_type'        => 'RSA',
                        'segment'           => $segment,
                        'model_code'        => $model ?: 'ANY',
                        'scheme_name'       => 'Std + ' . $years . ' Year',
                        'name'              => $std,
                        'tenure_years'      => $years,
                        'amount'            => $amt,
                        'is_default'        => $firstPaid,
                        'is_active'         => true,
                        'wef_date'          => $wef,
                        'created_by'        => $userId,
                        'updated_by'        => $userId,
                    ]));
                    $written++;
                    $firstPaid = false;
                } catch (\Throwable $e) {
                    $this->pushError($errors, $e->getMessage());
                }
            }
            if (! $any) {
                $skipped++;
            }
        }

        return compact('written', 'skipped', 'errors');
    }

    protected function importShield(array $rows, array $map, ImportSession $session, string $wef, ?int $userId): array
    {
        $written = 0;
        $skipped = 0;
        $errors = [];
        $this->expireAddons('SHIELD', $wef, $userId);

        foreach ($rows as $row) {
            $pack = $this->anyOrValue($this->str($row, $map, 'shield_pack'));
            $trans = $this->anyOrValue($this->str($row, $map, 'transmission'));
            $fuel = $this->anyOrValue($this->synonyms->resolve('Fuel', $this->str($row, $map, 'fuel')));
            $oemModel = $this->anyOrValue($this->str($row, $map, 'oem_model') ?? $this->str($row, $map, 'model'));
            $oemVariant = $this->anyOrValue($this->str($row, $map, 'oem_variant') ?? $this->str($row, $map, 'variant'));
            $hasAny = false;

            for ($i = 1; $i <= 6; $i++) {
                $name = $this->str($row, $map, 'scheme_' . $i . '_name');
                $amt = $this->dec($row, $map, 'scheme_' . $i . '_amt');
                if ($name === null && $amt === null) {
                    continue;
                }
                $hasAny = true;
                try {
                    Addon::query()->create($this->onlyFillable(Addon::class, [
                        'import_session_id' => $session->id,
                        'addon_type'        => 'SHIELD',
                        'model_code'        => $oemModel ?: 'ANY',
                        'variant_code'      => $oemVariant,
                        'scheme_name'       => $name ?: ('Shield Scheme ' . $i),
                        'name'              => $name,
                        'amount'            => $amt ?? 0,
                        'shield_pack'       => $pack,
                        'transmission'      => $trans,
                        'fuel'              => $fuel,
                        'is_default'        => $i === 1,
                        'is_active'         => true,
                        'wef_date'          => $wef,
                        'created_by'        => $userId,
                        'updated_by'        => $userId,
                    ]));
                    $written++;
                } catch (\Throwable $e) {
                    $this->pushError($errors, $e->getMessage());
                }
            }
            if (! $hasAny) {
                $skipped++;
            }
        }

        return compact('written', 'skipped', 'errors');
    }

    protected function importDiscount(
        array $rows,
        array $map,
        string $type,
        ImportSession $session,
        string $wef,
        ?int $userId
    ): array {
        $written = 0;
        $skipped = 0;
        $errors = [];
        $this->expireDiscounts($type, $wef, $userId);

        foreach ($rows as $row) {
            $model = $this->anyOrValue($this->str($row, $map, 'model') ?? $this->str($row, $map, 'oem_model'));
            $variant = $this->anyOrValue($this->str($row, $map, 'variant') ?? $this->str($row, $map, 'oem_variant'));
            $oem = $this->dec($row, $map, 'oem_share') ?? 0;
            $dlr = $this->dec($row, $map, 'dealer_share') ?? 0;
            $total = $this->dec($row, $map, 'total');
            $name = $this->str($row, $map, $type === 'CORPORATE' ? 'category' : 'scheme_type');
            if ($model === null && $variant === null && $name === null && $oem == 0 && $dlr == 0) {
                $skipped++;
                continue;
            }
            $sum = $total ?? round($oem + $dlr, 2);
            try {
                Discount::query()->create($this->onlyFillable(Discount::class, [
                    'import_session_id' => $session->id,
                    'discount_type'     => $type,
                    'scheme_name'       => $type === 'EXCHANGE' ? $name : null,
                    'category'          => $type === 'CORPORATE' ? $name : null,
                    'discount_category' => $type === 'CORPORATE' ? $name : $type,
                    'name'              => $name ?: $type,
                    'model_code'        => $model ?: 'ANY',
                    'variant_code'      => $variant,
                    'oem_share'         => $oem,
                    'dealer_share'      => $dlr,
                    'amount'            => $sum,
                    'total_discount'    => $sum,
                    'is_active'         => true,
                    'wef_date'          => $wef,
                    'created_by'        => $userId,
                    'updated_by'        => $userId,
                ]));
                $written++;
            } catch (\Throwable $e) {
                $this->pushError($errors, $e->getMessage());
            }
        }

        return compact('written', 'skipped', 'errors');
    }

    protected function expireAddons(string $type, string $wef, ?int $userId): void
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_addons')) {
            return;
        }
        Addon::query()->ofType($type)->where('is_active', true)->update([
            'is_active'  => false,
            'expired_on' => $wef,
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);
    }

    protected function expireDiscounts(string $type, string $wef, ?int $userId): void
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_discounts')) {
            return;
        }
        Discount::query()->ofType($type)->where('is_active', true)->update([
            'is_active'  => false,
            'expired_on' => $wef,
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);
    }

    protected function onlyFillable(string $modelClass, array $payload): array
    {
        $table = (new $modelClass)->getTable();
        $out = [];
        foreach ($payload as $k => $v) {
            if (Schema::hasColumn($table, $k)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    protected function str(array $row, array $map, string $field): ?string
    {
        $v = $this->headers->val($row, $map, $field);
        if ($v === null || trim((string) $v) === '') {
            return null;
        }

        return trim((string) $v);
    }

    protected function dec(array $row, array $map, string $field): ?float
    {
        $v = $this->headers->val($row, $map, $field);
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return round((float) $v, 2);
        }
        $s = preg_replace('/[^\d.\-]/', '', (string) $v);

        return is_numeric($s) ? round((float) $s, 2) : null;
    }

    protected function anyOrValue(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim($v);
        if ($t === '' || strcasecmp($t, 'ANY') === 0 || strcasecmp($t, 'ALL') === 0) {
            return null;
        }

        return $t;
    }

    protected function push(string $key, string $msg): void
    {
        $prev = Cache::get($key, []);
        $logs = $prev['logs'] ?? [];
        $logs[] = '[' . now()->format('H:i:s') . '] ' . $msg;
        Cache::put($key, array_merge($prev, [
            'message'    => $msg,
            'logs'       => array_slice($logs, -40),
            'updated_at' => now()->toIso8601String(),
        ]), now()->addHours(6));
    }

    protected function pushError(array &$errors, string $msg): void
    {
        if (count($errors) < 15 && ! in_array($msg, $errors, true)) {
            $errors[] = $msg;
        }
    }

    protected function trimErrors(array $result): array
    {
        foreach ($result as $k => $block) {
            if (isset($block['errors']) && is_array($block['errors'])) {
                $result[$k]['errors'] = array_slice($block['errors'], 0, 15);
                $result[$k]['error_count'] = count($block['errors']);
            }
        }

        return $result;
    }

    /**
     * When the header registry has no labels for Exchange / Corporate / RSA,
     * map the raw Excel header text to field_codes.
     *
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    protected function fallbackHeaderMap(array $headerRow): array
    {
        $aliases = [
            'segment' => 'segment',
            'permit' => 'permit',
            'model' => 'model',
            'oem model' => 'oem_model',
            'oem variant' => 'oem_variant',
            'variant' => 'variant',
            'incidental charges' => 'incidental_charges',
            'fasttag' => 'fast_tag',
            'fast tag' => 'fast_tag',
            'trc' => 'trc',
            'rto tape' => 'rto_tape',
            'cod charges' => 'cod_charges',
            'standard coverage' => 'std_coverage',
            'std + 1 year' => 'std_plus_1',
            'std + 2 years' => 'std_plus_2',
            'std + 3 years' => 'std_plus_3',
            'std + 4 years' => 'std_plus_4',
            'std + 5 years' => 'std_plus_5',
            'shield pack' => 'shield_pack',
            'transmission' => 'transmission',
            'fuel' => 'fuel',
            'standard warranty' => 'std_warranty',
            'shield scheme 1 name' => 'scheme_1_name',
            'shield scheme 1 amt' => 'scheme_1_amt',
            'shield scheme 2 name' => 'scheme_2_name',
            'shield scheme 2 amt' => 'scheme_2_amt',
            'scheme type' => 'scheme_type',
            'scheme' => 'scheme_type',
            'category' => 'category',
            'oem share' => 'oem_share',
            'dealer share' => 'dealer_share',
            'dlr share' => 'dealer_share',
            'total' => 'total',
        ];
        $map = [];
        foreach ($headerRow as $i => $label) {
            $n = strtolower(trim(preg_replace('/\s+/', ' ', (string) $label) ?? ''));
            if ($n === '' || ! isset($aliases[$n])) {
                continue;
            }
            $map[$aliases[$n]] ??= (int) $i;
        }

        return $map;
    }
}
