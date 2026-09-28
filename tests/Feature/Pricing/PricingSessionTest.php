<?php

namespace Tests\Feature\Pricing;

use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Variant;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * DEC-073: one open pricing process at a time; Discard undoes exactly that session's changes (inserted rows removed,
 * expired / updated rows restored) and only before publish; Complete reopens chosen holds and releases the gate.
 */
class PricingSessionTest extends TestCase
{
    use DatabaseTransactions;

    private PricingSessionService $sessions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->sessions = app(PricingSessionService::class);
        // no other process may be open in the test copy
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
    }

    private function start(array $holds = []): ImportSession
    {
        $result = $this->sessions->start(UploadedFile::fake()->create('Pricing.xlsx', 10), ['Price List PV'], now()->toDateString(), $holds);
        $this->assertTrue($result->ok, $result->message);

        return $result->get('session');
    }

    public function test_only_one_process_may_be_open(): void
    {
        $first = $this->start();

        $second = $this->sessions->start(UploadedFile::fake()->create('Pricing.xlsx', 10), ['Price List PV'], now()->toDateString());

        $this->assertFalse($second->ok);
        $this->assertSame('ALREADY_ACTIVE', $second->code);
        $this->assertSame($first->id, $this->sessions->gate()->id);
        $this->assertSame(PricingStage::Detecting, $first->stage());
    }

    public function test_discard_undoes_exactly_the_sessions_own_changes(): void
    {
        $addons = app(AddonService::class);
        $before = $addons->create(['addon_type' => 'RSA', 'segment' => 'PV', 'model_code' => 'ZQ-SESS', 'tenure_years' => 1, 'amount' => 1500, 'wef_date' => now()->subMonth()->toDateString()]);
        $session = $this->start(['LMM']);

        $stub = $this->sessions->record($session, function () use ($addons) {
            $addons->expireActive(now()->toDateString(), ['addon_type' => 'RSA', 'model_code' => 'ZQ-SESS']);
            $addons->create(['addon_type' => 'RSA', 'segment' => 'PV', 'model_code' => 'ZQ-SESS', 'tenure_years' => 1, 'amount' => 1999, 'wef_date' => now()->toDateString()]);

            return app(VehicleService::class)->createStubFromPriceList('ZQSESS'.strtoupper(substr(uniqid(), -4)).'WH', 'Zeta Sess', 'Base', 'Price List PV')['variant'];
        });
        $this->assertFalse((bool) $before->fresh()->is_active, 'expired inside the session');
        $this->assertTrue(Hold::isHeld('LMM'));

        $result = $this->sessions->discard($session);

        $this->assertTrue($result->ok, $result->message);
        $this->assertTrue((bool) $before->fresh()->is_active, 'the expired row is live again');
        $this->assertNull($before->fresh()->expired_on);
        $this->assertSame(1, Addon::where('model_code', 'ZQ-SESS')->count(), 'the session\'s new row is gone');
        $this->assertNull(Variant::withTrashed()->find($stub->id), 'the stub the session created is gone');
        $this->assertFalse(Hold::isHeld('LMM'), 'the hold set at start is undone');
        $this->assertNull($this->sessions->gate(), 'the gate is released');
    }

    public function test_a_published_process_cannot_be_discarded_only_completed(): void
    {
        $session = $this->start(['CV']);
        $this->sessions->markPublished($session);

        $this->assertSame('PUBLISHED', $this->sessions->discard($session)->code);
        $this->assertSame('NOT_READY', $this->sessions->complete($session)->code, 'complete needs the summary step');

        $this->sessions->advance($session, PricingStage::Summary);
        $result = $this->sessions->complete($session, ['CV']);

        $this->assertTrue($result->ok);
        $this->assertSame(PricingStage::Completed, $session->fresh()->stage());
        $this->assertFalse(app(PricingHoldService::class)->isHeld('CV'), 'held list reopened on completion');
        $this->assertNull($this->sessions->gate());
    }

    public function test_stages_only_move_forward_except_back_to_vehicle_info(): void
    {
        $session = $this->start();
        $this->sessions->advance($session, PricingStage::Addons);

        $this->sessions->advance($session, PricingStage::Prices);
        $this->assertSame(PricingStage::Addons, $session->stage());

        $this->sessions->advance($session, PricingStage::VehicleInfo);
        $this->assertSame(PricingStage::VehicleInfo, $session->stage());
    }
}
