<?php

namespace App\Http\Controllers\Admin\Import;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Revolution\Google\Sheets\Facades\Sheets;
use App\Jobs\ImportEnquiriesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Prologue\Alerts\Facades\Alert;

class SalesImportController extends Controller
{

    // Sales Import Page
    public function sales()
    {
        return view('admin.import.sales');
    }


    public function importEnquiries(Request $request)
    {
        if (! backpack_user()->can('SLS_ENQR_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import enquiries.');
        }

        if (! $request->hasFile('excel_file') || ! in_array($request->file('excel_file')->getClientOriginalExtension(), ['xlsx', 'xls'])) {
            Alert::error('Invalid or missing file! Only Excel files (.xlsx, .xls) allowed')->flash();

            return redirect()->back();
        }

        $absolutePath = Storage::disk('local')->path($request->file('excel_file')->store('imports', 'local'));
        $importLogId = DB::table('xlr8_crm_import_logs')->insertGetId([
            'file_name' => $request->file('excel_file')->getClientOriginalName(),
            'stored_path' => $absolutePath,
            'status' => 'queued',
            'created_by' => backpack_user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ImportEnquiriesJob::dispatch($importLogId, $absolutePath);
        Alert::success("File uploaded and queued for processing (Import #{$importLogId}).")->flash();

        return redirect()->back();
    }

    public function importStatus($id)
    {
        if (! backpack_user()->can('SLS_ENQR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view enquiries.');
        }

        if (! $log = DB::table('xlr8_crm_import_logs')->where('id', $id)->first()) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'id' => $log->id,
            'status' => $log->status,
            'total_rows' => $log->total_rows,
            'processed_rows' => $log->processed_rows,
            'percent' => $log->total_rows > 0 ? round(($log->processed_rows / $log->total_rows) * 100, 1) : 0,
            'stats' => $log->stats ? json_decode($log->stats, true) : null,
            'error_message' => $log->error_message,
        ]);
    }

    public function importHistory()
    {
        if (! backpack_user()->can('SLS_ENQR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view enquiries.');
        }

        return response()->json(DB::table('xlr8_crm_import_logs')->orderByDesc('id')->limit(5)->get());
    }


