<?php
// FILE PATH: app/Jobs/ImportEnquiriesJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;



class ImportEnquiriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Give the worker real headroom. This has NOTHING to do with the web
     * server / php-fpm timeout anymore — this runs on the CLI queue worker,
     * so nginx/apache's ~60s gateway timeout can never touch it again.
     */
    public int $timeout = 1800; // 30 min ceiling, tune as needed
    public int $tries = 1;      // don't silently re-run a huge import on failure

    private int $importLogId;
    private string $storedPath;

    /** Rows per DB transaction chunk — batches commits instead of autocommit-per-row */
    private const CHUNK_SIZE = 300;

    /**
     * The list of keyword_code groups (from xlr8_utils_keyvalue) that Quick /
     * Long sheet imports normalize free-text input against. Only codes with
     * an actual source column on those two sheets are included here —
     * SC_FUP_REMARKS_TYPE, SC_FUP_REMARKS, CALL_NATURE_VIRTUAL, ACTIVITY_TYPE,
     * APPLICATION, APPLICATION_TYPE, KM_TRAVELLED_DAILY, USAGE_AREA,
     * AGE_GROUP, OCCUPATION_SUB_TYPE, OCCUPATION_TYPE are intentionally left
     * out — no source column currently feeds them. (Note: FOLLOW_UP_REMARKS_TYPE
     * is a distinct, newer keyword_code — added alongside the followup_remarks_type
     * / first_followup_type / first_followup_remarks_type columns — and is NOT
     * the same as the still-excluded SC_FUP_REMARKS_TYPE.)
     */
    private const KEYVALUE_CODES = [
        'ENQUIRY_TYPE',
        'ENQUIRY_SUB_SOURCE',
        'LIKELY_PURCHASE_DATE',
        'FOLLOW_UP_TYPE',
        'FOLLOW_UP_REMARKS_TYPE',
    ];

    /**
     * Purchase Type is a fixed, closed list (Long sheet only) — NOT resolved
     * against xlr8_utils_keyvalue like the KEYVALUE_CODES above. Matching is
     * case-insensitive / whitespace-collapsed against the keys below; the
     * mapped value on the right is what's always saved verbatim. Anything
     * that doesn't match one of these four stays NULL — it is intentionally
     * never auto-created.
     */
    private const PURCHASE_TYPE_MAP = [
        'first time buy' => 'First Time Buy',
        'exchange buy'   => 'Exchange Buy',
        'additional buy' => 'Additional Buy',
        'scrappage'      => 'Scrappage',
    ];

    /**
     * In-memory cache of xlr8_utils_keyvalue, keyed as:
     *   [keyword_code][normalized_lowercase_value] => code
     * Loaded once per job run so ~85k rows don't each hit the DB for a
     * lookup that's the same handful of distinct values repeated over and
     * over. New values discovered during the run are added here as well as
     * inserted to the DB, so a value repeated 500 times in one file only
     * ever triggers a single INSERT.
     */
    private array $keyvalueCache = [];
    private bool $keyvalueCacheLoaded = false;

    /**
     * In-memory cache of xlr8_vehicle_model, keyed as:
     *   [normalized_lowercase_name] => ['code' => ..., 'segment_code' => ...]
     * Vehicle models are NOT auto-created on miss (unlike keyvalue) — a
     * miss just means model_code/segment_code stay null.
     */
    private array $vehicleModelCache = [];
    private bool $vehicleModelCacheLoaded = false;

    /**
     * In-memory cache of xlr8_vehicle_variant's distinct colors, keyed as:
     *   [normalized_lowercase_color_name] => ['color' => ..., 'color_code' => ...]
     * Built from the variant table's own `color` / `color_code` columns (not
     * the separate xlr8_vehicle_color table). Like the model cache, a miss
     * does NOT auto-create anything — the raw sheet text is kept as-is and
     * color_code just stays null.
     */
    private array $vehicleColorCache = [];
    private bool $vehicleColorCacheLoaded = false;

    public function __construct(int $importLogId, string $storedPath)
    {
        $this->importLogId = $importLogId;
        $this->storedPath  = $storedPath;
    }

    public function handle(): void
    {
        $log = DB::table('xlr8_crm_import_logs')->where('id', $this->importLogId);
        $log->update(['status' => 'processing', 'updated_at' => now()]);

        $sheetHandlers = [
            'Virtual'   => 'importVirtualSheet',
            'Quick'     => 'importQuickSheet',
            'Long'      => 'importLongSheet',
            'Reference' => 'importReferenceSheet',
            'Whatsapp'  => 'importWhatsappSheet',
        ];

        $now = now();
        $overallStats = [];

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($this->storedPath);

            Log::info('=== CRM Enquiry Import (Job) Started ===', [
                'import_log_id' => $this->importLogId,
                'sheets'        => array_keys($sheetHandlers),
            ]);

            // total rows across all present sheets, for progress %
            $totalRows = 0;
            foreach ($sheetHandlers as $sheetName => $handler) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                if ($sheet) {
                    $totalRows += max(0, $sheet->getHighestRow() - 1);
                }
            }
            $log->update(['total_rows' => $totalRows]);
            $this->processedSoFar = 0;

            // Load lookup caches once, up front — every sheet handler that
            // normalizes a value against xlr8_utils_keyvalue or resolves a
            // model against xlr8_vehicle_model reads from these instead of
            // querying per row.
            $this->loadKeyvalueCache();
            $this->loadVehicleModelCache();
            $this->loadVehicleColorCache();

            foreach ($sheetHandlers as $sheetName => $handlerMethod) {
                $sheet = $spreadsheet->getSheetByName($sheetName);

                if (!$sheet) {
                    Log::warning("Sheet [{$sheetName}] not found in uploaded file — skipped entirely.");
                    $overallStats[$sheetName] = [
                        'inserted' => 0,
                        'updated' => 0,
                        'skipped' => 0,
                        'note' => 'Sheet not found in file',
                    ];
                    continue;
                }

                Log::info("--- Processing sheet [{$sheetName}] ---");
                $overallStats[$sheetName] = $this->{$handlerMethod}($sheet, $now, $log);
                Log::info("--- Finished sheet [{$sheetName}] ---", $overallStats[$sheetName]);

                // persist stats incrementally so a later crash doesn't lose everything
                $log->update(['stats' => json_encode($overallStats), 'updated_at' => now()]);
            }

            Log::info('=== CRM Enquiry Import (Job) Completed ===', $overallStats);

            $log->update([
                'status'     => 'completed',
                'stats'      => json_encode($overallStats),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // \Throwable (NOT just \Exception) so we also catch fatal \Error
            // cases like memory exhaustion / type errors — this is exactly
            // what was silently killing the old synchronous import with no
            // log entry at all.
            Log::error('CRM Enquiry Import (Job) — fatal error', [
                'import_log_id' => $this->importLogId,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'stats'         => json_encode($overallStats),
                'updated_at'    => now(),
            ]);
        } finally {
            // clean up the temp upload regardless of outcome
            if (file_exists($this->storedPath)) {
                @unlink($this->storedPath);
            }
        }
    }

    /** running counter used across sheets for progress reporting */
    private int $processedSoFar = 0;

    private function bumpProgress($log, int $byRows): void
    {
        $this->processedSoFar += $byRows;
        $log->update(['processed_rows' => $this->processedSoFar, 'updated_at' => now()]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SHEET HANDLERS  (identical matching/column logic to the original controller,
    // just chunked into transactions + progress-tracked)
    // ══════════════════════════════════════════════════════════════════════════

    private function importVirtualSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'merged_duplicates_in_file' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        // Virtual No. is gone — Customer Number is the matching key now, but
        // ONLY for the duration of this import (no DB-level uniqueness on
        // mobile). If the same Customer Number appears more than once in
        // this file, keep just the last occurrence — its values are what
        // gets written to the existing/new record.
        $byMobile = [];
        $noMobileEntries = [];
        foreach ($rows as $i => $row) {
            $mobile = $this->cell($row, $headerMap, 'Customer Number');
            $entry = ['row' => $row, 'excelRow' => $i + 2];

            if (empty($mobile)) {
                $noMobileEntries[] = $entry;
                continue;
            }

            if (isset($byMobile[$mobile])) {
                $stats['merged_duplicates_in_file']++;
            }
            // later row with the same mobile overwrites the earlier one —
            // last-in-file wins
            $byMobile[$mobile] = $entry;
        }

        $deduped = array_merge(array_values($byMobile), $noMobileEntries);
        $duplicatesRemovedFromCount = count($rows) - count($deduped);

        foreach (array_chunk($deduped, self::CHUNK_SIZE) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $entry) {
                    $row = $entry['row'];
                    $excelRow = $entry['excelRow'];
                    try {
                        $mobile = $this->cell($row, $headerMap, 'Customer Number');

                        $data = $this->stripNulls([
                            'virtual_no'        => $this->cell($row, $headerMap, 'Virtual No'),
                            'call_status'       => $this->cell($row, $headerMap, 'Call Status'),
                            'call_duration'     => $this->formatCallDuration($this->cell($row, $headerMap, 'Call Duration')),
                            'mobile'            => $mobile,
                            'virtual_call_date' => $this->excelDate($this->cell($row, $headerMap, 'Starting Date'), true),
                        ]);
                        $data['updated_at'] = $now;

                        if (empty($mobile)) {
                            // mobile is NOT NULL in the DB with no default —
                            // there's no reliable key to tie this row to
                            // anything, so skip it instead of letting the
                            // insert fail.
                            Log::warning("[Virtual] Row {$excelRow} SKIPPED — Customer Number missing", ['row' => $row]);
                            $stats['skipped']++;
                            continue;
                        }

                        $existed = $this->upsertRow(
                            'xlr8_crm_enquiries',
                            ['mobile' => $mobile],
                            $data,
                            ['created_at' => $now, 'origin' => 'VIRTUAL', 'current_origin' => 'VIRTUAL']
                        );

                        $existed ? $stats['updated']++ : $stats['inserted']++;
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Virtual] Row {$excelRow} FAILED — " . $e->getMessage(), ['row' => $row]);
                    }
                }
            });
            $this->bumpProgress($log, count($chunk));
        }

        // account for the rows that got merged away during in-file dedup so
        // the overall progress % (computed from the pre-dedup total_rows)
        // still lands on 100% once this sheet finishes
        if ($duplicatesRemovedFromCount > 0) {
            $this->bumpProgress($log, $duplicatesRemovedFromCount);
        }

        return $stats;
    }

    private function importQuickSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $i => $row) {
                    $excelRow = $i + 2;
                    try {
                        // 'Enquiry Number' on this sheet is stored/matched via
                        // the sheet-specific `quick_enquiry_no` column — NOT the
                        // shared `enquiry_no` column, which is reserved for
                        // manually-created enquiries via the CRUD form.
                        $enquiryNo = $this->cell($row, $headerMap, 'Enquiry Number');

                        [$firstName, $lastName] = $this->splitCustomerName($this->cell($row, $headerMap, 'Customer Name'));
                        $scMileId = $this->cell($row, $headerMap, 'SC Mile Id');
                        $mobile   = $this->cell($row, $headerMap, 'Mobile Phone');

                        if (empty($mobile)) {
                            // mobile is NOT NULL in the DB with no default —
                            // skip rather than let the insert/update fail.
                            Log::warning("[Quick] Row {$excelRow} SKIPPED — Mobile Phone missing", ['row' => $row]);
                            $stats['skipped']++;
                            continue;
                        }

                        $modelName = $this->cell($row, $headerMap, 'Product Family');
                        $modelMatch = $this->resolveVehicleModel($modelName);
                        $colorMatch = $this->resolveVehicleColor($this->cell($row, $headerMap, 'Color'));

                        $data = $this->stripNulls([
                            'first_name'                        => $firstName,
                            'last_name'                          => $lastName,
                            'mobile'                              => $mobile,
                            'sc_mile_id'                          => $scMileId,
                            'model'                               => $modelName,
                            'model_code'                          => $modelMatch['model_code'],
                            'segment_code'                        => $modelMatch['segment_code'],
                            'variant'                             => $this->cell($row, $headerMap, 'Variant Description'),
                            'color'                               => $colorMatch['color'],
                            'color_code'                          => $colorMatch['color_code'],
                            'fuel_type'                           => $this->cell($row, $headerMap, 'Fuel Type'),
                            'seating'                             => $this->cell($row, $headerMap, 'Seating Capacity'),
                            'enquiry_type'                        => $this->resolveKeyValue('ENQUIRY_TYPE', $this->cell($row, $headerMap, 'Enquiry Type')),
                            'source_code'                         => $this->cell($row, $headerMap, 'Enquiry Source'),
                            'sub_source'                          => $this->resolveKeyValue('ENQUIRY_SUB_SOURCE', $this->cell($row, $headerMap, 'Enquiry Sub Source')),
                            'likely_purchase_date'                => $this->resolveKeyValue('LIKELY_PURCHASE_DATE', $this->cell($row, $headerMap, 'Likely Purchase In Days')),
                            'followup_type'                       => $this->resolveKeyValue('FOLLOW_UP_TYPE', $this->cell($row, $headerMap, 'Followup Type')),
                            'followup_remarks_type'               => $this->resolveKeyValue('FOLLOW_UP_REMARKS_TYPE', $this->cell($row, $headerMap, 'Follow-up Remarks Type')),
                            'quick_status'                        => $this->cell($row, $headerMap, 'Status'),
                            'quick_enquiry_date'                  => $this->excelDate($this->cell($row, $headerMap, 'Quick Enquiry Date')),
                            'test_drive_no'                       => $this->cell($row, $headerMap, 'Test Drive Number'),
                            'completed_followup_count'            => $this->cell($row, $headerMap, 'Completed Followup Count'),
                            'first_planned_followup_date'         => $this->excelDate($this->cell($row, $headerMap, 'First Planned Followup')),
                            'first_actual_followup_date'          => $this->excelDate($this->cell($row, $headerMap, 'First Actual Followup')),
                            'recent_planned_followup_date'        => $this->excelDate($this->cell($row, $headerMap, 'Recent Planned Followup')),
                            'recent_actual_followup_date'         => $this->excelDate($this->cell($row, $headerMap, 'Recent Actual Followup')),
                        ]);
                        $data['updated_at'] = $now;

                        if (empty($enquiryNo)) {
                            // No enquiry number to match against — matching on
                            // quick_enquiry_no IS NULL would lump every blank-number
                            // row onto the same existing record, so always insert
                            // fresh instead of upserting.
                            $data['quick_enquiry_no'] = null;
                            DB::table('xlr8_crm_enquiries')->insert(array_merge(
                                $data,
                                ['created_at' => $now, 'origin' => 'QUICK', 'current_origin' => 'QUICK']
                            ));
                            $stats['inserted']++;
                        } else {
                            $existed = $this->upsertRowWithAssignment(
                                'xlr8_crm_enquiries',
                                ['quick_enquiry_no' => $enquiryNo],
                                $data,
                                ['created_at' => $now, 'origin' => 'QUICK', 'current_origin' => 'QUICK'],
                                $scMileId,
                                'quick_enq_assign_date',
                                $now
                            );

                            $existed ? $stats['updated']++ : $stats['inserted']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Quick] Row {$excelRow} FAILED — " . $e->getMessage(), ['row' => $row]);
                    }
                }
            });
            $this->bumpProgress($log, count($chunk));
        }

        return $stats;
    }

    private function importLongSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $i => $row) {
                    $excelRow = $i + 2;
                    try {
                        // Blank Enquiry Number no longer skips the row — it's
                        // imported with enquiry_no left null. Matched/stored
                        // via the shared `enquiry_no` column (Long sheet uses
                        // this shared column, unlike Quick which has its own
                        // sheet-specific quick_enquiry_no column).
                        $enquiryNo = $this->cell($row, $headerMap, 'Enquiry Number');

                        [$firstName, $lastName] = $this->splitCustomerName($this->cell($row, $headerMap, 'Customer Name'));
                        $scMileId = $this->cell($row, $headerMap, 'SC Mile Id');
                        $mobile   = $this->cell($row, $headerMap, 'Customer Phone');

                        if (empty($mobile)) {
                            // mobile is NOT NULL in the DB with no default —
                            // skip rather than let the insert/update fail.
                            Log::warning("[Long] Row {$excelRow} SKIPPED — Customer Phone missing", ['row' => $row]);
                            $stats['skipped']++;
                            continue;
                        }

                        $modelName = $this->cell($row, $headerMap, 'Product Family');
                        $modelMatch = $this->resolveVehicleModel($modelName);
                        $colorMatch = $this->resolveVehicleColor($this->cell($row, $headerMap, 'Color'));

                        $data = $this->stripNulls([
                            'first_name'                => $firstName,
                            'last_name'                  => $lastName,
                            'mobile'                     => $mobile,
                            'sc_mile_id'                 => $scMileId,
                            'model'                      => $modelName,
                            'model_code'                 => $modelMatch['model_code'],
                            'segment_code'               => $modelMatch['segment_code'],
                            'variant'                    => $this->cell($row, $headerMap, 'Variant Description'),
                            'color'                      => $colorMatch['color'],
                            'color_code'                 => $colorMatch['color_code'],
                            'purchase_type'              => $this->resolvePurchaseType($this->cell($row, $headerMap, 'Purchase Type')),
                            'enquiry_type'               => $this->resolveKeyValue('ENQUIRY_TYPE', $this->cell($row, $headerMap, 'Enquiry Type')),
                            'source_code'                => $this->cell($row, $headerMap, 'Enquiry Source'),
                            'sub_source'                 => $this->resolveKeyValue('ENQUIRY_SUB_SOURCE', $this->cell($row, $headerMap, 'Enquiry Sub Source')),
                            'stage'                      => $this->cell($row, $headerMap, 'Stage'),
                            'enquiry_date'               => $this->excelDate($this->cell($row, $headerMap, 'Enquiry Date')),
                            'customer_address'           => $this->cell($row, $headerMap, 'Customer Address'),
                            'tehsil'                     => $this->cell($row, $headerMap, 'Tehsil'),
                            'district'                   => $this->cell($row, $headerMap, 'District'),
                            'zipcode'                    => $this->cell($row, $headerMap, 'Postal Code'),
                            'fuel_type'                  => $this->cell($row, $headerMap, 'Fuel Type'),
                            'seating'                    => $this->cell($row, $headerMap, 'Seating Capacity'),
                            'likely_purchase_date'       => $this->resolveKeyValue('LIKELY_PURCHASE_DATE', $this->cell($row, $headerMap, 'Likely Purchase In Days')),
                            'first_followup_type'        => $this->resolveKeyValue('FOLLOW_UP_TYPE', $this->cell($row, $headerMap, 'First Followup Type')),
                            'first_followup_remarks_type' => $this->resolveKeyValue('FOLLOW_UP_REMARKS_TYPE', $this->cell($row, $headerMap, 'First Followup Remarks Type')),
                            'customer_type'              => $this->cell($row, $headerMap, 'Customer Type'),
                            'interested_in_exchange'     => $this->cell($row, $headerMap, 'Intrested In Exchange'),
                            'completed_followup_count'   => $this->cell($row, $headerMap, 'Completed Followup Count'),
                            'td_count'                   => $this->cell($row, $headerMap, 'TD Count'),
                        ]);
                        $data['updated_at'] = $now;

                        if (empty($enquiryNo)) {
                            $data['enquiry_no'] = null;
                            DB::table('xlr8_crm_enquiries')->insert(array_merge(
                                $data,
                                ['created_at' => $now, 'origin' => 'LONG', 'current_origin' => 'LONG']
                            ));
                            $stats['inserted']++;
                        } else {
                            $existed = $this->upsertRowWithAssignment(
                                'xlr8_crm_enquiries',
                                ['enquiry_no' => $enquiryNo],
                                $data,
                                ['created_at' => $now, 'origin' => 'LONG', 'current_origin' => 'LONG'],
                                $scMileId,
                                'enq_assign_date',
                                $now
                            );

                            $existed ? $stats['updated']++ : $stats['inserted']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Long] Row {$excelRow} FAILED — " . $e->getMessage(), ['row' => $row]);
                    }
                }
            });
            $this->bumpProgress($log, count($chunk));
        }

        return $stats;
    }

    private function importReferenceSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $i => $row) {
                    $excelRow = $i + 2;
                    try {
                        [$firstName, $lastName] = $this->splitCustomerName($this->cell($row, $headerMap, 'Customer Name'));
                        $mobile = $this->cell($row, $headerMap, 'Customer Phone Number');

                        if (empty($mobile)) {
                            Log::warning("[Reference] Row {$excelRow} SKIPPED — Customer Phone Number missing", ['row' => $row]);
                            $stats['skipped']++;
                            continue;
                        }

                        $model = $this->cell($row, $headerMap, 'Model');
                        $matchCriteria = $model !== null ? ['mobile' => $mobile, 'model' => $model] : ['mobile' => $mobile];

                        $data = $this->stripNulls([
                            'first_name'          => $firstName,
                            'last_name'            => $lastName,
                            'model'                => $model,
                            'variant'              => $this->cell($row, $headerMap, 'Variant (Optional)'),
                            'lead_datetime'        => $this->excelDate($this->cell($row, $headerMap, 'Lead Date & Time'), true),
                            'referred_by'          => $this->cell($row, $headerMap, 'Referred By'),
                            'referee_name'         => $this->cell($row, $headerMap, 'Referee Name'),
                            'referee_phone'        => $this->cell($row, $headerMap, 'Referee Phone Number'),
                        ]);
                        $data['updated_at'] = $now;

                        $existed = $this->upsertRow(
                            'xlr8_crm_enquiries',
                            $matchCriteria,
                            $data,
                            ['created_at' => $now, 'origin' => 'REFERENCE', 'current_origin' => 'REFERENCE']
                        );

                        $existed ? $stats['updated']++ : $stats['inserted']++;
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Reference] Row {$excelRow} FAILED — " . $e->getMessage(), ['row' => $row]);
                    }
                }
            });
            $this->bumpProgress($log, count($chunk));
        }

        return $stats;
    }

    private function importWhatsappSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $i => $row) {
                    $excelRow = $i + 2;
                    try {
                        [$firstName, $lastName] = $this->splitCustomerName($this->cell($row, $headerMap, 'Customer Name'));
                        $mobile = $this->cell($row, $headerMap, 'Customer Phone Number');

                        if (empty($mobile)) {
                            Log::warning("[Whatsapp] Row {$excelRow} SKIPPED — Customer Phone Number missing", ['row' => $row]);
                            $stats['skipped']++;
                            continue;
                        }

                        $model = $this->cell($row, $headerMap, 'Model');
                        $matchCriteria = $model !== null ? ['mobile' => $mobile, 'model' => $model] : ['mobile' => $mobile];

                        $data = $this->stripNulls([
                            'first_name'             => $firstName,
                            'last_name'               => $lastName,
                            'model'                   => $model,
                            'variant'                 => $this->cell($row, $headerMap, 'Variant (Optional)'),
                            'lead_datetime'           => $this->excelDate($this->cell($row, $headerMap, 'Lead Date & Time'), true),
                            'wapp_campaign_name'      => $this->cell($row, $headerMap, 'Wapp Campaign Name'),
                            'wapp_campaign_date'      => $this->excelDate($this->cell($row, $headerMap, 'Wapp Campaign Date')),
                            'wapp_campaign_segment'   => $this->cell($row, $headerMap, 'Wapp Campaign Segment'),
                            'wapp_campaign_model'     => $this->cell($row, $headerMap, 'Wapp Campaign Model'),
                            'tehsil'                  => $this->cell($row, $headerMap, 'Tehsil'),
                        ]);
                        $data['updated_at'] = $now;

                        $existed = $this->upsertRow(
                            'xlr8_crm_enquiries',
                            $matchCriteria,
                            $data,
                            ['created_at' => $now, 'origin' => 'WHATSAPP', 'current_origin' => 'WHATSAPP']
                        );

                        $existed ? $stats['updated']++ : $stats['inserted']++;
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Whatsapp] Row {$excelRow} FAILED — " . $e->getMessage(), ['row' => $row]);
                    }
                }
            });
            $this->bumpProgress($log, count($chunk));
        }

        return $stats;
    }

