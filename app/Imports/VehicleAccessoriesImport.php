<?php
// app/Imports/VehicleAccessoriesImport.php

namespace App\Imports;

use App\Services\Vehicle\AccessoryImportService;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Throwable;

class VehicleAccessoriesImport implements OnEachRow, WithHeadingRow, WithEvents, SkipsEmptyRows, SkipsOnError
{
    public function __construct(
        protected AccessoryImportService $service,
        protected int $userId = 1,
        protected ?int $logId = null
    ) {}

    public function onRow(Row $row): void
    {
        $this->service->processRow($row->getIndex(), $row->toArray());
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function () {
                $this->service->start($this->userId, $this->logId);
            },
            AfterImport::class => function () {
                $this->service->finish();
            },
        ];
    }

    public function onError(Throwable $e): void
    {
        $this->service->pushError($e->getMessage());
    }
}