<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\Traits\ScopedCrud;
use Revolution\Google\Sheets\Facades\Sheets;

class RtoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use ScopedCrud;

    protected function getScopeType(): string
    {
        return '';
    }

    // =========================================================================
    //  RTO Google Sheets Import
    //
    //  Spreadsheet : https://docs.google.com/spreadsheets/d/1pZAC7e7uxc-5nco2ERABj6dWPqfK511m0tQPcXGyhZk
    //
    //  Sheet GIDs  :
    //    0          → RTO Manual
    //    991896693  → HSRP BKN
    //    253943540  → HSRP CHR
    //    950100465  → Vaahan (TC0056)
    //    475945448  → Vaahan (TC0281)
    //
    //  "Pending At" column (Vaahan sheets only) contains a text label such as
    //  "DEALER-NEW-RC-APPROVAL".  We match it against xlr8_booking_rto_rule
    //  using TWO keys:
    //    1. pending_at  — the label text (case-insensitive)
    //    2. rgn_no      — derived from the sheet's "Registration No" cell:
    //         • blank / "NEW"            → rule rgn_no is '' or NULL  (same bucket)
    //         • "TRC"                    → rule rgn_no = 'TRC'
    //         • any real reg number      → rule rgn_no = 'Registration No'
    //  The matched rule's `id` is stored as `pendat_id` in xlr8_booking_rto.
    // =========================================================================

    public function import()
    {
        // =====================================================================
        //  CONFIGURATION
        // =====================================================================

        $spreadsheetId = '1pZAC7e7uxc-5nco2ERABj6dWPqfK511m0tQPcXGyhZk';

        // exact sheet name => [ type, api-safe name ]
        // Sheet names that look like cell refs (e.g. 'TC0281') must be
        // single-quoted when sent to the Sheets API, otherwise the API
        // parses them as column+row and throws a 400 error.
        $sheetNames = [
            'RTO Manual'      => ['type' => 'rto_manual', 'safe' => 'RTO Manual'],
            'HSRP BKN'        => ['type' => 'hsrp_bkn',  'safe' => 'HSRP BKN'],
            'HSRP CHR'        => ['type' => 'hsrp_chr',  'safe' => 'HSRP CHR'],
            'Vaahan (TC0056)' => ['type' => 'vaahan',    'safe' => 'Vaahan (TC0056)'],
            'TC0281'          => ['type' => 'vaahan',    'safe' => "'TC0281'"],
        ];

        // =====================================================================
        //  ENUM MAPS  (sheet text → DB integer)
        // =====================================================================

        $saleTypeMap = [
            'within state'  => 1,
            'outside state' => 2,
        ];

        $permitMap = [
            'private - u/c (4 wheeler)'    => 1,
            'private - bh (4 wheeler)'     => 2,
            'private - ev (4 wheeler)'     => 3,
            'goods - g (4 wheeler)'        => 4,
            'goods - g 3 ton+ (4 wheeler)' => 5,
            'goods - g (3 wheeler)'        => 6,
            'goods - g ev (3 wheeler)'     => 7,
            'taxi - t (4 wheeler)'         => 8,
            'passenger - p (3 wheeler)'    => 9,
            'passenger - p ev (3 wheeler)' => 10,
            'ambulance (misc.)'            => 11,
        ];

        $bodyTypeMap = [
            'complete' => 1,
            'cbc'      => 2,
        ];

        $rgnTypeMap = [
            'trc only'  => 1,
            'tax only'  => 2,
            'trc + tax' => 3,
        ];

        $rgnNoTypeMap = [
            'regular' => 1,
            'bh'      => 2,
            'special' => 3,
        ];

        // =====================================================================
        //  PRE-LOAD LOOKUP TABLES
        // =====================================================================

        // --- bookings: chassis_no / dms_otf  →  id ---
        $bookingByChassis = \DB::table('xlr8_booking_master')
            ->whereNotNull('chassis_no')
            ->pluck('id', 'chassis_no')
            ->toArray();

        $bookingByOtf = \DB::table('xlr8_booking_master')
            ->whereNotNull('dms_otf')
            ->pluck('id', 'dms_otf')
            ->toArray();

        // --- rto rules: [pending_at_lower][rgn_no_bucket] → rule id ---
        //
        // rgn_no in the rules table is one of four values:
        //   ''  / NULL        → bucket 'blank'
        //   'NEW'             → bucket 'blank'   (treated same as empty)
        //   'Registration No' → bucket 'rgn'
        //   'TRC'             → bucket 'trc'
        //
        // We build a two-level map so lookups are O(1) per row.
        $rtoRules = \DB::table('xlr8_booking_rto_rule')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get(['id', 'pending_at', 'rgn_no']);

        $ruleMap = [];   // [ 'dealer-new-rc-approval' => [ 'blank' => 79, 'rgn' => 80 ], ... ]

        foreach ($rtoRules as $rule) {
            $paKey     = strtolower(trim((string) $rule->pending_at));
            $rgnRaw    = strtolower(trim((string) $rule->rgn_no));

            // Normalise rgn_no → bucket
            if ($rgnRaw === 'registration no') {
                $bucket = 'rgn';
            } elseif ($rgnRaw === 'trc') {
                $bucket = 'trc';
            } else {
                // '' / null / 'new' all collapse to 'blank'
                $bucket = 'blank';
            }

            $ruleMap[$paKey][$bucket] = (int) $rule->id;
        }

        // =====================================================================
        //  COLUMN MAPS  (DB field  =>  Google Sheet header — case-insensitive partial match)
        // =====================================================================

        $columnMaps = [

            // -----------------------------------------------------------------
            //  RTO Manual  (gid 991896693)
            // -----------------------------------------------------------------
            'rto_manual' => [
                'dms_otf'                 => 'OTF No.',
                'chassis_no'              => 'Chassis No',          // matches "Chassis No", "Chassis No.", "ChassisNo"
                'sale_type'               => 'Sale Type',
                'permit'                  => 'Permit',
                'body_type'               => 'Body Type',
                'rgn_type'                => 'Registration Type',
                'rgn_no_type'             => 'Registration No. Type',
                'app_no'                  => 'RTO Application Number',
                'trc_no'                  => 'TRC Number',
                'trc_amount'              => 'TRC Amount',
                'trc_trans_date'          => 'TRC Transaction Date',
                'trc_payment_no'          => 'TRC Payment Reference No',
                'tax_amount'              => 'Tax Amount',
                'tax_trans_date'          => 'Tax Transaction Date',
                'tax_payment_bank_ref_no' => 'Tax Payment Reference No',
                'vh_rgn_no'               => 'Registration No.',
            ],

            // -----------------------------------------------------------------
            //  HSRP BKN  (gid 253943540)  &  HSRP CHR  (gid 950100465)
            //  Same column layout — only hsrp_location differs
            // -----------------------------------------------------------------
            'hsrp_bkn' => [
                'vh_rgn_no'             => 'vehicleregno',
                'chassis_no'            => 'ChassisNo',
                'order_date'            => 'OrderDate',
                'hsrp_front_lasercode'  => 'hsrp_front_lasercode',
                'hsrp_rear_lasercode'   => 'hsrp_rear_lasercode',
                'prod_status'           => 'Productionstatus',
                'recieving_status'      => 'ReceivingStatus',
                'dispatch_date'         => 'DispatchDate',
                'order_delivery_date'   => 'OrderDeliveryDate',
                'affixation_date'       => 'Affixationdate',
            ],

            'hsrp_chr' => [
                'vh_rgn_no'             => 'vehicleregno',
                'chassis_no'            => 'ChassisNo',
                'order_date'            => 'OrderDate',
                'hsrp_front_lasercode'  => 'hsrp_front_lasercode',
                'hsrp_rear_lasercode'   => 'hsrp_rear_lasercode',
                'prod_status'           => 'Productionstatus',
                'recieving_status'      => 'ReceivingStatus',
                'dispatch_date'         => 'DispatchDate',
                'order_delivery_date'   => 'OrderDeliveryDate',
                'affixation_date'       => 'Affixationdate',
            ],

            // -----------------------------------------------------------------
            //  Vaahan / TC0056 / TC0281  (gid 475945448)
            //  Columns: Application No | Registration No | Purpose | Pending At
            // -----------------------------------------------------------------
            'vaahan' => [
                'app_no'     => 'Application No',
                'vh_rgn_no'  => 'Registration No',
                'purpose'    => 'Purpose',
                'pending_at' => 'Pending At',     // raw text — resolved to pending_at_id below
            ],
        ];

        // =====================================================================
        //  PROCESS SHEETS
        // =====================================================================

        $totalImported = 0;
        $totalSkipped  = 0;
        $now           = now();
        $userId        = auth()->id() ?? 1;

        foreach ($sheetNames as $sheetName => $sheetConfig) {
            $sheetType = $sheetConfig['type'];
            $sheetSafe = $sheetConfig['safe'];

            \Log::info("RTO Import: Starting sheet=[{$sheetName}] type={$sheetType}");

            $values = Sheets::spreadsheet($spreadsheetId)
                ->sheet($sheetSafe)
                ->all();

            if (empty($values) || count($values) < 2) {
                \Log::warning("RTO Import: Sheet=[{$sheetName}] is empty or has no data rows, skipping.");
                continue;
            }

            // ------------------------------------------------------------------
            //  Build header  →  column-index map  (case-insensitive partial match)
            // ------------------------------------------------------------------
            $gscolarr = $columnMaps[$sheetType] ?? [];
            $gs_pos   = array_fill_keys(array_keys($gscolarr), null);

            foreach ($values[0] as $colIdx => $header) {
                $header = trim((string) $header);
                foreach ($gscolarr as $dbField => $expectedHeader) {
                    if ($gs_pos[$dbField] === null && stripos($header, $expectedHeader) !== false) {
                        $gs_pos[$dbField] = $colIdx;
                        break;
                    }
                }
            }

            \Log::info("RTO Import [{$sheetName}]: Column positions resolved", ['gs_pos' => $gs_pos]);

            $imported = 0;
            $skipped  = 0;

            foreach (array_slice($values, 1) as $rowIndex => $row) {

                $actualRow = $rowIndex + 2;

                // Utility: safely fetch a cell by its DB-field name
                $get = fn(string $field): mixed => $row[$gs_pos[$field] ?? -1] ?? null;

                // ----------------------------------------------------------
                //  Per-sheet row anchor + bid resolution
                //
                //  rto_manual / hsrp_*  → chassis_no is the anchor
                //  vaahan               → app_no is the anchor (no chassis column)
                //                         find the existing rto row by app_no and
                //                         update only pendat_id + purpose on it
                // ----------------------------------------------------------
                if ($sheetType === 'vaahan') {

                    $appNo = trim((string) $get('app_no'));
                    if (empty($appNo)) {
                        $skipped++;
                        continue;
                    }

                    $rawPendingAt = trim((string) $get('pending_at'));
                    $rawRgnNo     = trim((string) $get('vh_rgn_no'));

                    $rgnNoBucket = match (true) {
                        $rawRgnNo === '' || strtoupper($rawRgnNo) === 'NEW' => 'blank',
                        strtoupper($rawRgnNo) === 'TRC'                     => 'trc',
                        default                                              => 'rgn',
                    };

                    $pendatId = $this->resolveRuleId($rawPendingAt, $rgnNoBucket, $ruleMap, $sheetName, $actualRow);

                    // Update the existing xlr8_booking_rto row matched by app_no
                    $affected = \DB::table('xlr8_booking_rto')
                        ->where('app_no', $appNo)
                        ->whereNull('deleted_at')
                        ->update([
                            'pendat_id'  => $pendatId,
                            'purpose'    => substr(trim((string) $get('purpose')), 0, 100),
                            'updated_at' => $now,
                            'updated_by' => $userId,
                        ]);

                    if ($affected === 0) {
                        \Log::warning("RTO Import [{$sheetName}] Row {$actualRow}: app_no not found in xlr8_booking_rto", [
                            'app_no' => $appNo,
                        ]);
                        $skipped++;
                    } else {
                        $imported++;
                    }
                    continue;  // skip the generic updateOrInsert below
                }

                // rto_manual + hsrp_* path — chassis is the anchor
                $chassis = strtoupper(trim((string) ($get('chassis_no') ?? '')));

                if (empty($chassis)) {
                    $skipped++;
                    continue;
                }

                // ----------------------------------------------------------
                //  Resolve bid (booking id)
                // ----------------------------------------------------------
                $otf = trim((string) ($get('dms_otf') ?? $get('app_no') ?? ''));
                $bid = $this->resolveBid($chassis, $otf, $bookingByChassis, $bookingByOtf, $sheetName, $actualRow);

                // ----------------------------------------------------------
                //  Build insert/update payload per sheet type
                // ----------------------------------------------------------
                if ($sheetType === 'rto_manual') {

                    $data = [
                        'bid'                     => $bid,
                        'chassis_no'              => substr($chassis, 0, 20),
                        'dms_otf'                 => substr(trim((string) $get('dms_otf')), 0, 100),
                        'sale_type'               => $this->mapEnum($get('sale_type'), $saleTypeMap),
                        'permit'                  => $this->mapEnum($get('permit'), $permitMap),
                        'body_type'               => $this->mapEnum($get('body_type'), $bodyTypeMap),
                        'rgn_type'                => $this->mapEnum($get('rgn_type'), $rgnTypeMap),
                        'rgn_no_type'             => $this->mapEnum($get('rgn_no_type'), $rgnNoTypeMap),
                        'app_no'                  => substr(trim((string) $get('app_no')), 0, 50),
                        'trc_no'                  => substr(trim((string) $get('trc_no')), 0, 50),
                        'trc_amount'              => $this->parseAmount($get('trc_amount')),
                        'trc_trans_date'          => $this->parseDate($get('trc_trans_date'), $sheetName, $actualRow),
                        'trc_payment_no'          => substr(trim((string) $get('trc_payment_no')), 0, 50),
                        'tax_amount'              => $this->parseAmount($get('tax_amount')),
                        'tax_trans_date'          => $this->parseDate($get('tax_trans_date'), $sheetName, $actualRow),
                        'tax_payment_bank_ref_no' => substr(trim((string) $get('tax_payment_bank_ref_no')), 0, 50),
                        'vh_rgn_no'               => substr(trim((string) $get('vh_rgn_no')), 0, 50),
                        'trade_used'              => 0,
                        'status'                  => 1,
                        'updated_at'              => $now,
                        'updated_by'              => $userId,
                    ];

                } elseif (in_array($sheetType, ['hsrp_bkn', 'hsrp_chr'])) {

                    $data = [
                        'bid'                   => $bid,
                        'chassis_no'            => substr($chassis, 0, 20),
                        'vh_rgn_no'             => substr(trim((string) $get('vh_rgn_no')), 0, 50),
                        'hsrp_location'         => ($sheetType === 'hsrp_bkn') ? 'BKN' : 'CHR',
                        'order_date'            => $this->parseDate($get('order_date'), $sheetName, $actualRow),
                        'hsrp_front_lasercode'  => substr(trim((string) $get('hsrp_front_lasercode')), 0, 50),
                        'hsrp_rear_lasercode'   => substr(trim((string) $get('hsrp_rear_lasercode')), 0, 50),
                        'prod_status'           => substr(trim((string) $get('prod_status')), 0, 10),
                        'recieving_status'      => substr(trim((string) $get('recieving_status')), 0, 10),
                        'dispatch_date'         => $this->parseDate($get('dispatch_date'), $sheetName, $actualRow),
                        'order_delivery_date'   => $this->parseDate($get('order_delivery_date'), $sheetName, $actualRow),
                        'affixation_date'       => $this->parseDate($get('affixation_date'), $sheetName, $actualRow),
                        'trade_used'            => 0,
                        'sale_type'             => null,
                        'permit'                => null,
                        'body_type'             => null,
                        'rgn_type'              => null,
                        'rgn_no_type'           => null,
                        'status'                => 1,
                        'updated_at'            => $now,
                        'updated_by'            => $userId,
                    ];

                }

                try {
                    \DB::table('xlr8_booking_rto')->updateOrInsert(
                        ['chassis_no' => $data['chassis_no']],
                        array_merge($data, [
                            'created_at' => $now,
                            'created_by' => $userId,
                        ])
                    );
                    $imported++;
                } catch (\Exception $e) {
                    \Log::error("RTO Import [{$sheetName}] Row {$actualRow}: DB write failed", [
                        'error'   => $e->getMessage(),
                        'chassis' => $chassis,
                    ]);
                    $skipped++;
                }
            }

            \Log::info("RTO Import: [{$sheetName}] done", [
                'imported' => $imported,
                'skipped'  => $skipped,
            ]);

            $totalImported += $imported;
            $totalSkipped  += $skipped;
        }

        \Log::info("RTO Import: All sheets done", [
            'total_imported' => $totalImported,
            'total_skipped'  => $totalSkipped,
        ]);

        $message = "RTO Import completed! Imported/Updated: {$totalImported}, Skipped: {$totalSkipped}";

        return $totalImported > 0
            ? redirect()->back()->with('success', $message)
            : redirect()->back()->with('warning', $message);
    }

    // =========================================================================
    //  PRIVATE HELPERS
    // =========================================================================

    /**
     * Resolve booking id: chassis_no first, then dms_otf / app_no.
     * Returns int|null.
     */
    private function resolveBid(
        string  $chassis,
        string  $otf,
        array   $bookingByChassis,
        array   $bookingByOtf,
        mixed   $gid,
        int     $row
    ): ?int {
        if (!empty($chassis) && isset($bookingByChassis[$chassis])) {
            return (int) $bookingByChassis[$chassis];
        }

        if (!empty($otf) && isset($bookingByOtf[$otf])) {
            return (int) $bookingByOtf[$otf];
        }

        \Log::warning("RTO Import GID={$gid} Row {$row}: bid not resolved", [
            'chassis' => $chassis,
            'otf'     => $otf,
        ]);

        return null;
    }

    /**
     * Map a text cell value to an enum integer via a lookup map.
     * Comparison is case-insensitive and trims whitespace.
     * Returns null when the value is empty or not found.
     */
    private function mapEnum(mixed $raw, array $map): ?int
    {
        if (empty($raw)) {
            return null;
        }

        return $map[strtolower(trim((string) $raw))] ?? null;
    }

    /**
     * Resolve a Vaahan row's "Pending At" label + rgn_no bucket
     * to the matching xlr8_booking_rto_rule.id (stored as pendat_id).
     *
     * $ruleMap structure (built during pre-load):
     *   [ 'dealer-new-rc-approval' => [ 'blank' => 79, 'rgn' => 80 ], ... ]
     *
     * Bucket values passed in:
     *   'blank'  — vh_rgn_no was empty or "NEW"
     *   'trc'    — vh_rgn_no was "TRC"
     *   'rgn'    — vh_rgn_no is a real registration number
     *
     * Fallback: if the exact bucket has no rule, try the other non-trc
     * bucket (blank ↔ rgn) before giving up, so minor mismatches don't
     * silently drop data.
     */
    private function resolveRuleId(
        string $pendingAt,
        string $rgnNoBucket,
        array  $ruleMap,
        mixed  $gid,
        int    $row
    ): ?int {
        if (empty($pendingAt)) {
            return null;
        }

        $paKey = strtolower(trim($pendingAt));

        if (!isset($ruleMap[$paKey])) {
            \Log::warning("RTO Import resolveRuleId: No rule found for pending_at", [
                'pending_at' => $pendingAt,
                'gid'        => $gid,
                'row'        => $row,
            ]);
            return null;
        }

        $buckets = $ruleMap[$paKey];

        // Primary: exact bucket match
        if (isset($buckets[$rgnNoBucket])) {
            return $buckets[$rgnNoBucket];
        }

        // Fallback: for blank/rgn, try the other one (both represent
        // "no real reg number yet" vs "reg number exists" but some rules
        // may only have one variant)
        $fallback = ($rgnNoBucket === 'blank') ? 'rgn' : 'blank';
        if (isset($buckets[$fallback])) {
            \Log::info("RTO Import resolveRuleId: Used fallback bucket", [
                'pending_at'      => $pendingAt,
                'requested_bucket'=> $rgnNoBucket,
                'fallback_bucket' => $fallback,
                'gid'             => $gid,
                'row'             => $row,
            ]);
            return $buckets[$fallback];
        }

        // Last resort: return whatever rule exists for this pending_at
        $firstId = reset($buckets);
        \Log::warning("RTO Import resolveRuleId: No bucket match, using first available rule", [
            'pending_at' => $pendingAt,
            'bucket'     => $rgnNoBucket,
            'used_id'    => $firstId,
            'gid'        => $gid,
            'row'        => $row,
        ]);
        return $firstId ?: null;
    }

    /**
     * Parse a cell value to Y-m-d string.
     * Handles Excel serial numbers, DateTime objects, and common string formats.
     */
    private function parseDate(mixed $raw, mixed $gid = null, int $row = 0): ?string
    {
        if (empty($raw) || trim((string) $raw) === '') {
            return null;
        }

        $raw = trim((string) $raw);

        $ignored = ['na', 'n/a', 'nil', '-', '--', 'none', '31-dec-1899'];
        if (in_array(strtolower($raw), $ignored)) {
            return null;
        }

        // Excel serial number (Google Sheets also returns these for date cells)
        if (is_numeric($raw)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);
                $year = (int) $date->format('Y');
                if ($year >= 2000 && $year <= 2100) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {}
        }

        // Explicit format list
        $formats = [
            'd/m/Y',
            'd-m-Y',
            'd/m/y',
            'd-M-Y',
            'd M Y',
            'Y-m-d',
            'Y-m-d H:i:s',
            'm/d/Y',
        ];

        foreach ($formats as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $raw);
            if ($d !== false) {
                $year = (int) $d->format('Y');
                if ($year >= 2000 && $year <= 2100) {
                    return $d->format('Y-m-d');
                }
            }
        }

        // 2-digit year: "1-Apr-26"
        if (preg_match('/^(\d{1,2})[-\/]([A-Za-z]{3})[-\/](\d{2})$/', $raw, $m)) {
            $d = \DateTime::createFromFormat('j-M-Y', $m[1] . '-' . $m[2] . '-20' . $m[3]);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }

        // 2-digit year: "13/04/26"
        if (preg_match('/^(\d{1,2})\/(\d{2})\/(\d{2})$/', $raw, $m)) {
            $d = \DateTime::createFromFormat('d/m/Y', $m[1] . '/' . $m[2] . '/20' . $m[3]);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }

        // Carbon fallback
        try {
            $d    = \Carbon\Carbon::parse($raw);
            $year = (int) $d->format('Y');
            if ($year >= 2000 && $year <= 2100) {
                return $d->format('Y-m-d');
            }
        } catch (\Exception $e) {}

        \Log::warning("RTO Import parseDate: Could not parse", [
            'raw' => $raw,
            'gid' => $gid,
            'row' => $row,
        ]);

        return null;
    }

    /**
     * Clean an amount string like "1,08,025.00" → 108025 (int).
     */
    private function parseAmount(mixed $raw): ?int
    {
        if (empty($raw)) {
            return null;
        }

        $clean = preg_replace('/[^0-9.]/', '', (string) $raw);

        return is_numeric($clean) ? (int) round((float) $clean) : null;
    }
}