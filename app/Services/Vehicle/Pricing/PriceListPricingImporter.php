<?php

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ChangeFlag;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\Profile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Stage 2: memory-safe OEM price import.
 *
 * NEVER load the whole workbook. Each selected sheet is opened alone,
 * processed in 50-row chunks, then discarded.
 */
class PriceListPricingImporter
{
    protected const MAX_COL = 'AZ';
    protected const CHUNK = 50;

    protected array $fieldToColumn = [
        'asse_value_freight'   => 'assessable_value_with_freight',
        'gst_pct'              => 'gst_percent',
        'gst_amount'           => 'gst_amount',
        'mm_inv_amt'           => 'mm_invoice_amount',
        'dealer_margin'        => 'dealer_margin',
        'dealer_handling'      => 'dealer_margin',
        'ex_showroom'          => 'ex_showroom_price',
        'curr_oem_scheme'      => 'curr_oem_scheme',
        'curr_dealer_cont'     => 'curr_dealer_cont',
        'curr_cash_discount'   => 'curr_cash_discount',
        'curr_acc_discount'    => 'curr_acc_discount',
        'curr_shield_discount' => 'curr_shield_discount',
        'old_oem_scheme'       => 'old_oem_scheme',
        'old_dealer_cont'      => 'old_dealer_cont',
        'old_cash_discount'    => 'old_cash_discount',
        'old_acc_discount'     => 'old_acc_discount',
        'old_shield_discount'  => 'old_shield_discount',
    ];

    public function __construct(
        protected SheetHeaderService $headers
    ) {}

