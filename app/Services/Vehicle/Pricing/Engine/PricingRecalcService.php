<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Jobs\Vehicle\Pricing\RecalculateAffectedJob;
use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\CalcResult;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\RecalcRun;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Automatic recalculation after a pricing parameter changes outside the Pricing Process (DEC-083).
 *
 *   mark($model)   the observer reports a saved / deleted pricing row → its scope (segment / model / vehicle, or every
 *                  vehicle for rule sets) and WEF join the pending list; dispatchPending() (request end, after each queued
 *                  job) queues one debounced RecalculateAffectedJob
 *   run()          the job: resolves the pending scopes to Active priced vehicles, republishes their snapshots at
 *                  max(change WEF, today, the vehicle's latest live snapshot WEF), logs a RecalcRun
 *
 * Changes made inside a Pricing Process are not marked (the process recalculates everything at step 9).
 */
class PricingRecalcService
{
    public const PENDING_KEY = 'pricing.recalc.pending';

    public const DEBOUNCE_SECONDS = 60;

    private const ANY = ['', 'ANY', 'ALL', '*'];

    private bool $toDispatch = false;

    private bool $hooked = false;

    public function __construct(
        private readonly PricingCalculationService $calculation,
        private readonly PricingSessionService $sessions,
    ) {}

    /** The scope of a changed row, or null when it does not affect prices. */
    public function scopeOf(Model $model): ?array
    {
        $scope = match (true) {
            $model instanceof Pricing => ['variant' => $model->model_code],
            $model instanceof Addon => ['segment' => $model->segment, 'model' => $model->model_code, 'variant' => $model->variant_code],
            $model instanceof Discount => ['model' => $model->model_code, 'variant' => $model->variant_code],
            $model instanceof DealerCharge => ['segment' => $model->segment, 'model' => $model->model_code],
            $model instanceof Variant => ['variant' => $model->code],
            $model instanceof VehicleModel => ['model' => $model->code],
            $model instanceof Segment => ['segment' => $model->code],
            $model instanceof SubSegment => ['segment' => $model->segment_code ?? null],
            default => ['all' => true],   // rule sets (RTO, insurance, TCS, permit map, insurance masters)
        };
        $wef = $model->getAttribute('wef_date');

        return $scope + ['wef' => $wef ? substr((string) ($wef instanceof \DateTimeInterface ? $wef->format('Y-m-d') : $wef), 0, 10) : null,
            'reason' => class_basename($model).' #'.$model->getKey()];
    }

    public function mark(Model $model): void
    {
        $scope = $this->scopeOf($model);
        if ($scope === null) {
            return;
        }
        Cache::lock(self::PENDING_KEY.'.lock', 10)->block(5, function () use ($scope) {
            $pending = (array) Cache::get(self::PENDING_KEY, []);
            $pending[md5(json_encode(array_diff_key($scope, ['reason' => 1])) ?: '')] = $scope;
            Cache::put(self::PENDING_KEY, $pending, now()->addDay());
        });
        $this->toDispatch = true;
        if (! $this->hooked) {
            $this->hooked = true;
            app()->terminating(fn () => $this->dispatchPending());
        }
    }

    /** Queue the debounced job once for everything marked so far (request end / after each queued job / tests). */
    public function dispatchPending(): void
    {
        if (! $this->toDispatch) {
            return;
        }
        $this->toDispatch = false;
        RecalculateAffectedJob::dispatch()->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
    }

    /** Re-queue marks that waited for a Pricing Process to close (PricingSessionService complete / discard). */
    public function resumeAfterProcess(): void
    {
        if ($this->pending() !== []) {
            RecalculateAffectedJob::dispatch()->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function pending(): array
    {
        return (array) Cache::get(self::PENDING_KEY, []);
    }

    /** Run the pending recalculation; null when nothing is pending or a Pricing Process is open (retried later). */
    public function run(): ?RecalcRun
    {
        if ($this->sessions->gate()) {
            return null;   // the open process owns the prices; its complete / discard re-queues the pending marks
        }
        $pending = Cache::lock(self::PENDING_KEY.'.lock', 10)->block(5, function () {
            $pending = (array) Cache::get(self::PENDING_KEY, []);
            Cache::forget(self::PENDING_KEY);

            return $pending;
        });
        if ($pending === []) {
            return null;
        }

        $wefs = $this->affected($pending);
        $run = RecalcRun::query()->create(['status' => 'running', 'started_at' => now(), 'vehicles' => count($wefs),
            'reasons' => array_slice(array_values(array_unique(array_column($pending, 'reason'))), 0, 50)]);
        $latest = Snapshot::query()->whereIn('model_code', array_keys($wefs))->where('is_active', true)
            ->selectRaw('model_code, max(wef_date) w')->groupBy('model_code')->pluck('w', 'model_code')
            ->mapWithKeys(fn ($w, $code) => [strtoupper((string) $code) => substr((string) $w, 0, 10)]);
        $today = now()->toDateString();
        $counts = ['published' => 0, 'failed' => 0, 'skipped' => 0, 'snapshots' => 0];
        $failures = [];
        try {
            foreach (array_chunk(array_keys($wefs), PricingCalculationService::CHUNK) as $chunk) {
                $this->calculation->publishVehicles($chunk,
                    fn (string $code) => max($wefs[$code] ?? $today, $today, (string) ($latest[$code] ?? $today)),
                    null,
                    function (string $code, ?string $list, string $status, int $snapshots, ?string $message) use (&$counts, &$failures) {
                        $counts[$status]++;
                        $counts['snapshots'] += $snapshots;
                        if ($status === CalcResult::FAILED && count($failures) < 200) {
                            $failures[] = ['code' => $code, 'message' => (string) $message];
                        }
                    });
            }
            $run->fill($counts + ['status' => 'done', 'failures' => $failures, 'finished_at' => now()])->save();
        } catch (\Throwable $e) {
            $run->fill($counts + ['status' => 'failed', 'failures' => array_merge($failures, [['code' => '*', 'message' => mb_substr($e->getMessage(), 0, 400)]]), 'finished_at' => now()])->save();
            throw $e;
        }

        return $run;
    }

    /**
     * Pending scopes → Active vehicles with a live normal price, each with the latest change WEF touching it.
     *
     * @param  array<string, array<string, mixed>>  $pending
     * @return array<string, string> code => WEF (Y-m-d)
     */
    public function affected(array $pending): array
    {
        $out = [];
        foreach ($pending as $scope) {
            $query = Variant::query()->where('is_active', true)
                ->whereIn('code', Pricing::query()->where('channel', 'normal')->where('is_active', true)->select('model_code'));
            if (empty($scope['all'])) {
                $filtered = false;
                foreach (['segment' => 'segment_code', 'model' => 'model_code', 'variant' => 'code'] as $key => $column) {
                    $values = $this->values($scope[$key] ?? null);
                    if ($values !== null) {
                        $query->whereIn($column, $values);
                        $filtered = true;
                    }
                }
                if (! $filtered && ! array_key_exists('segment', $scope) && ! array_key_exists('model', $scope)) {
                    continue;   // a vehicle-only scope without a code
                }
            }
            $wef = (string) ($scope['wef'] ?? now()->toDateString());
            foreach ($query->pluck('code') as $code) {
                $code = strtoupper((string) $code);
                $out[$code] = max($out[$code] ?? $wef, $wef);
            }
        }

        return $out;
    }

    /** @return list<string>|null null = no filter (blank / ANY / ALL / * anywhere in the list) */
    private function values(mixed $raw): ?array
    {
        $parts = array_map(fn ($v) => strtoupper(trim($v)), explode(',', (string) $raw));

        return array_intersect($parts, self::ANY) !== [] ? null : $parts;
    }
}
