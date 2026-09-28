<?php

namespace App\Jobs\Vehicle\Pricing\Process;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\PriceListDetectService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Step 2 — Detect (DEC-073): reads the session's selected price lists and creates FRESH INCOMPLETE stubs for new OEM
 * codes (recorded, so Discard removes them), then moves the session to Vehicle Info with the per-sheet report.
 */
class DetectPriceListsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $sessionId) {}

    public function handle(PricingSessionService $sessions, PriceListDetectService $detect): void
    {
        $session = ImportSession::find($this->sessionId);
        if (! $session || $session->isTerminal()) {
            return;
        }
        $sessions->progress($session, ['step' => 'detect', 'state' => 'running', 'message' => 'Reading price lists…', 'error' => null]);

        $report = $sessions->record($session, fn () => $detect->detect(
            $sessions->uploadAbsolutePath($session),
            (array) $session->selected_sheets,
            fn (array $p) => $sessions->progress($session, ['message' => "{$p['sheet']}: {$p['done']} codes read"])
        ));

        $totals = ['created' => 0, 'known' => 0, 'csd_unknown' => 0, 'duplicates' => 0, 'errors' => 0];
        foreach ($report as $sheet) {
            foreach (['created', 'known', 'csd_unknown', 'duplicates'] as $k) {
                $totals[$k] += (int) ($sheet[$k] ?? 0);
            }
            $totals['errors'] += count($sheet['errors'] ?? []);
        }

        $sessions->advance($session, PricingStage::VehicleInfo, ['detect' => ['sheets' => $report, 'totals' => $totals]]);
        $sessions->progress($session, ['step' => 'detect', 'state' => 'done', 'message' => "{$totals['created']} new vehicle(s), {$totals['known']} known."]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Pricing] detect failed', ['session_id' => $this->sessionId, 'error' => $e->getMessage()]);
        $session = ImportSession::find($this->sessionId);
        if ($session) {
            app(PricingSessionService::class)->progress($session, ['step' => 'detect', 'state' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
