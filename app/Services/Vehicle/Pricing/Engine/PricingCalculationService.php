<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Jobs\Vehicle\Pricing\Process\CalculateVehiclesJob;
use App\Models\Vehicle\Pricing\CalcResult;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Variant;
use App\Services\Utils\SynonymService;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use App\Services\Vehicle\VehicleCompleteness;
use App\Support\Result;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Step 9 — Calculate & Publish (DEC-073 / DEC-080), and the retry of step 10.
 *
 *  start($session)            HoldCheck → Calculating: records held / skipped vehicles, queues a batch of
 *                             CalculateVehiclesJob (CHUNK vehicles each); the batch's then() calls finish()
 *  calculate($session, $codes) one chunk: per vehicle build (SnapshotBuilder) → publish (SnapshotPublisher, one transaction)
 *                             → CalcResult published / failed (reason) / skipped; the first snapshot marks the session published
 *  finish($sessionId)         Calculating → Summary with the run's counts
 *  retryFailed($session)      re-queues the failed vehicles of this session
 *  counts($session)           published / failed / skipped / snapshots
 */
class PricingCalculationService
{
    public const CHUNK = 100;

    public function __construct(
        private readonly PricingSessionService $sessions,
        private readonly PricingHoldService $holds,
        private readonly VehicleCompleteness $completeness,
        private readonly SnapshotPublisher $publisher,
    ) {}

    public function start(ImportSession $session, ?int $userId = null): Result
    {
        if ($session->stage() !== PricingStage::HoldCheck) {
            return Result::fail('NOT_READY', 'Review the impact summary and the hold check first.');
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return Result::fail('RUNNING', 'A step is still running.');
        }
        [$codes, $skipped] = $this->targets();
        if ($codes === []) {
            return Result::fail('NOTHING', 'Nothing to calculate — no Active vehicle with a live price outside the held lists.');
        }
        CalcResult::query()->where('import_session_id', $session->id)->delete();   // a fresh run of this session
        foreach (array_chunk($skipped, 500, true) as $chunk) {
            $now = now();
            CalcResult::query()->insert(array_map(fn ($code, $row) => ['import_session_id' => $session->id, 'model_code' => $code, 'price_list' => $row[0],
                'status' => CalcResult::SKIPPED, 'snapshots' => 0, 'message' => $row[1], 'created_at' => $now, 'updated_at' => $now, 'created_by' => $userId], array_keys($chunk), $chunk));
        }
        $this->sessions->advance($session, PricingStage::Calculating, [], $userId);
        $batch = $this->dispatch($session, $codes);

        return Result::ok(['batch_id' => $batch->id, 'vehicles' => count($codes), 'skipped' => count($skipped)], 'Calculating '.number_format(count($codes)).' vehicle(s)…');
    }

    public function retryFailed(ImportSession $session): Result
    {
        if ($session->stage() !== PricingStage::Summary || ($session->progress['state'] ?? null) === 'running') {
            return Result::fail('NOT_READY', 'Retry is available on the summary once the run has finished.');
        }
        $codes = CalcResult::query()->where('import_session_id', $session->id)->where('status', CalcResult::FAILED)->pluck('model_code')->all();
        if ($codes === []) {
            return Result::fail('NOTHING', 'No failed vehicles to retry.');
        }
        $batch = $this->dispatch($session, $codes);

        return Result::ok(['batch_id' => $batch->id, 'vehicles' => count($codes)], 'Retrying '.count($codes).' vehicle(s)…');
    }

    /** @param list<string> $codes */
    public function calculate(ImportSession $session, array $codes): void
    {
        $book = new RuleBook(app(SynonymService::class));
        $builder = new SnapshotBuilder($book);
        $held = array_flip($this->holds->heldLists());
        $wef = (string) (data_get($session->stats, 'prices.wef') ?? $session->wef_date?->toDateString() ?? now()->toDateString());
        $variants = Variant::query()->with('vehicleModel')->whereIn('code', $codes)->get()->keyBy(fn (Variant $v) => strtoupper($v->code));
        $prices = Pricing::query()->whereIn('model_code', $codes)->where('is_active', true)->get()->groupBy(fn (Pricing $p) => strtoupper($p->model_code));

        foreach ($codes as $code) {
            $code = strtoupper($code);
            $variant = $variants->get($code);
            $live = ($prices->get($code) ?? collect())->keyBy('channel');
            $list = $live->get('normal')?->price_list;
            try {
                if (! $variant || ! $variant->is_active || ! $this->completeness->isComplete($variant)) {
                    $this->result($session, $code, $list, CalcResult::SKIPPED, 0, 'Not Active or incomplete.');

                    continue;
                }
                if (isset($held['CSD']) || isset($held['ALL'])) {
                    $live->forget('csd');
                }
                // TAXI on hold: drop the extra Passenger snapshots of taxi-priced vehicles (not a Passenger vehicle's own)
                $ownPermit = $book->keyvalueCodes['PERMIT'][(int) $variant->permit_id] ?? null;
                $snapshots = array_values(array_filter($builder->build($variant, $live->all(), $wef),
                    fn (array $s) => ! (isset($held['TAXI']) && $s['permit'] === 'PASSENGER' && $ownPermit !== 'PASSENGER')));
                $count = $this->publisher->publish($variant, $snapshots, $wef, $session->id);
                $this->sessions->markPublished($session);
                $this->result($session, $code, $list, CalcResult::PUBLISHED, $count, null);
            } catch (PricingFailure $e) {
                $this->result($session, $code, $list, CalcResult::FAILED, 0, $e->getMessage());
            } catch (\Throwable $e) {
                Log::error('[Pricing] calculate vehicle failed', ['session_id' => $session->id, 'code' => $code, 'error' => $e->getMessage()]);
                $this->result($session, $code, $list, CalcResult::FAILED, 0, 'Unexpected error: '.mb_substr($e->getMessage(), 0, 400));
            }
        }
    }

