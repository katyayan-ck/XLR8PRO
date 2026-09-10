<?php

/**
 * Path: app/Services/Vehicle/Pricing/VehicleInfoImportService.php
 *
 * Stage 1B — complete stubs via VehicleService::applyVehicleInfo.
 * Gate: always-required + permit conditionals. Active only if complete.
 */

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Profile;
use App\Models\Vehicle\Variant;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class VehicleInfoImportService
{
    public function __construct(
        protected SheetHeaderService $headers,
        protected VehicleService $vehicles
    ) {}

    /**
     * @return array{updated:int,completed:int,left_inactive:int,masters_updated:int,rejected:list<string>}
     */
    public function importFile(string $absolutePath, ImportSession $session, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $progressKey = 'pricing_vi_progress_' . $session->id;
        $plog = new PricingProcessLogger($session->id);

        $this->pushProgress($progressKey, [
            'phase'   => 'loading',
            'message' => 'Loading workbook…',
            'percent' => 2,
            'done'    => false,
            'logs'    => ['[' . now()->format('H:i:s') . '] Loading Vehicle Info workbook…'],
        ]);

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);
        $worksheet = $spreadsheet->getSheetByName('Vehicle Info') ?? $spreadsheet->getSheet(0);
        $matrix = $worksheet->toArray(null, true, true, false);
        if ($matrix === []) {
            throw new RuntimeException('Vehicle Info sheet is empty.');
        }

        [$headerIdx, $fieldMap] = $this->resolveHeaderMap($matrix);
        if (! isset($fieldMap['model_code'])) {
            throw new RuntimeException('Vehicle Info sheet must contain Model Code (OEM Code) column.');
        }

        $dataRows = array_values(array_filter(
            array_slice($matrix, $headerIdx + 1),
            fn ($row) => trim((string) ($this->headers->val($row, $fieldMap, 'model_code') ?? '')) !== ''
        ));
        $total = count($dataRows);
        $plog->info('Vehicle Info rows', ['total' => $total]);

        $stats = [
            'updated'         => 0,
            'completed'       => 0,
            'left_inactive'   => 0,
            'masters_updated' => 0,
            'rejected'        => [],
        ];

        $processed = 0;
        foreach ($dataRows as $row) {
            $processed++;
            $oemCode = strtoupper(preg_replace('/\s+/', '', (string) $this->headers->val($row, $fieldMap, 'model_code')));
            if ($oemCode === '') {
                continue;
            }

            try {
                $mapped = $this->mapRow($row, $fieldMap);
                $variant = Variant::query()->where('code', $oemCode)->first();
                if (! $variant) {
                    $created = $this->vehicles->createStubFromPriceList(
                        $oemCode,
                        (string) ($mapped['oem_model'] ?? $mapped['custom_model'] ?? $oemCode),
                        (string) ($mapped['oem_variant'] ?? $mapped['custom_variant'] ?? ''),
                        'Price List ' . ($mapped['segment'] ?: 'PV'),
                        $userId
                    );
                    $variant = $created['variant'];
                }

                $result = $this->vehicles->applyVehicleInfo($variant, $mapped, $userId);
                $stats['masters_updated']++;
                $stats['updated']++;

                $profile = Profile::query()->where('model_code', $oemCode)->first();
                if ($profile) {
                    $profile->is_vehicle_master_complete = $result['complete'];
                    $profile->is_disabled = ! $result['active'];
                    $profile->segment = $mapped['segment'] ?: $profile->segment;
                    $profile->updated_by = $userId;
                    $profile->save();
                }

                if ($result['complete']) {
                    $stats['completed']++;
                } else {
                    $stats['left_inactive']++;
                    if (($mapped['status'] ?? '') === 'ACTIVE') {
                        $stats['rejected'][] = $oemCode . ': cannot set Active while incomplete [' . implode(',', $result['missing']) . ']';
                    }
                }
            } catch (\Throwable $e) {
                $stats['rejected'][] = $oemCode . ': ' . $e->getMessage();
                $plog->warning('Row failed', ['oem_code' => $oemCode, 'error' => $e->getMessage()]);
            }

            $this->maybeProgress($progressKey, $processed, $total, $stats, $oemCode);
        }

        $this->pushProgress($progressKey, [
            'phase'     => 'done',
            'message'   => "Done — completed {$stats['completed']}, inactive {$stats['left_inactive']}",
            'percent'   => 100,
            'processed' => $processed,
            'total'     => $total,
            'done'      => true,
            'stats'     => $stats,
            'logs'      => ['[' . now()->format('H:i:s') . '] Import finished'],
        ]);

        Log::info('[VehicleInfoImport] done', $stats);
        $plog->info('Vehicle Info import done', [
            'completed' => $stats['completed'],
            'inactive'  => $stats['left_inactive'],
            'rejected'  => count($stats['rejected']),
        ]);

        return $stats;
    }

    protected function mapRow(array $row, array $fieldMap): array
    {
        $g = fn (string $f) => $this->headers->val($row, $fieldMap, $f);

        return [
            'oem_model'       => $g('oem_model'),
            'oem_variant'     => $g('oem_variant'),
            'segment'         => $g('segment'),
            'sub_segment'     => $g('sub_segment'),
            'fuel'            => $g('fuel_type') ?? $g('fuel'),
            'seating'         => $g('seating'),
            'wheels'          => $g('wheels'),
            'transmission'    => $g('transmission'),
            'drivetrain'      => $g('drivetrain'),
            'body_make'       => $g('body_make'),
            'body_type'       => $g('body_type'),
            'cc'              => $g('cc_or_power') ?? $g('cc'),
            'motor'           => $g('motor'),
            'gvw'             => $g('gvw'),
            'gst_percent'     => $g('gst_pct') ?? $g('gst_percent'),
            'permit'          => $g('permit'),
            'taxi_price'      => $g('taxi_price'),
            'custom_model'    => $g('custom_model'),
            'custom_variant'  => $g('custom_variant'),
            'display_name'    => $g('display_name'),
            'colour_name'     => $g('colour_name'),
            'status'          => $g('status'),
            'shield_pack'     => $g('shield_pack'),
        ];
    }

    protected function resolveHeaderMap(array $matrix): array
    {
        [$idx, $map] = $this->headers->findHeaderRow('VEHICLE_INFO', $matrix, 15);
        if ($idx !== null && isset($map['model_code'])) {
            return [$idx, $map];
        }

        $hard = [
            'model code' => 'model_code',
            'oem code' => 'model_code',
            'oem model' => 'oem_model',
            'oem variant' => 'oem_variant',
            'segment' => 'segment',
            'sub segment' => 'sub_segment',
            'fuel' => 'fuel_type',
            'seating' => 'seating',
            'wheels' => 'wheels',
            'transmission' => 'transmission',
            'drivetrain' => 'drivetrain',
            'body make' => 'body_make',
            'body type' => 'body_type',
            'cc' => 'cc_or_power',
            'motor' => 'motor',
            'gvw' => 'gvw',
            'gst%' => 'gst_pct',
            'gst' => 'gst_pct',
            'permit' => 'permit',
            'taxi price' => 'taxi_price',
            'custom model' => 'custom_model',
            'custom variant' => 'custom_variant',
            'display name' => 'display_name',
            'colour name' => 'colour_name',
            'color name' => 'colour_name',
            'status' => 'status',
            'shield pack' => 'shield_pack',
            'master complete (y/n)' => 'is_vehicle_master_complete',
            'inactive (y/n)' => 'is_disabled',
        ];

        foreach ($matrix as $i => $cells) {
            $found = [];
            foreach ($cells as $col => $raw) {
                $label = mb_strtolower(trim((string) $raw));
                if (isset($hard[$label])) {
                    $found[$hard[$label]] = (int) $col;
                }
            }
            if (isset($found['model_code'])) {
                return [$i, $found];
            }
        }

        return [0, []];
    }

    protected function pushProgress(string $key, array $data): void
    {
        $prev = Cache::get($key, []);
        $logs = $prev['logs'] ?? [];
        if (isset($data['logs']) && is_array($data['logs'])) {
            $logs = array_merge($logs, $data['logs']);
            unset($data['logs']);
        }
        Cache::put($key, array_merge($prev, $data, [
            'logs'       => array_slice($logs, -80),
            'updated_at' => now()->toIso8601String(),
        ]), now()->addHours(6));
    }

    protected function maybeProgress(string $key, int $processed, int $total, array $stats, string $code): void
    {
        if ($processed % 25 !== 0 && $processed !== $total) {
            return;
        }
        $pct = $total > 0 ? min(99, 5 + (int) round(($processed / $total) * 90)) : 50;
        $this->pushProgress($key, [
            'phase'     => 'importing',
            'message'   => "{$processed}/{$total} (last {$code})",
            'percent'   => $pct,
            'processed' => $processed,
            'total'     => $total,
            'done'      => false,
            'logs'      => ['[' . now()->format('H:i:s') . "] {$processed}/{$total} last={$code} ok={$stats['completed']} reject=" . count($stats['rejected'])],
        ]);
    }
}
