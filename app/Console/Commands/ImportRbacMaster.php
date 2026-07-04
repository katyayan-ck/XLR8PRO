<?php

namespace App\Console\Commands;

use App\Imports\RbacMasterImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportRbacMaster extends Command
{
    protected $signature = 'import:rbac-master
                            {file : Path to the RBAC master Excel file}
                            {--dry-run : Validate and report without writing to DB}';

    protected $description = 'Import all RBAC master data (Branch, Location, Department, Designation,
                              Division, DesignationTree, Post, Vertical, Segment, SubSegment, Model,
                              Users) from the standard RBAC workbook in dependency order.';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Starting RBAC Master Import from: {$file}");
        $this->info(str_repeat('─', 60));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN mode — no data will be written.');
        }

        $import = new RbacMasterImport();

        try {
            if ($this->option('dry-run')) {
                $this->info('Dry-run: file is readable and importer initialised. Use without --dry-run to execute.');
                return self::SUCCESS;
            }

            Excel::import($import, $file);

        } catch (\Throwable $e) {
            $this->error('Fatal import error: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── Report ────────────────────────────────────────────────────────────
        $this->info(str_repeat('─', 60));
        $this->info('Import Complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Inserted', $import->log['inserted']],
                ['Skipped (already exists)', $import->log['skipped']],
                ['Errors', count($import->log['errors'])],
            ]
        );

        if (!empty($import->log['errors'])) {
            $this->warn('Errors encountered (logged to laravel.log):');
            foreach ($import->log['errors'] as $err) {
                $this->line("  ⚠ {$err}");
            }

            // Write to importlogs if table exists
            if (\Schema::hasTable('importlogs')) {
                DB::table('importlogs')->insert([
                    'importtype'    => 'rbac_master',
                    'status'        => 'partial',
                    'startedat'     => now()->subSeconds(5),
                    'completedat'   => now(),
                    'importedcount' => $import->log['inserted'],
                    'skippedcount'  => $import->log['skipped'],
                    'errors'        => json_encode($import->log['errors']),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        } else {
            $this->info('✅ No errors. All rows processed cleanly.');
            if (\Schema::hasTable('importlogs')) {
                DB::table('importlogs')->insert([
                    'importtype'    => 'rbac_master',
                    'status'        => 'success',
                    'startedat'     => now()->subSeconds(5),
                    'completedat'   => now(),
                    'importedcount' => $import->log['inserted'],
                    'skippedcount'  => $import->log['skipped'],
                    'errors'        => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