    /**
     * @param  list<string>  $selectedSheetCodes
     * @param  null|callable(array):void  $onProgress
     * @return array{written:int,changed:int,skipped:int,skipped_incomplete:int,skipped_no_price:int,errors:list<string>}
     */
    public function importFile(
        string $absolutePath,
        ImportSession $session,
        array $selectedSheetCodes,
        ?string $wefDate = null,
        string $channel = 'normal',
        ?int $userId = null,
        ?callable $onProgress = null
    ): array {
        $userId  = $userId ?? Auth::id();
        $wefDate = $wefDate ?? ($session->wef_date?->format('Y-m-d') ?? now()->toDateString());
        $selectedSheetCodes = array_map('strtoupper', $selectedSheetCodes);

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $plog = new PricingProcessLogger($session->id);
        $plog->info('Price import start', [
            'sheets' => $selectedSheetCodes,
            'wef'    => $wefDate,
            'file'   => basename($absolutePath),
        ]);

        $stats = [
            'written'            => 0,
            'changed'            => 0,
            'skipped'            => 0,
            'skipped_incomplete' => 0,
            'skipped_no_price'   => 0,
            'errors'             => [],
        ];

        $completeCodes = Profile::query()
            ->where('is_vehicle_master_complete', true)
            ->pluck('model_code')
            ->map(fn ($c) => strtoupper(preg_replace('/\s+/', '', (string) $c) ?? (string) $c))
            ->flip()
            ->all();

        $plog->info('Complete profiles loaded', ['count' => count($completeCodes)]);
        if ($onProgress) {
            $onProgress([
                'message' => 'Complete profiles: ' . count($completeCodes),
                'percent' => 5,
                'logs'    => ['[' . now()->format('H:i:s') . '] Complete profiles: ' . count($completeCodes)],
            ]);
        }

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $wanted = [];
        foreach ($reader->listWorksheetNames($absolutePath) as $title) {
            $sheetCode = PriceListVehicleDetector::sheetCodeFromTitle($title);
            if ($sheetCode === null || ! in_array($sheetCode, $selectedSheetCodes, true)) {
                continue;
            }
            $wanted[] = ['title' => $title, 'sheetCode' => $sheetCode];
        }

        $plog->info('Sheets selected (names only)', [
            'count'  => count($wanted),
            'titles' => array_column($wanted, 'title'),
        ]);

        $processed = 0;
        $totalRows = 0;
        $sheetIndex = 0;

        foreach ($wanted as $item) {
            $sheetIndex++;
            $title = $item['title'];
            $sheetCode = $item['sheetCode'];
            $rowChannel = $sheetCode === 'PRICE_LIST_CSD' ? 'csd' : $channel;

            if ($onProgress) {
                $onProgress([
                    'message' => "Opening {$title} ({$sheetIndex}/" . count($wanted) . ')…',
                    'sheet'   => $title,
                    'logs'    => ['[' . now()->format('H:i:s') . "] Opening sheet {$title} only"],
                ]);
            }

            $spreadsheet = $this->loadOneSheet($reader, $absolutePath, $title);
            $ws = $spreadsheet->getSheetByName($title) ?? $spreadsheet->getActiveSheet();

            $highest = min((int) $ws->getHighestRow(), 20000);
            $colLetter = $this->capColumn((string) $ws->getHighestColumn());

            if ($highest < 2) {
                $this->release($spreadsheet);
                continue;
            }

            $headerProbe = $ws->rangeToArray('A1:' . $colLetter . '20', null, false, false, false);
            [$headerIdx, $fieldMap] = $this->headers->findHeaderRow($sheetCode, $headerProbe, 20);

            if (($headerIdx === null || ! isset($fieldMap['model_code']))
                && in_array($sheetCode, ['PRICE_LIST_LMM', 'PRICE_LIST_LMM_TZU', 'PRICE_LIST_BEV', 'PRICE_LIST_CSD'], true)
            ) {
                [$headerIdx, $fieldMap] = $this->headers->findHeaderRow('PRICE_LIST_PV', $headerProbe, 20);
            }
            if (isset($fieldMap['oem_code']) && ! isset($fieldMap['model_code'])) {
                $fieldMap['model_code'] = $fieldMap['oem_code'];
            }
            if ($headerIdx === null || ! isset($fieldMap['model_code'])) {
                $fieldMap = $this->headers->mapHeaderRow($sheetCode, $headerProbe[0] ?? []);
                if (! isset($fieldMap['model_code'])) {
                    $fieldMap = $this->mapMCodeFallback($headerProbe[$headerIdx ?? 0] ?? $headerProbe[0] ?? []);
                }
                $headerIdx = $headerIdx ?? 0;
            }
            unset($headerProbe);

            if ($headerIdx === null || ! isset($fieldMap['model_code'])) {
                $stats['errors'][] = "{$title}: header/model_code not found";
                $plog->warning('Price sheet skipped — no model_code', ['sheet' => $title]);
                $this->release($spreadsheet);
                continue;
            }

            $dataStart = $headerIdx + 2;
            $dataEnd = $highest;
            $dataCount = max(0, $dataEnd - $dataStart + 1);
            $totalRows += $dataCount;

            $plog->info('Price sheet begin', [
                'sheet'      => $title,
                'rows'       => $dataCount,
                'header_row' => $headerIdx + 1,
                'col'        => $colLetter,
                'fields'     => array_keys($fieldMap),
            ]);

            $sheetWritten = 0;

            for ($from = $dataStart; $from <= $dataEnd; $from += self::CHUNK) {
                $to = min($from + self::CHUNK - 1, $dataEnd);
                $chunkRows = $ws->rangeToArray('A' . $from . ':' . $colLetter . $to, null, false, false, false);

                $chunkCodes = [];
                foreach ($chunkRows as $row) {
                    $raw = $this->headers->val($row, $fieldMap, 'model_code');
                    if ($raw !== null && $raw !== '') {
                        $chunkCodes[] = strtoupper(preg_replace('/\s+/', '', (string) $raw) ?? (string) $raw);
                    }
                }
                $chunkCodes = array_values(array_unique(array_filter($chunkCodes)));

                $existingByCode = collect();
                $existingSameWef = collect();
                if ($chunkCodes !== []) {
                    $existingByCode = Pricing::query()
                        ->whereIn('model_code', $chunkCodes)
                        ->where('channel', $rowChannel)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->orderByDesc('wef_date')
                        ->get()
                        ->groupBy(fn ($p) => strtoupper((string) $p->model_code))
                        ->map(fn ($g) => $g->first());

                    $existingSameWef = Pricing::query()
                        ->whereIn('model_code', $chunkCodes)
                        ->where('channel', $rowChannel)
                        ->whereDate('wef_date', $wefDate)
                        ->whereNull('deleted_at')
                        ->get()
                        ->keyBy(fn ($p) => strtoupper((string) $p->model_code));
                }

                $writtenCodes = [];

                DB::transaction(function () use (
                    $chunkRows, $fieldMap, $rowChannel, $wefDate, $session, $userId,
                    &$stats, $existingByCode, $existingSameWef, &$sheetWritten, &$processed,
                    $completeCodes, &$writtenCodes, $plog, $title
                ) {
                    foreach ($chunkRows as $row) {
                        $rawCode = $this->headers->val($row, $fieldMap, 'model_code');
                        if ($rawCode === null || $rawCode === '') {
                            $processed++;
                            continue;
                        }
                        $modelCode = strtoupper(preg_replace('/\s+/', '', (string) $rawCode) ?? (string) $rawCode);

                        if (! isset($completeCodes[$modelCode])) {
                            $stats['skipped_incomplete']++;
                            $stats['skipped']++;
                            $processed++;
                            continue;
                        }

                        try {
                            $payload = [
                                'import_session_id' => $session->id,
                                'model_code'        => $modelCode,
                                'channel'           => $rowChannel,
                                'wef_date'          => $wefDate,
                                'is_active'         => true,
                                'updated_by'        => $userId,
                            ];

                            foreach ($this->fieldToColumn as $fieldCode => $column) {
                                if (! isset($fieldMap[$fieldCode])) {
                                    continue;
                                }
                                $val = $this->toDecimal($this->headers->val($row, $fieldMap, $fieldCode));
                                if ($val !== null) {
                                    if ($fieldCode === 'dealer_handling' && isset($payload['dealer_margin'])) {
                                        continue;
                                    }
                                    $payload[$column] = $val;
                                }
                            }

                            if (! isset($payload['ex_showroom_price'])) {
                                $derived = $this->deriveExShowroom($payload);
                                if ($derived !== null) {
                                    $payload['ex_showroom_price'] = $derived;
                                }
                            }

                            if (! isset($payload['ex_showroom_price'])) {
                                $stats['skipped_no_price']++;
                                $stats['skipped']++;
                                $processed++;
                                continue;
                            }

                            $previous = $existingByCode->get($modelCode);
                            $sameWef = $existingSameWef->get($modelCode);
                            $changed = $this->hasMaterialChange($previous, $payload);

                            if ($sameWef) {
                                $sameWef->fill($payload);
                                $sameWef->save();
                            } else {
                                if ($previous && $changed) {
                                    $previous->expired_on = $wefDate;
                                    $previous->is_active = false;
                                    $previous->updated_by = $userId;
                                    $previous->save();
                                }
                                $payload['created_by'] = $userId;
                                Pricing::query()->create($payload);
                            }

                            if ($changed) {
                                ChangeFlag::query()->create([
                                    'import_session_id' => $session->id,
                                    'change_type'       => 'pricing',
                                    'model_code'        => $modelCode,
                                    'field_name'        => 'ex_showroom_price',
                                    'old_value'         => $previous?->ex_showroom_price,
                                    'new_value'         => $payload['ex_showroom_price'],
                                    'is_processed'      => false,
                                    'created_by'        => $userId,
                                    'updated_by'        => $userId,
                                ]);
                                $stats['changed']++;
                            }

                            $stats['written']++;
                            $sheetWritten++;
                            $writtenCodes[] = $modelCode;
                        } catch (\Throwable $e) {
                            $stats['errors'][] = $modelCode . ': ' . $e->getMessage();
                        }

                        $processed++;
                    }
                });

                if ($writtenCodes !== []) {
                    Profile::query()
                        ->whereIn('model_code', array_unique($writtenCodes))
                        ->update([
                            'is_pricing_template_complete' => true,
                            'updated_by'                   => $userId,
                            'updated_at'                   => now(),
                        ]);
                }

                $percent = $totalRows > 0
                    ? min(95, 8 + (int) round(($processed / max($totalRows, 1)) * 87))
                    : 50;
                $lastCode = end($writtenCodes) ?: null;

                if ($onProgress) {
                    $onProgress([
                        'message'     => "{$title}: chunk {$from}-{$to}",
                        'percent'     => $percent,
                        'processed'   => $processed,
                        'total'       => $totalRows,
                        'sheet'       => $title,
                        'last_code'   => $lastCode,
                        'price_stats' => [
                            'written'            => $stats['written'],
                            'changed'            => $stats['changed'],
                            'skipped'            => $stats['skipped'],
                            'skipped_incomplete' => $stats['skipped_incomplete'],
                            'skipped_no_price'   => $stats['skipped_no_price'],
                        ],
                        'logs' => [
                            '[' . now()->format('H:i:s') . "] {$title} {$from}-{$to}"
                            . " written={$stats['written']} skip-inc={$stats['skipped_incomplete']}"
                            . " skip-price={$stats['skipped_no_price']}"
                            . ($lastCode ? " last={$lastCode}" : ''),
                        ],
                    ]);
                }

                $plog->info('Chunk done', [
                    'sheet' => $title, 'from' => $from, 'to' => $to,
                    'processed' => $processed, 'written' => $stats['written'],
                ]);

                unset($chunkRows, $existingByCode, $existingSameWef, $writtenCodes);
            }

            $plog->info('Price sheet done', ['sheet' => $title, 'written' => $sheetWritten]);
            $this->release($spreadsheet);
        }

        Log::info('[PriceListPricingImporter] done', ['session_id' => $session->id, 'stats' => $stats]);
        $plog->info('Price import done', $stats);

        if ($onProgress) {
            $onProgress([
                'message'     => "Done — written {$stats['written']}, skipped {$stats['skipped']}",
                'percent'     => 100,
                'processed'   => $processed,
                'total'       => $totalRows,
                'done'        => true,
                'price_stats' => $stats,
                'logs'        => [
                    '[' . now()->format('H:i:s') . '] Price import finished',
                    "Written: {$stats['written']} | Changed: {$stats['changed']}"
                    . " | Skip incomplete: {$stats['skipped_incomplete']}"
                    . " | Skip no-price: {$stats['skipped_no_price']}",
                ],
            ]);
        }

        return $stats;
    }

