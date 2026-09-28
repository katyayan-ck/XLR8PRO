<?php

namespace Tests\Feature\Pricing;

use App\Models\User;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Prices\PriceService;
use App\Services\Vehicle\Pricing\Session\PricingImpactService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-073 steps 7–8 / DEC-079: the impact summary counts exactly what the session changed (new / activated vehicles,
 * prices new / up / down) and what will calculate per list; holding a list takes it out and is undone by Discard.
 */
class PricingImpactTest extends TestCase
{
    use DatabaseTransactions;

    private ImportSession $session;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
        Hold::query()->update(['is_held' => false]);   // start with no list on hold
        $this->tag = strtoupper(substr(uniqid(), -5));
        $sessions = app(PricingSessionService::class);
        $this->session = $sessions->start(UploadedFile::fake()->create('Pricing.xlsx', 10), ['Price List PV'], '2026-10-01')->get('session');
    }

    private function complete(string $code): void
    {
        $vehicles = app(VehicleService::class);
        $vehicles->applyVehicleInfo($vehicles->findByOemCode($code), ['segment' => 'PV', 'sub_segment' => 'PV', 'fuel' => 'DIESEL', 'seating' => '7', 'wheels' => '4',
            'transmission' => 'Manual', 'drivetrain' => 'RWD', 'body_make' => 'SUV', 'body_type' => 'COMPLETE', 'cc' => '2184', 'gst_percent' => '40', 'permit' => 'PRIVATE',
            'taxi_price' => 'Y', 'custom_model' => 'Zeta', 'custom_variant' => 'ZX', 'display_name' => 'Zeta ZX', 'colour_name' => 'WHITE', 'status' => 'ACTIVE']);
    }

    private function scenario(): array
    {
        $vehicles = app(VehicleService::class);
        $prices = app(PriceService::class);
        $old = "ZQI{$this->tag}WH";                          // existed before the process, with a price
        $vehicles->createStubFromPriceList($old, 'ZETA IMPACT', 'ZX', 'Price List PV');
        $this->complete($old);
        $before = $prices->create(['model_code' => $old, 'channel' => 'normal', 'price_list' => 'PV', 'wef_date' => '2026-09-01', 'ex_showroom_price' => 1000000]);

        $sessions = app(PricingSessionService::class);
        $sessions->record($this->session, function () use ($vehicles, $prices, $old, $before) {
            $new = "ZQJ{$this->tag}WH";                      // detected + completed in this process
            $vehicles->createStubFromPriceList($new, 'ZETA IMPACT', 'ZY', 'Price List PV');
            $this->complete($new);
            $vehicles->createStubFromPriceList("ZQK{$this->tag}WH", 'ZETA IMPACT', 'ZZ', 'Price List PV');   // stays incomplete
            $prices->expire($before, '2026-10-01');
            $prices->create(['model_code' => $old, 'channel' => 'normal', 'price_list' => 'PV', 'wef_date' => '2026-10-01', 'ex_showroom_price' => 1050000]);
            $prices->create(['model_code' => $new, 'channel' => 'normal', 'price_list' => 'PV', 'wef_date' => '2026-10-01', 'ex_showroom_price' => 900000]);
        });
        $sessions->advance($this->session, PricingStage::Impact);

        return [$old, "ZQJ{$this->tag}WH", "ZQK{$this->tag}WH"];
    }

    public function test_summary_counts_exactly_what_the_session_changed(): void
    {
        [, , $incomplete] = $this->scenario();

        $summary = app(PricingImpactService::class)->summary($this->session->fresh());

        $this->assertSame(2, $summary['vehicles']['new'], 'two stubs inserted in the session');
        $this->assertSame(1, $summary['vehicles']['activated'], 'the one completed in the session');
        $this->assertSame(['new' => 1, 'up' => 1, 'down' => 0, 'other' => 0], $summary['prices']['normal']);
        $this->assertGreaterThanOrEqual(2, $summary['calculate']['lists']['PV']['vehicles']);
        $this->assertGreaterThanOrEqual(2, $summary['calculate']['lists']['TAXI']['vehicles'], 'taxi_price YES vehicles get a Passenger snapshot');
        $this->assertContains($incomplete, array_column(app(PricingImpactService::class)->incomplete(), 0));
    }

    public function test_hold_check_takes_a_list_out_and_discard_undoes_it(): void
    {
        $this->scenario();
        Permission::firstOrCreate(['name' => 'PRC_WKFL_VIEW', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'PRC_WKFL_MANAGE', 'guard_name' => 'web']);
        $user = User::create(['username' => 'pri_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);
        $this->app['auth']->guard('backpack')->setUser($user);

        $this->get(route('pricing.workflow.impact-summary-view', $this->session->id))->assertOk()->assertSee('Vehicles to calculate')->assertSee('Reviewed — continue to hold check');
        $this->post(route('pricing.workflow.impact-continue'))->assertRedirect(route('pricing.workflow.impact-summary-view', $this->session->id));
        $this->assertSame(PricingStage::HoldCheck, $this->session->fresh()->stage());

        $before = app(PricingImpactService::class)->summary($this->session->fresh())['calculate']['vehicles'];
        $this->post(route('pricing.workflow.hold-check'), ['action' => 'hold', 'hold_lists' => ['PV']])->assertSessionHas('success');
        $summary = app(PricingImpactService::class)->summary($this->session->fresh());
        $this->assertTrue($summary['calculate']['lists']['PV']['held']);
        $this->assertLessThan($before, $summary['calculate']['vehicles']);
        $this->get(route('pricing.workflow.impact-incomplete', $this->session->id))->assertOk()->assertDownload();

        app(PricingSessionService::class)->discard($this->session->fresh());
        $this->assertFalse(Hold::isHeld('PV'), 'the hold set in the process is undone by Discard');
    }
}
