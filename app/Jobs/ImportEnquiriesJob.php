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

    public int $timeout = 1800;
    public int $tries = 1;

    private int $importLogId;
    private string $storedPath;

    private const CHUNK_SIZE = 300;

    // 1. ADDED ALL REQUESTED KEYVALUE CODES HERE
    private const KEYVALUE_CODES = [
        'ENQUIRY_TYPE',
        'ENQUIRY_SUB_SOURCE',
        'LIKELY_PURCHASE_DATE',
        'FOLLOW_UP_TYPE',
        'FOLLOW_UP_REMARKS_TYPE',
        'TEST_DRIVE_STAGE',
        'DEVIATION_STAGE',
        'LOST_SUBREASON',
        'LOST_REASON',
        'APPLICATION',
        'SC_FUP_REMARKS_TYPE',
        'SC_FUP_REMARKS',
        'CALL_NATURE_VIRTUAL',
        'APPLICATION_TYPE',
        'KM_TRAVELLED_DAILY',
        'USAGE_AREA',
        'AGE_GROUP',
        'OCCUPATION_SUB_TYPE',
        'OCCUPATION_TYPE',
        'ACTIVITY_TYPE',
    ];

    private const PURCHASE_TYPE_MAP = [
        'first time buy' => 'First Time Buy',
        'exchange buy'   => 'Exchange Buy',
        'additional buy' => 'Additional Buy',
        'scrappage'      => 'Scrappage',
    ];

    private array $keyvalueCache = [];
    private bool $keyvalueCacheLoaded = false;

    private array $vehicleModelCache = [];
    private bool $vehicleModelCacheLoaded = false;

    private array $vehicleColorCache = [];
    private bool $vehicleColorCacheLoaded = false;

    private int $processedSoFar = 0;

    public function __construct(int $importLogId, string $storedPath)
    {
        $this->importLogId = $importLogId;
        $this->storedPath  = $storedPath;
    }

    public function handle(): void
    {
        $log = DB::table('xlr8_crm_import_logs')->where('id', $this->importLogId);
        $log->update(['status' => 'processing', 'updated_at' => now()]);

        // 2. ADDED 'Booking' TO SHEET HANDLERS
        $sheetHandlers = [
            'Virtual'    => 'importVirtualSheet',
            'Hyperlocal' => 'importHyperlocalSheet',
            'Quick'      => 'importQuickSheet',
            'Long'       => 'importLongSheet',
            'Reference'  => 'importReferenceSheet',
            'Whatsapp'   => 'importWhatsappSheet',
            'Follow Up'  => 'importFollowUpSheet',
            'Test Drive' => 'importTestDriveSheet',
            'Booking'    => 'importBookingSheet',
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

            $totalRows = 0;
            foreach ($sheetHandlers as $sheetName => $handler) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                if ($sheet) {
                    $totalRows += max(0, $sheet->getHighestRow() - 1);
                }
            }
            $log->update(['total_rows' => $totalRows]);
            $this->processedSoFar = 0;

            $this->loadKeyvalueCache();
            $this->loadVehicleModelCache();
            $this->loadVehicleColorCache();

            foreach ($sheetHandlers as $sheetName => $handlerMethod) {
                $sheet = $spreadsheet->getSheetByName($sheetName);

                if (!$sheet) {
                    Log::warning("Sheet [{$sheetName}] not found in uploaded file — skipped.");
                    $overallStats[$sheetName] = [
                        'inserted' => 0,
                        'updated'  => 0,
                        'skipped'  => 0,
                        'note'     => 'Sheet not found in file',
                    ];
                    continue;
                }

                Log::info("--- Processing sheet [{$sheetName}] ---");
                $overallStats[$sheetName] = $this->{$handlerMethod}($sheet, $now, $log);
                Log::info("--- Finished sheet [{$sheetName}] ---", $overallStats[$sheetName]);

                $log->update(['stats' => json_encode($overallStats), 'updated_at' => now()]);
            }

            Log::info('=== CRM Enquiry Import (Job) Completed ===', $overallStats);

            $log->update([
                'status'     => 'completed',
                'stats'      => json_encode($overallStats),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
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
            if (file_exists($this->storedPath)) {
                @unlink($this->storedPath);
            }
        }
    }

    private function bumpProgress($log, int $byRows): void
    {
        $this->processedSoFar += $byRows;
        $log->update(['processed_rows' => $this->processedSoFar, 'updated_at' => now()]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SHEET HANDLERS
    // ══════════════════════════════════════════════════════════════════════════

    private function importVirtualSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'merged_duplicates_in_file' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        $byMobile = [];
        $noMobileEntries = [];
        foreach ($rows as $i => $row) {
            $mobile = $this->cleanString($this->cell($row, $headerMap, 'Customer Number'), 15);
            $entry = ['row' => $row, 'excelRow' => $i + 2];

            if (empty($mobile)) {
                $noMobileEntries[] = $entry;
                continue;
            }

            if (isset($byMobile[$mobile])) {
                $stats['merged_duplicates_in_file']++;
            }
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
                        $mobile = $this->cleanString($this->cell($row, $headerMap, 'Customer Number'), 15);

                        $data = $this->stripNulls([
                            'virtual_no'        => $this->cleanString($this->cell($row, $headerMap, 'Virtual No'), 50),
                            'call_status'       => $this->cleanString($this->cell($row, $headerMap, 'Call Status'), 50),
                            'call_duration'     => $this->formatCallDuration($this->cell($row, $headerMap, 'Call Duration')),
                            'mobile'            => $mobile,
                            'virtual_call_date' => $this->excelDate($this->cell($row, $headerMap, 'Starting Date'), true),
                        ]);
                        $data['updated_at'] = $now;

                        if (empty($mobile)) {
                            DB::table('xlr8_crm_enquiries')->insert(array_merge(
                                $data,
                                ['created_at' => $now, 'origin' => 'VIRTUAL', 'current_origin' => 'VIRTUAL']
                            ));
                            $stats['inserted']++;
                            continue;
                        }

                        // UNIQUENESS: By Mobile for Virtual
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

        if ($duplicatesRemovedFromCount > 0) {
            $this->bumpProgress($log, $duplicatesRemovedFromCount);
        }

        return $stats;
    }

    private function importHyperlocalSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $i => $row) {
                    $excelRow = $i + 2;
                    try {
                        [$firstName, $lastName] = $this->splitCustomerName($this->cell($row, $headerMap, 'Name'));
                        $mobile = $this->cleanString($this->cell($row, $headerMap, 'Phone-Number'), 15);

                        $modelName = $this->cell($row, $headerMap, 'Model');
                        $modelMatch = $this->resolveVehicleModel($modelName);

                        // 3. UPDATED HYPERLOCAL COLUMNS PER YOUR SPECIFICATION
                        $data = $this->stripNulls([
                            'lead_id'             => $this->cleanString($this->cell($row, $headerMap, 'Leads-ID'), 100),
                            'first_name'           => $this->cleanString($firstName, 100),
                            'last_name'            => $this->cleanString($lastName, 100),
                            'mobile'               => $mobile,
                            'virtual_call_date'    => $this->excelDate($this->cell($row, $headerMap, 'Call-Start-Time'), true),
                            'call_recording_url'   => $this->cleanString($this->cell($row, $headerMap, 'Call-Recording-URL'), 255),
                            'call_duration'        => $this->formatCallDuration($this->cell($row, $headerMap, 'Call-Duration-in-Seconds')),
                            'call_status'          => $this->cleanString($this->cell($row, $headerMap, 'Call-Status'), 50),
                            'call_type'            => $this->cleanString($this->cell($row, $headerMap, 'Call-Type'), 50),
                            'notes'                => $this->cleanString($this->cell($row, $headerMap, 'Notes')),
                            'lead_status'          => $this->cleanString($this->cell($row, $headerMap, 'Lead-Status'), 50),
                            'client_crm_status'    => $this->cleanString($this->cell($row, $headerMap, 'Client-CRM-Status'), 50),
                            'model'                => $this->cleanString($modelName, 100),
                            'model_code'           => $modelMatch['model_code'],
                            'segment_code'         => $modelMatch['segment_code'],
                            'dealer_code'          => $this->cleanString($this->cell($row, $headerMap, 'Dealer-Code'), 50),
                        ]);
                        $data['updated_at'] = $now;

                        if (empty($mobile)) {
                            DB::table('xlr8_crm_enquiries')->insert(array_merge(
                                $data,
                                ['created_at' => $now, 'origin' => 'HYPERLOCAL', 'current_origin' => 'HYPERLOCAL']
                            ));
                            $stats['inserted']++;
                            continue;
                        }

                        // UNIQUENESS: By Mobile for Hyperlocal
                        $existed = $this->upsertRow(
                            'xlr8_crm_enquiries',
                            ['mobile' => $mobile],
                            $data,
                            ['created_at' => $now, 'origin' => 'HYPERLOCAL', 'current_origin' => 'HYPERLOCAL']
                        );

                        $existed ? $stats['updated']++ : $stats['inserted']++;
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Hyperlocal] Row {$excelRow} FAILED — " . $e->getMessage(), ['row' => $row]);
                    }
                }
            });
            $this->bumpProgress($log, count($chunk));
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
                        $enquiryNo = $this->cleanString($this->cell($row, $headerMap, 'Enquiry Number'), 50);

                        [$firstName, $lastName] = $this->splitCustomerName($this->cell($row, $headerMap, 'Customer Name'));
                        $scMileId = $this->cleanString($this->cell($row, $headerMap, 'SC Mile Id'), 50);
                        $mobile   = $this->cleanString($this->cell($row, $headerMap, 'Mobile Phone'), 15);

                        $modelName = $this->cell($row, $headerMap, 'Product Family');
                        $modelMatch = $this->resolveVehicleModel($modelName);
                        $colorMatch = $this->resolveVehicleColor($this->cell($row, $headerMap, 'Color'));

                        $data = $this->stripNulls([
                            'first_name'                   => $this->cleanString($firstName, 100),
                            'last_name'                     => $this->cleanString($lastName, 100),
                            'mobile'                        => $mobile,
                            'email'                         => $this->cleanString($this->cell($row, $headerMap, 'Email'), 150),
                            'sc_mile_id'                    => $scMileId,
                            'model'                         => $this->cleanString($modelName, 100),
                            'model_code'                    => $modelMatch['model_code'],
                            'segment_code'                  => $modelMatch['segment_code'],
                            'variant'                       => $this->cleanString($this->cell($row, $headerMap, 'Variant Description'), 100),
                            'color'                         => $this->cleanString($colorMatch['color'], 100),
                            'color_code'                    => $colorMatch['color_code'],
                            'fuel_type'                     => $this->cleanString($this->cell($row, $headerMap, 'Fuel Type'), 50),
                            'seating'                       => $this->cleanString($this->cell($row, $headerMap, 'Seating Capacity'), 20),
                            'enquiry_type'                  => $this->resolveKeyValue('ENQUIRY_TYPE', $this->cell($row, $headerMap, 'Enquiry Type')),
                            'source_code'                   => $this->cleanString($this->cell($row, $headerMap, 'Enquiry Source'), 50),
                            'sub_source'                    => $this->resolveKeyValue('ENQUIRY_SUB_SOURCE', $this->cell($row, $headerMap, 'Enquiry Sub Source')),
                            'quick_status'                  => $this->cleanString($this->cell($row, $headerMap, 'Status'), 50),
                            'quick_enquiry_date'            => $this->excelDate($this->cell($row, $headerMap, 'Quick Enquiry Date')),
                            'test_drive_no'                 => $this->cleanString($this->cell($row, $headerMap, 'Test Drive Number'), 50),
                            'first_planned_followup_date'   => $this->excelDate($this->cell($row, $headerMap, 'First Planned Followup')),
                            'first_actual_followup_date'    => $this->excelDate($this->cell($row, $headerMap, 'First Actual Followup')),
                            'recent_planned_followup_date'  => $this->excelDate($this->cell($row, $headerMap, 'Recent Planned Followup')),
                            'recent_actual_followup_date'   => $this->excelDate($this->cell($row, $headerMap, 'Recent Actual Followup')),
                        ]);
                        $data['updated_at'] = $now;

                        // UNIQUENESS: By Quick Enquiry Number for Quick Sheet
                        if (empty($enquiryNo)) {
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
                        $enquiryNo = $this->cleanString($this->cell($row, $headerMap, 'Enquiry Number'), 50);

                        [$firstName, $lastName] = $this->splitCustomerName($this->cell($row, $headerMap, 'Customer Name'));
                        $scMileId = $this->cleanString($this->cell($row, $headerMap, 'SC Mile Id'), 50);
                        $mobile   = $this->cleanString($this->cell($row, $headerMap, 'Customer Phone'), 15);

                        $modelName = $this->cell($row, $headerMap, 'Product Family');
                        $modelMatch = $this->resolveVehicleModel($modelName);
                        $colorMatch = $this->resolveVehicleColor($this->cell($row, $headerMap, 'Color'));

                        $data = $this->stripNulls([
                            'first_name'             => $this->cleanString($firstName, 100),
                            'last_name'               => $this->cleanString($lastName, 100),
                            'mobile'                  => $mobile,
                            'email'                   => $this->cleanString($this->cell($row, $headerMap, 'Customer Email'), 150),
                            'sc_mile_id'              => $scMileId,
                            'model'                   => $this->cleanString($modelName, 100),
                            'model_code'              => $modelMatch['model_code'],
                            'segment_code'            => $modelMatch['segment_code'],
                            'variant'                 => $this->cleanString($this->cell($row, $headerMap, 'Variant Description'), 100),
                            'color'                   => $this->cleanString($colorMatch['color'], 100),
                            'color_code'              => $colorMatch['color_code'],
                            'purchase_type'           => $this->resolvePurchaseType($this->cell($row, $headerMap, 'Purchase Type')),
                            'enquiry_type'            => $this->resolveKeyValue('ENQUIRY_TYPE', $this->cell($row, $headerMap, 'Enquiry Type')),
                            'source_code'             => $this->cleanString($this->cell($row, $headerMap, 'Enquiry Source'), 50),
                            'sub_source'              => $this->resolveKeyValue('ENQUIRY_SUB_SOURCE', $this->cell($row, $headerMap, 'Enquiry Sub Source')),
                            'stage'                   => $this->cleanString($this->cell($row, $headerMap, 'Stage'), 50),
                            'enquiry_date'            => $this->excelDate($this->cell($row, $headerMap, 'Enquiry Date')),
                            'customer_address'        => $this->cleanString($this->cell($row, $headerMap, 'Customer Address'), 255),
                            'tehsil'                  => $this->cleanString($this->cell($row, $headerMap, 'Tehsil'), 100),
                            'district'                => $this->cleanString($this->cell($row, $headerMap, 'District'), 100),
                            'zipcode'                 => $this->cleanString($this->cell($row, $headerMap, 'Postal Code'), 20),
                            'fuel_type'               => $this->cleanString($this->cell($row, $headerMap, 'Fuel Type'), 50),
                            'seating'                 => $this->cleanString($this->cell($row, $headerMap, 'Seating Capacity'), 20),
                            'customer_type'           => $this->cleanString($this->cell($row, $headerMap, 'Customer Type'), 50),
                            'interested_in_exchange'  => $this->cleanString($this->cell($row, $headerMap, 'Intrested In Exchange'), 10),
                            'td_count'                => $this->cleanString($this->cell($row, $headerMap, 'TD Count'), 10),
                            // 4. ADDED resolveKeyValue FOR LOST REASONS
                            'lost_reason'             => $this->resolveKeyValue('LOST_REASON', $this->cell($row, $headerMap, 'Lost-Reason')),
                            'lost_sub_reason'         => $this->resolveKeyValue('LOST_SUBREASON', $this->cell($row, $headerMap, 'Lost-Sub Reason')),
                            'lost_detail_reason'      => $this->cleanString($this->cell($row, $headerMap, 'Lost-Detailed Reason'), 255),
                            'lost_remarks'            => $this->cleanString($this->cell($row, $headerMap, 'Lost remarks by Sales Consultant'), 255),
                        ]);
                        $data['updated_at'] = $now;

                        // UNIQUENESS: By Enquiry Number for Long Sheet
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
                        $mobile = $this->cleanString($this->cell($row, $headerMap, 'Customer Phone Number'), 15);

                        $model = $this->cleanString($this->cell($row, $headerMap, 'Model'), 100);

                        $data = $this->stripNulls([
                            'first_name'    => $this->cleanString($firstName, 100),
                            'last_name'     => $this->cleanString($lastName, 100),
                            'mobile'        => $mobile,
                            'model'         => $model,
                            'variant'       => $this->cleanString($this->cell($row, $headerMap, 'Variant (Optional)'), 100),
                            'lead_datetime' => $this->excelDate($this->cell($row, $headerMap, 'Lead Date & Time'), true),
                            'referred_by'   => $this->cleanString($this->cell($row, $headerMap, 'Referred By'), 100),
                            'referee_name'  => $this->cleanString($this->cell($row, $headerMap, 'Referee Name'), 100),
                            'referee_phone' => $this->cleanString($this->cell($row, $headerMap, 'Referee Phone Number'), 15),
                        ]);
                        $data['updated_at'] = $now;

                        if (empty($mobile)) {
                            DB::table('xlr8_crm_enquiries')->insert(array_merge(
                                $data,
                                ['created_at' => $now, 'origin' => 'REFERENCE', 'current_origin' => 'REFERENCE']
                            ));
                            $stats['inserted']++;
                            continue;
                        }

                        // UNIQUENESS: Updated strictly to checking 'mobile' (number uniqueness) as requested
                        $existed = $this->upsertRow(
                            'xlr8_crm_enquiries',
                            ['mobile' => $mobile],
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
                        $mobile = $this->cleanString($this->cell($row, $headerMap, 'Customer Phone Number'), 15);

                        $model = $this->cleanString($this->cell($row, $headerMap, 'Model'), 100);

                        $data = $this->stripNulls([
                            'first_name'            => $this->cleanString($firstName, 100),
                            'last_name'             => $this->cleanString($lastName, 100),
                            'mobile'                => $mobile,
                            'model'                 => $model,
                            'variant'               => $this->cleanString($this->cell($row, $headerMap, 'Variant (Optional)'), 100),
                            'lead_datetime'         => $this->excelDate($this->cell($row, $headerMap, 'Lead Date & Time'), true),
                            'wapp_campaign_name'    => $this->cleanString($this->cell($row, $headerMap, 'Wapp Campaign Name'), 100),
                            'wapp_campaign_date'    => $this->excelDate($this->cell($row, $headerMap, 'Wapp Campaign Date')),
                            'wapp_campaign_segment' => $this->cleanString($this->cell($row, $headerMap, 'Wapp Campaign Segment'), 100),
                            'wapp_campaign_model'   => $this->cleanString($this->cell($row, $headerMap, 'Wapp Campaign Model'), 100),
                            'tehsil'                => $this->cleanString($this->cell($row, $headerMap, 'Tehsil'), 100),
                        ]);
                        $data['updated_at'] = $now;

                        if (empty($mobile)) {
                            DB::table('xlr8_crm_enquiries')->insert(array_merge(
                                $data,
                                ['created_at' => $now, 'origin' => 'WHATSAPP', 'current_origin' => 'WHATSAPP']
                            ));
                            $stats['inserted']++;
                            continue;
                        }

                        // Aligning with standard uniqueness by mobile
                        $existed = $this->upsertRow(
                            'xlr8_crm_enquiries',
                            ['mobile' => $mobile],
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

    private function importFollowUpSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $i => $row) {
                    $excelRow = $i + 2;
                    try {
                        $modelName = $this->cell($row, $headerMap, 'Model Name');
                        $modelMatch = $this->resolveVehicleModel($modelName);

                        // 5. APPLIED resolveKeyValue FOR FUP SHEET FIELDS
                        $data = [
                            'enquiry_no'            => $this->cleanString($this->cell($row, $headerMap, 'Enquiry Number'), 50),
                            'sc_code'               => $this->cleanString($this->cell($row, $headerMap, 'Sales Consultant'), 200),
                            'sc_mile_id'            => $this->cleanString($this->cell($row, $headerMap, 'SC Mile Id'), 100),
                            'followup_type'         => $this->resolveKeyValue('FOLLOW_UP_TYPE', $this->cell($row, $headerMap, 'Followup Type')),
                            'remark_type'           => $this->resolveKeyValue('SC_FUP_REMARKS_TYPE', $this->cell($row, $headerMap, 'Remark Type')),
                            'planned_followup_date' => $this->excelDate($this->cell($row, $headerMap, 'Planned Followup Date')),
                            'actual_followup_date'  => $this->excelDate($this->cell($row, $headerMap, 'Actual Followup Date')),
                            'remarks'               => $this->resolveKeyValue('SC_FUP_REMARKS', $this->cell($row, $headerMap, 'Remark')),
                            'comments'              => $this->cleanString($this->cell($row, $headerMap, 'Comments')),
                            'enquiry_date'          => $this->excelDate($this->cell($row, $headerMap, 'Enquiry Date')),
                            'enquiry_type'          => $this->resolveKeyValue('ENQUIRY_TYPE', $this->cell($row, $headerMap, 'Enquiry Type')),
                            'enquiry_source'        => $this->cleanString($this->cell($row, $headerMap, 'Enquiry Source'), 50),
                            'enquiry_sub_source'    => $this->resolveKeyValue('ENQUIRY_SUB_SOURCE', $this->cell($row, $headerMap, 'Enquiry Sub Source')),
                            'enquiry_status'        => $this->cleanString($this->cell($row, $headerMap, 'Enquiry Status'), 50),
                            'purchase_type'         => $this->cleanString($this->cell($row, $headerMap, 'Purchase Type'), 50),
                            'deviation_stage'       => $this->resolveKeyValue('DEVIATION_STAGE', $this->cell($row, $headerMap, 'Deviation Stage')),
                            'customer_name'         => $this->cleanString($this->cell($row, $headerMap, 'Customer Name'), 200),
                            'customer_phone'        => $this->cleanString($this->cell($row, $headerMap, 'Customer Phone'), 15),
                            'model_name'            => $this->cleanString($modelName, 150),
                            'variant_description'   => $this->cleanString($this->cell($row, $headerMap, 'Variant Description'), 150),
                            'seating_capacity'     => $this->cleanString($this->cell($row, $headerMap, 'Seating Capacity'), 50),
                            'fuel_type'            => $this->cleanString($this->cell($row, $headerMap, 'Fuel Type'), 50),
                            'color'                 => $this->cleanString($this->cell($row, $headerMap, 'Color'), 100),
                            'dealer_branch'         => $this->cleanString($this->cell($row, $headerMap, 'Dealer Name'), 100),
                            'dealer_location'       => $this->cleanString($this->cell($row, $headerMap, 'Dealer Location'), 100),
                            'segment'               => null,
                            'segment_code'          => $modelMatch['segment_code'] ?? null,
                        ];

                        $query = DB::table('xlr8_crm_enquiries_fup');
                        foreach ($data as $column => $value) {
                            if (is_null($value)) {
                                $query->whereNull($column);
                            } else {
                                $query->where($column, $value);
                            }
                        }

                        if ($query->exists()) {
                            $stats['skipped']++;
                        } else {
                            DB::table('xlr8_crm_enquiries_fup')->insert(array_merge($data, [
                                'is_active'  => 1,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]));
                            $stats['inserted']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Follow Up] Row {$excelRow} SKIPPED (Parsing Exception) — " . $e->getMessage(), ['row' => $row]);
                    }
                }
            });
            $this->bumpProgress($log, count($chunk));
        }

        return $stats;
    }

    private function importTestDriveSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $i => $row) {
                    $excelRow = $i + 2;
                    try {
                        $modelName = $this->cell($row, $headerMap, 'Product Family');
                        $modelMatch = $this->resolveVehicleModel($modelName);

                        $customerName  = $this->cell($row, $headerMap, 'Customer Name') ?? $this->cell($row, $headerMap, 'Lead Name');
                        $customerPhone = $this->cell($row, $headerMap, 'Customer Phone') ?? $this->cell($row, $headerMap, 'Lead Phone');

                        // 6. APPLIED resolveKeyValue FOR TD STAGE
                        $data = [
                            'test_drive_no'           => $this->cleanString($this->cell($row, $headerMap, 'Test Drive Number'), 50),
                            'enquiry_no'              => $this->cleanString($this->cell($row, $headerMap, 'Enquiry Number'), 50),
                            'sc_code'                 => $this->cleanString($this->cell($row, $headerMap, 'Sales Consultant'), 200),
                            'sc_mile_id'              => $this->cleanString($this->cell($row, $headerMap, 'SC Mile Id'), 100),
                            'stage'                   => $this->resolveKeyValue('TEST_DRIVE_STAGE', $this->cell($row, $headerMap, 'Stage')),
                            'td_created_date'         => $this->excelDate($this->cell($row, $headerMap, 'TD Created Date')),
                            'scheduled_td_start_time' => $this->excelDate($this->cell($row, $headerMap, 'Scheduled TD Start Time'), true),
                            'scheduled_td_end_time'   => $this->excelDate($this->cell($row, $headerMap, 'Scheduled TD End Time'), true),
                            'actual_td_start_time'    => $this->excelDate($this->cell($row, $headerMap, 'Actual TD Start Time'), true),
                            'actual_td_end_time'      => $this->excelDate($this->cell($row, $headerMap, 'Actual TD End Time'), true),
                            'model'                   => $this->cleanString($modelName, 150),
                            'model_code'              => $modelMatch['model_code'] ?? null,
                            'variant'                 => $this->cleanString($this->cell($row, $headerMap, 'Variant Description'), 150),
                            'variant_code'            => null,
                            'customer_name'           => $this->cleanString($customerName, 200),
                            'customer_phone'          => $this->cleanString($customerPhone, 15),
                        ];

                        $query = DB::table('xlr8_crm_testdrive');
                        foreach ($data as $column => $value) {
                            if (is_null($value)) {
                                $query->whereNull($column);
                            } else {
                                $query->where($column, $value);
                            }
                        }

                        if ($query->exists()) {
                            $stats['skipped']++;
                        } else {
                            DB::table('xlr8_crm_testdrive')->insert(array_merge($data, [
                                'is_active'  => 1,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]));
                            $stats['inserted']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Test Drive] Row {$excelRow} SKIPPED (Parsing Exception) — " . $e->getMessage(), ['row' => $row]);
                    }
                }
            });
            $this->bumpProgress($log, count($chunk));
        }

        return $stats;
    }

    // 7. NEW BOOKING SHEET FUNCTION
    private function importBookingSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, $now, $log): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $headerMap = $this->getSheetHeaderMap($sheet);
        $rows = array_slice($sheet->toArray(null, true, true, false), 1);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            DB::transaction(function () use ($chunk, $headerMap, $now, &$stats) {
                foreach ($chunk as $i => $row) {
                    $excelRow = $i + 2;
                    try {
                        $bookingNumber = $this->cleanString($this->cell($row, $headerMap, 'Booking Number'), 100);

                        if (empty($bookingNumber)) {
                            $stats['skipped']++;
                            continue;
                        }

                        $data = $this->stripNulls([
                            'booking_date'                    => $this->excelDate($this->cell($row, $headerMap, 'Booking Date')),
                            'sc_code'                         => $this->cleanString($this->cell($row, $headerMap, 'SC Code'), 100),
                            'booking_status'                  => $this->cleanString($this->cell($row, $headerMap, 'Booking Status'), 50),
                            'booking_cancellation_date'       => $this->excelDate($this->cell($row, $headerMap, 'Booking Cancellation Date')),
                            'model_group'                     => $this->cleanString($this->cell($row, $headerMap, 'Model Group'), 100),
                            'model_variant'                   => $this->cleanString($this->cell($row, $headerMap, 'Model Variant'), 100),
                            'oem_model_code'                  => $this->cleanString($this->cell($row, $headerMap, 'OEM Model Code'), 100),
                            'booking_customer_code'           => $this->cleanString($this->cell($row, $headerMap, 'Booking Customer Code'), 100),
                            'booking_customer_name'           => $this->cleanString($this->cell($row, $headerMap, 'Booking Customer Name'), 255),
                            'booking_customer_address'        => $this->cleanString($this->cell($row, $headerMap, 'Booking Customer Address'), 255),
                            'booking_customer_city'           => $this->cleanString($this->cell($row, $headerMap, 'Booking Customer City'), 100),
                            'booking_customer_tehsil'         => $this->cleanString($this->cell($row, $headerMap, 'Booking Customer Tehsil'), 100),
                            'booking_customer_district'       => $this->cleanString($this->cell($row, $headerMap, 'Booking Customer District'), 100),
                            'billing_customer_pan_number'     => $this->cleanString($this->cell($row, $headerMap, 'Billing Customer PAN Number'), 50),
                            'billing_customer_tan_number'     => $this->cleanString($this->cell($row, $headerMap, 'Billing Customer TAN Number'), 50),
                            'billing_customer_aadhaar_number' => $this->cleanString($this->cell($row, $headerMap, 'Billing Customer Aadhaar Number'), 50),
                            'invoice_no'                      => $this->cleanString($this->cell($row, $headerMap, 'Invoice No.'), 100),
                            'evaluation_id'                   => $this->cleanString($this->cell($row, $headerMap, 'Evaluation ID'), 100),
                            'so_number'                       => $this->cleanString($this->cell($row, $headerMap, 'SO Number'), 100),
                            'otf_number'                      => $this->cleanString($this->cell($row, $headerMap, 'OTF Number'), 100),
                        ]);

                        $data['updated_at'] = $now;

                        // UNIQUENESS: Handled by Booking Number
                        $existed = $this->upsertRow(
                            'xlr8_crm_booking',
                            ['booking_number' => $bookingNumber],
                            $data,
                            ['created_at' => $now]
                        );

                        $existed ? $stats['updated']++ : $stats['inserted']++;
                    } catch (\Throwable $e) {
                        $stats['skipped']++;
                        Log::error("[Booking] Row {$excelRow} FAILED — " . $e->getMessage(), ['row' => $row]);
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

    private function cleanString($value, ?int $maxLength = null): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '' || $string === '-' || strtolower($string) === 'null' || strtolower($string) === 'n/a' || strtolower($string) === 'nat') {
            return null;
        }

        if ($maxLength && mb_strlen($string) > $maxLength) {
            return mb_substr($string, 0, $maxLength);
        }

        return $string;
    }

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

        foreach ($tempCache as $normalized => $codes) {
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

    private function resolveVehicleModel($rawModelName): array
    {
        $normalized = $rawModelName !== null ? $this->normalizeForMatch((string) $rawModelName) : '';

        if ($normalized === '' || !isset($this->vehicleModelCache[$normalized])) {
            return ['model_code' => null, 'segment_code' => null];
        }

        return $this->vehicleModelCache[$normalized];
    }

    private function resolveVehicleColor($rawColorName): array
    {
        $raw = $rawColorName !== null ? trim((string) $rawColorName) : null;
        $normalized = $raw !== null && $raw !== '' ? $this->normalizeForMatch($raw) : '';

        if ($normalized === '' || !isset($this->vehicleColorCache[$normalized])) {
            return ['color' => $raw, 'color_code' => null];
        }

        return $this->vehicleColorCache[$normalized];
    }

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

        return null;
    }

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

        $trimmedValue = trim(preg_replace('/\s+/', ' ', $rawValue));
        $newCode = $this->generateKeyvalueCode($trimmedValue);

        $existing = DB::table('xlr8_utils_keyvalue')
            ->where('keyword_code', $keywordCode)
            ->where('code', $newCode)
            ->first();

        if ($existing) {
            $this->keyvalueCache[$keywordCode][$normalized] = $existing->code;

            $existingNormalized = $this->normalizeForMatch($existing->value);
            if ($existingNormalized !== $normalized) {
                $this->keyvalueCache[$keywordCode][$existingNormalized] = $existing->code;
            }

            return $existing->code;
        }

        $existingByValue = DB::table('xlr8_utils_keyvalue')
            ->where('keyword_code', $keywordCode)
            ->where('value', $trimmedValue)
            ->first();

        if ($existingByValue) {
            $this->keyvalueCache[$keywordCode][$normalized] = $existingByValue->code;
            return $existingByValue->code;
        }

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

            return $newCode;
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                $existing = DB::table('xlr8_utils_keyvalue')
                    ->where('keyword_code', $keywordCode)
                    ->where('code', $newCode)
                    ->first();

                if ($existing) {
                    $this->keyvalueCache[$keywordCode][$normalized] = $existing->code;
                    return $existing->code;
                }
            }

            throw $e;
        }
    }

    private function normalizeForMatch(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
    }

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

    private function formatCallDuration($value): ?string
    {
        if ($value === null || $value === '' || $value === '-' || $value === 'N/A' || $value === 'NaT') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $num = (float) $value;

                if ($num < 1) {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($num);
                    return $dt->format('H:i:s');
                }

                $totalSeconds = (int) round($num);
                $h = intdiv($totalSeconds, 3600);
                $m = intdiv($totalSeconds % 3600, 60);
                $s = $totalSeconds % 60;
                return sprintf('%02d:%02d:%02d', $h, $m, $s);
            }

            $str = trim((string) $value);

            if (preg_match('/^(\d{1,3}):([0-5]?\d)(?::([0-5]?\d))?$/', $str, $m)) {
                $h = (int) $m[1];
                $mi = (int) $m[2];
                $s = isset($m[3]) ? (int) $m[3] : 0;
                return sprintf('%02d:%02d:%02d', $h, $mi, $s);
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function excelDate($value, bool $withTime = false): ?string
    {
        if ($value === null || $value === '' || $value === '-' || $value === 'N/A' || $value === 'NaT') {
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
            return null;
        }
    }
}
