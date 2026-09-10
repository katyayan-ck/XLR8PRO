<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\Traits\ScopedCrud;
use Revolution\Google\Sheets\Facades\Sheets;

class FinanceCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use ScopedCrud;

    /**
     * Required by ScopedCrud Trait
     */
    protected function getScopeType(): string
    {
        return '';
    }

     public function import()
    {
        $spreadsheetId = '1148dQQ35IOZNwLVeJ-cfXZpnhqFeJu5i5KTaSeDOWsc';

        $sheetGids = [
            
            '308746320',
            '172916626',
            '2043019899',
            '1564512315',
            '785816274',
            '2139648103'
            
        ];

        $gscolarr = [
            'financier_code'    => 'Financier',
            'trans_date'        => 'Transaction Date',
            'trans_description' => 'Transaction Description',
            'trans_type'        => 'Transaction Type',
            'do_no'             => 'DO NO',
            'debit_amount'      => 'Debit Amount',
            'credit_amount'     => 'Credit Amount',
            'running_balance'   => 'Running Balance',
        ];

        $totalImported = 0;
        $totalSkipped  = 0;
        $now           = now();

        foreach ($sheetGids as $gid) {

            \Log::info("Finance Import: Starting sheet GID={$gid}");

            $values = Sheets::spreadsheet($spreadsheetId)
                            ->sheetById($gid)
                            ->all();

            if (empty($values) || count($values) < 2) {
                \Log::warning("Finance Import: No data in GID={$gid}, skipping.");
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
            $skipped  = 0;

            foreach (array_slice($values, 1) as $rowIndex => $row) {

                $actualRow = $rowIndex + 2;

                if (empty($row[$gs_pos['financier_code'] ?? 0]) &&
                    empty($row[$gs_pos['trans_description'] ?? 0])) {
                    $skipped++;
                    continue;
                }

                
                $transDate = $this->parseDate($row[$gs_pos['trans_date']] ?? null, $gid, $actualRow);

                $insertData = [
                    'financier_code'    => trim($row[$gs_pos['financier_code']] ?? 'UNKNOWN'),
                    'trans_date'        => $transDate,
                    'trans_description' => substr(trim($row[$gs_pos['trans_description']] ?? ''), 0, 150),
                    'trans_type'        => substr(trim($row[$gs_pos['trans_type']] ?? 'O'), 0, 5),
                    'do_no'             => substr(trim($row[$gs_pos['do_no']] ?? ''), 0, 150),
                    'debit_amount'      => is_numeric($row[$gs_pos['debit_amount']] ?? null)
                                            ? round((float)$row[$gs_pos['debit_amount']], 2) : null,
                    'credit_amount'     => is_numeric($row[$gs_pos['credit_amount']] ?? null)
                                            ? round((float)$row[$gs_pos['credit_amount']], 2) : null,
                    'running_balance'   => is_numeric($row[$gs_pos['running_balance']] ?? null)
                                            ? -round((float)$row[$gs_pos['running_balance']], 2) : null,
                    'status'            => 1,
                    'created_at'        => $now,
                    'created_by'        => auth()->id() ?? 1,
                ];

                try {
                    \DB::table('xlr8_financer_statement')->insert($insertData);
                    $imported++;
                } catch (\Exception $e) {
                    \Log::error("GID={$gid} Row {$actualRow}: Insert failed", [
                        'error'      => $e->getMessage(),
                        'insertData' => $insertData,
                    ]);
                    $skipped++;
                }
            }

            \Log::info("Finance Import: GID={$gid} done", [
                'imported' => $imported,
                'skipped'  => $skipped,
            ]);

            $totalImported += $imported;
            $totalSkipped  += $skipped;
        }

        \Log::info("Finance Import: All sheets done", [
            'total_imported' => $totalImported,
            'total_skipped'  => $totalSkipped,
        ]);

        $message = "Import completed! Imported: {$totalImported}, Skipped: {$totalSkipped}";

        if ($totalImported > 0) {
            return redirect()->back()->with('success', $message);
        } else {
            return redirect()->back()->with('warning', $message);
        }
    }
    /**
     * Parse date from various formats to Y-m-d
     * Handles: d/m/Y, d-M-y (1-Apr-26), Excel serial, Y-m-d, d-m-Y etc.
     */
    private function parseDate($raw, $gid = null, $row = null): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $raw = trim($raw);

        if (is_numeric($raw)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$raw)
                    ->format('Y-m-d');
            } catch (\Exception $e) {}
        }

        $formats = [
            'd/m/Y',
            'd-m-Y',
            'd/m/y',
            'd-M-y',
            'd-M-Y',
            'd M Y',
            'Y-m-d',
            'm/d/Y',
        ];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $raw);
            if ($date && $date->format(str_replace(['d','m','y','Y','M'], ['d','m','y','Y','M'], $format)) || $date) {
                $formatted = $date->format('Y-m-d');
                
                if ($date->format('Y') >= 2000 && $date->format('Y') <= 2100) {
                    return $formatted;
                }
            }
        }

        try {
            return \Carbon\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Exception $e) {
            \Log::warning("parseDate failed", [
                'raw'   => $raw,
                'gid'   => $gid,
                'row'   => $row,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
