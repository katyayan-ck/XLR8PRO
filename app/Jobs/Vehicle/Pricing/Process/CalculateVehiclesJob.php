<?php

namespace App\Jobs\Vehicle\Pricing\Process;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Engine\PricingCalculationService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Step 9 — one chunk of Calculate & Publish (DEC-080): builds and publishes the snapshots of up to
 * PricingCalculationService::CHUNK vehicles (one transaction per vehicle; a failing vehicle is recorded, not fatal).
 * Part of the session's Bus::batch — progress is the batch's processed / total.
 */
class CalculateVehiclesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param list<string> $codes */
    public function __construct(public int $sessionId, public array $codes) {}

    public function handle(PricingCalculationService $calculation, PricingSessionService $sessions): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $session = ImportSession::find($this->sessionId);
        if (! $session || $session->isTerminal()) {
            return;
        }
        $calculation->calculate($session, $this->codes);
        $batch = $this->batch()?->fresh();
        if ($batch) {
            $done = $batch->processedJobs() + 1;
            $sessions->progress($session, ['message' => sprintf('Calculated %d of %d chunk(s) (%d vehicles each)…', min($done, $batch->totalJobs), $batch->totalJobs, PricingCalculationService::CHUNK)]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Pricing] calculate chunk failed', ['session_id' => $this->sessionId, 'codes' => count($this->codes), 'error' => $e->getMessage()]);
    }
}
