<?php

namespace App\Console\Commands;

use App\Services\Vehicle\AccessoryExportService;
use Illuminate\Console\Command;

class ExportVehicleAccessories extends Command
{
    protected $signature = 'vehicle-accessories:export
                            {--active-only : Export only active accessories}
                            {--user=1 : User id for export log}
                            {--path= : Relative storage path, e.g. exports/vehicle-accessories/custom.xlsx}';

    protected $description = 'Export vehicle accessories to Excel';

    public function handle(AccessoryExportService $service): int
    {
        $path = $this->option('path') ?: 'exports/vehicle-accessories/vehicle_accessories_' . now()->format('Ymd_His') . '.xlsx';

        $result = $service->store(
            relativePath: $path,
            filters: [
                'active_only' => (bool) $this->option('active-only'),
            ],
            activeFirst: true,
            userId: (int) $this->option('user')
        );

        $this->info('Export completed');
        $this->line('Rows: ' . $result['rows']);
        $this->line('Path: ' . $result['absolute_path']);

        return self::SUCCESS;
    }
}