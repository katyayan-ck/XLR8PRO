<?php

namespace App\Jobs\Vehicle\Pricing\Process;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\InsuranceWorkbookService;
use App\Services\Vehicle\Pricing\Import\RtoWorkbookService;
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
 * Step 6 — insurance or RTO rules import (DEC-073 / DEC-078): replaces that kind's rules at the WEF inside the session
 * change log (Discard restores the previous rules) and keeps the run's summary under stats.rules.{kind}.
 */
class ImportRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const KINDS = ['insurance', 'rto'];

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $sessionId, public string $kind, public string $uploadPath, public string $wefDate) {}

    public function handle(PricingSessionService $sessions, InsuranceWorkbookService $insurance, RtoWorkbookService $rto): void
    {
        $session = ImportSession::find($this->sessionId);
        if (! $session || $session->isTerminal()) {
            return;
        }
        $label = $this->kind === 'rto' ? 'RTO' : 'Insurance';
        $sessions->progress($session, ['step' => 'rules', 'state' => 'running', 'message' => "Reading the {$label} workbook…", 'error' => null]);
        $path = $sessions->uploadAbsolutePath($session, $this->uploadPath);
        $progress = fn (array $p) => $sessions->progress($session, ['message' => (string) ($p['message'] ?? '')]);

        try {
            $result = $sessions->record($session, fn () => $this->kind === 'rto'
                ? $rto->import($path, $this->wefDate, $progress)
                : $insurance->import($path, $this->wefDate, $progress));
        } catch (ValidationException $e) {
            $sessions->progress($session, ['step' => 'rules', 'state' => 'failed', 'error' => implode(' ', array_merge(...array_values($e->errors())))]);

            return;
        }

        $stats = (array) data_get($session->stats, 'rules', []);
        $run = ((int) data_get($stats, "{$this->kind}.run", 0)) + 1;
        $result = app(PricingIssueStore::class)->split($session, 'rules-'.$this->kind, $result);   // issues → file, preview in stats
        $stats[$this->kind] = ['run' => $run, 'at' => now()->toIso8601String(), 'wef' => $this->wefDate] + $result;
        $sessions->putStats($session, 'rules', $stats);
        $written = $this->kind === 'rto' ? $result['written'] : array_sum(array_column($result['sheets'], 'written'));
        $sessions->progress($session, ['step' => 'rules', 'state' => 'done', 'message' => "{$label} run {$run}: {$written} row(s) written."]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Pricing] rules import failed', ['session_id' => $this->sessionId, 'kind' => $this->kind, 'error' => $e->getMessage()]);
        $session = ImportSession::find($this->sessionId);
        if ($session) {
            app(PricingSessionService::class)->progress($session, ['step' => 'rules', 'state' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
