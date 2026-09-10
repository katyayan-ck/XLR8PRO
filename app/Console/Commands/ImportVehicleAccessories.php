<?php

namespace App\Console\Commands;

use App\Services\Vehicle\AccessoryService;
use Illuminate\Console\Command;

class ImportVehicleAccessories extends Command
{
    protected $signature = 'import:vehicle-accessories
                            {path : Absolute path to Accessories.xlsx}
                            {--user=1 : User ID for import log}';

    protected $description = 'Purge and re-import vehicle accessories from multi-sheet Excel (all types)';

    public function handle(AccessoryService $service): int
    {
        $path = $this->argument('path');
        $this->info("Importing from: {$path}");
        $this->warn('Existing accessory catalog will be PURGED and reloaded.');

        // Prefer order-based import so numeric sheet keys from Maatwebsite still map correctly
        $result = $service->importExcelWithSheetOrder(
            $path,
            (int) $this->option('user')
        );

        $this->info($result['message'] ?? 'Done');
        $this->line('Total rows seen : ' . $result['total_records']);
        $this->line('Imported        : ' . $result['imported_count']);
        $this->line('Skipped         : ' . $result['skipped_count']);
        $this->line('Errors          : ' . $result['errors_count']);

        if (!empty($result['warnings'])) {
            $this->warn('Warnings: ' . count($result['warnings']));
            foreach (array_slice($result['warnings'], 0, 15) as $w) {
                $this->line('  - ' . $w);
            }
            if (count($result['warnings']) > 15) {
                $this->line('  ... and ' . (count($result['warnings']) - 15) . ' more');
            }
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $e) {
                $this->error($e);
            }
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
