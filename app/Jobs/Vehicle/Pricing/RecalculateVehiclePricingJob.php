<?php

/**
 * Path: app/Jobs/Vehicle/Pricing/RecalculateVehiclePricingJob.php
 */

namespace App\Jobs\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\Affected;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Variant;
use App\Services\Vehicle\Pricing\PricingEngineService;
use App\Services\Vehicle\Pricing\PricingProcessLogger;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RecalculateVehiclePricingJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(
        public string $oemCode,
        public string $channel,
        public string $wefDate,
        public ?int $sessionId = null,
        public ?int $userId = null
    ) {}

    public function handle(PricingEngineService $engine): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $plog = new PricingProcessLogger($this->sessionId);
        $this->touchAffected('processing');

        $nv = $engine->calculateAndPublish(
            $this->oemCode, $this->channel, 'nv', $this->wefDate, $this->sessionId, $this->userId
        );
        $ov = $engine->calculateAndPublish(
            $this->oemCode, $this->channel, 'ov', $this->wefDate, $this->sessionId, $this->userId
        );

        $variant = Variant::query()->where('code', $this->oemCode)->first();
        $taxi = strtoupper((string) ($variant->taxi_price ?? 'NO'));
        if ($taxi === 'YES' || $taxi === 'Y' || $taxi === '1') {
            foreach (['PRIVATE', 'PASSENGER'] as $permit) {
                $engine->calculateAndPublish(
                    $this->oemCode, $this->channel, 'nv', $this->wefDate, $this->sessionId, $this->userId, $permit
                );
            }
        }

        $ok = ($nv['published'] || ($nv['payload']['incomplete'] ?? false) || ($nv['payload']['hold'] ?? false));
        $this->touchAffected($nv['published'] ? 'done' : (($nv['payload']['incomplete'] ?? false) ? 'skipped' : 'error'), $nv['errors'][0] ?? null);

        $plog->info('Recalc', [
            'oem_code' => $this->oemCode,
            'published_nv' => $nv['published'],
            'published_ov' => $ov['published'],
            'on_road' => $nv['payload']['on_road'] ?? null,
            'errors' => $nv['errors'],
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->touchAffected('error', $e->getMessage());
    }

    protected function touchAffected(string $status, ?string $error = null): void
    {
        if (! $this->sessionId) {
            return;
        }
        try {
            Affected::query()->updateOrCreate(
                [
                    'import_session_id' => $this->sessionId,
                    'model_code'        => $this->oemCode,
                ],
                [
                    'variant_code'  => $this->oemCode,
                    'status'        => $status,
                    'error_message' => $error,
                    'updated_by'    => $this->userId,
                ]
            );
        } catch (Throwable $e) {
            // table optional
        }
    }
}
