<?php

namespace App\Http\Controllers;

use App\Exports\VehicleDataExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Download vehicle data as Excel file
     * GET /export/vehicle-data
     */
    public function vehicleDataExcel()
    {
        try {
            $filename = 'VehicleDataExport_' . now()->format('d-m-Y-H-i-s') . '.xlsx';

            return Excel::download(new VehicleDataExport(), $filename);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download vehicle data as CSV file
     * GET /export/vehicle-data-csv
     */
    public function vehicleDataCsv()
    {
        try {
            $filename = 'VehicleDataExport_' . now()->format('d-m-Y-H-i-s') . '.csv';

            return Excel::download(new VehicleDataExport(), $filename, \Maatwebsite\Excel\Excel::CSV);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    
    public function vehicleDataSimpleCsv()
    {
        try {
            $export = new VehicleDataExport();
            $data = $export->collection();

            $filename = 'VehicleDataExport_' . now()->format('d-m-Y-H-i-s') . '.csv';

           
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
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
