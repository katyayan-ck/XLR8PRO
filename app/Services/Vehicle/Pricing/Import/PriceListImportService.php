<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Import;

use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\PricingHistory;
use App\Models\Vehicle\Variant;
use App\Services\Vehicle\Pricing\Prices\PriceService;
use App\Services\Vehicle\Pricing\SheetHeaderService;
use App\Services\Vehicle\VehicleCompleteness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Step 4 — price import (DEC-073 / DEC-076). Reads the chosen price lists and writes ex-showroom, assessable, GST, MM
 * invoice, dealer margin, the NV / OV scheme blocks and discount eligibility per vehicle and channel through
 * PriceService, plus one history row per code. No on-road here (step 9).
 *
 *  - columns: the sheet_headers registry per list (DEC-076 choices); PV's OV block repeats the NV labels, so the second
 *    occurrence is OV; only PV / CV / BEV have an OV block (others store OV = 0).
 *  - WEF (DEC-058): same WEF → update the row; a newer WEF with a material change → expire the live row and insert;
 *    no material change → keep the live row ("unchanged"); a live row with a later WEF → the row is rejected.
 *  - skipped: incomplete vehicles, codes not in the master (CSD: never created), rows without ex-showroom;
 *    conflicting duplicate codes in a sheet are rejected (identical duplicates count once).
 * Run it inside PricingSessionService::record() so Discard restores the previous prices exactly.
 */
class PriceListImportService
{
    public const OV_LISTS = ['PRICE_LIST_PV', 'PRICE_LIST_CV', 'PRICE_LIST_BEV'];

    /** sheet field code => pricing column (amounts) */
    public const AMOUNT_FIELDS = [
        'asse_value_freight' => 'assessable_value_with_freight', 'gst_amount' => 'gst_amount', 'mm_inv_amt' => 'mm_invoice_amount',
        'ex_showroom' => 'ex_showroom_price',
        'curr_oem_scheme' => 'curr_oem_scheme', 'curr_dealer_cont' => 'curr_dealer_cont', 'curr_cash_discount' => 'curr_cash_discount',
        'curr_acc_discount' => 'curr_acc_discount', 'curr_shield_discount' => 'curr_shield_discount',
        'old_oem_scheme' => 'old_oem_scheme', 'old_dealer_cont' => 'old_dealer_cont', 'old_cash_discount' => 'old_cash_discount',
        'old_acc_discount' => 'old_acc_discount', 'old_shield_discount' => 'old_shield_discount',
    ];

    /** NV field => OV field, for the PV-style repeated block */
    private const NV_TO_OV = [
        'curr_oem_scheme' => 'old_oem_scheme', 'curr_dealer_cont' => 'old_dealer_cont', 'curr_total_scheme' => 'old_total_scheme',
        'curr_cash_discount' => 'old_cash_discount', 'curr_acc_discount' => 'old_acc_discount',
        'curr_shield_discount' => 'old_shield_discount', 'curr_acc_elg' => 'old_acc_elg', 'curr_shield_elg' => 'old_shield_elg',
    ];

    public const MAX_ISSUES = 2000;

    public function __construct(
        private readonly PricingWorkbookReader $reader,
        private readonly SheetHeaderService $headers,
        private readonly PriceService $prices,
        private readonly VehicleCompleteness $completeness,
    ) {}

