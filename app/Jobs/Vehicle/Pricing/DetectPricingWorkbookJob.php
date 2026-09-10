<?php

/**
 * Path: app/Jobs/Vehicle/Pricing/DetectPricingWorkbookJob.php
 *
 * Detect + stub + Vehicle Info export. Never imports prices.
 */

namespace App\Jobs\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\PriceListVehicleDetector;
use App\Services\Vehicle\Pricing\PricingProcessLogger;
use App\Services\Vehicle\Pricing\PricingSessionService;
use App\Services\Vehicle\Pricing\VehicleInfoExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DetectPricingWorkbookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public int $sessionId,
        public string $absolutePath,
        public array $sheetCodes,
        public string $wefDate,
        public ?int $userId = null
    ) {}

    public function handle(
        PricingSessionService $sessions,
        PriceListVehicleDetector $detector,
        VehicleInfoExportService $vehicleInfoExport
    ): void {
        $session = ImportSession::find($this->sessionId);
        if (! $session) {
            return;
        }

        $plog = new PricingProcessLogger($session->id);

        try {
            $this->progress($session->id, [
                'phase'   => 'detect',
                'message' => 'Detecting vehicles…',
                'percent' => 2,
                'done'    => false,
                'failed'  => false,
                'logs'    => ['[' . now()->format('H:i:s') . '] Detect started'],
            ]);

            $detect = $detector->detectFromFile(
                $this->absolutePath,
                $session,
                $this->sheetCodes,
                $this->userId,
                function (array $p) use ($session) {
                    $this->progress($session->id, array_merge(['phase' => 'detect', 'done' => false], $p));
                }
            );

            $sessions->updateStats($session, [
                'fresh_created' => $detect['created'],
                'already_known' => count($detect['known']),
                'incomplete'    => $detect['created'],
                'stubs'         => $detect['masters_created'] ?? 0,
            ]);

            $this->progress($session->id, [
                'phase'   => 'export',
                'message' => 'Building Vehicle Info export…',
                'percent' => 90,
            ]);

            $export = $vehicleInfoExport->exportForSession($session);
            $sessions->advance($session, ImportSession::STAGE_AWAITING_VEHICLE, [
                'incomplete' => $detect['created'],
            ]);

            $this->progress($session->id, [
                'phase'           => 'done',
                'message'         => "Detect done. Fresh={$detect['created']}. Download Vehicle Info if incomplete, then continue to prices.",
                'percent'         => 100,
                'done'            => true,
                'failed'          => false,
                'detect'          => [
                    'created'    => $detect['created'],
                    'codes_seen' => $detect['codes_seen'] ?? 0,
                    'known'      => count($detect['known']),
                    'stubs'      => $detect['masters_created'] ?? 0,
                ],
                'export_filename' => $export['filename'],
                'stage'           => ImportSession::STAGE_AWAITING_VEHICLE,
                'next_step'       => 'vehicle-info',
                'logs'            => ['[' . now()->format('H:i:s') . '] Export ready: ' . $export['filename']],
            ]);

            $plog->info('Detect job finished', [
                'fresh'  => $detect['created'],
                'export' => $export['filename'],
            ]);
        } catch (Throwable $e) {
            $plog->error('Detect job failed', ['error' => $e->getMessage()]);
            Log::error('[DetectPricingWorkbookJob] failed', ['error' => $e->getMessage()]);
            $session->status = 'failed';
            $session->notes = $e->getMessage();
            $session->save();
            $this->progress($session->id, [
                'phase'   => 'failed',
                'message' => $e->getMessage(),
                'percent' => 100,
                'done'    => true,
                'failed'  => true,
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->progress($this->sessionId, [
            'phase'   => 'failed',
            'message' => $e->getMessage(),
            'done'    => true,
            'failed'  => true,
            'percent' => 100,
        ]);
    }

    protected function progress(int $sessionId, array $data): void
    {
        $key = "pricing_progress_{$sessionId}";
        $prev = Cache::get($key, []);
        $logs = $prev['logs'] ?? [];
        if (isset($data['logs']) && is_array($data['logs'])) {
            $logs = array_merge($logs, $data['logs']);
            unset($data['logs']);
        }
        Cache::put($key, array_merge($prev, $data, [
            'logs'       => array_slice($logs, -100),
            'updated_at' => now()->toIso8601String(),
        ]), now()->addHours(6));
    }
}