    public function finish(int $sessionId): void
    {
        $session = ImportSession::find($sessionId);
        if (! $session || $session->isTerminal()) {
            return;
        }
        $counts = $this->counts($session);
        $this->sessions->putStats($session, 'calculate', ['finished_at' => now()->toIso8601String()] + $counts);
        $this->sessions->advance($session, PricingStage::Summary);
        $this->sessions->progress($session, ['step' => 'calculate', 'state' => 'done', 'batch_id' => null,
            'message' => sprintf('%s published (%s snapshots), %s failed, %s skipped.', number_format($counts['published']), number_format($counts['snapshots']), number_format($counts['failed']), number_format($counts['skipped']))]);
    }

    /** @return array{published: int, failed: int, skipped: int, snapshots: int} */
    public function counts(ImportSession $session): array
    {
        $rows = CalcResult::query()->where('import_session_id', $session->id)->selectRaw('status, count(*) c, sum(snapshots) s')->groupBy('status')->get()->keyBy('status');

        return [
            'published' => (int) ($rows[CalcResult::PUBLISHED]->c ?? 0), 'failed' => (int) ($rows[CalcResult::FAILED]->c ?? 0),
            'skipped' => (int) ($rows[CalcResult::SKIPPED]->c ?? 0), 'snapshots' => (int) ($rows[CalcResult::PUBLISHED]->s ?? 0),
        ];
    }

    /**
     * Active vehicles with a live normal price → [codes to calculate, code => [list, reason] skipped (held lists)].
     *
     * @return array{0: list<string>, 1: array<string, array{0: string|null, 1: string}>}
     */
    private function targets(): array
    {
        $held = array_flip($this->holds->heldLists());
        $rows = Pricing::query()->where('channel', 'normal')->where('is_active', true)
            ->whereIn('model_code', Variant::query()->where('is_active', true)->select('code'))
            ->get(['model_code', 'price_list']);
        $codes = [];
        $skipped = [];
        foreach ($rows as $row) {
            $list = $row->price_list;
            if (isset($held['ALL']) || ($list !== null && isset($held[$list]))) {
                $skipped[strtoupper($row->model_code)] = [$list, 'List '.(isset($held['ALL']) ? 'ALL' : $list).' is on hold.'];
            } else {
                $codes[strtoupper($row->model_code)] = true;
            }
        }

        return [array_keys($codes), $skipped];
    }

    /** @param list<string> $codes */
    private function dispatch(ImportSession $session, array $codes): Batch
    {
        $sessionId = $session->id;
        $jobs = array_map(fn (array $chunk) => new CalculateVehiclesJob($sessionId, $chunk), array_chunk($codes, self::CHUNK));
        // progress first: with a sync queue the whole batch (and finish()) runs inside dispatch()
        $this->sessions->progress($session, ['step' => 'calculate', 'state' => 'running', 'batch_id' => null, 'error' => null,
            'message' => 'Calculating '.number_format(count($codes)).' vehicle(s)…']);
        $batch = Bus::batch($jobs)->name("pricing-calculate-{$sessionId}")->allowFailures()
            ->catch(fn (Batch $b, \Throwable $e) => Log::error('[Pricing] calculate batch job failed', ['session_id' => $sessionId, 'error' => $e->getMessage()]))
            ->finally(fn (Batch $b) => app(self::class)->finish($sessionId))   // runs whether or not a chunk job failed
            ->dispatch();
        $fresh = $session->fresh();
        if ($fresh && ($fresh->progress['state'] ?? null) === 'running') {
            $this->sessions->progress($fresh, ['batch_id' => $batch->id]);
        }

        return $batch;
    }

    private function result(ImportSession $session, string $code, ?string $list, string $status, int $snapshots, ?string $message): void
    {
        CalcResult::query()->updateOrCreate(['import_session_id' => $session->id, 'model_code' => $code],
            ['price_list' => $list, 'status' => $status, 'snapshots' => $snapshots, 'message' => $message]);
    }
}
