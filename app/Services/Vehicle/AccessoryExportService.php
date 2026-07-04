<?php

namespace App\Services\Vehicle;

use App\Exports\VehicleAccessoriesExport;
use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\AccessoryScope;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class AccessoryExportService
{
    protected $log = null;

    protected array $segmentCache = [];
    protected array $modelCache = [];
    protected array $variantCache = [];
    protected array $rowsCache = [];
    protected array $exportLogColumns = [];

    public function rows(bool $activeFirst = true, array $filters = []): array
    {
        $cacheKey = md5(json_encode([$activeFirst, $filters]));

        if (isset($this->rowsCache[$cacheKey])) {
            return $this->rowsCache[$cacheKey];
        }

        $query = Accessory::query()
            ->with(['scopes' => function ($q) use ($filters, $activeFirst) {
                if (!empty($filters['active_only'])) {
                    $q->where('status', 1);
                }

                if (!empty($filters['segment_code'])) {
                    $q->where('segment_code', $filters['segment_code']);
                }

                if (!empty($filters['model_code'])) {
                    $q->where('model_code', $filters['model_code']);
                }

                if (!empty($filters['variant_code'])) {
                    $q->where('variant_code', $filters['variant_code']);
                }

                if ($activeFirst) {
                    $q->orderByDesc('status');
                }

                $q->orderBy('segment_code')
                  ->orderBy('model_code')
                  ->orderBy('variant_code');
            }]);

        if (!empty($filters['active_only'])) {
            $query->where('status', 1);
        }

        if (!empty($filters['part_no'])) {
            $query->where('part_no', 'like', '%' . trim($filters['part_no']) . '%');
        }

        if (!empty($filters['item'])) {
            $query->where('item', 'like', '%' . trim($filters['item']) . '%');
        }

        if ($activeFirst) {
            $query->orderByDesc('status');
        }

        $accessories = $query
            ->orderBy('item')
            ->orderBy('part_no')
            ->get();

        $rows = [];

        foreach ($accessories as $accessory) {
            $scopes = $accessory->scopes ?? collect();

            if ($scopes->isEmpty()) {
                $rows[] = $this->mapRow($accessory, null);
                continue;
            }

            foreach ($scopes as $scope) {
                $rows[] = $this->mapRow($accessory, $scope);
            }
        }

        return $this->rowsCache[$cacheKey] = $rows;
    }

    public function store(
        ?string $relativePath = null,
        array $filters = [],
        bool $activeFirst = true,
        ?int $userId = null,
        string $disk = 'public'
    ): array {
        $fileName = $relativePath
            ? basename($relativePath)
            : 'vehicle_accessories_' . now()->format('Ymd_His') . '.xlsx';

        $relativePath = $relativePath ?: 'exports/vehicle-accessories/' . $fileName;
        $userId = $userId ?: (auth()->id() ?: 1);

        $this->start($userId, $fileName);

        try {
            $rows = $this->rows($activeFirst, $filters);

            Excel::store(
                new VehicleAccessoriesExport($this, $filters, $activeFirst),
                $relativePath,
                $disk
            );

            $size = Storage::disk($disk)->exists($relativePath)
                ? Storage::disk($disk)->size($relativePath)
                : null;

            $this->finishSuccess($relativePath, $fileName, count($rows), $size);

            return [
                'disk'          => $disk,
                'file_name'     => $fileName,
                'relative_path' => $relativePath,
                'absolute_path' => Storage::disk($disk)->path($relativePath),
                'url'           => method_exists(Storage::disk($disk), 'url')
                    ? Storage::disk($disk)->url($relativePath)
                    : null,
                'rows'          => count($rows),
                'log_id'        => $this->log?->id,
            ];
        } catch (Throwable $e) {
            $this->finishFailed($e);
            throw $e;
        }
    }

    public function markDownloaded(): void
    {
        if (!$this->log) {
            return;
        }

        $payload = $this->onlyExportLogColumns([
            'downloadedat' => now(),
            'updatedat'    => now(),
        ]);

        if (!empty($payload)) {
            $this->log->update($payload);
        } elseif (method_exists($this->log, 'markAsDownloaded')) {
            $this->log->markAsDownloaded();
        }
    }

    protected function mapRow($accessory, $scope = null): array
    {
        $segmentCode = $scope->segment_code ?? null;
        $modelCode   = $scope->model_code ?? null;
        $variantCode = $scope->variant_code ?? null;

        $masterStatus = (int) ($accessory->status ?? 0);
        $scopeStatus  = $scope ? (int) ($scope->status ?? 0) : 1;
        $finalStatus  = ($masterStatus === 1 && $scopeStatus === 1) ? 'ACTIVE' : 'INACTIVE';

        return [
            'SEGMENT'        => $segmentCode ? $this->segmentName($segmentCode) : '',
            'MODEL'          => $modelCode ? $this->modelName($modelCode) : '',
            'Variant'        => $variantCode ? $this->variantName($variantCode) : '',
            'DISPLAY NAME'   => (string) ($accessory->display_name ?? ''),
            'ITEM NAME'      => (string) ($accessory->item ?? ''),
            'PART NO.'       => (string) ($accessory->part_no ?? ''),
            'Set Qty'        => (int) ($accessory->set_qty ?? 1),
            'NDP'            => $accessory->ndp,
            'MRP (ROUNDED)'  => $accessory->mrp,
            'STATUS'         => $finalStatus,
        ];
    }

    protected function segmentName(string $code): string
    {
        if (!isset($this->segmentCache[$code])) {
            $modelClass = $this->segmentModelClass();

            $name = $modelClass::query()
                ->where('code', $code)
                ->value('name');

            $this->segmentCache[$code] = $name ?: $code;
        }

        return $this->segmentCache[$code];
    }

    protected function modelName(string $code): string
    {
        if (!isset($this->modelCache[$code])) {
            $modelClass = $this->vehicleModelClass();

            $row = $modelClass::query()
                ->select('name', 'customname')
                ->where('code', $code)
                ->first();

            $this->modelCache[$code] = $row?->customname ?: $row?->name ?: $code;
        }

        return $this->modelCache[$code];
    }

    protected function variantName(string $code): string
    {
        if (!isset($this->variantCache[$code])) {
            $modelClass = $this->variantModelClass();

            $row = $modelClass::query()
                ->select('name', 'customname')
                ->where('code', $code)
                ->first();

            $this->variantCache[$code] = $row?->customname ?: $row?->name ?: $code;
        }

        return $this->variantCache[$code];
    }

    protected function start(int $userId, string $fileName): void
    {
        $modelClass = $this->exportLogModelClass();

        $payload = $this->onlyExportLogColumns([
            'userid'      => $userId,
            'filename'    => $fileName,
            'exporttype'  => 'custom',
            'status'      => 'processing',
            'startedat'   => now(),
            'createdat'   => now(),
            'updatedat'   => now(),
        ]);

        if (!empty($payload)) {
            $this->log = $modelClass::create($payload);
        }
    }

    protected function finishSuccess(string $relativePath, string $fileName, int $rowCount, ?int $size = null): void
    {
        if (!$this->log) {
            return;
        }

        $payload = $this->onlyExportLogColumns([
            'filename'      => $fileName,
            'filepath'      => $relativePath,
            'filesize'      => $size,
            'totalrecords'  => $rowCount,
            'status'        => 'success',
            'completedat'   => now(),
            'updatedat'     => now(),
        ]);

        if (!empty($payload)) {
            $this->log->update($payload);
        }
    }

    protected function finishFailed(Throwable $e): void
    {
        if (!$this->log) {
            return;
        }

        $payload = $this->onlyExportLogColumns([
            'status'       => 'failed',
            'errormessage' => mb_substr($e->getMessage(), 0, 1000),
            'completedat'  => now(),
            'updatedat'    => now(),
        ]);

        if (!empty($payload)) {
            $this->log->update($payload);
        }
    }

    protected function onlyExportLogColumns(array $payload): array
    {
        $columns = $this->getExportLogColumns();

        return array_filter(
            $payload,
            fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected function getExportLogColumns(): array
    {
        if (!empty($this->exportLogColumns)) {
            return $this->exportLogColumns;
        }

        $modelClass = $this->exportLogModelClass();
        $table = (new $modelClass)->getTable();

        return $this->exportLogColumns = Schema::getColumnListing($table);
    }

    protected function exportLogModelClass(): string
    {
        $candidates = [
            \App\Models\ExportLog::class,
            \App\Models\Core\ExportLog::class,
            \App\ExportLog::class,
        ];

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        throw new \RuntimeException('ExportLog model class not found.');
    }

    protected function segmentModelClass(): string
    {
        $candidates = [
            \App\Models\Vehicle\Segment::class,
            \App\Models\Segment::class,
            \App\Segment::class,
        ];

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        throw new \RuntimeException('Segment model class not found.');
    }

    protected function vehicleModelClass(): string
    {
        $candidates = [
            \App\Models\Vehicle\VehicleModel::class,
            \App\Models\VehicleModel::class,
            \App\VehicleModel::class,
        ];

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        throw new \RuntimeException('VehicleModel class not found.');
    }

    protected function variantModelClass(): string
    {
        $candidates = [
            \App\Models\Vehicle\Variant::class,
            \App\Models\Variant::class,
            \App\Variant::class,
        ];

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        throw new \RuntimeException('Variant model class not found.');
    }
}