    public function insimport()
    {
        if (! backpack_user()->can('INS_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import insurance policies.');
        }

        $spreadsheetId = '1n4aJilDZo1WtY0QPYOp9dzQqKHyOJFm6qfA3gewADm4';

        $sheetGids = [
            '0',
            '324376505',
        ];

        $columnMaps = [

            '0' => [
                'pol_no' => 'Policy No.',
                'pol_date' => 'Created Date',
                'policy_type' => 'Policy Type',
                'pol_tenure' => 'Policy Tenure',
                'insured_name' => 'Insured Name',
                'mob_no' => 'Customer Phone',
                'rgn_no' => 'Vehicle Reg No.',
                'yom' => 'YOM',
                'ncb' => 'NCB%',
                'vh_class' => 'Vehicle Class',
                'pol_effective_date' => 'Policy Effective Date',
                'pol_expiry_date' => 'Policy Expiry Date',
                'product_type' => 'Product Type',
                'model' => 'Model',
                'vh_body_type' => 'Vehicle Body Type',
                'fuel' => 'Fuel Type',
                'vin' => 'VIN',
                'engine_no' => 'Engine No.',
                'created_date' => 'Created Date',
                'payment_generation' => 'Payment Generated',
                'payment_no' => 'Payment No.',
                'od_discount' => 'OD Discount',
                'total_idv' => 'Total IDV',
                'addon_prem_a' => 'Add On Premium A',
                'netod_prem_a' => 'Net OD Premium A',
                'net_prem' => 'Net Premium',
                'imt23' => 'IMT 23',
                'gross_prem' => 'Gross Premium',
                'prev_pol_no' => 'Previous Policy No.',
                'prev_insurance_company' => 'Previous Insurance Company',
                'own_dmg_cover_start' => 'Period of Own Damage Cover start',
                'own_dmg_cover_end' => 'Period of Own Damage Cover end',
                'liability_cover_start' => 'Period of Liability Cover start',
                'liability_cover_end' => 'Period of Liability Cover end',
                'cpa_cover_start' => 'Period of CPA Cover start',
                'cpa_cover_end' => 'Period of CPA Cover end',
                '64vb_status' => '64VB Status',
                'bundle_addon' => 'Bundle Addon',
                'insurer_code' => 'Insurance Company',
            ],

            '324376505' => [
                'pol_no' => 'Policy Number',
                'insured_name' => 'Policy Holder Name',
                'created_date' => 'Transaction Date',
                'pol_effective_date' => 'Effective Start Date',
                'pol_expiry_date' => 'Policy Expiry Date',
                'net_prem' => 'Premium',
            ],

        ];

        $totalImported = 0;
        $totalSkipped = 0;
        $now = now();

        $allInsurers = DB::table('xlr8_booking_insurer')
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->get(['id', 'name', 'short_name'])
            ->toArray();

        foreach ($sheetGids as $gid) {

            Log::info("Insurance Import: Starting sheet GID={$gid}");

            $gscolarr = $columnMaps[$gid] ?? [];

            if (empty($gscolarr)) {
                Log::warning("Insurance Import: No column map defined for GID={$gid}, skipping.");

                continue;
            }

            $values = Sheets::spreadsheet($spreadsheetId)
                ->sheetById($gid)
                ->all();

            if (empty($values) || count($values) < 2) {
                Log::warning("Insurance Import: No data in GID={$gid}, skipping.");

                continue;
            }

            $gs_pos = array_fill_keys(array_keys($gscolarr), null);

            foreach ($values[0] as $key => $header) {
                $header = trim($header);
                foreach ($gscolarr as $dbField => $expectedHeader) {
                    if (stripos($header, $expectedHeader) !== false) {
                        $gs_pos[$dbField] = $key;
                        break;
                    }
                }
            }

            Log::info("Insurance Import GID={$gid}: Column mapping", ['gs_pos' => $gs_pos]);

            $imported = 0;
            $skipped = 0;

            foreach (array_slice($values, 1) as $rowIndex => $row) {

                $actualRow = $rowIndex + 2;

                $polNo = trim($row[$gs_pos['pol_no']] ?? '');
                $insuredName = trim($row[$gs_pos['insured_name']] ?? '');

                if (empty($polNo) && empty($insuredName)) {
                    $skipped++;

                    continue;
                }

                if ($gid === '324376505') {

                    $insertData = [
                        'insurer_code' => 'NIA',
                        'pol_no' => substr($polNo, 0, 50),
                        'insured_name' => substr($insuredName, 0, 50),
                        'created_date' => $this->parseDate($row[$gs_pos['created_date']] ?? null, $gid, $actualRow),
                        'pol_effective_date' => $this->parseDate($row[$gs_pos['pol_effective_date']] ?? null, $gid, $actualRow),
                        'pol_expiry_date' => $this->parseDate($row[$gs_pos['pol_expiry_date']] ?? null, $gid, $actualRow),
                        'net_prem' => is_numeric($row[$gs_pos['net_prem']] ?? null)
                            ? (int) $row[$gs_pos['net_prem']] : null,
                        'status' => 1,
                        'created_at' => $now,
                        'created_by' => auth()->id() ?? 1,
                    ];
                } else {

                    $rawInsurerName = trim($row[$gs_pos['insurer_code']] ?? '');
                    $insurerCode = $this->resolveInsurerCode($rawInsurerName, $allInsurers, $gid, $actualRow);

                    $polDate = $this->parseDate($row[$gs_pos['pol_date']] ?? null, $gid, $actualRow);
                    $polEffectiveDate = $this->parseDate($row[$gs_pos['pol_effective_date']] ?? null, $gid, $actualRow);
                    $polExpiryDate = $this->parseDate($row[$gs_pos['pol_expiry_date']] ?? null, $gid, $actualRow);
                    $createdDate = $this->parseDate($row[$gs_pos['created_date']] ?? null, $gid, $actualRow);
                    $ownDmgStart = $this->parseDate($row[$gs_pos['own_dmg_cover_start']] ?? null, $gid, $actualRow);
                    $ownDmgEnd = $this->parseDate($row[$gs_pos['own_dmg_cover_end']] ?? null, $gid, $actualRow);
                    $liabStart = $this->parseDate($row[$gs_pos['liability_cover_start']] ?? null, $gid, $actualRow);
                    $liabEnd = $this->parseDate($row[$gs_pos['liability_cover_end']] ?? null, $gid, $actualRow);
                    $cpaStart = $this->parseDate($row[$gs_pos['cpa_cover_start']] ?? null, $gid, $actualRow);
                    $cpaEnd = $this->parseDate($row[$gs_pos['cpa_cover_end']] ?? null, $gid, $actualRow);

                    $insertData = [
                        'insurer_code' => $insurerCode,
                        'pol_no' => substr($polNo, 0, 50),
                        'pol_date' => $polDate,
                        'policy_type' => substr(trim($row[$gs_pos['policy_type']] ?? ''), 0, 50),
                        'pol_tenure' => is_numeric($row[$gs_pos['pol_tenure']] ?? null)
                            ? (int) $row[$gs_pos['pol_tenure']] : null,
                        'insured_name' => substr($insuredName, 0, 50),
                        'mob_no' => substr(trim($row[$gs_pos['mob_no']] ?? ''), 0, 50),
                        'rgn_no' => substr(trim($row[$gs_pos['rgn_no']] ?? ''), 0, 50),
                        'yom' => is_numeric($row[$gs_pos['yom']] ?? null)
                            ? (int) $row[$gs_pos['yom']] : null,
                        'ncb' => is_numeric($row[$gs_pos['ncb']] ?? null)
                            ? (int) $row[$gs_pos['ncb']] : null,
                        'vh_class' => substr(trim($row[$gs_pos['vh_class']] ?? ''), 0, 50),
                        'pol_effective_date' => $polEffectiveDate,
                        'pol_expiry_date' => $polExpiryDate,
                        'product_type' => substr(trim($row[$gs_pos['product_type']] ?? ''), 0, 50),
                        'model' => substr(trim($row[$gs_pos['model']] ?? ''), 0, 50),
                        'vh_body_type' => substr(trim($row[$gs_pos['vh_body_type']] ?? ''), 0, 50),
                        'fuel' => substr(trim($row[$gs_pos['fuel']] ?? ''), 0, 50),
                        'vin' => substr(trim($row[$gs_pos['vin']] ?? ''), 0, 50),
                        'engine_no' => substr(trim($row[$gs_pos['engine_no']] ?? ''), 0, 50),
                        'created_date' => $createdDate,
                        'payment_generation' => substr(trim($row[$gs_pos['payment_generation']] ?? ''), 0, 50),
                        'payment_no' => substr(trim($row[$gs_pos['payment_no']] ?? ''), 0, 50),
                        'od_discount' => is_numeric($row[$gs_pos['od_discount']] ?? null)
                            ? (int) $row[$gs_pos['od_discount']] : null,
                        'total_idv' => is_numeric($row[$gs_pos['total_idv']] ?? null)
                            ? (int) $row[$gs_pos['total_idv']] : null,
                        'addon_prem_a' => is_numeric($row[$gs_pos['addon_prem_a']] ?? null)
                            ? (int) $row[$gs_pos['addon_prem_a']] : null,
                        'netod_prem_a' => is_numeric($row[$gs_pos['netod_prem_a']] ?? null)
                            ? (int) $row[$gs_pos['netod_prem_a']] : null,
                        'net_prem' => is_numeric($row[$gs_pos['net_prem']] ?? null)
                            ? (int) $row[$gs_pos['net_prem']] : null,
                        'imt23' => is_numeric($row[$gs_pos['imt23']] ?? null)
                            ? (int) $row[$gs_pos['imt23']] : null,
                        'gross_prem' => is_numeric($row[$gs_pos['gross_prem']] ?? null)
                            ? (int) $row[$gs_pos['gross_prem']] : null,
                        'prev_pol_no' => is_numeric($row[$gs_pos['prev_pol_no']] ?? null)
                            ? (int) $row[$gs_pos['prev_pol_no']] : null,
                        'prev_insurance_company' => substr(trim($row[$gs_pos['prev_insurance_company']] ?? ''), 0, 50),
                        'own_dmg_cover_start' => $ownDmgStart,
                        'own_dmg_cover_end' => $ownDmgEnd,
                        'liability_cover_start' => $liabStart,
                        'liability_cover_end' => $liabEnd,
                        'cpa_cover_start' => $cpaStart,
                        'cpa_cover_end' => $cpaEnd,
                        '64vb_status' => substr(trim($row[$gs_pos['64vb_status']] ?? ''), 0, 50),
                        'bundle_addon' => substr(trim($row[$gs_pos['bundle_addon']] ?? ''), 0, 50),
                        'status' => 1,
                        'created_at' => $now,
                        'created_by' => auth()->id() ?? 1,
                    ];
                }

                try {
                    DB::table('xlr8_booking_insurance')->insert($insertData);
                    $imported++;
                } catch (\Exception $e) {
                    Log::error("Insurance GID={$gid} Row {$actualRow}: Insert failed", [
                        'error' => $e->getMessage(),
                        'insertData' => $insertData,
                    ]);
                    $skipped++;
                }
            }

            Log::info("Insurance Import: GID={$gid} done", [
                'imported' => $imported,
                'skipped' => $skipped,
            ]);

            $totalImported += $imported;
            $totalSkipped += $skipped;
        }

        Log::info('Insurance Import: All sheets done', [
            'total_imported' => $totalImported,
            'total_skipped' => $totalSkipped,
        ]);

        $message = "Insurance Import completed! Imported: {$totalImported}, Skipped: {$totalSkipped}";

        if ($totalImported > 0) {
            return redirect()->back()->with('success', $message);
        } else {
            return redirect()->back()->with('warning', $message);
        }
    }
    public function finimport()
    {
        if (! backpack_user()->can('FIN_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import finance statements.');
        }

        $spreadsheetId = '1148dQQ35IOZNwLVeJ-cfXZpnhqFeJu5i5KTaSeDOWsc';

        $sheetGids = [

            '308746320',
            '172916626',
            '2043019899',
            '1564512315',
            '785816274',
            '2139648103',

        ];

        $gscolarr = [
            'financier_code' => 'Financier',
            'trans_date' => 'Transaction Date',
            'trans_description' => 'Transaction Description',
            'trans_type' => 'Transaction Type',
            'do_no' => 'DO NO',
            'debit_amount' => 'Debit Amount',
            'credit_amount' => 'Credit Amount',
            'running_balance' => 'Running Balance',
        ];

        $totalImported = 0;
        $totalSkipped = 0;
        $now = now();

        foreach ($sheetGids as $gid) {

            Log::info("Finance Import: Starting sheet GID={$gid}");

            $values = Sheets::spreadsheet($spreadsheetId)
                ->sheetById($gid)
                ->all();

            if (empty($values) || count($values) < 2) {
                Log::warning("Finance Import: No data in GID={$gid}, skipping.");

                continue;
            }

            $gs_pos = array_fill_keys(array_keys($gscolarr), 0);

            foreach ($values[0] as $key => $header) {
                $header = trim($header);
                foreach ($gscolarr as $dbField => $expectedHeader) {
                    if (stripos($header, $expectedHeader) !== false) {
                        $gs_pos[$dbField] = $key;
                        break;
                    }
                }
            }

            $imported = 0;
            $skipped = 0;

            foreach (array_slice($values, 1) as $rowIndex => $row) {

                $actualRow = $rowIndex + 2;

                if (
                    empty($row[$gs_pos['financier_code'] ?? 0]) &&
                    empty($row[$gs_pos['trans_description'] ?? 0])
                ) {
                    $skipped++;

                    continue;
                }

                $transDate = $this->parseDate($row[$gs_pos['trans_date']] ?? null, $gid, $actualRow);

                $insertData = [
                    'financier_code' => trim($row[$gs_pos['financier_code']] ?? 'UNKNOWN'),
                    'trans_date' => $transDate,
                    'trans_description' => substr(trim($row[$gs_pos['trans_description']] ?? ''), 0, 150),
                    'trans_type' => substr(trim($row[$gs_pos['trans_type']] ?? 'O'), 0, 5),
                    'do_no' => substr(trim($row[$gs_pos['do_no']] ?? ''), 0, 150),
                    'debit_amount' => is_numeric($row[$gs_pos['debit_amount']] ?? null)
                        ? round((float) $row[$gs_pos['debit_amount']], 2) : null,
                    'credit_amount' => is_numeric($row[$gs_pos['credit_amount']] ?? null)
                        ? round((float) $row[$gs_pos['credit_amount']], 2) : null,
                    'running_balance' => is_numeric($row[$gs_pos['running_balance']] ?? null)
                        ? -round((float) $row[$gs_pos['running_balance']], 2) : null,
                    'status' => 1,
                    'created_at' => $now,
                    'created_by' => auth()->id() ?? 1,
                ];

                try {
                    DB::table('xlr8_financer_statement')->insert($insertData);
                    $imported++;
                } catch (\Exception $e) {
                    Log::error("GID={$gid} Row {$actualRow}: Insert failed", [
                        'error' => $e->getMessage(),
                        'insertData' => $insertData,
                    ]);
                    $skipped++;
                }
            }

            Log::info("Finance Import: GID={$gid} done", [
                'imported' => $imported,
                'skipped' => $skipped,
            ]);

            $totalImported += $imported;
            $totalSkipped += $skipped;
        }

        Log::info('Finance Import: All sheets done', [
            'total_imported' => $totalImported,
            'total_skipped' => $totalSkipped,
        ]);

        $message = "Import completed! Imported: {$totalImported}, Skipped: {$totalSkipped}";

        if ($totalImported > 0) {
            return redirect()->back()->with('success', $message);
        } else {
            return redirect()->back()->with('warning', $message);
        }
    }
    public function rtoimport()
    {
        if (! backpack_user()->can('RTO_IMPORT')) {
            abort(403, 'Unauthorized. You do not have permission to import RTO data.');
        }

        $sheetNames = [
            'RTO Manual' => ['type' => 'rto_manual', 'safe' => 'RTO Manual'],
            'HSRP BKN' => ['type' => 'hsrp_bkn',  'safe' => 'HSRP BKN'],
            'HSRP CHR' => ['type' => 'hsrp_chr',  'safe' => 'HSRP CHR'],
            'Vaahan (TC0056)' => ['type' => 'vaahan',    'safe' => 'Vaahan (TC0056)'],
            'TC0281' => ['type' => 'vaahan',    'safe' => "'TC0281'"],
        ];

        $saleTypeMap = [
            'within state' => 1,
            'outside state' => 2,
        ];

        $permitMap = [
            'private - u/c (4 wheeler)' => 1,
            'private - bh (4 wheeler)' => 2,
            'private - ev (4 wheeler)' => 3,
            'goods - g (4 wheeler)' => 4,
            'goods - g 3 ton+ (4 wheeler)' => 5,
            'goods - g (3 wheeler)' => 6,
            'goods - g ev (3 wheeler)' => 7,
            'taxi - t (4 wheeler)' => 8,
            'passenger - p (3 wheeler)' => 9,
            'passenger - p ev (3 wheeler)' => 10,
            'ambulance (misc.)' => 11,
        ];

        $bodyTypeMap = [
            'complete' => 1,
            'cbc' => 2,
        ];

        $rgnTypeMap = [
            'trc only' => 1,
            'tax only' => 2,
            'trc + tax' => 3,
        ];

        $rgnNoTypeMap = [
            'regular' => 1,
            'bh' => 2,
            'special' => 3,
        ];

        $bookingByChassis = DB::table('xlr8_booking_master')
            ->whereNotNull('chassis_no')
            ->pluck('id', 'chassis_no')
            ->toArray();

        $bookingByOtf = DB::table('xlr8_booking_master')
            ->whereNotNull('dms_otf')
            ->pluck('id', 'dms_otf')
            ->toArray();

        $rtoRules = DB::table('xlr8_booking_rto_rule')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get(['id', 'pending_at', 'rgn_no']);

        $ruleMap = [];
        foreach ($rtoRules as $rule) {
            $paKey = strtolower(trim((string) $rule->pending_at));
            $rgnRaw = strtolower(trim((string) $rule->rgn_no));

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
                'dms_otf' => 'OTF No.',
                'chassis_no' => 'Chassis No.',
                'sale_type' => 'Sale Type',
                'permit' => 'Permit',
                'body_type' => 'Body Type',
                'rgn_type' => 'Registration Type',
                'rgn_no_type' => 'Registration No. Type',
                'app_no' => 'RTO Application Number',
                'trc_no' => 'TRC Number',
                'trc_amount' => 'TRC Amount',
                'trc_trans_date' => 'TRC Transaction Date',
                'trc_payment_no' => 'TRC Payment Reference No.',
                'tax_amount' => 'Tax Amount',
                'tax_trans_date' => 'Tax Transaction Date',
                'tax_payment_bank_ref_no' => 'Tax Payment Reference No.',
                'vh_rgn_no' => 'Registration No.',
            ],

            'hsrp_bkn' => [
                'vh_rgn_no' => 'vehicleregno',
                'chassis_no' => 'ChassisNo',
                'order_date' => 'OrderDate',
                'hsrp_front_lasercode' => 'hsrp_front_lasercode',
                'hsrp_rear_lasercode' => 'hsrp_rear_lasercode',
                'prod_status' => 'Productionstatus',
                'recieving_status' => 'ReceivingStatus',
                'dispatch_date' => 'DispatchDate',
                'order_delivery_date' => 'OrderDeliveryDate',
                'affixation_date' => 'Affixationdate',
            ],

            'hsrp_chr' => [
                'vh_rgn_no' => 'vehicleregno',
                'chassis_no' => 'ChassisNo',
                'order_date' => 'OrderDate',
                'hsrp_front_lasercode' => 'hsrp_front_lasercode',
                'hsrp_rear_lasercode' => 'hsrp_rear_lasercode',
                'prod_status' => 'Productionstatus',
                'recieving_status' => 'ReceivingStatus',
                'dispatch_date' => 'DispatchDate',
                'order_delivery_date' => 'OrderDeliveryDate',
                'affixation_date' => 'Affixationdate',
            ],

            'vaahan' => [
                'app_no' => 'Application No.',
                'vh_rgn_no' => 'Registration No.',
                'purpose' => 'Purpose',
                'pending_at' => 'Pending At',
            ],
        ];

