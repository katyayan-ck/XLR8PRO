<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Import;

use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Models\Vehicle\Pricing\InsIdvSlot;
use App\Models\Vehicle\Pricing\PermitMap;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\Pricing\Rules\InsAddonRateService;
use App\Services\Vehicle\Pricing\Rules\InsBaseRuleService;
use App\Services\Vehicle\Pricing\Rules\InsDefaultService;
use App\Services\Vehicle\Pricing\Rules\InsIdvSlotService;
use App\Services\Vehicle\Pricing\Rules\PermitMapService;
use App\Services\Vehicle\Pricing\Rules\RuleFormula;
use App\Services\Vehicle\Pricing\Rules\RuleRange;
use App\Services\Vehicle\Pricing\SheetHeaderService;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Step 6 — the standalone Insurance workbook (DEC-073 / DEC-078).
 *
 *  Sheets:  "Insurance Co."  model + permit → up to 3 companies (Co. 1 = the default)       → ins_defaults
 *           "Insu Premium"   company / permit / wheels / fuel / CC / GVW / seating / plan,
 *                            IDV 1–3 basis, every OD / TP head, 17 add-ons                  → ins_base_rules (+ heads JSON),
 *                                                                                              ins_idv_slots, ins_addon_rates
 *           "Permit Map"     vehicle permit + wheels → RTO permit, insurance permit          → permit_map
 *  presence()             live rows per part (companies, premium, permit_map)
 *  export($path)          the live rules exactly as written (numbers and formulas), plus a blank company row for every
 *                         model / insurance permit without one
 *  import($path, $wef)    per sheet present, one transaction: expire the part's live rows, insert the sheet's rows
 *                         through the entity services. The reference layout imports as is (typo labels aliased, the
 *                         calculator columns ignored, the rightmost "IDV n" column used). Run inside record().
 */
class InsuranceWorkbookService
{
    public const SHEETS = ['companies' => 'Insurance Co.', 'premium' => 'Insu Premium', 'permit_map' => 'Permit Map'];

    public const KEY_COLUMNS = ['company' => 'Insu Co.', 'permit' => 'Permit', 'wheels' => 'Wheels', 'fuel' => 'Fuel', 'cc' => 'CC', 'gvw' => 'GVW', 'seating' => 'Seating', 'plan' => 'Plan', 'idv_1' => 'IDV 1', 'idv_2' => 'IDV 2', 'idv_3' => 'IDV 3'];

    /** head field => column label (sheet_headers INSU_PREMIUM) */
    public const HEADS = [
        'od_factor' => 'OD - OD Factor', 'od_cng_kit' => 'OD - CNG / LPG Kit', 'od_imt23' => 'OD - IMT 23', 'tp_basic' => 'TP Cover Basic',
        'tp_per_pass' => 'TP Cover Per Pass', 'tp_bi_fuel' => 'TP Cover Bi Fuel Kit (CNG / LPG)', 'tp_pa_owner' => 'TP - Compulsory PA Cover Owner Driver',
        'tp_pa_passengers' => 'TP - PA Cover for Passengers (1 Lakh Per Person)', 'tp_ll_driver' => 'TP - Legal Liability to Driver',
        'tp_ll_non_fare' => 'TP - Legal Liability for Non Fare Paying Passengers',
    ];

    /** add-on field => column label; the slug stored is the field without "addon_", upper case */
    public const ADDONS = [
        'addon_nil_dep' => 'Add On - Nil Depreciation', 'addon_consumables' => 'Add On - Consumables', 'addon_engine' => 'Add On - Engine Protect',
        'addon_battery_full' => 'Add On - Battery Protect (Including Motor, Charger & Adapter)', 'addon_battery_motor' => 'Add On - Battery Protect (Including Motor)',
        'addon_battery' => 'Add On - Battery Protect', 'addon_charger' => 'Add On - Charger & Adapter Cover', 'addon_motor' => 'Add On - Motor Protect',
        'addon_tyre' => 'Add On - Tyre Protection', 'addon_rti' => 'Add On - Return to Invoice', 'addon_key' => 'Add On - Key Replacement',
        'addon_rsa' => 'Add On - RSA', 'addon_belongings' => 'Add On - Personal Belongings', 'addon_daily_cash' => 'Add On - Daily Cash',
        'addon_advance' => 'Add On - Advance Assistance Cover', 'addon_medical' => 'Add On - Medical Expenses Cover', 'addon_towing' => 'Add On - Towing',
    ];

