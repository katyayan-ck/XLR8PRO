<?php

namespace App\Services\Vehicle;

use App\Exports\VehicleAccessoriesExport;
use App\Models\Core\ExportLog;
use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Accessory master → Excel (`php artisan vehicle-accessories:export`): one row per accessory scope (or one unscoped row),
 * with segment / model / variant names, logged in `export_logs`.
 */
class AccessoryExportService
{
    protected ?ExportLog $log = null;

    protected array $segmentCache = [];

    protected array $modelCache = [];

    protected array $variantCache = [];

    protected array $rowsCache = [];

    public function rows(bool $activeFirst = true, array $filters = []): array
    {
        $cacheKey = md5(json_encode([$activeFirst, $filters]));

        if (isset($this->rowsCache[$cacheKey])) {
            return $this->rowsCache[$cacheKey];
        }

        $query = Accessory::query()
            ->with(['scopes' => function ($q) use ($filters, $activeFirst) {
                if (! empty($filters['active_only'])) {
                    $q->where('status', 1);
                }

                if (! empty($filters['segment_code'])) {
                    $q->where('segment_code', $filters['segment_code']);
                }

                if (! empty($filters['model_code'])) {
                    $q->where('model_code', $filters['model_code']);
                }

                if (! empty($filters['variant_code'])) {
                    $q->where('variant_code', $filters['variant_code']);
                }

                if ($activeFirst) {
                    $q->orderByDesc('status');
                }

                $q->orderBy('segment_code')
                    ->orderBy('model_code')
                    ->orderBy('variant_code');
            }]);

        if (! empty($filters['active_only'])) {
            $query->where('status', 1);
        }

        if (! empty($filters['part_no'])) {
            $query->where('part_no', 'like', '%'.trim($filters['part_no']).'%');
        }

        if (! empty($filters['item'])) {
            $query->where('item', 'like', '%'.trim($filters['item']).'%');
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
            : 'vehicle_accessories_'.now()->format('Ymd_His').'.xlsx';

        $relativePath = $relativePath ?: 'exports/vehicle-accessories/'.$fileName;
        $userId = $userId ?: (auth()->id() ?: 1);

        $this->start($userId, $fileName, $filters);

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
                'disk' => $disk,
                'file_name' => $fileName,
                'relative_path' => $relativePath,
                'absolute_path' => Storage::disk($disk)->path($relativePath),
                'url' => method_exists(Storage::disk($disk), 'url')
                    ? Storage::disk($disk)->url($relativePath)
                    : null,
                'rows' => count($rows),
                'log_id' => $this->log?->id,
            ];
        } catch (Throwable $e) {
            $this->finishFailed($e);
            throw $e;
        }
    }

    public function markDownloaded(): void
    {
        $this->log?->update(['downloaded_at' => now(), 'download_count' => (int) $this->log->getAttribute('download_count') + 1]);
    }

    protected function mapRow($accessory, $scope = null): array
    {
        $segmentCode = $scope->segment_code ?? null;
        $modelCode = $scope->model_code ?? null;
        $variantCode = $scope->variant_code ?? null;

        $masterStatus = (int) ($accessory->status ?? 0);
        $scopeStatus = $scope ? (int) ($scope->status ?? 0) : 1;
        $finalStatus = ($masterStatus === 1 && $scopeStatus === 1) ? 'ACTIVE' : 'INACTIVE';

        return [
            'SEGMENT' => $segmentCode ? $this->segmentName($segmentCode) : '',
            'MODEL' => $modelCode ? $this->modelName($modelCode) : '',
            'Variant' => $variantCode ? $this->variantName($variantCode) : '',
            'DISPLAY NAME' => (string) ($accessory->display_name ?? ''),
            'ITEM NAME' => (string) ($accessory->item ?? ''),
            'PART NO.' => (string) ($accessory->part_no ?? ''),
            'Set Qty' => (int) ($accessory->set_qty ?? 1),
            'NDP' => $accessory->ndp,
            'MRP (ROUNDED)' => $accessory->mrp,
            'STATUS' => $finalStatus,
        ];
    }

    protected function segmentName(string $code): string
    {
        return $this->segmentCache[$code] ??= (string) (Segment::query()->where('code', $code)->value('name') ?: $code);
    }

    protected function modelName(string $code): string
    {
        return $this->modelCache[$code] ??= (string) (VehicleModel::query()->where('code', $code)->value('name') ?: $code);
    }

    /** Variant rows are per colour; any row of the code gives its display / custom name (BUG-221: was `name` / `customname`). */
    protected function variantName(string $code): string
    {
        if (! isset($this->variantCache[$code])) {
            $row = Variant::query()->select('display_name', 'custom_name')->where('code', $code)->first();
            $this->variantCache[$code] = (string) ($row?->display_name ?: $row?->custom_name ?: $code);
        }

        return $this->variantCache[$code];
    }

    /** Export log row (`export_logs`, BUG-221: keys were written without underscores and never matched a column). */
    protected function start(int $userId, string $fileName, array $filters): void
    {
        $this->log = ExportLog::query()->create([
            'user_id' => $userId,
            'filename' => $fileName,
            'export_type' => 'custom',
            'filters' => $filters,
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    protected function finishSuccess(string $relativePath, string $fileName, int $rowCount, ?int $size = null): void
    {
        $this->log?->update([
            'filename' => $fileName,
            'file_path' => $relativePath,
            'file_size' => $size,
            'total_records' => $rowCount,
            'status' => 'success',
            'completed_at' => now(),
            'duration_seconds' => (int) $this->log->getAttribute('started_at')?->diffInSeconds(now()),
        ]);
    }

    protected function finishFailed(Throwable $e): void
    {
        report($e);
        $this->log?->update(['status' => 'failed', 'completed_at' => now()]);
    }
}
