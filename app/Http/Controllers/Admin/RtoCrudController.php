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

    public function import()
    {
        
        $sheetNames = [
            'RTO Manual'      => ['type' => 'rto_manual', 'safe' => 'RTO Manual'],
            'HSRP BKN'        => ['type' => 'hsrp_bkn',  'safe' => 'HSRP BKN'],
            'HSRP CHR'        => ['type' => 'hsrp_chr',  'safe' => 'HSRP CHR'],
            'Vaahan (TC0056)' => ['type' => 'vaahan',    'safe' => 'Vaahan (TC0056)'],
            'TC0281'          => ['type' => 'vaahan',    'safe' => "'TC0281'"],
        ];

        
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

       
        $bookingByChassis = \DB::table('xlr8_booking_master')
            ->whereNotNull('chassis_no')
            ->pluck('id', 'chassis_no')
            ->toArray();

        $bookingByOtf = \DB::table('xlr8_booking_master')
            ->whereNotNull('dms_otf')
            ->pluck('id', 'dms_otf')
            ->toArray();

        
        $rtoRules = \DB::table('xlr8_booking_rto_rule')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get(['id', 'pending_at', 'rgn_no']);

        $ruleMap = [];   
        foreach ($rtoRules as $rule) {
            $paKey     = strtolower(trim((string) $rule->pending_at));
            $rgnRaw    = strtolower(trim((string) $rule->rgn_no));

            if ($rgnRaw === 'registration no') {
                $bucket = 'rgn';
            } elseif ($rgnRaw === 'trc') {
                $bucket = 'trc';
            } else {
                $bucket = 'blank';
            }

            $ruleMap[$paKey][$bucket] = (int) $rule->id;
        }

      

        $columnMaps = [

          
            'rto_manual' => [
                'dms_otf'                 => 'OTF No.',
                'chassis_no'              => 'Chassis No.',         
                'sale_type'               => 'Sale Type',
                'permit'                  => 'Permit',
                'body_type'               => 'Body Type',
                'rgn_type'                => 'Registration Type',
                'rgn_no_type'             => 'Registration No. Type',
                'app_no'                  => 'RTO Application Number',
                'trc_no'                  => 'TRC Number',
                'trc_amount'              => 'TRC Amount',
                'trc_trans_date'          => 'TRC Transaction Date',
                'trc_payment_no'          => 'TRC Payment Reference No.',
                'tax_amount'              => 'Tax Amount',
                'tax_trans_date'          => 'Tax Transaction Date',
                'tax_payment_bank_ref_no' => 'Tax Payment Reference No.',
                'vh_rgn_no'               => 'Registration No.',
            ],

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

           
            'vaahan' => [
                'app_no'     => 'Application No.',
                'vh_rgn_no'  => 'Registration No.',
                'purpose'    => 'Purpose',
                'pending_at' => 'Pending At',    
            ],
        ];

       

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

               
                $get = fn(string $field): mixed => $row[$gs_pos[$field] ?? -1] ?? null;

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
                    continue;  
                }

              
                $chassis = strtoupper(trim((string) ($get('chassis_no') ?? '')));

                if (empty($chassis)) {
                    $skipped++;
                    continue;
                }

               
                $otf = trim((string) ($get('dms_otf') ?? $get('app_no') ?? ''));
                $bid = $this->resolveBid($chassis, $otf, $bookingByChassis, $bookingByOtf, $sheetName, $actualRow);

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

   
    private function mapEnum(mixed $raw, array $map): ?int
    {
        if (empty($raw)) {
            return null;
        }

        return $map[strtolower(trim((string) $raw))] ?? null;
    }

   
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

        if (isset($buckets[$rgnNoBucket])) {
            return $buckets[$rgnNoBucket];
        }

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

        if (is_numeric($raw)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);
                $year = (int) $date->format('Y');
                if ($year >= 2000 && $year <= 2100) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {}
        }

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

        if (preg_match('/^(\d{1,2})[-\/]([A-Za-z]{3})[-\/](\d{2})$/', $raw, $m)) {
            $d = \DateTime::createFromFormat('j-M-Y', $m[1] . '-' . $m[2] . '-20' . $m[3]);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }

        if (preg_match('/^(\d{1,2})\/(\d{2})\/(\d{2})$/', $raw, $m)) {
            $d = \DateTime::createFromFormat('d/m/Y', $m[1] . '/' . $m[2] . '/20' . $m[3]);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }

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

    private function parseAmount(mixed $raw): ?int
    {
        if (empty($raw)) {
            return null;
        }

        $clean = preg_replace('/[^0-9.]/', '', (string) $raw);

        return is_numeric($clean) ? (int) round((float) $clean) : null;
    }
}