    /** base-rule numeric column fed by a head when the head is a plain number */
    private const HEAD_COLUMNS = [
        'od_factor' => 'od_factor', 'tp_basic' => 'tp_basic', 'tp_per_pass' => 'tp_per_passenger', 'tp_bi_fuel' => 'tp_bi_fuel_kit',
        'tp_pa_owner' => 'tp_pa_owner', 'tp_ll_driver' => 'tp_legal_driver', 'tp_ll_non_fare' => 'tp_non_fare_passenger',
    ];

    public const MAX_ISSUES = 2000;

    public function __construct(
        private readonly PricingWorkbookReader $reader,
        private readonly SheetHeaderService $headers,
        private readonly VehicleService $vehicles,
        private readonly InsDefaultService $defaults,
        private readonly InsBaseRuleService $baseRules,
        private readonly InsIdvSlotService $slots,
        private readonly InsAddonRateService $addonRates,
        private readonly PermitMapService $permitMap,
    ) {}

    /** @return array{companies: int, premium: int, permit_map: int} */
    public function presence(): array
    {
        return [
            'companies' => InsDefault::query()->where('is_active', true)->count(),
            'premium' => InsBaseRule::query()->where('is_active', true)->count(),
            'permit_map' => PermitMap::query()->where('is_active', true)->count(),
        ];
    }

    // ------------------------------------------------------------------ export

