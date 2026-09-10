<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\{WithMultipleSheets, WithEvents};
use Maatwebsite\Excel\Events\AfterImport;
use App\Imports\Sheets\{RulesSheetImport, EmployeeSheetImport};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class EmployeeMasterImport implements WithMultipleSheets, WithEvents
{
    private RulesSheetImport   $rulesSheet;
    private EmployeeSheetImport $empSheet;

    public function __construct(bool $dryRun = false, bool $update = false)
    {
        $this->rulesSheet = new RulesSheetImport();
        $this->empSheet   = new EmployeeSheetImport($dryRun, $update);
    }

    // ── Sheet routing ─────────────────────────────────────────────

    public function sheets(): array
    {
        return [
            'Rules'                   => $this->rulesSheet,
            'Employee (Full Format)'  => $this->empSheet,

            // Fallback: some exports name it differently
            0 => $this->rulesSheet,   // first sheet = Rules
            1 => $this->empSheet,     // second sheet = Employees
        ];
    }

    // ── After import: Phase 5 + report write ─────────────────────

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                // Phase 5 — Reporting Manager resolution
                $managerStats = EmployeeSheetImport::resolveReportingManagers();

                // Merge stats
                $stats = array_merge(
                    $this->empSheet->getStats(),
                    ['manager_resolution' => $managerStats]
                );

                // Write JSON import report
                $this->writeReport($stats);
            },
        ];
    }

    private function writeReport(array $stats): void
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $path = "logs/imports/employees_{$timestamp}.json";

        Storage::put($path, json_encode([
            'import_at'    => $timestamp,
            'summary'      => [
                'created'  => $stats['created'],
                'updated'  => $stats['updated'],
                'skipped'  => $stats['skipped'],
                'errors'   => count($stats['errors']),
                'warnings' => count($stats['warnings']),
            ],
            'manager_resolution' => $stats['manager_resolution'] ?? [],
            'errors'   => $stats['errors'],
            'warnings' => $stats['warnings'],
        ], JSON_PRETTY_PRINT));

        Log::info("Employee import complete. Report: storage/{$path}", $stats['summary'] ?? []);
    }

    public function getEmpSheet(): EmployeeSheetImport
    {
        return $this->empSheet;
    }
}
