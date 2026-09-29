<?php

namespace App\Jobs\Vehicle\Pricing;

use App\Services\Vehicle\Pricing\Engine\PricingRecalcService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Debounced automatic recalculation (DEC-083): queued with a delay after a pricing master change; unique until it starts,
 * so a burst of edits / an import runs once, and changes made while it runs queue the next run.
 */
class RecalculateAffectedJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function uniqueId(): string
    {
        return 'pricing-recalc';
    }

    public function handle(PricingRecalcService $recalc): void
    {
        $recalc->run();
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Pricing] automatic recalculation failed', ['error' => $e->getMessage()]);
    }
}
