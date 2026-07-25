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
                            'virtual_no'        => $this->cell($row, $headerMap, 'Virtual No.'),
                            'call_status'       => $this->cell($row, $headerMap, 'Call Status'),
                            'call_duration'     => $this->cell($row, $headerMap, 'Call Duration'),
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

                        $data = $this->stripNulls([
                            'first_name'                        => $firstName,
                            'last_name'                          => $lastName,
                            'mobile'                              => $mobile,
                            'sc_mile_id'                          => $scMileId,
                            'segment'                             => $this->cell($row, $headerMap, 'Product Family'),
                            'variant'                             => $this->cell($row, $headerMap, 'Variant Description'),
                            'color'                               => $this->cell($row, $headerMap, 'Color'),
                            'fuel_type'                           => $this->cell($row, $headerMap, 'Fuel Type'),
                            'seating'                             => $this->cell($row, $headerMap, 'Seating Capacity'),
                            'enquiry_type'                        => $this->cell($row, $headerMap, 'Enquiry Type'),
                            'source_code'                         => $this->cell($row, $headerMap, 'Enquiry Source'),
                            'sub_source'                          => $this->cell($row, $headerMap, 'Enquiry Sub Source'),
                            'likely_purchase_date'                => $this->cell($row, $headerMap, 'Likely Purchase In Days'),
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
                        // imported with long_enquiry_no left null. Matched/stored
                        // via the sheet-specific `long_enquiry_no` column — NOT
                        // the shared `enquiry_no` column (reserved for manually
                        // created enquiries via the CRUD form).
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

                        $data = $this->stripNulls([
                            'first_name'                => $firstName,
                            'last_name'                  => $lastName,
                            'mobile'                     => $mobile,
                            'sc_mile_id'                 => $scMileId,
                            'segment'                    => $this->cell($row, $headerMap, 'Product Family'),
                            'variant'                    => $this->cell($row, $headerMap, 'Variant Description'),
                            'color'                      => $this->cell($row, $headerMap, 'Color'),
                            'purchase_type'              => $this->cell($row, $headerMap, 'Purchase Type'),
                            'enquiry_type'               => $this->cell($row, $headerMap, 'Enquiry Type'),
                            'source_code'                => $this->cell($row, $headerMap, 'Enquiry Source'),
                            'sub_source'                 => $this->cell($row, $headerMap, 'Enquiry Sub Source'),
                            'stage'                      => $this->cell($row, $headerMap, 'Stage'),
                            'long_enquiry_date'          => $this->excelDate($this->cell($row, $headerMap, 'Enquiry Date')),
                            'enq_assign_date'            => $this->excelDate($this->cell($row, $headerMap, 'Enq Assign Date'), true),
                            'customer_address'           => $this->cell($row, $headerMap, 'Customer Address'),
                            'tehsil'                     => $this->cell($row, $headerMap, 'Tehsil'),
                            'district'                   => $this->cell($row, $headerMap, 'District'),
                            'zipcode'                    => $this->cell($row, $headerMap, 'Postal Code'),
                            'fuel_type'                  => $this->cell($row, $headerMap, 'Fuel Type'),
                            'seating'                    => $this->cell($row, $headerMap, 'Seating Capacity'),
                            'likely_purchase_date'       => $this->cell($row, $headerMap, 'Likely Purchase In Days'),
                            'customer_type'              => $this->cell($row, $headerMap, 'Customer Type'),
                            'interested_in_exchange'     => $this->cell($row, $headerMap, 'Intrested In Exchange'),
                            'completed_followup_count'   => $this->cell($row, $headerMap, 'Completed Followup Count'),
                            'td_count'                   => $this->cell($row, $headerMap, 'TD Count'),
                        ]);
                        $data['updated_at'] = $now;

                        if (empty($enquiryNo)) {
                            $data['long_enquiry_no'] = null;
                            DB::table('xlr8_crm_enquiries')->insert(array_merge(
                                $data,
                                ['created_at' => $now, 'origin' => 'LONG', 'current_origin' => 'LONG']
                            ));
                            $stats['inserted']++;
                        } else {
                            $existed = $this->upsertRowWithAssignment(
                                'xlr8_crm_enquiries',
                                ['long_enquiry_no' => $enquiryNo],
                                $data,
                                ['created_at' => $now, 'origin' => 'LONG', 'current_origin' => 'LONG'],
                                $scMileId,
                                'long_enq_assign_date',
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
     * or long_enq_assign_date) is only ever stamped with $now when ALL of this
     * is true —
     *   - the row already existed (matched by the sheet's own enquiry-number
     *     column, e.g. quick_enquiry_no / long_enquiry_no), AND
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