    protected function loadOneSheet(IReader $reader, string $path, string $title)
    {
        $reader->setLoadSheetsOnly([$title]);

        return $reader->load($path);
    }

    protected function release($spreadsheet): void
    {
        try {
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable) {
        }
        unset($spreadsheet);
        gc_collect_cycles();
    }

    protected function capColumn(string $col): string
    {
        $col = strtoupper($col);
        if (strlen($col) > 2 || strcmp($col, self::MAX_COL) > 0) {
            return self::MAX_COL;
        }

        return $col;
    }

    protected function deriveExShowroom(array $payload): ?float
    {
        if (isset($payload['mm_invoice_amount']) && (float) $payload['mm_invoice_amount'] > 0) {
            return round((float) $payload['mm_invoice_amount'], 2);
        }

        $asse = isset($payload['assessable_value_with_freight'])
            ? (float) $payload['assessable_value_with_freight'] : null;
        $gst = isset($payload['gst_amount'])
            ? (float) $payload['gst_amount'] : null;

        if ($asse !== null && $gst !== null && ($asse + $gst) > 0) {
            return round($asse + $gst, 2);
        }
        if ($asse !== null && $asse > 0) {
            return round($asse, 2);
        }

        return null;
    }

    protected function mapMCodeFallback(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $raw) {
            $label = mb_strtolower(trim((string) $raw));
            $label = str_replace(['.', '_'], ' ', $label);
            $label = preg_replace('/\s+/', ' ', $label) ?? $label;
            if (in_array($label, ['m code', 'mcode', 'material code', 'model code', 'vehicle code'], true)) {
                $map['model_code'] = (int) $idx;
            }
            if (in_array($label, ['ex showroom', 'ex-showroom', 'ex showroom pre subsidy', 'ex-showroom pre subsidy'], true)) {
                $map['ex_showroom'] = (int) $idx;
            }
            if (str_contains($label, 'assessable')) {
                $map['asse_value_freight'] = (int) $idx;
            }
            if (str_contains($label, 'gst on assessable')) {
                $map['gst_amount'] = (int) $idx;
            }
            if (str_contains($label, 'mm inv') || str_contains($label, 'mm invoice')) {
                $map['mm_inv_amt'] = (int) $idx;
            }
        }

        return $map;
    }

    protected function hasMaterialChange(?Pricing $previous, array $payload): bool
    {
        if (! $previous) {
            return true;
        }
        foreach ([
            'ex_showroom_price', 'assessable_value_with_freight',
            'curr_oem_scheme', 'curr_dealer_cont', 'curr_cash_discount',
            'old_oem_scheme', 'old_dealer_cont', 'old_cash_discount',
        ] as $k) {
            if (! array_key_exists($k, $payload)) {
                continue;
            }
            if (abs((float) $previous->{$k} - (float) $payload[$k]) > 0.009) {
                return true;
            }
        }

        return false;
    }

    protected function toDecimal(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return round((float) $v, 2);
        }
        $s = preg_replace('/[^\d.\-]/', '', (string) $v);
        if ($s === '' || $s === null || ! is_numeric($s)) {
            return null;
        }

        return round((float) $s, 2);
    }
}
