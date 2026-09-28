<?php

namespace Tests\Feature\Pricing;

use App\Models\User;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Services\Vehicle\Pricing\Engine\PriceListService;
use App\Services\Vehicle\Pricing\Engine\PricingContract;
use App\Services\Vehicle\Pricing\PricingHoldService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * DEC-081: each Price List shows the NV snapshots of its list valid on the date — own-permit rows for PV / CV / …,
 * the extra PASSENGER rows for Taxi, the csd channel for CSD — with the default on-road, hover break-ups and the
 * conditional discounts as columns; open to any logged-in user.
 */
class PriceListTest extends TestCase
{
    use DatabaseTransactions;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        Hold::query()->update(['is_held' => false]);
        Snapshot::query()->update(['expired_on' => '2000-01-01']);   // only this test's snapshots are valid
        $this->code = 'ZQL'.strtoupper(substr(uniqid(), -5)).'WH';
        $this->snapshot('PRIVATE', 'normal');
        $this->snapshot('PASSENGER', 'normal', 1100000);
        $this->snapshot('PRIVATE', 'csd', 900000);
    }

    private function snapshot(string $permit, string $channel, float $onRoad = 1200000, string $vin = 'NV'): void
    {
        $payload = PricingContract::normalize([
            'oem_code' => $this->code, 'custom_model' => 'Zeta', 'display_name' => 'Zeta Z8', 'colour' => 'WHITE', 'price_list' => $channel === 'csd' ? 'CSD' : 'PV',
            'vehicle_permit' => 'PRIVATE', 'permit' => $permit, 'channel' => $channel, 'vin_type' => $vin, 'wef_date' => '2026-10-01',
            'ex_showroom' => 1000000, 'on_road' => $onRoad,
            'dealer_charges' => ['incidental' => 500, 'fastag' => 600, 'trc' => 1000, 'rto_tape' => 1299, 'total' => 3399],
            'insurance' => ['default' => ['company' => 'ZQINS', 'plan' => '1+3', 'addons' => ['NIL_DEP'], 'od' => 10000, 'tp' => 5000, 'addons_total' => 1000, 'gst' => 2880, 'total' => 18880]],
            'rto' => ['rto_permit' => 'Private', 'tax' => 120000, 'surcharge' => 15000, 'total' => 137100],
            'discounts' => ['cash' => 20000, 'total' => 20000, 'corporate' => ['options' => [['category' => 'CAT A', 'total' => 5000]]]],
        ]);
        Snapshot::query()->forceCreate([
            'model_code' => $this->code, 'variant_code' => $this->code, 'channel' => $channel, 'vin_type' => $vin, 'permit' => $permit,
            'price_list' => $payload['price_list'], 'vehicle_permit' => 'PRIVATE', 'wef_date' => '2026-10-01', 'is_active' => true, 'payload' => $payload,
        ]);
    }

    public function test_each_list_takes_its_own_snapshots(): void
    {
        $lists = app(PriceListService::class);

        $pv = $lists->rows('pv', '2026-10-15');
        $this->assertCount(1, $pv['rows']);
        $row = $pv['rows'][0];
        $this->assertSame([$this->code, 'Zeta', 'Zeta Z8', 'WHITE', 1200000.0], [$row['code'], $row['model'], $row['variant'], $row['colour'], $row['on_road']]);
        $this->assertSame([500.0, 1600.0, 1299.0], [$row['incidental'], $row['fastag_trc'], $row['other_charges']], 'dealer charges split like the PDF');
        $this->assertSame(['title' => 'ZQINS 1+3', 'OD' => 10000.0, 'TP' => 5000.0, 'Add-ons (NIL_DEP)' => 1000.0, 'GST' => 2880.0], $row['insurance_tip']);
        $this->assertSame(['title' => 'Private · Regular', 'Road tax' => 120000.0, 'Surcharge' => 15000.0], $row['rto_tip']);
        $this->assertSame(['CAT A'], $pv['corporate']);
        $this->assertSame(['CAT A' => 5000.0], $row['corporate']);

        $this->assertSame(1100000.0, $lists->rows('taxi', '2026-10-15')['rows'][0]['on_road'], 'Taxi = the extra PASSENGER snapshot');
        $this->assertSame(900000.0, $lists->rows('csd', '2026-10-15')['rows'][0]['on_road']);
        $this->assertSame([], $lists->rows('cv', '2026-10-15')['rows']);
        $this->assertSame([], $lists->rows('pv', '2026-09-15')['rows'], 'nothing valid before the WEF');
        $this->assertSame(['pv' => 1, 'taxi' => 1, 'csd' => 1], array_filter($lists->counts('2026-10-15')));
    }

    public function test_a_new_publish_refreshes_the_cached_list_and_holds_are_flagged(): void
    {
        $lists = app(PriceListService::class);
        $this->assertCount(1, $lists->rows('pv', '2026-10-15')['rows']);

        $this->code = 'ZQM'.strtoupper(substr(uniqid(), -5)).'RD';
        $this->snapshot('PRIVATE', 'normal');
        $this->assertCount(2, $lists->rows('pv', '2026-10-15')['rows']);

        app(PricingHoldService::class)->hold(['PV']);
        $this->assertTrue($lists->rows('pv', '2026-10-15')['hold']);
        $this->assertFalse($lists->rows('csd', '2026-10-15')['hold']);
    }

    public function test_any_logged_in_user_can_open_the_lists(): void
    {
        $this->get(route('pricing.price-list.index'))->assertRedirect();

        $user = User::create(['username' => 'prl_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $this->actingAs($user, backpack_guard_name());
        $this->get(route('pricing.price-list.index', ['date' => '2026-10-15']))->assertOk()->assertSee('Taxi')->assertSee('LMM TZU');
        $this->get(route('pricing.price-list.show', ['list' => 'pv', 'date' => '2026-10-15']))->assertOk()->assertSee('pl-grid');
        $this->getJson(route('pricing.price-list.rows', ['list' => 'pv', 'date' => '2026-10-15']))->assertOk()->assertJsonPath('rows.0.code', $this->code);
        $this->get('/admin/pricing/price-list/nope')->assertNotFound();
    }
}
