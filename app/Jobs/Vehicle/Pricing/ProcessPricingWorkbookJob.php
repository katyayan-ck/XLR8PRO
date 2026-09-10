<?php

/**
 * Path: app/Jobs/Vehicle/Pricing/ProcessPricingWorkbookJob.php
 *
 * Compatibility wrapper.
 * - runPrices=false or stage detecting/idle → DetectPricingWorkbookJob logic
 * - stage importing_prices / awaiting_addons → ImportPriceListsJob only
 */

namespace App\Jobs\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPricingWorkbookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public int $sessionId,
        public string $absolutePath,
        public array $sheetCodes,
        public string $wefDate,
        public bool $runPrices,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        $session = ImportSession::find($this->sessionId);
        $stage = (string) ($session?->current_stage ?? '');

        $priceStages = [
            ImportSession::STAGE_IMPORTING_PRICES,
            ImportSession::STAGE_AWAITING_ADDONS,
            'importing_prices',
            'awaiting_addons',
        ];

        if ($this->runPrices && in_array($stage, $priceStages, true)) {
            ImportPriceListsJob::dispatchSync(
                $this->sessionId,
                $this->absolutePath,
                $this->sheetCodes,
                $this->wefDate,
                $this->userId
            );

            return;
        }

        DetectPricingWorkbookJob::dispatchSync(
            $this->sessionId,
            $this->absolutePath,
            $this->sheetCodes,
            $this->wefDate,
            $this->userId
        );
    }
}
