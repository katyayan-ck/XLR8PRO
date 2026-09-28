<?php

namespace App\Jobs\Vehicle\Pricing\Process;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\VehicleInfoWorkbookService;
use App\Services\Vehicle\Pricing\Session\PricingIssueStore;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Step 3 — Vehicle Info import (DEC-073): fills the masters from the uploaded sheet inside the session change log, then
 * stores this round's summary (completed / still incomplete / rejected + row issues) under stats.vehicle_info. The
 * session stays at Vehicle Info so the user can export → fix → re-import until they continue.
 */
class ImportVehicleInfoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $sessionId, public string $uploadPath) {}

    public function handle(PricingSessionService $sessions, VehicleInfoWorkbookService $workbook): void
    {
        $session = ImportSession::find($this->sessionId);
        if (! $session || $session->isTerminal()) {
            return;
        }
        $sessions->progress($session, ['step' => 'vehicle_info', 'state' => 'running', 'message' => 'Reading Vehicle Info…', 'error' => null]);

        try {
            $result = $sessions->record($session, fn () => $workbook->import(
                $sessions->uploadAbsolutePath($session, $this->uploadPath),
                fn (array $p) => $sessions->progress($session, ['message' => "{$p['rows']} rows — {$p['completed']} complete, {$p['incomplete']} incomplete, ".($p['rejected'] + $p['unknown']).' rejected'])
            ));
        } catch (ValidationException $e) {
            $sessions->progress($session, ['step' => 'vehicle_info', 'state' => 'failed', 'error' => implode(' ', array_merge(...array_values($e->errors())))]);

            return;
        }

        $round = ((int) data_get($session->stats, 'vehicle_info.round', 0)) + 1;
        $result = app(PricingIssueStore::class)->split($session, 'vehicle_info', $result);   // issues → file, preview in stats
        $sessions->putStats($session, 'vehicle_info', ['round' => $round, 'at' => now()->toIso8601String()] + $result);
        $sessions->progress($session, ['step' => 'vehicle_info', 'state' => 'done', 'message' => "Round {$round}: {$result['completed']} complete, {$result['incomplete']} incomplete, ".($result['rejected'] + $result['unknown']).' rejected.']);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Pricing] vehicle info import failed', ['session_id' => $this->sessionId, 'error' => $e->getMessage()]);
        $session = ImportSession::find($this->sessionId);
        if ($session) {
            app(PricingSessionService::class)->progress($session, ['step' => 'vehicle_info', 'state' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
