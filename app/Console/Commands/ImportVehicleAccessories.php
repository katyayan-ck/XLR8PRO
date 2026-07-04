<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Vehicle\AccessoryImportService;

class ImportVehicleAccessories extends Command
{
    protected $signature = 'import:vehicle-accessories
                            {path : Absolute file path}
                            {--user=1 : User ID for import log}
                            {--update-existing=1 : Update existing rows or not}';

    protected $description = 'Import vehicle accessories from Excel/CSV';

    public function handle(AccessoryImportService $service): int
    {
        $result = $service->execute(
            $this->argument('path'),
            (int) $this->option('user'),
            [
                'update_existing' => (bool) $this->option('update-existing'),
            ]
        );

        $this->info($result['message'] ?? 'Done');
        $this->line('Total: ' . $result['total_records']);
        $this->line('Imported: ' . $result['imported_count']);
        $this->line('Skipped: ' . $result['skipped_count']);
        $this->line('Errors: ' . $result['errors_count']);

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}