    /** @return array<string, int> rows per sheet */
    public function export(string $path): array
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $counts = [];
        foreach (['companies' => $this->companyRows(), 'premium' => $this->premiumRows(), 'permit_map' => $this->permitRows()] as $part => [$head, $rows]) {
            $sheet = $book->createSheet()->setTitle(self::SHEETS[$part]);
            $sheet->fromArray(array_merge([$head], $rows), null, 'A1', true);
            $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($head)).'1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            $counts[$part] = count($rows);
        }
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $counts;
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function companyRows(): array
    {
        $names = VehicleModel::query()->get(['code', 'name', 'oem_name'])->mapWithKeys(fn (VehicleModel $m) => [strtoupper($m->code) => (string) ($m->oem_name ?: $m->name ?: $m->code)]);
        $rows = [];
        foreach (InsDefault::query()->where('is_active', true)->orderBy('priority')->get()->groupBy(fn (InsDefault $d) => strtoupper($d->model_code.'|'.$d->permit)) as $group) {
            $first = $group->first();
            $model = in_array(strtoupper((string) $first->model_code), ['ANY', ''], true) ? 'Any' : ($names[strtoupper((string) $first->model_code)] ?? $first->model_code);
            $rows[strtoupper($first->model_code.'|'.$first->permit)] = array_merge([$model, $first->permit], array_pad($group->pluck('insurance_company')->take(3)->all(), 3, ''));
        }
        // every model × insurance permit its vehicles use, blank where no company is stored yet
        $variants = Variant::query()->whereNotNull('model_code')->get(['model_code', 'permit_id', 'wheels', 'taxi_price']);
        foreach ($variants as $v) {
            $permit = $this->vehicles->permitCode($v);
            $targets = $permit ? [$permit] : [];
            if ($permit && strtoupper((string) $v->taxi_price) === 'YES') {
                $targets[] = 'PASSENGER';
            }
            foreach ($targets as $vehiclePermit) {
                $insu = $this->permitMap->resolve($vehiclePermit, $v->wheels)?->insu_permit;
                $key = strtoupper($v->model_code.'|'.$insu);
                if ($insu && ! isset($rows[$key])) {
                    $rows[$key] = [$names[strtoupper((string) $v->model_code)] ?? $v->model_code, $insu, '', '', ''];
                }
            }
        }
        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => [strtoupper((string) $a[0]), $a[1]] <=> [strtoupper((string) $b[0]), $b[1]]);

        return [['Model', 'Permit', 'Insu Co. 1', 'Insu Co. 2', 'Insu Co. 3'], $rows];
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function premiumRows(): array
    {
        $rules = InsBaseRule::query()->where('is_active', true)->orderBy('id')->get();
        $slots = InsIdvSlot::query()->whereIn('base_rule_id', $rules->pluck('id'))->get()->groupBy('base_rule_id');
        $rates = InsAddonRate::query()->where('is_active', true)->whereIn('base_rule_id', $rules->pluck('id'))->get()->groupBy('base_rule_id');
        $rows = [];
        foreach ($rules as $rule) {
            $slot = ($slots[$rule->id] ?? collect())->keyBy('year_no');
            $rate = ($rates[$rule->id] ?? collect())->keyBy(fn (InsAddonRate $r) => strtoupper($r->addon_slug));
            $heads = (array) ($rule->heads ?? []);
            $row = [$rule->company, $rule->permit, $rule->wheels ?? 'ANY', $rule->fuel_type, $rule->cc_range, $rule->gvw_range, $rule->seating, $rule->plan,
                $slot->get(1)?->idv_basis, $slot->get(2)?->idv_basis, $slot->get(3)?->idv_basis];
            foreach (array_keys(self::HEADS) as $head) {
                $row[] = $heads[$head] ?? null;
            }
            foreach (array_keys(self::ADDONS) as $addon) {
                $r = $rate->get(strtoupper(substr($addon, 6)));
                $row[] = $r ? (is_numeric($r->rate_text) ? (float) $r->rate_text : ($r->rate_text ?? (float) $r->rate_value)) : null;
            }
            $rows[] = $row;
        }

        return [array_merge(array_values(self::KEY_COLUMNS), array_values(self::HEADS), array_values(self::ADDONS)), $rows];
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function permitRows(): array
    {
        $rows = PermitMap::query()->where('is_active', true)->orderBy('vehicle_permit')->orderBy('wheels')->get()
            ->map(fn (PermitMap $p) => [$p->vehicle_permit, $p->wheels ?? 'ANY', $p->rto_permit, $p->insu_permit, $p->label])->all();

        return [['Vehicle Permit', 'Wheels', 'RTO Permit', 'Insurance Permit', 'Label'], $rows];
    }

    // ------------------------------------------------------------------ import

    /**
     * @param  (callable(array<string, mixed>): void)|null  $onProgress
     * @return array{sheets: array<string, array<string, int>>, issues: list<array{sheet: string, row: int, reason: string}>}
     */
    public function import(string $path, string $wefDate, ?callable $onProgress = null): array
    {
        $result = ['sheets' => [], 'issues' => []];
        $titles = [];
        foreach ($this->reader->sheetNames($path) as $title) {
            foreach (self::SHEETS as $part => $sheet) {
                if (strtoupper(trim($title)) === strtoupper($sheet)) {
                    $titles[$part] = $title;
                }
            }
        }
        if ($titles === []) {
            throw ValidationException::withMessages(['file' => 'No "Insurance Co.", "Insu Premium" or "Permit Map" sheet found.']);
        }
        // the permit map first: companies and premiums are keyed by insurance permit
        foreach (['permit_map', 'companies', 'premium'] as $part) {
            if (! isset($titles[$part])) {
                continue;
            }
            $onProgress && $onProgress(['message' => 'Importing '.$titles[$part].'…']);
            $result['sheets'][$part] = match ($part) {
                'permit_map' => $this->importPermitMap($path, $titles[$part], $result),
                'companies' => $this->importCompanies($path, $titles[$part], $result),
                default => $this->importPremium($path, $titles[$part], $wefDate, $result),
            };
        }
        Log::info('[Pricing] insurance import', ['wef' => $wefDate, 'sheets' => $result['sheets']]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, int>
     */
    private function importPermitMap(string $path, string $sheet, array &$result): array
    {
        return $this->importPart($path, $sheet, 'PERMIT_MAP', $result, function (callable $get) {
            if ($get('vehicle_permit') === '') {
                return null;
            }

            return [strtoupper($get('vehicle_permit').'|'.$get('wheels')), [[
                'vehicle_permit' => $get('vehicle_permit'), 'wheels' => $get('wheels') ?: null, 'rto_permit' => $get('rto_permit'),
                'insu_permit' => $get('insu_permit'), 'label' => $get('label') ?: null, 'is_active' => true,
            ]]];
        }, fn () => $this->permitMap->expireActive(now()->toDateString()), fn (array $record) => $this->permitMap->create($record));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, int>
     */
    private function importCompanies(string $path, string $sheet, array &$result): array
    {
        return $this->importPart($path, $sheet, 'INSU_COMPANY', $result, function (callable $get) {
            $companies = array_values(array_filter([$get('company_1'), $get('company_2'), $get('company_3')]));
            if ($companies === [] || $get('model') === '') {
                return null;
            }
            $model = in_array(strtoupper($get('model')), ['ANY', 'ALL', '*'], true) ? 'ANY' : ($this->vehicles->findModel($get('model'))->code
                ?? throw new \InvalidArgumentException("Model \"{$get('model')}\" is not in the vehicle master."));
            $records = [];
            foreach ($companies as $i => $company) {
                $records[] = ['model_code' => $model, 'permit' => $get('permit') ?: null, 'insurance_company' => $company, 'priority' => $i + 1, 'is_default' => $i === 0, 'is_active' => true];
            }

            return [strtoupper($model.'|'.$get('permit')), $records];
        }, fn () => $this->defaults->expireActive(now()->toDateString()), fn (array $record) => $this->defaults->create($record));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, int>
     */
    private function importPremium(string $path, string $sheet, string $wefDate, array &$result): array
    {
        return $this->importPart($path, $sheet, 'INSU_PREMIUM', $result, function (callable $get, callable $raw, int $rowNo) use (&$result, $sheet, $wefDate) {
            if ($get('company') === '' || $get('plan') === '') {
                return null;
            }
            foreach (['cc' => 'CC', 'gvw' => 'GVW', 'seating' => 'Seating'] as $field => $label) {
                if (RuleRange::parse($get($field))->isInverted()) {
                    $this->issue($result, $sheet, $rowNo, "{$label} \"{$get($field)}\" runs backwards — it can never match (imported as written).");
                }
            }
            $rule = ['company' => $get('company'), 'plan' => $get('plan'), 'permit' => $get('permit'), 'wheels' => $get('wheels') ?: null,
                'fuel_type' => $get('fuel') ?: null, 'cc_range' => $get('cc') ?: null, 'gvw_range' => $get('gvw') ?: null, 'seating' => $get('seating') ?: null,
                'wef_date' => $wefDate, 'is_active' => true];
            $heads = [];
            foreach (array_keys(self::HEADS) as $head) {
                $value = $raw($head);
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }
                RuleFormula::variables($value);   // rejects a head that is not a number or a known formula
                $heads[$head] = RuleFormula::isNumber($value) ? (float) $value : PricingWorkbookReader::text($value);
                if (isset(self::HEAD_COLUMNS[$head]) && RuleFormula::isNumber($value)) {
                    $rule[self::HEAD_COLUMNS[$head]] = (float) $value;
                }
            }
            $rule['heads'] = $heads;
            $slots = [];
            foreach ([1, 2, 3] as $year) {
                if (($basis = $get('idv_'.$year)) !== '') {
                    $slots[] = ['year_no' => $year, 'idv_basis' => $basis];
                }
            }
            $rates = [];
            foreach (self::ADDONS as $addon => $label) {
                $value = $raw($addon);
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }
                $numeric = RuleFormula::isNumber($value);
                if (! $numeric) {
                    RuleFormula::variables($value);
                }
                $rates[] = ['insurance_company' => $get('company'), 'permit' => $get('permit') ?: null, 'addon_slug' => strtoupper(substr($addon, 6)),
                    'addon_name' => substr($label, 9), 'rate_type' => ! $numeric ? 'formula' : ((float) $value < 1 ? 'idv_rate' : 'flat'),
                    'rate_value' => $numeric ? (float) $value : 0, 'rate_text' => $numeric ? (string) $value : PricingWorkbookReader::text($value),
                    'applies_on' => $numeric && (float) $value < 1 ? 'idv' : 'premium', 'wef_date' => $wefDate, 'is_active' => true];
            }
            $key = strtoupper(implode('|', [$rule['company'], $rule['permit'], $rule['wheels'], $rule['fuel_type'], $rule['cc_range'], $rule['gvw_range'], $rule['seating'], $rule['plan']]));

            return [$key, [['rule' => $rule, 'slots' => $slots, 'rates' => $rates]]];
        }, fn () => $this->baseRules->expireActive($wefDate) + $this->addonRates->expireActive($wefDate), function (array $record) {
            $rule = $this->baseRules->create($record['rule']);
            foreach ($record['slots'] as $slot) {
                $this->slots->create($slot + ['base_rule_id' => $rule->id]);
            }
            foreach ($record['rates'] as $rate) {
                $this->addonRates->create($rate + ['base_rule_id' => $rule->id]);
            }
        }, idvRightmost: true);
    }

    /**
     * Read one sheet, turn rows into records ($parse → [scope key, records] or null for a blank row), then in one
     * transaction expire the part and write every record; conflicting duplicate scopes are rejected.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, int>
     */
    private function importPart(string $path, string $sheet, string $registry, array &$result, callable $parse, callable $expire, callable $write, bool $idvRightmost = false): array
    {
        $stats = ['rows' => 0, 'written' => 0, 'blank' => 0, 'duplicates' => 0, 'rejected' => 0, 'expired' => 0];
        $header = $this->reader->header($path, $sheet, $registry);
        if ($header['row'] === null) {
            $this->issue($result, $sheet, 0, 'Header row not found — this sheet was left unchanged.');

            return $stats;
        }
        $map = $idvRightmost ? $this->rightmostIdv($header['cells'], $header['map']) : $header['map'];

        $byScope = [];
        foreach ($this->reader->rows($path, $sheet, $header['row'] + 1, 'AZ') as $rowNo => $cells) {
            $stats['rows']++;
            $raw = fn (string $f) => isset($map[$f]) ? ($cells[$map[$f]] ?? null) : null;
            $get = fn (string $f) => PricingWorkbookReader::text($raw($f) ?? '');
            try {
                $parsed = $parse($get, $raw, $rowNo);
            } catch (\InvalidArgumentException $e) {
                $stats['rejected']++;
                $this->issue($result, $sheet, $rowNo, $e->getMessage());

                continue;
            }
            if ($parsed === null) {
                $stats['blank']++;

                continue;
            }
            $byScope[$parsed[0]][] = [$rowNo, $parsed[1]];
        }

        DB::transaction(function () use ($byScope, $sheet, $expire, $write, &$stats, &$result) {
            $stats['expired'] = $expire();
            foreach ($byScope as $rows) {
                if (count(array_unique(array_map(fn ($r) => serialize($r[1]), $rows))) > 1) {
                    $stats['rejected'] += count($rows);
                    foreach ($rows as [$rowNo]) {
                        $this->issue($result, $sheet, $rowNo, 'The same scope appears on rows '.implode(', ', array_column($rows, 0)).' with different values — fix the sheet; nothing written for it.');
                    }

                    continue;
                }
                $stats['duplicates'] += count($rows) - 1;
                foreach ($rows[0][1] as $record) {
                    try {
                        $write($record);
                        $stats['written']++;
                    } catch (ValidationException $e) {
                        $stats['rejected']++;
                        $this->issue($result, $sheet, $rows[0][0], implode(' ', array_merge(...array_values($e->errors()))));
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * The reference sheet repeats "IDV 1..3": calculator amounts on the left, the basis ("95% of Invoice") on the right.
     *
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $map
     * @return array<string, int>
     */
    private function rightmostIdv(array $cells, array $map): array
    {
        foreach ([1, 2, 3] as $n) {
            foreach ($cells as $col => $cell) {
                if ($this->headers->normalizeLabel($cell) === "idv {$n}") {
                    $map["idv_{$n}"] = (int) $col;
                }
            }
        }

        return $map;
    }

    /** @param array<string, mixed> $result */
    private function issue(array &$result, string $sheet, int $row, string $reason): void
    {
        if (count($result['issues']) < self::MAX_ISSUES) {
            $result['issues'][] = ['sheet' => $sheet, 'row' => $row, 'reason' => $reason];
        }
    }
}