        $totalImported = 0;
        $totalSkipped = 0;
        $now = now();
        $userId = auth()->id() ?? 1;

        foreach ($sheetNames as $sheetName => $sheetConfig) {
            $sheetType = $sheetConfig['type'];
            $sheetSafe = $sheetConfig['safe'];

            Log::info("RTO Import: Starting sheet=[{$sheetName}] type={$sheetType}");

            $values = Sheets::spreadsheet($spreadsheetId)
                ->sheet($sheetSafe)
                ->all();

            if (empty($values) || count($values) < 2) {
                Log::warning("RTO Import: Sheet=[{$sheetName}] is empty or has no data rows, skipping.");

                continue;
            }

            $gscolarr = $columnMaps[$sheetType] ?? [];
            $gs_pos = array_fill_keys(array_keys($gscolarr), null);

            foreach ($values[0] as $colIdx => $header) {
                $header = trim((string) $header);
                foreach ($gscolarr as $dbField => $expectedHeader) {
                    if ($gs_pos[$dbField] === null && stripos($header, $expectedHeader) !== false) {
                        $gs_pos[$dbField] = $colIdx;
                        break;
                    }
                }
            }

            Log::info("RTO Import [{$sheetName}]: Column positions resolved", ['gs_pos' => $gs_pos]);

            $imported = 0;
            $skipped = 0;

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
                    $rawRgnNo = trim((string) $get('vh_rgn_no'));

                    $rgnNoBucket = match (true) {
                        $rawRgnNo === '' || strtoupper($rawRgnNo) === 'NEW' => 'blank',
                        strtoupper($rawRgnNo) === 'TRC' => 'trc',
                        default => 'rgn',
                    };

                    $pendatId = $this->resolveRuleId($rawPendingAt, $rgnNoBucket, $ruleMap, $sheetName, $actualRow);

                    $affected = DB::table('xlr8_booking_rto')
                        ->where('app_no', $appNo)
                        ->whereNull('deleted_at')
                        ->update([
                            'pendat_id' => $pendatId,
                            'purpose' => substr(trim((string) $get('purpose')), 0, 100),
                            'updated_at' => $now,
                            'updated_by' => $userId,
                        ]);

                    if ($affected === 0) {
                        Log::warning("RTO Import [{$sheetName}] Row {$actualRow}: app_no not found in xlr8_booking_rto", [
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
                        'bid' => $bid,
                        'chassis_no' => substr($chassis, 0, 20),
                        'dms_otf' => substr(trim((string) $get('dms_otf')), 0, 100),
                        'sale_type' => $this->mapEnum($get('sale_type'), $saleTypeMap),
                        'permit' => $this->mapEnum($get('permit'), $permitMap),
                        'body_type' => $this->mapEnum($get('body_type'), $bodyTypeMap),
                        'rgn_type' => $this->mapEnum($get('rgn_type'), $rgnTypeMap),
                        'rgn_no_type' => $this->mapEnum($get('rgn_no_type'), $rgnNoTypeMap),
                        'app_no' => substr(trim((string) $get('app_no')), 0, 50),
                        'trc_no' => substr(trim((string) $get('trc_no')), 0, 50),
                        'trc_amount' => $this->parseAmount($get('trc_amount')),
                        'trc_trans_date' => $this->parseDate($get('trc_trans_date'), $sheetName, $actualRow),
                        'trc_payment_no' => substr(trim((string) $get('trc_payment_no')), 0, 50),
                        'tax_amount' => $this->parseAmount($get('tax_amount')),
                        'tax_trans_date' => $this->parseDate($get('tax_trans_date'), $sheetName, $actualRow),
                        'tax_payment_bank_ref_no' => substr(trim((string) $get('tax_payment_bank_ref_no')), 0, 50),
                        'vh_rgn_no' => substr(trim((string) $get('vh_rgn_no')), 0, 50),
                        'trade_used' => 0,
                        'status' => 1,
                        'updated_at' => $now,
                        'updated_by' => $userId,
                    ];
                } elseif (in_array($sheetType, ['hsrp_bkn', 'hsrp_chr'])) {

                    $data = [
                        'bid' => $bid,
                        'chassis_no' => substr($chassis, 0, 20),
                        'vh_rgn_no' => substr(trim((string) $get('vh_rgn_no')), 0, 50),
                        'hsrp_location' => ($sheetType === 'hsrp_bkn') ? 'BKN' : 'CHR',
                        'order_date' => $this->parseDate($get('order_date'), $sheetName, $actualRow),
                        'hsrp_front_lasercode' => substr(trim((string) $get('hsrp_front_lasercode')), 0, 50),
                        'hsrp_rear_lasercode' => substr(trim((string) $get('hsrp_rear_lasercode')), 0, 50),
                        'prod_status' => substr(trim((string) $get('prod_status')), 0, 10),
                        'recieving_status' => substr(trim((string) $get('recieving_status')), 0, 10),
                        'dispatch_date' => $this->parseDate($get('dispatch_date'), $sheetName, $actualRow),
                        'order_delivery_date' => $this->parseDate($get('order_delivery_date'), $sheetName, $actualRow),
                        'affixation_date' => $this->parseDate($get('affixation_date'), $sheetName, $actualRow),
                        'trade_used' => 0,
                        'sale_type' => null,
                        'permit' => null,
                        'body_type' => null,
                        'rgn_type' => null,
                        'rgn_no_type' => null,
                        'status' => 1,
                        'updated_at' => $now,
                        'updated_by' => $userId,
                    ];
                }

                try {
                    DB::table('xlr8_booking_rto')->updateOrInsert(
                        ['chassis_no' => $data['chassis_no']],
                        array_merge($data, [
                            'created_at' => $now,
                            'created_by' => $userId,
                        ])
                    );
                    $imported++;
                } catch (\Exception $e) {
                    Log::error("RTO Import [{$sheetName}] Row {$actualRow}: DB write failed", [
                        'error' => $e->getMessage(),
                        'chassis' => $chassis,
                    ]);
                    $skipped++;
                }
            }

            Log::info("RTO Import: [{$sheetName}] done", [
                'imported' => $imported,
                'skipped' => $skipped,
            ]);

            $totalImported += $imported;
            $totalSkipped += $skipped;
        }

