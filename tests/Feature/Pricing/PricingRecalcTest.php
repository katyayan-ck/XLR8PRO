<?php

namespace Tests\Feature\Pricing;

use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\RecalcRun;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Services\SystemSettingService;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Services\Vehicle\Pricing\Engine\PricingRecalcService;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Services\Vehicle\Pricing\PricingSyncStamp;
use App\Services\Vehicle\Pricing\Session\PricingChangeRecorder;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * DEC-083: a pricing parameter changed outside the Pricing Process marks the affected vehicles and the debounced run
 * republishes exactly those; changes inside a process and accessory changes do not; the app's sync stamp moves on a
 * publish, a vehicle master change and an accessory change.
 */
class PricingRecalcTest extends TestCase
{
    use BuildsPricedVehicle;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
        Hold::query()->update(['is_held' => false]);
        foreach ([RtoRule::class, InsBaseRule::class] as $rules) {
            $rules::query()->update(['is_active' => false]);
        }
        $this->code = 'ZQR'.strtoupper(substr(uniqid(), -5)).'WH';
        $this->vehicleAndRules();
    }

    private function rsa(): float
    {
        return (float) Snapshot::query()->where('model_code', $this->code)->where('permit', 'PRIVATE')->where('vin_type', 'NV')
            ->where('is_active', true)->firstOrFail()->payload['rsa']['selected_amount'];
    }

    public function test_a_master_change_republishes_the_affected_vehicles(): void
    {
        $recalc = app(PricingRecalcService::class);
        $this->assertArrayHasKey($this->code, $recalc->affected($recalc->pending()), 'the fixture\'s own writes are marked');

        $run = $recalc->run();
        $this->assertSame('done', $run->status);
        $this->assertNotContains($this->code, array_column((array) $run->failures, 'code'), 'rule sets mark every priced vehicle; this one publishes');
        $this->assertGreaterThanOrEqual(1, $run->published);
        $this->assertSame(2021.0, $this->rsa());
        $this->assertSame([], $recalc->pending(), 'pending cleared by the run');

        $rsa = Addon::query()->where('model_code', $this->vehicleModelCode())->where('addon_type', 'RSA')->where('tenure_years', 1)->firstOrFail();
        app(AddonService::class)->update($rsa, ['amount' => 2500]);
        $this->assertSame([$this->code], array_keys(array_intersect_key($recalc->affected($recalc->pending()), [$this->code => 1])));
        $recalc->run();
        $this->assertSame(2500.0, $this->rsa(), 'republished with the new RSA');
        $this->assertSame(2, RecalcRun::query()->where('created_at', '>=', now()->subMinute())->count());
    }

    public function test_changes_inside_a_process_and_accessory_changes_are_not_marked(): void
    {
        $recalc = app(PricingRecalcService::class);
        $recalc->run();
        $rsa = Addon::query()->where('model_code', $this->vehicleModelCode())->where('addon_type', 'RSA')->where('tenure_years', 1)->firstOrFail();

        $session = ImportSession::query()->create(['status' => ImportSession::STATUS_ACTIVE, 'current_stage' => 'addons', 'wef_date' => '2026-10-01']);
        app(PricingChangeRecorder::class)->within($session->id, fn () => app(AddonService::class)->update($rsa, ['amount' => 2600]));
        $this->assertSame([], $recalc->pending(), 'the process recalculates at step 9');
        $session->forceFill(['status' => ImportSession::STATUS_CANCELLED])->save();

        $stamp = app(PricingSyncStamp::class);
        $stamp->flush();
        Accessory::query()->create(['part_no' => 'ZQ'.substr(uniqid(), -8), 'type' => 'Essential', 'item' => 'Test mat', 'mrp' => 999, 'set_qty' => 1, 'bundle' => 0, 'status' => 1]);
        $this->assertSame([], $recalc->pending(), 'accessories never recalculate');
        $this->assertTrue($stamp->isPending(), 'but the app must re-sync');
    }

    public function test_the_run_waits_for_an_open_process_and_respects_holds(): void
    {
        $recalc = app(PricingRecalcService::class);
        $session = ImportSession::query()->create(['status' => ImportSession::STATUS_ACTIVE, 'current_stage' => 'addons', 'wef_date' => '2026-10-01']);
        $this->assertNull($recalc->run());
        $this->assertNotSame([], $recalc->pending(), 'kept for after the process');
        $session->forceFill(['status' => ImportSession::STATUS_CANCELLED])->save();

        app(PricingHoldService::class)->hold(['PV']);
        $run = $recalc->run();
        $this->assertSame([0, 1], [$run->published, $run->skipped]);
        $this->assertFalse(Snapshot::query()->where('model_code', $this->code)->exists(), 'held list not published');
    }

    public function test_the_sync_stamp_is_written_once_on_flush(): void
    {
        $stamp = app(PricingSyncStamp::class);
        $stamp->flush();
        $before = $stamp->lastUpdated();
        $this->travel(2)->seconds();

        app(PricingRecalcService::class)->run();   // publishes → touch()
        $this->assertTrue($stamp->isPending());
        $stamp->flush();
        $this->assertNotSame($before, $stamp->lastUpdated());
        $this->assertFalse($stamp->isPending());
        $this->assertArrayHasKey(PricingSyncStamp::KEY, app(SystemSettingService::class)->getPricingSettings(), 'served by the app settings endpoint');
    }

    private function vehicleModelCode(): string
    {
        return (string) app(VehicleService::class)->findByOemCode($this->code)->model_code;
    }
}
