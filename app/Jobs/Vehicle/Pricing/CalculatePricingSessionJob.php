<?php

/**
 * Path: app/Jobs/Vehicle/Pricing/CalculatePricingSessionJob.php
 *
 * Builds a Bus batch of per-vehicle recalc jobs for complete priced vehicles.
 */

namespace App\Jobs\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\Profile;
use App\Services\Vehicle\Pricing\PricingProcessLogger;
use App\Services\Vehicle\Pricing\PricingSessionService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalculatePricingSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $sessionId,
        public string $channel = 'normal',
        public ?int $userId = null
    ) {}

    public function handle(PricingSessionService $sessions): void
    {
        $session = ImportSession::find($this->sessionId);
        if (! $session) {
            return;
        }

        $plog = new PricingProcessLogger($session->id);
        $sessions->advance($session, ImportSession::STAGE_CALCULATING);
        $wef = $session->wef_date?->format('Y-m-d') ?? now()->toDateString();

        $codes = Profile::query()
            ->where('is_vehicle_master_complete', true)
            ->pluck('model_code')
            ->map(fn ($c) => strtoupper((string) $c))
            ->unique()
            ->values();

        $priced = Pricing::query()
            ->where('is_active', true)
            ->whereIn('model_code', $codes)
            ->pluck('model_code')
            ->map(fn ($c) => strtoupper((string) $c))
            ->unique()
            ->values();

        $plog->info('Calculate batch assemble', [
            'complete_profiles' => $codes->count(),
            'with_price' => $priced->count(),
            'wef' => $wef,
        ]);

        $this->progress($session->id, [
            'phase' => 'calculating',
            'message' => 'Queueing '.$priced->count().' vehicles…',
            'percent' => 5,
            'total' => $priced->count(),
            'done' => false,
            'logs' => ['['.now()->format('H:i:s').'] Calculate batch size '.$priced->count()],
        ]);

        $jobs = [];
        foreach ($priced as $code) {
            $jobs[] = new RecalculateVehiclePricingJob(
                $code,
                $this->channel,
                $wef,
                $session->id,
                $this->userId
            );
        }

        if ($jobs === []) {
            $sessions->advance($session, ImportSession::STAGE_SUMMARY, [
                'calculated' => 0,
            ]);
            $this->progress($session->id, [
                'phase' => 'done',
                'message' => 'Nothing to calculate (no complete+priced vehicles).',
                'percent' => 100,
                'done' => true,
            ]);

            return;
        }

        $sessionId = $session->id;
        Bus::batch($jobs)
            ->name('pricing-calc-'.$sessionId)
            ->allowFailures()
            ->then(function (Batch $batch) use ($sessionId) {
                $s = ImportSession::find($sessionId);
                if ($s) {
                    app(PricingSessionService::class)->advance($s, ImportSession::STAGE_SUMMARY, [
                        'calculated' => $batch->totalJobs - $batch->failedJobs,
                        'calc_failed' => $batch->failedJobs,
                    ]);
                }
                Cache::put('pricing_progress_'.$sessionId, [
                    'phase' => 'done',
                    'message' => 'Calculate finished. '.($batch->totalJobs - $batch->failedJobs).' published.',
                    'percent' => 100,
                    'done' => true,
                    'failed' => false,
                    'updated_at' => now()->toIso8601String(),
                ], now()->addHours(6));
            })
            ->catch(function (Batch $batch, Throwable $e) use ($sessionId) {
                Log::error('[CalculatePricingSessionJob] batch failed', ['error' => $e->getMessage()]);
                Cache::put('pricing_progress_'.$sessionId, [
                    'phase' => 'failed',
                    'message' => $e->getMessage(),
                    'percent' => 100,
                    'done' => true,
                    'failed' => true,
                    'updated_at' => now()->toIso8601String(),
                ], now()->addHours(6));
            })
            ->dispatch();
    }

    protected function progress(int $sessionId, array $data): void
    {
        $key = "pricing_progress_{$sessionId}";
        $prev = Cache::get($key, []);
        $logs = $prev['logs'] ?? [];
        if (isset($data['logs'])) {
            $logs = array_merge($logs, $data['logs']);
            unset($data['logs']);
        }
        Cache::put($key, array_merge($prev, $data, [
            'logs' => array_slice($logs, -80),
            'updated_at' => now()->toIso8601String(),
        ]), now()->addHours(6));
    }
}
