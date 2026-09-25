<?php

/**
 * Path: app/Services/Vehicle/Pricing/RulesWorkbookService.php
 *
 * Insurance + RTO workbook export / import for workflow stage awaiting_rules.
 *
 * If rules already exist the UI asks Keep existing vs Import new.
 * Import expires previous active rows on WEF then inserts the workbook.
 */

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Utils\SynonymService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RulesWorkbookService
{
    public function __construct(
        protected SheetHeaderService $headers,
        protected SynonymService $synonyms
    ) {}

    /**
     * @return array{rto:bool,insurance:bool,any:bool,rto_count:int,insurance_count:int}
     */
    public function presence(): array
    {
        $rto = $this->countTable('xlr8_vehicle_pricing_rto_rules');
        $ins = $this->countTable('xlr8_vehicle_pricing_ins_base_rules');

        return [
            'rto' => $rto > 0,
            'insurance' => $ins > 0,
            'any' => ($rto + $ins) > 0,
            'rto_count' => $rto,
            'insurance_count' => $ins,
        ];
    }

    /**
     * @return array{path:string,filename:string}
     */
    public function exportCurrent(ImportSession $session): array
    {
        $ss = new Spreadsheet;
        $ss->removeSheetByIndex(0);

        $this->writeSheet($ss, 'RTO Rules', $this->dumpRtoForOps());
        $this->writeSheet($ss, 'Insurance Co', $this->dumpTable('xlr8_vehicle_pricing_ins_defaults'));
        $this->writeSheet($ss, 'Insurance Base', $this->dumpTable('xlr8_vehicle_pricing_ins_base_rules'));
        $this->writeSheet($ss, 'Insurance Addons', $this->dumpTable('xlr8_vehicle_pricing_ins_addon_rates'));

        $dir = storage_path('app/pricing-exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'Insurance_RTO_Rules_'.$session->id.'_'.now()->format('Y-m-d_His').'.xlsx';
        $path = $dir.DIRECTORY_SEPARATOR.$filename;
        (new Xlsx($ss))->save($path);
        $ss->disconnectWorksheets();

        return compact('path', 'filename');
    }

    /**
     * @param  list<string>  $kinds  rto|insurance
     * @return array<string, array{written:int,skipped:int,errors:list<string>}>
     */
    public function importFile(
        string $absolutePath,
        ImportSession $session,
        array $kinds,
        ?string $wefDate = null,
        ?int $userId = null
    ): array {
        $userId = $userId ?? Auth::id();
        $wefDate = $wefDate ?? ($session->wef_date?->format('Y-m-d') ?? now()->toDateString());
        $kinds = array_map('strtolower', $kinds);
        $plog = new PricingProcessLogger($session->id);

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $ss = $reader->load($absolutePath);

        $out = [];
        foreach ($ss->getWorksheetIterator() as $ws) {
            $title = $ws->getTitle();
            $kind = $this->kindFromTitle($title);
            if ($kind === null || ! in_array($kind, $kinds, true)) {
                continue;
            }

            $plog->info('Rules sheet begin', ['title' => $title, 'kind' => $kind]);
            $matrix = $ws->toArray(null, true, true, false);
            if ($matrix === []) {
                continue;
            }

            if ($kind === 'rto') {
                $out['RTO:'.$title] = $this->importRto($matrix, $session, $wefDate, $userId, $plog);
            } else {
                $out['INS:'.$title] = $this->importInsuranceSheet($title, $matrix, $session, $wefDate, $userId, $plog);
            }
        }

        $ss->disconnectWorksheets();
        Cache::forget('pricing.rto.rules');
        Cache::forget('pricing.ins.base_rules');
        Cache::forget('pricing.ins.idv_slots');
        Cache::forget('pricing.ins.addon_rates');

        Log::info('[RulesWorkbook] import done', $out);
        $plog->info('Rules import done', $out);

        return $out;
    }

    public function kindFromTitle(string $title): ?string
    {
        $t = mb_strtoupper($title);
        if (str_contains($t, 'RTO') || str_contains($t, 'RTA')) {
            return 'rto';
        }
        if (
            str_contains($t, 'INSUR')
            || str_contains($t, 'INSU')
            || str_contains($t, 'ADDON')
            || str_contains($t, 'DEFAULT')
            || str_contains($t, 'PREMIUM')
            || $t === 'RULES'
        ) {
            return 'insurance';
        }

        return null;
    }

    protected function importRto(array $matrix, ImportSession $session, string $wef, ?int $userId, PricingProcessLogger $plog): array
    {
        $table = 'xlr8_vehicle_pricing_rto_rules';
        if (! Schema::hasTable($table)) {
            return ['written' => 0, 'skipped' => 0, 'errors' => ['rto table missing']];
        }

        [$idx, $map] = $this->locateHeader($matrix, ['permit', 'wheels', 'tax slab', 'tax factor', 'hypothecation']);
        $this->expireTable($table, $wef, $userId);

        $written = 0;
        $skipped = 0;
        $errors = [];
        foreach (array_slice($matrix, $idx + 1) as $row) {
            $permit = $this->synonyms->resolve('Permit', $this->cell($row, $map, ['permit', 'rto_permit']));
            $wheels = $this->cell($row, $map, ['wheels']);
            if ($permit === null && $wheels === null) {
                $skipped++;

                continue;
            }
            $taxBasis = $this->cell($row, $map, ['tax_factor', 'tax factor']);
            $taxSlabRaw = $this->cell($row, $map, ['tax_slab', 'tax slab']);
            $surchargeRaw = $this->cell($row, $map, ['surcharge']);
            $taxSlabNum = $this->num($taxSlabRaw);
            $surchargeNum = $this->percentOrNum($surchargeRaw);
            $payload = $this->onlyExisting($table, [
                'import_session_id' => $session->id,
                'permit' => $permit,
                'wheels' => $wheels,
                'reg_type' => $this->cell($row, $map, ['reg_type', 'reg type']),
                'body_type' => $this->cell($row, $map, ['body_type', 'body type']),
                'gvw_range' => $this->cell($row, $map, ['gvw', 'gvw_range']),
                'seater' => $this->cell($row, $map, ['seater', 'seating']),
                'fuel_type' => $this->synonyms->resolve('Fuel', $this->cell($row, $map, ['fuel', 'fuel_type'])),
                'cc_range' => $this->cell($row, $map, ['cc', 'cc_range']),
                'tax_basis' => $taxBasis,
                'tax_slab' => $taxSlabNum,
                'tax_factor' => is_numeric($taxBasis) ? $this->num($taxBasis) : $taxSlabNum,
                'surcharge' => $surchargeNum,
                'surcharge_formula' => $surchargeRaw,
                'hypothecation' => $this->num($this->cell($row, $map, ['hypothecation'])),
                'green_tax' => $this->num($this->cell($row, $map, ['green_tax', 'green tax'])),
                'registration_fee' => $this->num($this->cell($row, $map, ['registration_fee', 'registration fee'])),
                'duplicate_tax_card' => $this->num($this->cell($row, $map, ['duplicate_tax_card', 'duplicate tax card'])),
                'fitness' => $this->num($this->cell($row, $map, ['fitness'])),
                'penalty' => $this->num($this->cell($row, $map, ['penalty'])),
                'rto_tape' => $this->num($this->cell($row, $map, ['outside state - trc only', 'outside_state_trc_only', 'trc', 'rto_tape'])),
                'extra_json' => json_encode(['tax_basis' => $taxBasis, 'surcharge_formula' => $surchargeRaw]),
                'is_active' => 1,
                'wef_date' => $wef,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $plog);
            try {
                DB::table($table)->insert($payload);
                $written++;
            } catch (\Throwable $e) {
                if (count($errors) < 8) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $plog->info('RTO import', compact('written', 'skipped'));

        return compact('written', 'skipped', 'errors');
    }

    protected function importInsuranceSheet(
        string $title,
        array $matrix,
        ImportSession $session,
        string $wef,
        ?int $userId,
        PricingProcessLogger $plog
    ): array {
        $t = mb_strtoupper($title);
        if (str_contains($t, 'CO') && ! str_contains($t, 'PREMIUM') && ! str_contains($t, 'ADDON')) {
            return $this->importInsuranceCompanies($matrix, $session, $wef, $userId, $plog);
        }
        if ($t === 'RULES' || (str_contains($t, 'RULE') && ! str_contains($t, 'PREMIUM'))) {
            return $this->importPermitMap($matrix, $session);
        }

        return $this->importInsurancePremium($matrix, $session, $wef, $userId, $plog);
    }

    protected function importInsuranceCompanies(array $matrix, ImportSession $session, string $wef, ?int $userId, PricingProcessLogger $plog): array
    {
        $table = 'xlr8_vehicle_pricing_ins_defaults';
        if (! Schema::hasTable($table)) {
            return ['written' => 0, 'skipped' => 0, 'errors' => [$table.' missing']];
        }
        $this->expireTable($table, $wef, $userId);
        [$idx, $map] = $this->locateHeader($matrix, ['model', 'permit', 'insu co']);
        $written = 0;
        $skipped = 0;
        $errors = [];
        foreach (array_slice($matrix, $idx + 1) as $row) {
            $model = $this->cell($row, $map, ['model']);
            $permit = $this->synonyms->resolve('Permit', $this->cell($row, $map, ['permit']));
            $companies = array_values(array_filter([
                $this->cell($row, $map, ['insu_co_1', 'insu co 1', 'insu co.', 'company', 'insu_co']),
                $this->cell($row, $map, ['insu_co_2', 'insu co 2']),
                $this->cell($row, $map, ['insu_co_3', 'insu co 3']),
            ]));
            if ($model === null && $permit === null) {
                $skipped++;

                continue;
            }
            $first = true;
            foreach ($companies as $co) {
                try {
                    DB::table($table)->insert($this->onlyExisting($table, [
                        'import_session_id' => $session->id,
                        'model_code' => $model,
                        'permit' => $permit,
                        'company' => $co,
                        'insurance_company' => $co,
                        'is_default' => $first ? 1 : 0,
                        'priority' => $first ? 1 : null,
                        'is_active' => 1,
                        'wef_date' => $wef,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $plog));
                    $written++;
                    $first = false;
                } catch (\Throwable $e) {
                    if (count($errors) < 8) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
            if ($companies === []) {
                $skipped++;
            }
        }

        return compact('written', 'skipped', 'errors');
    }

    protected function importPermitMap(array $matrix, ImportSession $session): array
    {
        [$idx, $map] = $this->locateHeader($matrix, ['rto permit', 'insu permit']);
        $pairs = [];
        foreach (array_slice($matrix, $idx + 1) as $row) {
            $from = $this->cell($row, $map, ['rto_permit', 'rto permit']);
            $to = $this->cell($row, $map, ['insu_permit', 'insu permit']);
            if ($from && $to) {
                $pairs[$from] = $to;
            }
        }
        $notes = $session->notes ?? [];
        if (is_string($notes)) {
            $notes = json_decode($notes, true) ?: [];
        }
        if (! is_array($notes)) {
            $notes = [];
        }
        $notes['insu_permit_map'] = $pairs;
        $session->notes = json_encode($notes);
        $session->save();

        return ['written' => count($pairs), 'skipped' => 0, 'errors' => []];
    }

    protected function importInsurancePremium(
        array $matrix,
        ImportSession $session,
        string $wef,
        ?int $userId,
        PricingProcessLogger $plog
    ): array {
        $table = 'xlr8_vehicle_pricing_ins_base_rules';
        $slotTable = 'xlr8_vehicle_pricing_ins_idv_slots';
        if (! Schema::hasTable($table)) {
            return ['written' => 0, 'skipped' => 0, 'errors' => [$table.' missing']];
        }
        $this->expireTable($table, $wef, $userId);
        if (Schema::hasTable('xlr8_vehicle_pricing_ins_addon_rates')) {
            $this->expireTable('xlr8_vehicle_pricing_ins_addon_rates', $wef, $userId);
        }
        [$idx, $map] = $this->locateHeader($matrix, ['permit', 'wheels', 'fuel', 'plan', 'insu co']);
        $idvColumns = $this->findIdvColumns($matrix, $idx);
        $written = 0;
        $skipped = 0;
        $errors = [];
        $idvSlotsWritten = 0;
        foreach (array_slice($matrix, $idx + 1) as $row) {
            $permit = $this->synonyms->resolve('Permit', $this->cell($row, $map, ['permit']));
            $company = $this->cell($row, $map, ['insu_co', 'insu co', 'insu co.', 'company']);
            $plan = $this->cell($row, $map, ['plan']);
            if ($permit === null && $company === null && $plan === null) {
                $skipped++;

                continue;
            }

            [$odYears, $tpYears] = $this->parsePlanYears($plan);

            $payload = $this->onlyExisting($table, [
                'import_session_id' => $session->id,
                'company' => $company,
                'plan' => $plan,
                'od_years' => $odYears,
                'tp_years' => $tpYears,
                'permit' => $permit,
                'wheels' => $this->cell($row, $map, ['wheels']),
                'fuel_type' => $this->synonyms->resolve('Fuel', $this->cell($row, $map, ['fuel', 'fuel_type'])),
                'cc_range' => $this->cell($row, $map, ['cc', 'cc_range']),
                'gvw_range' => $this->cell($row, $map, ['gvw', 'gvw_range']),
                'seating' => $this->cell($row, $map, ['seatng', 'seating', 'seater']),
                'od_factor' => $this->num($this->cell($row, $map, ['od_factor', 'od - od factor', 'od od factor'])),
                'tp_basic' => $this->num($this->cell($row, $map, ['tp_basic', 'tp cover basic'])),
                'is_active' => 1,
                'wef_date' => $wef,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $plog);

            try {
                $baseRuleId = DB::table($table)->insertGetId($payload);
                $written++;

                if (Schema::hasTable($slotTable)) {
                    $yearNo = 0;
                    foreach ($idvColumns as $colIdx) {
                        $yearNo++;
                        $raw = $row[$colIdx] ?? null;
                        if ($raw === null || trim((string) $raw) === '') {
                            continue;
                        }
                        $raw = trim((string) $raw);
                        DB::table($slotTable)->insert([
                            'base_rule_id' => $baseRuleId,
                            'year_no' => $yearNo,
                            'idv_basis' => $raw,
                            'idv_pct' => $this->percentOrNum($raw),
                            'created_by' => $userId,
                            'updated_by' => $userId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $idvSlotsWritten++;
                    }
                }
            } catch (\Throwable $e) {
                if (count($errors) < 8) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        $plog->info('Insurance premium import', compact('written', 'skipped', 'idvSlotsWritten'));

        return compact('written', 'skipped', 'errors');
    }

    /**
     * The source sheet repeats "IDV 1/IDV 2/IDV 3" as two side-by-side groups
     * with identical labels: the first group holds a worked rupee-amount
     * example (95% of the sample "Inv" column's value, already computed —
     * not the target data), the second holds the actual percentage FORMULA
     * text ("95% of Invoice") that is the real, importable IDV basis. A plain
     * label => index map (fallbackMap()) can't hold two columns under the
     * same key, so this scans the raw header row for every "IDV" match, then
     * inspects real data beneath each candidate column and keeps only the
     * ones whose populated cells are formula TEXT (contain '%'), discarding
     * the pure-numeric example columns. Column order becomes the year_no
     * sequence (1, 2, 3, ...), so a future 5+8 plan just means more matching
     * text columns, not a schema change.
     *
     * @return list<int>
     */
    protected function findIdvColumns(array $matrix, int $headerIdx): array
    {
        $headerRow = $matrix[$headerIdx] ?? [];
        $candidates = [];
        foreach ($headerRow as $i => $label) {
            if (preg_match('/^IDV\s*\d*$/i', trim((string) $label))) {
                $candidates[] = $i;
            }
        }

        if ($candidates === []) {
            return [];
        }

        $dataRows = array_slice($matrix, $headerIdx + 1, 50);
        $columns = [];
        foreach ($candidates as $i) {
            foreach ($dataRows as $row) {
                $val = trim((string) ($row[$i] ?? ''));
                if ($val === '') {
                    continue;
                }
                if (str_contains($val, '%')) {
                    $columns[] = $i;
                }
                break;
            }
        }

        return $columns;
    }

    /**
     * "1+3" -> [1, 3]. "3+3" -> [3, 3]. Unrecognised/blank -> [null, null].
     * Never guess a formula for a pattern this doesn't recognise.
     *
     * @return array{0:?int,1:?int}
     */
    protected function parsePlanYears(?string $plan): array
    {
        if ($plan === null || trim($plan) === '') {
            return [null, null];
        }
        if (preg_match('/^\s*(\d+)\s*\+\s*(\d+)\s*$/', $plan, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [null, null];
    }

    protected function importGeneric(
        string $table,
        array $matrix,
        ImportSession $session,
        string $wef,
        ?int $userId,
        array $wanted
    ): array {
        if (! Schema::hasTable($table)) {
            return ['written' => 0, 'skipped' => 0, 'errors' => [$table.' missing']];
        }
        $this->expireTable($table, $wef, $userId);
        $header = $matrix[0] ?? [];
        $map = $this->fallbackMap($header);
        $written = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_slice($matrix, 1) as $row) {
            $payload = [
                'import_session_id' => $session->id,
                'is_active' => 1,
                'wef_date' => $wef,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $any = false;
            foreach ($wanted as $field) {
                $val = $this->cell($row, $map, [$field]);
                if ($val === null) {
                    continue;
                }
                $any = true;
                if (in_array($field, ['segment'], true)) {
                    $val = $this->synonyms->resolve('Segment', $val);
                }
                if (in_array($field, ['permit'], true)) {
                    $val = $this->synonyms->resolve('Permit', $val);
                }
                if (in_array($field, ['fuel'], true)) {
                    $val = $this->synonyms->resolve('Fuel', $val);
                }
                $payload[$field] = $val;
            }
            if (! $any) {
                $skipped++;

                continue;
            }
            try {
                DB::table($table)->insert($this->onlyExisting($table, $payload));
                $written++;
            } catch (\Throwable $e) {
                if (count($errors) < 8) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        return compact('written', 'skipped', 'errors');
    }

    protected function expireTable(string $table, string $wef, ?int $userId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'is_active')) {
            return;
        }
        $upd = ['is_active' => 0, 'updated_at' => now()];
        if (Schema::hasColumn($table, 'expired_on')) {
            $upd['expired_on'] = $wef;
        }
        if (Schema::hasColumn($table, 'updated_by')) {
            $upd['updated_by'] = $userId;
        }
        DB::table($table)->where('is_active', 1)->update($upd);
    }

    protected function dumpRtoForOps(): array
    {
        $table = 'xlr8_vehicle_pricing_rto_rules';
        if (! Schema::hasTable($table)) {
            return [['info' => 'RTO table missing']];
        }
        $q = DB::table($table);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        if (Schema::hasColumn($table, 'is_active')) {
            $q->where('is_active', 1);
        }
        $rows = $q->limit(5000)->get();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'Permit' => $r->permit ?? '',
                'Wheels' => $r->wheels ?? '',
                'Reg Type' => $r->reg_type ?? '',
                'Body Type' => $r->body_type ?? '',
                'GVW' => $r->gvw_range ?? '',
                'Seater' => $r->seater ?? '',
                'Fuel' => $r->fuel_type ?? '',
                'CC' => $r->cc_range ?? '',
                'Outside State - TRC' => $r->rto_tape ?? '',
                'Tax Factor' => $r->tax_basis ?? '',
                'Tax Slab' => $r->tax_slab ?? '',
                'Surcharge' => $r->surcharge_formula ?? $r->surcharge ?? '',
                'Hypothecation' => $r->hypothecation ?? '',
                'Green Tax' => $r->green_tax ?? '',
                'Registration Fee' => $r->registration_fee ?? '',
                'Duplicate Tax Card' => $r->duplicate_tax_card ?? '',
                'Fitness' => $r->fitness ?? '',
                'Penalty' => $r->penalty ?? '',
            ];
        }

        return $out ?: [['info' => 'No active RTO rows']];
    }

    protected function dumpTable(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [['info' => $table.' not present']];
        }
        $q = DB::table($table);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        if (Schema::hasColumn($table, 'is_active')) {
            $q->where('is_active', 1);
        }

        return json_decode(json_encode($q->limit(5000)->get()), true) ?: [];
    }

    protected function writeSheet(Spreadsheet $ss, string $title, array $rows): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle(mb_substr($title, 0, 31));
        if ($rows === []) {
            $ws->setCellValue('A1', 'No rows');

            return;
        }
        $headers = array_keys($rows[0]);
        foreach ($headers as $i => $h) {
            $ws->setCellValueByColumnAndRow($i + 1, 1, $h);
        }
        $last = $ws->getCellByColumnAndRow(count($headers), 1)->getCoordinate();
        $ws->getStyle('A1:'.$last)->getFont()->setBold(true);
        $ws->getStyle('A1:'.$last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $ws->getStyle('A1:'.$last)->getFont()->getColor()->setRGB('FFFFFF');
        $r = 2;
        foreach ($rows as $row) {
            $c = 1;
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                if (is_array($val)) {
                    $val = json_encode($val);
                }
                $ws->setCellValueByColumnAndRow($c, $r, $val);
                $c++;
            }
            $r++;
        }
    }

    protected function fallbackMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $i => $label) {
            $key = strtolower(trim((string) $label));
            $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;
            $key = trim($key, '_');
            if ($key === '') {
                continue;
            }
            $map[$key] = $i;
            $map[str_replace('_', '', $key)] = $i;
            $map[str_replace('_', ' ', $key)] = $i;
        }

        return $map;
    }

    /**
     * @param  list<string>  $hints
     * @return array{0:int,1:array<string,int>}
     */
    protected function locateHeader(array $matrix, array $hints): array
    {
        $bestIdx = 0;
        $bestMap = $this->fallbackMap($matrix[0] ?? []);
        $bestScore = 0;
        $limit = min(count($matrix), 12);
        for ($i = 0; $i < $limit; $i++) {
            $map = $this->fallbackMap($matrix[$i] ?? []);
            $score = 0;
            foreach ($hints as $h) {
                $k = strtolower(str_replace(' ', '_', $h));
                if (isset($map[$k]) || isset($map[str_replace('_', '', $k)])) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $i;
                $bestMap = $map;
            }
        }

        return [$bestIdx, $bestMap];
    }

    public static function percentOrNum(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        if (preg_match('/([\d.]+)\s*%/', (string) $v, $m)) {
            return (float) $m[1];
        }

        return self::num($v);
    }

    protected function cell(array $row, array $map, array $aliases): ?string
    {
        foreach ($aliases as $a) {
            $k = strtolower(str_replace(' ', '_', $a));
            $compact = str_replace('_', '', $k);
            $idx = $map[$k] ?? $map[$compact] ?? $map[str_replace('_', ' ', $k)] ?? null;
            if ($idx === null || ! array_key_exists($idx, $row)) {
                continue;
            }
            if ($row[$idx] === null || $row[$idx] === '') {
                continue;
            }

            return trim((string) $row[$idx]);
        }

        return null;
    }

    public static function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $s = preg_replace('/[^\d.\-]/', '', (string) $v);

        return is_numeric($s) ? (float) $s : null;
    }

    protected function onlyExisting(string $table, array $payload, ?PricingProcessLogger $plog = null): array
    {
        $nullable = $this->nullableColumns($table);
        $out = [];
        $stripped = [];
        foreach ($payload as $col => $val) {
            if (! Schema::hasColumn($table, $col)) {
                if ($val !== null && $val !== '') {
                    $stripped[] = $col;
                }

                continue;
            }

            // A blank source cell on a NOT NULL column (e.g. "3+3" plans
            // carry no OD Factor in the sheet at all) must fall through to
            // the column's own DB default rather than explicitly write NULL
            // and crash the insert — never guess a value, just don't force one.
            if ($val === null && ! ($nullable[$col] ?? true)) {
                continue;
            }

            $out[$col] = $val;
        }

        if ($stripped !== [] && $plog !== null) {
            $plog->warning('onlyExisting() stripped payload keys with no matching column', [
                'table' => $table,
                'columns' => $stripped,
            ]);
        }

        return $out;
    }

    /**
     * @return array<string, bool>
     */
    protected function nullableColumns(string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $map = [];
        foreach (Schema::getColumns($table) as $col) {
            $map[$col['name']] = (bool) $col['nullable'];
        }

        return $cache[$table] = $map;
    }

    protected function countTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $q = DB::table($table);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        if (Schema::hasColumn($table, 'is_active')) {
            $q->where('is_active', 1);
        }

        return (int) $q->count();
    }
}
