<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\Traits\ScopedCrud;
use Revolution\Google\Sheets\Facades\Sheets;

class InsuranceCrudController extends CrudController
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
        // ================== CONFIGURATION ==================
        $spreadsheetId = '1n4aJilDZo1WtY0QPYOp9dzQqKHyOJFm6qfA3gewADm4';

        // ✅ Sare GIDs yahan add karo
        $sheetGids = [
            '0',           // Sheet 1 — full mapping
            '324376505',   // NIA sheet — partial mapping (6 columns only)
        ];

        // ================== PER-SHEET COLUMN MAPS ==================
        // Each GID has its own: DB column => Google Sheet header (case-insensitive partial match)

        $columnMaps = [

            // ---------- Sheet 1 (gid=0): full mapping ----------
            '0' => [
                'pol_no'                => 'Policy No',
                'pol_date'              => 'Created Date',
                'policy_type'           => 'Policy Type',
                'pol_tenure'            => 'Policy Tenure',
                'insured_name'          => 'Insured Name',
                'mob_no'                => 'Customer Phone',
                'rgn_no'                => 'Vehicle Reg No',
                'yom'                   => 'YOM',
                'ncb'                   => 'NCB%',
                'vh_class'              => 'Vehicle Class',
                'pol_effective_date'    => 'Policy Effective Date',
                'pol_expiry_date'       => 'Policy Expiry Date',
                'product_type'          => 'Product Type',
                'model'                 => 'Model',
                'vh_body_type'          => 'Vehicle Body Type',
                'fuel'                  => 'Fuel Type',
                'vin'                   => 'VIN',
                'engine_no'             => 'Engine No',
                'created_date'          => 'Created Date',
                'payment_generation'    => 'Payment Generated',
                'payment_no'            => 'Payment No.',
                'od_discount'           => 'OD Discount',
                'total_idv'             => 'Total IDV',
                'addon_prem_a'          => 'Add On Premium A',
                'netod_prem_a'          => 'Net OD Premium A',
                'net_prem'              => 'Net Premium',
                'imt23'                 => 'IMT 23',
                'gross_prem'            => 'Gross Premium',
                'prev_pol_no'           => 'Previous Policy No',
                'prev_insurance_company'=> 'Previous Insurance Company',
                'own_dmg_cover_start'   => 'Period of Own Damage Cover start',
                'own_dmg_cover_end'     => 'Period of Own Damage Cover end',
                'liability_cover_start' => 'Period of Liability Cover start',
                'liability_cover_end'   => 'Period of Liability Cover end',
                'cpa_cover_start'       => 'Period of CPA Cover start',
                'cpa_cover_end'         => 'Period of CPA Cover end',
                '64vb_status'           => '64VB Status',
                'bundle_addon'          => 'Bundle Addon',
                'insurer_code'          => 'Insurance Company',
            ],

            // ---------- NIA sheet (gid=324376505): 6 columns only ----------
            '324376505' => [
                'pol_no'             => 'Policy Number',
                'insured_name'       => 'Policy Holder Name',
                'created_date'       => 'Transaction Date',
                'pol_effective_date' => 'Effective Start Date',
                'pol_expiry_date'    => 'Policy Expiry Date',
                'net_prem'           => 'Premium',
            ],

        ];

        $totalImported = 0;
        $totalSkipped  = 0;
        $now           = now();

        // ================== LOAD INSURERS ONCE ==================
        // xlr8_booking_insurer se saare records ek baar load karo
        // Format: [ ['id'=>1, 'name'=>'Acko General...', 'short_name'=>'Acko'], ... ]
        $allInsurers = \DB::table('xlr8_booking_insurer')
                          ->whereNull('deleted_at')
                          ->where('status', 1)
                          ->get(['id', 'name', 'short_name'])
                          ->toArray();

        foreach ($sheetGids as $gid) {

            \Log::info("Insurance Import: Starting sheet GID={$gid}");

            $gscolarr = $columnMaps[$gid] ?? [];

            if (empty($gscolarr)) {
                \Log::warning("Insurance Import: No column map defined for GID={$gid}, skipping.");
                continue;
            }

            $values = Sheets::spreadsheet($spreadsheetId)
                            ->sheetById($gid)
                            ->all();

            if (empty($values) || count($values) < 2) {
                \Log::warning("Insurance Import: No data in GID={$gid}, skipping.");
                continue;
            }

            // ================== COLUMN MAPPING ==================
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

            \Log::info("Insurance Import GID={$gid}: Column mapping", ['gs_pos' => $gs_pos]);

            $imported = 0;
            $skipped  = 0;

            foreach (array_slice($values, 1) as $rowIndex => $row) {

                $actualRow = $rowIndex + 2;

                // Empty row skip — pol_no aur insured_name dono empty ho to skip
                $polNo       = trim($row[$gs_pos['pol_no']] ?? '');
                $insuredName = trim($row[$gs_pos['insured_name']] ?? '');

                if (empty($polNo) && empty($insuredName)) {
                    $skipped++;
                    continue;
                }

                if ($gid === '324376505') {
                    // ================== NIA SHEET INSERT ==================
                    // NIA = 'New India Assurance Co. Ltd.' — short_name hardcoded
                    $insertData = [
                        'insurer_code'       => 'NIA',
                        'pol_no'             => substr($polNo, 0, 50),
                        'insured_name'       => substr($insuredName, 0, 50),
                        'created_date'       => $this->parseDate($row[$gs_pos['created_date']] ?? null, $gid, $actualRow),
                        'pol_effective_date' => $this->parseDate($row[$gs_pos['pol_effective_date']] ?? null, $gid, $actualRow),
                        'pol_expiry_date'    => $this->parseDate($row[$gs_pos['pol_expiry_date']] ?? null, $gid, $actualRow),
                        'net_prem'           => is_numeric($row[$gs_pos['net_prem']] ?? null)
                                                    ? (int)$row[$gs_pos['net_prem']] : null,
                        'status'             => 1,
                        'created_at'         => $now,
                        'created_by'         => auth()->id() ?? 1,
                    ];
                } else {
                    // ================== SHEET 1: INSURER LOOKUP ==================
                    // Sheet mein 'Insurance Company' column ka raw value hai (full name)
                    // xlr8_booking_insurer.name mein search karo → short_name uthao
                    $rawInsurerName = trim($row[$gs_pos['insurer_code']] ?? '');
                    $insurerCode    = $this->resolveInsurerCode($rawInsurerName, $allInsurers, $gid, $actualRow);

                    // ================== SHEET 1 DATE PARSING ==================
                    $polDate          = $this->parseDate($row[$gs_pos['pol_date']] ?? null, $gid, $actualRow);
                    $polEffectiveDate = $this->parseDate($row[$gs_pos['pol_effective_date']] ?? null, $gid, $actualRow);
                    $polExpiryDate    = $this->parseDate($row[$gs_pos['pol_expiry_date']] ?? null, $gid, $actualRow);
                    $createdDate      = $this->parseDate($row[$gs_pos['created_date']] ?? null, $gid, $actualRow);
                    $ownDmgStart      = $this->parseDate($row[$gs_pos['own_dmg_cover_start']] ?? null, $gid, $actualRow);
                    $ownDmgEnd        = $this->parseDate($row[$gs_pos['own_dmg_cover_end']] ?? null, $gid, $actualRow);
                    $liabStart        = $this->parseDate($row[$gs_pos['liability_cover_start']] ?? null, $gid, $actualRow);
                    $liabEnd          = $this->parseDate($row[$gs_pos['liability_cover_end']] ?? null, $gid, $actualRow);
                    $cpaStart         = $this->parseDate($row[$gs_pos['cpa_cover_start']] ?? null, $gid, $actualRow);
                    $cpaEnd           = $this->parseDate($row[$gs_pos['cpa_cover_end']] ?? null, $gid, $actualRow);

                    // ================== SHEET 1 INSERT ==================
                    $insertData = [
                        'insurer_code'              => $insurerCode,
                        'pol_no'                    => substr($polNo, 0, 50),
                        'pol_date'                  => $polDate,
                        'policy_type'               => substr(trim($row[$gs_pos['policy_type']] ?? ''), 0, 50),
                        'pol_tenure'                => is_numeric($row[$gs_pos['pol_tenure']] ?? null)
                                                        ? (int)$row[$gs_pos['pol_tenure']] : null,
                        'insured_name'              => substr($insuredName, 0, 50),
                        'mob_no'                    => substr(trim($row[$gs_pos['mob_no']] ?? ''), 0, 50),
                        'rgn_no'                    => substr(trim($row[$gs_pos['rgn_no']] ?? ''), 0, 50),
                        'yom'                       => is_numeric($row[$gs_pos['yom']] ?? null)
                                                        ? (int)$row[$gs_pos['yom']] : null,
                        'ncb'                       => is_numeric($row[$gs_pos['ncb']] ?? null)
                                                        ? (int)$row[$gs_pos['ncb']] : null,
                        'vh_class'                  => substr(trim($row[$gs_pos['vh_class']] ?? ''), 0, 50),
                        'pol_effective_date'        => $polEffectiveDate,
                        'pol_expiry_date'           => $polExpiryDate,
                        'product_type'              => substr(trim($row[$gs_pos['product_type']] ?? ''), 0, 50),
                        'model'                     => substr(trim($row[$gs_pos['model']] ?? ''), 0, 50),
                        'vh_body_type'              => substr(trim($row[$gs_pos['vh_body_type']] ?? ''), 0, 50),
                        'fuel'                      => substr(trim($row[$gs_pos['fuel']] ?? ''), 0, 50),
                        'vin'                       => substr(trim($row[$gs_pos['vin']] ?? ''), 0, 50),
                        'engine_no'                 => substr(trim($row[$gs_pos['engine_no']] ?? ''), 0, 50),
                        'created_date'              => $createdDate,
                        'payment_generation'        => substr(trim($row[$gs_pos['payment_generation']] ?? ''), 0, 50),
                        'payment_no'                => substr(trim($row[$gs_pos['payment_no']] ?? ''), 0, 50),
                        'od_discount'               => is_numeric($row[$gs_pos['od_discount']] ?? null)
                                                        ? (int)$row[$gs_pos['od_discount']] : null,
                        'total_idv'                 => is_numeric($row[$gs_pos['total_idv']] ?? null)
                                                        ? (int)$row[$gs_pos['total_idv']] : null,
                        'addon_prem_a'              => is_numeric($row[$gs_pos['addon_prem_a']] ?? null)
                                                        ? (int)$row[$gs_pos['addon_prem_a']] : null,
                        'netod_prem_a'              => is_numeric($row[$gs_pos['netod_prem_a']] ?? null)
                                                        ? (int)$row[$gs_pos['netod_prem_a']] : null,
                        'net_prem'                  => is_numeric($row[$gs_pos['net_prem']] ?? null)
                                                        ? (int)$row[$gs_pos['net_prem']] : null,
                        'imt23'                     => is_numeric($row[$gs_pos['imt23']] ?? null)
                                                        ? (int)$row[$gs_pos['imt23']] : null,
                        'gross_prem'                => is_numeric($row[$gs_pos['gross_prem']] ?? null)
                                                        ? (int)$row[$gs_pos['gross_prem']] : null,
                        'prev_pol_no'               => is_numeric($row[$gs_pos['prev_pol_no']] ?? null)
                                                        ? (int)$row[$gs_pos['prev_pol_no']] : null,
                        'prev_insurance_company'    => substr(trim($row[$gs_pos['prev_insurance_company']] ?? ''), 0, 50),
                        'own_dmg_cover_start'       => $ownDmgStart,
                        'own_dmg_cover_end'         => $ownDmgEnd,
                        'liability_cover_start'     => $liabStart,
                        'liability_cover_end'       => $liabEnd,
                        'cpa_cover_start'           => $cpaStart,
                        'cpa_cover_end'             => $cpaEnd,
                        '64vb_status'               => substr(trim($row[$gs_pos['64vb_status']] ?? ''), 0, 50),
                        'bundle_addon'              => substr(trim($row[$gs_pos['bundle_addon']] ?? ''), 0, 50),
                        'status'                    => 1,
                        'created_at'                => $now,
                        'created_by'                => auth()->id() ?? 1,
                    ];
                }

                try {
                    \DB::table('xlr8_booking_insurance')->insert($insertData);
                    $imported++;
                } catch (\Exception $e) {
                    \Log::error("Insurance GID={$gid} Row {$actualRow}: Insert failed", [
                        'error'      => $e->getMessage(),
                        'insertData' => $insertData,
                    ]);
                    $skipped++;
                }
            }

            \Log::info("Insurance Import: GID={$gid} done", [
                'imported' => $imported,
                'skipped'  => $skipped,
            ]);

            $totalImported += $imported;
            $totalSkipped  += $skipped;
        }

        \Log::info("Insurance Import: All sheets done", [
            'total_imported' => $totalImported,
            'total_skipped'  => $totalSkipped,
        ]);

        $message = "Insurance Import completed! Imported: {$totalImported}, Skipped: {$totalSkipped}";

        if ($totalImported > 0) {
            return redirect()->back()->with('success', $message);
        } else {
            return redirect()->back()->with('warning', $message);
        }
    }

    /**
     * Sheet ke raw insurer name ko xlr8_booking_insurer se match karke short_name return karo.
     *
     * Match strategy (upar se neeche try karta hai):
     *   1. Exact match on name          (case-insensitive)
     *   2. DB name contains sheet value (sheet value is a substring of DB name)
     *   3. Sheet value contains DB name (DB name is a substring of sheet value)
     *
     * Agar koi match nahi mila to null return karo aur warning log karo.
     */
    private function resolveInsurerCode(string $raw, array $allInsurers, $gid = null, $row = null): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $rawLower = strtolower($raw);

        // Pass 1: exact match on full name
        foreach ($allInsurers as $insurer) {
            if (strtolower($insurer->name) === $rawLower) {
                return $insurer->short_name;
            }
        }

        // Pass 2: DB name contains sheet value
        foreach ($allInsurers as $insurer) {
            if (stripos($insurer->name, $raw) !== false) {
                return $insurer->short_name;
            }
        }

        // Pass 3: sheet value contains DB name
        foreach ($allInsurers as $insurer) {
            if (stripos($raw, $insurer->name) !== false) {
                return $insurer->short_name;
            }
        }

        \Log::warning("Insurance resolveInsurerCode: No match found", [
            'raw' => $raw,
            'gid' => $gid,
            'row' => $row,
        ]);

        return null;
    }

    /**
     * Parse date from various formats to Y-m-d
     */
    private function parseDate($raw, $gid = null, $row = null): ?string
    {
        if (empty($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);

        // Known non-date values — silently return null (no warning logged)
        $ignoredValues = ['na', 'n/a', 'nil', '-', '--', 'none', '31-dec-1899'];
        if (in_array(strtolower($raw), $ignoredValues)) {
            return null;
        }

        // Excel serial number
        if (is_numeric($raw)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$raw);
                $year = (int)$date->format('Y');
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

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $raw);
            if ($date !== false) {
                $year = (int)$date->format('Y');
                if ($year >= 2000 && $year <= 2100) {
                    return $date->format('Y-m-d');
                }
            }
        }

        // 2-digit year: 1-Apr-26
        if (preg_match('/^(\d{1,2})[-\/]([A-Za-z]{3})[-\/](\d{2})$/', $raw, $m)) {
            $date = \DateTime::createFromFormat('j-M-Y', $m[1] . '-' . $m[2] . '-20' . $m[3]);
            if ($date !== false) return $date->format('Y-m-d');
        }

        // 2-digit year: 13/04/26
        if (preg_match('/^(\d{1,2})\/(\d{2})\/(\d{2})$/', $raw, $m)) {
            $date = \DateTime::createFromFormat('d/m/Y', $m[1] . '/' . $m[2] . '/20' . $m[3]);
            if ($date !== false) return $date->format('Y-m-d');
        }

        // Carbon fallback
        try {
            $date = \Carbon\Carbon::parse($raw);
            $year = (int)$date->format('Y');
            if ($year >= 2000 && $year <= 2100) {
                return $date->format('Y-m-d');
            }
        } catch (\Exception $e) {}

        \Log::warning("Insurance parseDate: Could not parse", [
            'raw' => $raw,
            'gid' => $gid,
            'row' => $row,
        ]);

        return null;
    }
}
