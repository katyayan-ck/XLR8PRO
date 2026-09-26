<?php

namespace App\Http\Controllers;

use App\Exports\VehicleDataExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * See known-bugs-report.md BUG-044: these routes had no authentication or
 * authorization of any kind (routes/web.php previously registered them with
 * only the default 'web' middleware) — fixed by adding the standard
 * 'web' + 'admin' (CheckIfAdmin) middleware and a vehicles.view permission
 * check on each method. Separately, `App\Exports\VehicleDataExport` (used by
 * all three methods) does not exist anywhere in the codebase — every method
 * still fatals when actually invoked, unrelated to and not fixed by this
 * change.
 */
class ExportController extends Controller
{
    /**
     * Download vehicle data as Excel file
     * GET /export/vehicle-data
     */
    public function vehicleDataExcel()
    {
        if (! backpack_user()->can('vehicles.view')) {
            abort(403, 'Unauthorized. You do not have permission to export vehicle data.');
        }

        try {
            $filename = 'VehicleDataExport_'.now()->format('d-m-Y-H-i-s').'.xlsx';

            return Excel::download(new VehicleDataExport, $filename);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download vehicle data as CSV file
     * GET /export/vehicle-data-csv
     */
    public function vehicleDataCsv()
    {
        if (! backpack_user()->can('vehicles.view')) {
            abort(403, 'Unauthorized. You do not have permission to export vehicle data.');
        }

        try {
            $filename = 'VehicleDataExport_'.now()->format('d-m-Y-H-i-s').'.csv';

            return Excel::download(new VehicleDataExport, $filename, \Maatwebsite\Excel\Excel::CSV);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function vehicleDataSimpleCsv()
    {
        if (! backpack_user()->can('vehicles.view')) {
            abort(403, 'Unauthorized. You do not have permission to export vehicle data.');
        }

        try {
            $export = new VehicleDataExport;
            $data = $export->collection();

            $filename = 'VehicleDataExport_'.now()->format('d-m-Y-H-i-s').'.csv';

            $callback = function () use ($data) {
                $file = fopen('php://output', 'w');

                if ($data->count() > 0) {
                    $firstRow = $data->first();
                    if (is_array($firstRow)) {
                        fputcsv($file, array_keys($firstRow));
                    }
                }

                foreach ($data as $row) {
                    if (is_array($row)) {
                        fputcsv($file, $row);
                    }
                }

                fclose($file);
            };

            return response()->stream($callback, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
