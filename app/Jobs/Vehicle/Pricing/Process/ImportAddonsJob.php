<?php

namespace App\Jobs\Vehicle\Pricing\Process;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\AddonDiscountWorkbookService;
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
 * Step 5 — add-ons & discounts import (DEC-073 / DEC-077): replaces the ticked groups at the WEF inside the session
 * change log (Discard restores the previous rows) and keeps the run's summary under stats.addons.
 */
class ImportAddonsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param list<string> $groups */
    public function __construct(public int $sessionId, public string $uploadPath, public array $groups, public string $wefDate) {}

    public function handle(PricingSessionService $sessions, AddonDiscountWorkbookService $workbook): void
    {
        $session = ImportSession::find($this->sessionId);
        if (! $session || $session->isTerminal()) {
            return;
        }
        $sessions->progress($session, ['step' => 'addons', 'state' => 'running', 'message' => 'Reading Addon-N-Discounts…', 'error' => null]);

        $result = $sessions->record($session, fn () => $workbook->import(
            $sessions->uploadAbsolutePath($session, $this->uploadPath),
            $this->groups,
            $this->wefDate,
            fn (array $p) => $sessions->progress($session, ['message' => "Importing {$p['sheet']}…"])
        ));

        $run = ((int) data_get($session->stats, 'addons.run', 0)) + 1;
        $result = app(PricingIssueStore::class)->split($session, 'addons', $result);   // issues → file, preview in stats
        $sessions->putStats($session, 'addons', ['run' => $run, 'at' => now()->toIso8601String(), 'wef' => $this->wefDate, 'groups' => $this->groups] + $result);
        $written = array_sum(array_column($result['sheets'], 'written'));
        $sessions->progress($session, ['step' => 'addons', 'state' => 'done', 'message' => "Run {$run}: {$written} row(s) written across ".count($result['sheets']).' sheet(s).']);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Pricing] add-ons import failed', ['session_id' => $this->sessionId, 'error' => $e->getMessage()]);
        $session = ImportSession::find($this->sessionId);
        if ($session) {
            app(PricingSessionService::class)->progress($session, ['step' => 'addons', 'state' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