        Log::info('RTO Import: All sheets done', [
            'total_imported' => $totalImported,
            'total_skipped' => $totalSkipped,
        ]);

        $message = "RTO Import completed! Imported/Updated: {$totalImported}, Skipped: {$totalSkipped}";

        return $totalImported > 0
            ? redirect()->back()->with('success', $message)
            : redirect()->back()->with('warning', $message);
    }


    private function resolveBid(
        string $chassis,
        string $otf,
        array $bookingByChassis,
        array $bookingByOtf,
        mixed $gid,
        int $row
    ): ?int {
        if (! empty($chassis) && isset($bookingByChassis[$chassis])) {
            return (int) $bookingByChassis[$chassis];
        }

        if (! empty($otf) && isset($bookingByOtf[$otf])) {
            return (int) $bookingByOtf[$otf];
        }

        Log::warning("RTO Import GID={$gid} Row {$row}: bid not resolved", [
            'chassis' => $chassis,
            'otf' => $otf,
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
        array $ruleMap,
        mixed $gid,
        int $row
    ): ?int {
        if (empty($pendingAt)) {
            return null;
        }

        $paKey = strtolower(trim($pendingAt));

        if (! isset($ruleMap[$paKey])) {
            Log::warning('RTO Import resolveRuleId: No rule found for pending_at', [
                'pending_at' => $pendingAt,
                'gid' => $gid,
                'row' => $row,
            ]);

            return null;
        }

        $buckets = $ruleMap[$paKey];

        if (isset($buckets[$rgnNoBucket])) {
            return $buckets[$rgnNoBucket];
        }

        $fallback = ($rgnNoBucket === 'blank') ? 'rgn' : 'blank';
        if (isset($buckets[$fallback])) {
            Log::info('RTO Import resolveRuleId: Used fallback bucket', [
                'pending_at' => $pendingAt,
                'requested_bucket' => $rgnNoBucket,
                'fallback_bucket' => $fallback,
                'gid' => $gid,
                'row' => $row,
            ]);

            return $buckets[$fallback];
        }

        $firstId = reset($buckets);
        Log::warning('RTO Import resolveRuleId: No bucket match, using first available rule', [
            'pending_at' => $pendingAt,
            'bucket' => $rgnNoBucket,
            'used_id' => $firstId,
            'gid' => $gid,
            'row' => $row,
        ]);

        return $firstId ?: null;
    }


    private function parseAmount(mixed $raw): ?int
    {
        if (empty($raw)) {
            return null;
        }

        $clean = preg_replace('/[^0-9.]/', '', (string) $raw);

        return is_numeric($clean) ? (int) round((float) $clean) : null;
    }

    private function resolveInsurerCode(string $raw, array $allInsurers, $gid = null, $row = null): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $rawLower = strtolower($raw);

        foreach ($allInsurers as $insurer) {
            if (strtolower($insurer->name) === $rawLower) {
                return $insurer->short_name;
            }
        }

        foreach ($allInsurers as $insurer) {
            if (stripos($insurer->name, $raw) !== false) {
                return $insurer->short_name;
            }
        }

        foreach ($allInsurers as $insurer) {
            if (stripos($raw, $insurer->name) !== false) {
                return $insurer->short_name;
            }
        }

        Log::warning('Insurance resolveInsurerCode: No match found', [
            'raw' => $raw,
            'gid' => $gid,
            'row' => $row,
        ]);

        return null;
    }

    private function parseDate($raw, $gid = null, $row = null): ?string
    {
        if (empty($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);

        $ignoredValues = ['na', 'n/a', 'nil', '-', '--', 'none', '31-dec-1899'];
        if (in_array(strtolower($raw), $ignoredValues)) {
            return null;
        }

        if (is_numeric($raw)) {
            try {
                $date = Date::excelToDateTimeObject((float) $raw);
                $year = (int) $date->format('Y');
                if ($year >= 2000 && $year <= 2100) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
            }
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

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $raw);
            if ($date !== false) {
                $year = (int) $date->format('Y');
                if ($year >= 2000 && $year <= 2100) {
                    return $date->format('Y-m-d');
                }
            }
        }

        if (preg_match('/^(\d{1,2})[-\/]([A-Za-z]{3})[-\/](\d{2})$/', $raw, $m)) {
            $date = \DateTime::createFromFormat('j-M-Y', $m[1] . '-' . $m[2] . '-20' . $m[3]);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        if (preg_match('/^(\d{1,2})\/(\d{2})\/(\d{2})$/', $raw, $m)) {
            $date = \DateTime::createFromFormat('d/m/Y', $m[1] . '/' . $m[2] . '/20' . $m[3]);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        try {
            $date = Carbon::parse($raw);
            $year = (int) $date->format('Y');
            if ($year >= 2000 && $year <= 2100) {
                return $date->format('Y-m-d');
            }
        } catch (\Exception $e) {
        }

        Log::warning('Insurance parseDate: Could not parse', [
            'raw' => $raw,
            'gid' => $gid,
            'row' => $row,
        ]);

        return null;
    }
}
