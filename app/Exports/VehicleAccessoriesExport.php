<?php

namespace App\Exports;

use App\Services\Vehicle\AccessoryExportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VehicleAccessoriesExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        protected AccessoryExportService $service,
        protected array $filters = [],
        protected bool $activeFirst = true
    ) {}

    public function collection()
    {
        return collect($this->service->rows($this->activeFirst, $this->filters));
    }

    public function headings(): array
    {
        return [
            'SEGMENT',
            'MODEL',
            'Variant',
            'DISPLAY NAME',
            'ITEM NAME',
            'PART NO.',
            'Set Qty',
            'NDP',
            'MRP (ROUNDED)',
            'STATUS',
        ];
    }
}
