<?php
// app/Services/Vehicle/AccessoryImportService.php

namespace App\Services\Vehicle;

use App\Models\ImportLog;
use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\AccessoryScope;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class AccessoryImportService
{
    protected array $errors = [];
    protected array $warnings = [];
    protected int $total = 0;
    protected int $imported = 0;
    protected int $skipped = 0;
    protected ?ImportLog $log = null;
    protected int $userId = 1;

    public function execute(string $path, int $userId = 1, array $options = []): array
    {
        $this->start($userId);

        try {
            $collection = Excel::toCollection(null, $path)->first();

            if ($collection && $collection->isNotEmpty()) {
                // FIRST ROW = HEADERS
                $headers = $collection->first()->toArray();

                // Process remaining rows
                $collection->skip(1)->each(function ($row, $index) use ($headers) {
                    $rowNo = $index + 2;
                    $associativeRow = $this->mapRowWithHeaders($row->toArray(), $headers);
                    $this->processRow($rowNo, $associativeRow);
                });
            }
        } catch (Throwable $e) {
            $this->pushError($e->getMessage());
        }

        $this->finish();

        $success = count($this->errors) === 0;
        return [
            'success'        => $success,
            'message'        => $success ? 'Done' : 'Done with errors',
            'total_records'  => $this->total,
            'imported_count' => $this->imported,
            'skipped_count'  => $this->skipped,
            'errors_count'   => count($this->errors),
        ];
    }

    protected function mapRowWithHeaders(array $rowData, array $headers): array
    {
        $mapped = [];
        foreach ($rowData as $i => $value) {
            $header = $headers[$i] ?? 'col_' . $i;
            $mapped[strtolower(trim($header))] = $value;
            $mapped[strtoupper(trim($header))] = $value;   // for easy lookup
        }
        return $mapped;
    }

    public function start(int $userId = 1, ?int $logId = null): void
    {
        $this->userId = $userId;

        $data = [
            'user_id'     => $userId,
            'filename'    => 'vehicle_accessories.xlsx',
            'import_type' => 'custom',
            'status'      => 'processing',
            'started_at'  => now(),
            'warnings'    => json_encode(['module' => 'vehicle_accessories']),
        ];

        $this->log = $logId ? ImportLog::find($logId) : ImportLog::forceCreate($data);

        Accessory::query()->update(['status' => 0, 'updated_by' => $userId, 'updated_at' => now()]);
        AccessoryScope::query()->update(['status' => 0, 'updated_by' => $userId, 'updated_at' => now()]);
    }

    public function processRow(int $rowNo, array $row): void
    {
        $this->total++;

        echo "\n=== ROW {$rowNo} ===\n";
        print_r($row);   // full row with header keys

        $partNo = strtoupper(trim((string)($row['part no.'] ?? $row['part no'] ?? $row['part_no'] ?? $row['partno'] ?? '')));
        $item   = trim((string)($row['item name'] ?? $row['item_name'] ?? $row['item'] ?? ''));

        echo "[PART NO] '{$partNo}' | [ITEM NAME] '{$item}'\n";

        if ($partNo === '' || $item === '') {
            $this->skipped++;
            echo "→ SKIPPED (part/item missing)\n";
            $this->warnings[] = "Row {$rowNo}: skipped (part/item missing)";
            return;
        }

        echo "→ PROCESSING\n";

        DB::transaction(function () use ($partNo, $item, $row, $rowNo) {
            $disp    = trim((string)($row['display name'] ?? ''));
            $seg     = trim((string)($row['segment'] ?? ''));
            $model   = trim((string)($row['model'] ?? ''));
            $variant = trim((string)($row['variant'] ?? ''));
            $ndp     = $row['ndp'] ?? null;
            $mrp     = $row['mrp (rounded)'] ?? null;

            Accessory::updateOrCreate(
                ['part_no' => $partNo],
                [
                    'display_name' => $disp ?: null,
                    'item'         => $item,
                    'ndp'          => $ndp !== '' && $ndp !== null ? round((float)$ndp, 2) : null,
                    'mrp'          => $mrp !== '' && $mrp !== null ? round((float)$mrp, 2) : null,
                    'status'       => 1,
                    'updated_by'   => $this->userId,
                    'updated_at'   => now(),
                    'created_by'   => $this->userId,
                ]
            );

            foreach ($this->expandScopes($seg, $model, $variant, $rowNo) as $scope) {
                AccessoryScope::updateOrCreate(
                    [
                        'part_no'      => $partNo,
                        'segment_code' => $scope['segment_code'],
                        'model_code'   => $scope['model_code'],
                        'variant_code' => $scope['variant_code'],
                    ],
                    [
                        'status'      => 1,
                        'updated_by'  => $this->userId,
                        'updated_at'  => now(),
                        'created_by'  => $this->userId,
                    ]
                );
            }
        });

        $this->imported++;
    }

    protected function expandScopes(?string $segments, ?string $models, ?string $variants, int $rowNo): array
    {
        $segCodes = $this->resolveList($segments, Segment::class, 'name', 'code', $rowNo, 'segment');
        $mdlCodes = $this->resolveList($models, VehicleModel::class, 'name', 'code', $rowNo, 'model');
        $varCodes = $this->resolveList($variants, Variant::class, 'name', 'code', $rowNo, 'variant');

        $rows = [];
        foreach ($segCodes as $seg) {
            foreach ($mdlCodes as $mdl) {
                foreach ($varCodes as $var) {
                    $rows[] = ['segment_code' => $seg, 'model_code' => $mdl, 'variant_code' => $var];
                }
            }
        }
        return array_values(array_unique($rows, SORT_REGULAR));
    }

    protected function resolveList(?string $value, string $model, string $nameCol, string $codeCol, int $rowNo, string $label): array
    {
        if (!$value || strtoupper(trim($value)) === 'ANY') {
            return [null];
        }

        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $value))) as $piece) {
            $rec = $model::query()
                ->whereRaw('UPPER(' . $nameCol . ') = ?', [Str::upper($piece)])
                ->orWhereRaw('UPPER(' . $codeCol . ') = ?', [Str::upper($piece)])
                ->first();

            if ($rec) {
                $out[] = $rec->{$codeCol};
            } else {
                $this->warnings[] = "Row {$rowNo}: {$label} '{$piece}' not found, treated as ANY.";
            }
        }
        return $out ?: [null];
    }

    public function pushError(string $message): void { $this->errors[] = $message; }

    public function finish(): void
    {
        if (!$this->log) return;

        $data = [
            'total_records'   => $this->total,
            'imported_count'  => $this->imported,
            'skipped_count'   => $this->skipped,
            'errors_count'    => count($this->errors),
            'errors'          => json_encode($this->errors),
            'warnings'        => json_encode($this->warnings),
            'status'          => count($this->errors) ? 'partial' : 'success',
            'completed_at'    => now(),
            'duration_seconds'=> optional($this->log->started_at)->diffInSeconds(now()),
        ];

        $this->log->forceFill($data)->save();
    }
}