// ══════════════════════════════════════════════════════════════════════════
// SHARED HELPERS
// ══════════════════════════════════════════════════════════════════════════

    /**
     * Single select + single write instead of the original's select + (select
     * again inside updateOrInsert) + write = 3 queries. This is 2. Cuts total
     * query volume by ~1/3 across ~85k rows without touching matching logic.
     */
    private function upsertRow(string $table, array $matchCriteria, array $data, array $insertExtra): bool
    {
        $existed = DB::table($table)->where($matchCriteria)->exists();

        if ($existed) {
            DB::table($table)->where($matchCriteria)->update($data);
        } else {
            DB::table($table)->insert(array_merge($matchCriteria, $data, $insertExtra));
        }

        return $existed;
    }

    /**
     * Same upsert as upsertRow(), plus the SC-assignment-date business rule
     * used by the Quick and Long sheets: $assignDateColumn (quick_enq_assign_date
     * for Quick, enq_assign_date for Long) is only ever stamped with $now when ALL of this
     * is true —
     *   - the row already existed (matched by the sheet's enquiry-number
     *     column, e.g. quick_enquiry_no for Quick / enquiry_no for Long), AND
     *   - its sc_mile_id was empty before this import, AND
     *   - this import row is setting a non-empty sc_mile_id for the first time.
     * Fresh inserts and re-imports of an already-assigned SC never touch the
     * assignment date — it's a "first assignment" timestamp, not a "last
     * touched" timestamp.
     */
    private function upsertRowWithAssignment(
        string $table,
        array $matchCriteria,
        array $data,
        array $insertExtra,
        ?string $newScMileId,
        string $assignDateColumn,
        $now
    ): bool {
        $existingRow = DB::table($table)->where($matchCriteria)->first(['sc_mile_id']);
        $existed = $existingRow !== null;

        if ($existed) {
            if (empty($existingRow->sc_mile_id) && !empty($newScMileId)) {
                $data[$assignDateColumn] = $now;
            }
            DB::table($table)->where($matchCriteria)->update($data);
        } else {
            DB::table($table)->insert(array_merge($matchCriteria, $data, $insertExtra));
        }

        return $existed;
    }

    /**
     * Loads xlr8_utils_keyvalue into $this->keyvalueCache, scoped to just the
     * keyword_code groups this import cares about (self::KEYVALUE_CODES) —
     * no point holding the whole lookup table (activity types, remarks,
     * etc.) in memory when only 4 groups are ever consulted here.
     */
    private function loadKeyvalueCache(): void
    {
        if ($this->keyvalueCacheLoaded) {
            return;
        }

        $rows = DB::table('xlr8_utils_keyvalue')
            ->whereIn('keyword_code', self::KEYVALUE_CODES)
            ->where('is_active', 1)
            ->get(['keyword_code', 'code', 'value']);

        foreach ($rows as $row) {
            $normalized = $this->normalizeForMatch($row->value);
            if ($normalized === '') {
                continue;
            }
            $this->keyvalueCache[$row->keyword_code][$normalized] = $row->code;
        }

        $this->keyvalueCacheLoaded = true;
    }

    /**
     * Loads xlr8_vehicle_model into $this->vehicleModelCache, keyed by
     * normalized `name`, holding both `code` and `segment_code` for each.
     */
    private function loadVehicleModelCache(): void
    {
        if ($this->vehicleModelCacheLoaded) {
            return;
        }

        $rows = DB::table('xlr8_vehicle_model')
            ->where('is_active', 1)
            ->get(['name', 'code', 'segment_code']);

        foreach ($rows as $row) {
            $normalized = $this->normalizeForMatch($row->name);
            if ($normalized === '') {
                continue;
            }
            $this->vehicleModelCache[$normalized] = [
                'model_code'   => $row->code,
                'segment_code' => $row->segment_code,
            ];
        }

        $this->vehicleModelCacheLoaded = true;
    }

    /**
     * Loads distinct colors from xlr8_vehicle_variant (its own `color` /
     * `color_code` columns — NOT the separate xlr8_vehicle_color table)
     * into $this->vehicleColorCache, keyed by normalized `color` name.
     *
     * If the same normalized color name maps to more than one distinct
     * color_code across the table (e.g. two genuinely different codes both
     * labelled "Red" on different models), the match is ambiguous — we keep
     * the first one seen and log a warning so it can be reviewed, rather
     * than silently guessing.
     */
    /**
     * Loads distinct colors from xlr8_vehicle_variant (its own `color` /
     * `color_code` columns — NOT the separate xlr8_vehicle_color table)
     * into $this->vehicleColorCache, keyed by normalized `color` name.
     *
     * If the same normalized color name maps to more than one distinct
     * color_code across the table, we store all variants and use the most
     * common one as default.
     */
    private function loadVehicleColorCache(): void
    {
        if ($this->vehicleColorCacheLoaded) {
            return;
        }

        $rows = DB::table('xlr8_vehicle_variant')
            ->where('is_active', 1)
            ->whereNotNull('color')
            ->whereNotNull('color_code')
            ->where('color', '!=', '')
            ->where('color_code', '!=', '')
            ->get(['color', 'color_code']);

        $tempCache = [];

        foreach ($rows as $row) {
            $normalized = $this->normalizeForMatch($row->color);
            if ($normalized === '') {
                continue;
            }

            if (!isset($tempCache[$normalized])) {
                $tempCache[$normalized] = [];
            }

            $tempCache[$normalized][$row->color_code] = [
                'color'      => $row->color,
                'color_code' => $row->color_code,
                'count'      => ($tempCache[$normalized][$row->color_code]['count'] ?? 0) + 1,
            ];
        }

        // For each normalized color, pick the most common color_code
        foreach ($tempCache as $normalized => $codes) {
            if (count($codes) > 1) {
                Log::warning('Ambiguous color name across xlr8_vehicle_variant — multiple color_codes share this name; using the most common one.', [
                    'color'    => $codes[array_key_first($codes)]['color'],
                    'variants' => array_map(function ($v) {
                        return ['code' => $v['color_code'], 'count' => $v['count']];
                    }, $codes),
                ]);
            }

            // Sort by count descending and pick the first one
            uasort($codes, function ($a, $b) {
                return $b['count'] - $a['count'];
            });

            $selected = reset($codes);
            $this->vehicleColorCache[$normalized] = [
                'color'      => $selected['color'],
                'color_code' => $selected['color_code'],
            ];
        }

        $this->vehicleColorCacheLoaded = true;
    }

   
    // Add these methods to your ImportEnquiriesJob class after the existing helper methods

    /**
     * Resolves a raw model name against xlr8_vehicle_model.name, matching
     * case-insensitively with whitespace collapsed/trimmed. Unlike
     * resolveKeyValue(), a miss does NOT create a new vehicle_model row —
     * it just returns nulls for both codes, so only the raw `model` text
     * (and variant, handled by the caller) gets saved.
     */
    private function resolveVehicleModel($rawModelName): array
    {
        $normalized = $rawModelName !== null ? $this->normalizeForMatch((string) $rawModelName) : '';

        if ($normalized === '' || !isset($this->vehicleModelCache[$normalized])) {
            return ['model_code' => null, 'segment_code' => null];
        }

        return $this->vehicleModelCache[$normalized];
    }

    /**
     * Resolves a raw imported color name against the xlr8_vehicle_variant
     * color cache, matching case-insensitively with whitespace collapsed/
     * trimmed. On a match, returns the canonical stored `color` name and its
     * `color_code`. On a miss, returns the raw text as-is for `color` and
     * null for `color_code` — nothing is auto-created.
     */
    private function resolveVehicleColor($rawColorName): array
    {
        $raw = $rawColorName !== null ? trim((string) $rawColorName) : null;
        $normalized = $raw !== null && $raw !== '' ? $this->normalizeForMatch($raw) : '';

        if ($normalized === '' || !isset($this->vehicleColorCache[$normalized])) {
            return ['color' => $raw, 'color_code' => null];
        }

        return $this->vehicleColorCache[$normalized];
    }




    /**
     * Maps a raw "Purchase Type" value (Long sheet only) against the fixed
     * PURCHASE_TYPE_MAP, case-insensitive / whitespace-collapsed. Returns
     * the canonical mapped string, or null if it doesn't match one of the
     * four known values (never auto-created, never guessed).
     */
    private function resolvePurchaseType($rawValue): ?string
    {
        if ($rawValue === null) {
            return null;
        }

        $normalized = $this->normalizeForMatch((string) $rawValue);
        if ($normalized === '') {
            return null;
        }

        if (isset(self::PURCHASE_TYPE_MAP[$normalized])) {
            return self::PURCHASE_TYPE_MAP[$normalized];
        }

        Log::warning('Unrecognized Purchase Type value — left NULL', ['value' => $rawValue]);

        return null;
    }


    /**
     * Resolves a raw imported value against xlr8_utils_keyvalue for the
     * given keyword_code group, matching case-insensitively (and with
     * whitespace collapsed/trimmed) against the `value` column. Returns the
     * matching `code`. If nothing matches, a new xlr8_utils_keyvalue row is
     * created on the fly (code derived from the raw text) and its code is
     * returned — the cache is updated too, so the same new value appearing
     * again later in this same import reuses it instead of creating a
     * duplicate.
     */
    private function resolveKeyValue(string $keywordCode, $rawValue): ?string
    {
        if ($rawValue === null) {
            return null;
        }

        $rawValue = (string) $rawValue;
        $normalized = $this->normalizeForMatch($rawValue);

        if ($normalized === '') {
            return null;
        }

        if (isset($this->keyvalueCache[$keywordCode][$normalized])) {
            return $this->keyvalueCache[$keywordCode][$normalized];
        }

        // Not seen before under this keyword_code — create it.
        $trimmedValue = trim(preg_replace('/\s+/', ' ', $rawValue));
        $newCode = $this->generateKeyvalueCode($trimmedValue);

        // Check if this code already exists in DB (might have been created
        // outside the cache, e.g., by another import or manual entry)
        $existing = DB::table('xlr8_utils_keyvalue')
            ->where('keyword_code', $keywordCode)
            ->where('code', $newCode)
            ->first();

        if ($existing) {
            // Code already exists — use it and update cache
            $this->keyvalueCache[$keywordCode][$normalized] = $existing->code;

            // Also cache by the existing value's normalized form to avoid future misses
            $existingNormalized = $this->normalizeForMatch($existing->value);
            if ($existingNormalized !== $normalized) {
                $this->keyvalueCache[$keywordCode][$existingNormalized] = $existing->code;
            }

            Log::info("Existing xlr8_utils_keyvalue entry found for new value", [
                'keyword_code' => $keywordCode,
                'new_value'    => $trimmedValue,
                'existing_code' => $existing->code,
                'existing_value' => $existing->value,
            ]);

            return $existing->code;
        }

        // Check if the same value exists with a different code
        $existingByValue = DB::table('xlr8_utils_keyvalue')
            ->where('keyword_code', $keywordCode)
            ->where('value', $trimmedValue)
            ->first();

        if ($existingByValue) {
            // Same value, different code — use the existing code
            $this->keyvalueCache[$keywordCode][$normalized] = $existingByValue->code;

            Log::info("Value already exists with different code in xlr8_utils_keyvalue", [
                'keyword_code' => $keywordCode,
                'value'        => $trimmedValue,
                'existing_code' => $existingByValue->code,
                'attempted_code' => $newCode,
            ]);

            return $existingByValue->code;
        }

        // Truly new entry — insert it
        try {
            DB::table('xlr8_utils_keyvalue')->insert([
                'keyword_code' => $keywordCode,
                'key'          => null,
                'code'         => $newCode,
                'value'        => $trimmedValue,
                'details'      => null,
                'parent_id'    => null,
                'level'        => 0,
                'path'         => null,
                'extra_data'   => null,
                'status'       => 1,
                'is_active'    => 1,
                'created_by'   => null,
                'updated_by'   => null,
                'deleted_by'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
                'deleted_at'   => null,
            ]);

            $this->keyvalueCache[$keywordCode][$normalized] = $newCode;

            Log::info("New xlr8_utils_keyvalue entry created", [
                'keyword_code' => $keywordCode,
                'value'        => $trimmedValue,
                'code'         => $newCode,
            ]);

            return $newCode;
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle race condition: if another process inserted the same code
            // between our check and insert
            if ($e->getCode() == 23000) { // Integrity constraint violation
                $existing = DB::table('xlr8_utils_keyvalue')
                    ->where('keyword_code', $keywordCode)
                    ->where('code', $newCode)
                    ->first();

                if ($existing) {
                    $this->keyvalueCache[$keywordCode][$normalized] = $existing->code;

                    Log::warning("Race condition: xlr8_utils_keyvalue entry created by another process", [
                        'keyword_code' => $keywordCode,
                        'code'         => $newCode,
                    ]);

                    return $existing->code;
                }
            }

            // If it's not a duplicate key error, rethrow it
            throw $e;
        }
    }


    /** Lowercase + trim + collapse internal whitespace, for case/whitespace-insensitive matching. */
    private function normalizeForMatch(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
    }

    /**
     * Derives a new xlr8_utils_keyvalue `code` from raw free-text input,
     * matching the style of the existing seed data (e.g. "Test Drive" ->
     * "TEST_DRIVE"): uppercase, non-alphanumeric runs collapsed to a single
     * underscore, leading/trailing underscores trimmed.
     */
    private function generateKeyvalueCode(string $rawValue): string
    {
        $code = mb_strtoupper($rawValue);
        $code = preg_replace('/[^A-Z0-9]+/', '_', $code);
        $code = trim($code, '_');

        return $code !== '' ? $code : 'UNKNOWN';
    }

    private function getSheetHeaderMap(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $headerRow = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, true, false)[0];

        $map = [];
        foreach ($headerRow as $colIndex => $header) {
            $header = trim((string) $header);
            if ($header !== '') {
                $map[$header] = $colIndex;
            }
        }

        return $map;
    }

    private function cell(array $row, array $headerMap, string $header)
    {
        if (!isset($headerMap[$header])) {
            return null;
        }

        $value = $row[$headerMap[$header]] ?? null;

        if (is_string($value)) {
            $value = trim($value);
        }

        return ($value === '' || $value === null) ? null : $value;
    }

    private function splitCustomerName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $fullName, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    private function stripNulls(array $data): array
    {
        return array_filter($data, fn($value) => $value !== null);
    }

    /**
     * Normalizes a raw "Call Duration" cell into MySQL TIME format
     * (H:i:s, e.g. "05:23:00") for the now-nullable `call_duration` TIME
     * column. Handles the shapes this kind of column commonly shows up in:
     *
     *   - Excel time-of-day serial (fraction of a day, e.g. 0.0037...)
     *     -> converted via PhpSpreadsheet's date engine
     *   - Plain number >= 1 -> treated as total duration in SECONDS
     *     (a fraction-of-a-day serial for a phone call would virtually
     *     always be well under 1, so anything >= 1 is unambiguous)
     *   - String already in "H:i:s" or "i:s" form (e.g. "5:23", "05:23:00")
     *     -> parsed and re-padded
     *
     * Anything that doesn't cleanly fit one of these is logged and stored
     * as NULL rather than guessed at.
     */
    private function formatCallDuration($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $num = (float) $value;

                if ($num < 1) {
                    // Excel time-of-day fraction (e.g. 0.00385 == 5m 32s)
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($num);
                    return $dt->format('H:i:s');
                }

                // Treat as total seconds
                $totalSeconds = (int) round($num);
                $h = intdiv($totalSeconds, 3600);
                $m = intdiv($totalSeconds % 3600, 60);
                $s = $totalSeconds % 60;
                return sprintf('%02d:%02d:%02d', $h, $m, $s);
            }

            $str = trim((string) $value);

            // "H:i:s" or "i:s" style text
            if (preg_match('/^(\d{1,3}):([0-5]?\d)(?::([0-5]?\d))?$/', $str, $m)) {
                $h = (int) $m[1];
                $mi = (int) $m[2];
                $s = isset($m[3]) ? (int) $m[3] : 0;
                return sprintf('%02d:%02d:%02d', $h, $mi, $s);
            }

            Log::warning('Unparseable Call Duration value — stored as NULL', ['value' => $value]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('Unparseable Call Duration value — stored as NULL', ['value' => $value, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function excelDate($value, bool $withTime = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            } else {
                $dt = new \DateTime((string) $value);
            }

            return $withTime ? $dt->format('Y-m-d H:i:s') : $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            Log::warning("Unparseable date value encountered — stored as NULL", ['value' => $value]);
            return null;
        }
    }
}