    /**
     * @param  list<string>  $sheets  sheet titles ("Price List PV" …)
     * @param  (callable(array<string, mixed>): void)|null  $onProgress
     * @return array{sheets: array<string, array<string, int>>, totals: array<string, int>, issues: list<array{sheet: string, code: string, reason: string}>}
     */
    public function import(string $path, array $sheets, string $wefDate, ?callable $onProgress = null): array
    {
        $result = ['sheets' => [], 'totals' => [], 'issues' => []];
        foreach ($sheets as $sheet) {
            $code = PriceListDetectService::sheetCode($sheet);
            if ($code === null) {
                $this->issue($result, $sheet, '', 'Not a price list sheet.');

                continue;
            }
            $result['sheets'][$sheet] = $this->importSheet($path, $sheet, $code, $wefDate, $result, $onProgress);
        }
        foreach ($result['sheets'] as $stats) {
            foreach ($stats as $key => $n) {
                $result['totals'][$key] = ($result['totals'][$key] ?? 0) + $n;
            }
        }
        Log::info('[Pricing] price import', ['wef' => $wefDate, 'totals' => $result['totals']]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, int>
     */
    private function importSheet(string $path, string $sheet, string $sheetCode, string $wefDate, array &$result, ?callable $onProgress): array
    {
        $stats = ['rows' => 0, 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped_incomplete' => 0, 'skipped_unknown' => 0, 'no_price' => 0, 'duplicates' => 0, 'conflicts' => 0, 'rejected' => 0];
        $header = $this->reader->header($path, $sheet, $sheetCode);
        $map = $header['map'];
        if ($header['row'] === null || ! isset($map['model_code'], $map['ex_showroom'])) {
            $this->issue($result, $sheet, '', 'No "Model Code" / ex-showroom column found.');

            return $stats;
        }
        if (in_array($sheetCode, self::OV_LISTS, true)) {
            $map = $this->repeatedBlockAsOv($header['cells'], $map);
        }
        $channel = $sheetCode === 'PRICE_LIST_CSD' ? 'csd' : 'normal';

        // read the whole sheet once (≤ a few thousand rows) so duplicate codes can be judged before writing
        $rows = [];
        $conflicted = [];
        foreach ($this->reader->rows($path, $sheet, $header['row'] + 1) as $cells) {
            $code = PricingWorkbookReader::code($cells[$map['model_code']] ?? '');
            if ($code === '') {
                continue;
            }
            $payload = $this->payload($cells, $map);
            if (isset($rows[$code])) {
                if ($rows[$code] == $payload) {
                    $stats['duplicates']++;
                } else {
                    $conflicted[$code] = true;
                }

                continue;
            }
            $rows[$code] = $payload;
        }
        foreach (array_keys($conflicted) as $code) {
            unset($rows[$code]);
            $stats['conflicts']++;
            $this->issue($result, $sheet, $code, 'The code appears more than once with different amounts — fix the sheet; no price written.');
        }
        $stats['rows'] = count($rows) + count($conflicted);

        foreach (array_chunk($rows, PricingWorkbookReader::CHUNK, true) as $chunk) {
            // a plain closure: the counters are passed by reference (an arrow fn would count on copies)
            DB::transaction(function () use ($chunk, $sheet, $channel, $wefDate, &$stats, &$result) {
                $this->writeChunk($chunk, $sheet, $channel, $wefDate, $stats, $result);
            });
            $onProgress && $onProgress(['sheet' => $sheet, 'done' => $stats['inserted'] + $stats['updated'] + $stats['unchanged'], 'rows' => $stats['rows']]);
        }

        return $stats;
    }

    /**
     * @param  array<string, array<string, float|null>>  $chunk
     * @param  array<string, int>  $stats
     * @param  array<string, mixed>  $result
     */
    private function writeChunk(array $chunk, string $sheet, string $channel, string $wefDate, array &$stats, array &$result): void
    {
        $codes = array_keys($chunk);
        $variants = Variant::query()->with('vehicleModel')->whereIn('code', $codes)->get()->keyBy(fn (Variant $v) => strtoupper($v->code));
        $live = Pricing::query()->whereIn('model_code', $codes)->where('channel', $channel)->where('is_active', true)
            ->orderByDesc('wef_date')->get()->groupBy(fn (Pricing $p) => strtoupper($p->model_code))->map->first();
        $sameWef = Pricing::query()->whereIn('model_code', $codes)->where('channel', $channel)->whereDate('wef_date', $wefDate)
            ->get()->keyBy(fn (Pricing $p) => strtoupper($p->model_code));

        foreach ($chunk as $code => $payload) {
            $variant = $variants->get($code);
            if (! $variant) {
                $stats['skipped_unknown']++;
                $this->issue($result, $sheet, $code, $channel === 'csd' ? 'CSD code not in the vehicle master — CSD never creates vehicles.' : 'Not in the vehicle master — run Detect for this list.');

                continue;
            }
            if (! $this->completeness->isComplete($variant)) {
                $stats['skipped_incomplete']++;
                $this->issue($result, $sheet, $code, 'Incomplete vehicle — skipped (Missing: '.implode(', ', $this->completeness->missingLabels($variant)).').');

                continue;
            }
            if (($payload['ex_showroom_price'] ?? 0) <= 0) {
                $stats['no_price']++;
                $this->issue($result, $sheet, $code, 'No ex-showroom price in the sheet.');

                continue;
            }
            $values = array_filter($payload, fn ($v) => $v !== null);

            try {
                $row = $sameWef->get($code);
                $previous = $live->get($code);
                if ($row) {
                    $row = $this->prices->update($row, $values);
                    $action = PricingHistory::ACTION_UPDATE;
                    $stats['updated']++;
                } elseif ($previous && $previous->wef_date?->toDateString() > $wefDate) {
                    $stats['rejected']++;
                    $this->issue($result, $sheet, $code, "A newer price (WEF {$previous->wef_date->toDateString()}) is live — use that WEF or later.");

                    continue;
                } elseif ($previous && ! $this->changed($previous, $values)) {
                    $row = $previous;
                    $action = PricingHistory::ACTION_UNCHANGED;
                    $stats['unchanged']++;
                } else {
                    if ($previous) {
                        $this->prices->expire($previous, $wefDate);
                    }
                    $row = $this->prices->create($values + ['model_code' => $code, 'channel' => $channel, 'wef_date' => $wefDate, 'is_active' => true]);
                    $action = PricingHistory::ACTION_INSERT;
                    $stats['inserted']++;
                }
                PricingHistory::query()->create(['pricing_id' => $row->id, 'model_code' => $code, 'channel' => $channel, 'wef_date' => $wefDate, 'payload' => $payload, 'action' => $action]);
            } catch (ValidationException $e) {
                $stats['rejected']++;
                $this->issue($result, $sheet, $code, implode(' ', array_merge(...array_values($e->errors()))));
            }
        }
    }

    /**
     * Sheet cells → pricing columns (null = the sheet left it blank).
     *
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $map
     * @return array<string, float|null>
     */
    private function payload(array $cells, array $map): array
    {
        $num = fn (string $field) => isset($map[$field]) ? PricingWorkbookReader::number($cells[$map[$field]] ?? null) : null;
        $sum = fn (?float ...$parts) => array_filter($parts, fn ($p) => $p !== null) === [] ? null : round(array_sum($parts), 2);

        $out = [];
        foreach (self::AMOUNT_FIELDS as $field => $column) {
            $out[$column] = $num($field);
        }
        // LMM: assessable + separate freight; dealer margin + handling (DEC-076)
        $out['assessable_value_with_freight'] = $sum($out['assessable_value_with_freight'], $num('freight'));
        $out['dealer_margin'] = $sum($num('dealer_margin'), $num('dealer_handling'));

        $gst = isset($map['gst_pct']) ? PricingWorkbookReader::percent($cells[$map['gst_pct']] ?? null) : null;
        if ($gst === null && ($out['assessable_value_with_freight'] ?? 0) > 0 && $out['gst_amount'] !== null) {
            $gst = round($out['gst_amount'] / $out['assessable_value_with_freight'] * 100, 2);
        }
        $out['gst_percent'] = $gst;
        foreach (PriceService::ELIGIBILITY as $column) {
            $out[$column] = $num($column);
        }

        return $out;
    }

    /**
     * PV-style sheets repeat the NV labels for the OV block: map each NV field's label, seen again further right, to the
     * OV field (only where the registry did not already find a prefixed OV column).
     *
     * @param  list<mixed>  $headerCells
     * @param  array<string, int>  $map
     * @return array<string, int>
     */
    private function repeatedBlockAsOv(array $headerCells, array $map): array
    {
        foreach (self::NV_TO_OV as $nv => $ov) {
            if (! isset($map[$nv]) || isset($map[$ov])) {
                continue;
            }
            $label = $this->headers->normalizeLabel($headerCells[$map[$nv]] ?? '');
            foreach ($headerCells as $col => $cell) {
                if ($col > $map[$nv] && $this->headers->normalizeLabel($cell) === $label) {
                    $map[$ov] = (int) $col;
                    break;
                }
            }
        }

        return $map;
    }

    /** @param array<string, float> $values */
    private function changed(Pricing $previous, array $values): bool
    {
        foreach ($values as $column => $value) {
            if (abs((float) $previous->{$column} - (float) $value) > 0.009) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $result */
    private function issue(array &$result, string $sheet, string $code, string $reason): void
    {
        if (count($result['issues']) < self::MAX_ISSUES) {
            $result['issues'][] = ['sheet' => $sheet, 'code' => $code, 'reason' => $reason];
        }
    }
}
