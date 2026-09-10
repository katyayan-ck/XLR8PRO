<?php

/**
 * Path: app/Jobs/Vehicle/Pricing/ImportPriceListsJob.php
 *
 * Stage 2 — prices only. Does NOT re-detect or create masters.
 */

namespace App\Jobs\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\PriceListPricingImporter;
use App\Services\Vehicle\Pricing\PricingProcessLogger;
use App\Services\Vehicle\Pricing\PricingSessionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportPriceListsJob implements ShouldQueue
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
        PriceListPricingImporter $priceImporter
    ): void {
        $session = ImportSession::find($this->sessionId);
        if (! $session) {
            return;
        }

        $plog = new PricingProcessLogger($session->id);
        $sessions->advance($session, ImportSession::STAGE_IMPORTING_PRICES);

        try {
            $this->progress($session->id, [
                'phase'   => 'prices',
                'message' => 'Importing prices…',
                'percent' => 5,
                'done'    => false,
                'failed'  => false,
                'logs'    => ['[' . now()->format('H:i:s') . '] Price import started (no detect)'],
            ]);

            $priceStats = $priceImporter->importFile(
                $this->absolutePath,
                $session,
                $this->sheetCodes,
                $this->wefDate,
                'normal',
                $this->userId,
                function (array $p) use ($session) {
                    $this->progress($session->id, array_merge(['phase' => 'prices', 'done' => false], $p));
                }
            );

            $sessions->updateStats($session, [
                'prices_written' => $priceStats['written'] ?? 0,
                'price_changes'  => $priceStats['changed'] ?? 0,
            ]);
            $sessions->advance($session, ImportSession::STAGE_AWAITING_ADDONS, [
                'prices_written' => $priceStats['written'] ?? 0,
            ]);

            $this->progress($session->id, [
                'phase'       => 'done',
                'message'     => 'Pricing import finished.',
                'percent'     => 100,
                'done'        => true,
                'failed'      => false,
                'price_stats' => $priceStats,
                'stage'       => ImportSession::STAGE_AWAITING_ADDONS,
                'next_step'   => 'addons',
                'logs'        => ['[' . now()->format('H:i:s') . '] Prices written=' . ($priceStats['written'] ?? 0)],
            ]);

            $plog->info('Price import finished', $priceStats);
        } catch (Throwable $e) {
            $plog->error('Price job failed', ['error' => $e->getMessage()]);
            Log::error('[ImportPriceListsJob] failed', ['error' => $e->getMessage()]);
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
