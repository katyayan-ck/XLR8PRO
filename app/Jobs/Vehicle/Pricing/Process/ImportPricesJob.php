<?php

namespace App\Jobs\Vehicle\Pricing\Process;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\PriceListImportService;
use App\Services\Vehicle\Pricing\Session\PricingIssueStore;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Step 4 — price import (DEC-073 / DEC-076): writes the chosen price lists at the given WEF inside the session change
 * log (Discard restores the previous prices exactly) and keeps the run's summary under stats.prices.
 */
class ImportPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param list<string> $sheets sheet titles */
    public function __construct(public int $sessionId, public string $uploadPath, public array $sheets, public string $wefDate) {}

    public function handle(PricingSessionService $sessions, PriceListImportService $importer): void
    {
        $session = ImportSession::find($this->sessionId);
        if (! $session || $session->isTerminal()) {
            return;
        }
        $sessions->progress($session, ['step' => 'prices', 'state' => 'running', 'message' => 'Reading price lists…', 'error' => null]);

        $result = $sessions->record($session, fn () => $importer->import(
            $sessions->uploadAbsolutePath($session, $this->uploadPath),
            $this->sheets,
            $this->wefDate,
            fn (array $p) => $sessions->progress($session, ['message' => "{$p['sheet']}: {$p['done']} of {$p['rows']} codes"])
        ));

        $run = ((int) data_get($session->stats, 'prices.run', 0)) + 1;
        $result = app(PricingIssueStore::class)->split($session, 'prices', $result);   // issues → file, preview in stats
        $sessions->putStats($session, 'prices', ['run' => $run, 'at' => now()->toIso8601String(), 'wef' => $this->wefDate, 'upload' => $this->uploadPath] + $result);
        $t = $result['totals'];
        $sessions->progress($session, ['step' => 'prices', 'state' => 'done', 'message' => sprintf(
            'Run %d: %d new, %d updated, %d unchanged, %d incomplete skipped.',
            $run, $t['inserted'] ?? 0, $t['updated'] ?? 0, $t['unchanged'] ?? 0, $t['skipped_incomplete'] ?? 0
        )]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Pricing] price import failed', ['session_id' => $this->sessionId, 'error' => $e->getMessage()]);
        $session = ImportSession::find($this->sessionId);
        if ($session) {
            app(PricingSessionService::class)->progress($session, ['step' => 'prices', 'state' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
