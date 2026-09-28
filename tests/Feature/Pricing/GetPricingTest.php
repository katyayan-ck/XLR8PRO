<?php

namespace Tests\Feature\Pricing;

use App\Models\IAM\DeviceSession;
use App\Models\User;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Services\Vehicle\Pricing\Engine\PricingContract;
use App\Services\Vehicle\Pricing\Engine\PricingQueryService;
use App\Services\Vehicle\Pricing\PricingHoldService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-073 step 11 / DEC-080: getPricing serves the published snapshot valid on the date, applies the caller's
 * selections and recomputes the totals the way Calculate & Publish does; a held list answers ON_HOLD (API 423), a
 * missing price NOT_FOUND (API 404).
 */
class GetPricingTest extends TestCase
{
    use DatabaseTransactions;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        Hold::query()->update(['is_held' => false]);
        $this->code = 'ZQG'.strtoupper(substr(uniqid(), -5)).'WH';
        $this->snapshot('PRIVATE', '2026-10-01', 1000000);
        $this->snapshot('PASSENGER', '2026-10-01', 1000000);
    }

    private function snapshot(string $permit, string $wef, float $ex, ?string $expired = null): Snapshot
    {
        $payload = PricingContract::normalize([
            'oem_code' => $this->code, 'price_list' => 'PV', 'vehicle_permit' => 'PRIVATE', 'permit' => $permit, 'taxi_price' => 'YES',
            'channel' => 'normal', 'vin_type' => 'NV', 'wef_date' => $wef, 'ex_showroom' => $ex,
            'dealer_charges' => ['fastag' => 600, 'cod' => 5000, 'total' => 600],
            'rsa' => ['selected_years' => 1, 'selected_amount' => 2000, 'options' => [['years' => 1, 'amount' => 2000.0], ['years' => 2, 'amount' => 3000.0]]],
            'discounts' => ['cash' => 20000, 'total' => 20000,
                'exchange' => ['selected' => null, 'amount' => 0, 'options' => [['scheme' => 'Scrappage', 'oem' => 10000, 'dealer' => 5000, 'total' => 15000]]]],
            'insurance' => [
                'default' => ['company' => 'ZQINS', 'plan' => '1+3', 'addons' => ['NIL_DEP'], 'od' => 10000, 'tp' => 5000, 'addons_total' => 1000, 'gst' => 2880, 'total' => 18880, 'frozen' => true],
                'companies' => [['company' => 'ZQINS', 'default' => true, 'plans' => [[
                    'plan' => '1+3', 'od' => 10000, 'od_heads_total' => 0, 'tp' => 5000, 'tp_gst' => 900, 'od_gst_pct' => 18,
                    'addons' => [['code' => 'NIL_DEP', 'premium' => 1000, 'default' => true], ['code' => 'KEY', 'premium' => 500, 'default' => false]],
                ]]]],
            ],
            'rto' => ['total' => 100000, 'outside_state_trc' => 1000, 'options' => [['reg_type' => 'BH', 'total' => 80000.0]]],
            'tcs' => ['limit' => 1000000, 'rate' => 1],
        ]);

        return Snapshot::query()->forceCreate([
            'model_code' => $this->code, 'variant_code' => $this->code, 'channel' => 'normal', 'vin_type' => 'NV', 'permit' => $permit,
            'wef_date' => $wef, 'expired_on' => $expired, 'is_active' => $expired === null, 'payload' => $payload,
        ]);
    }

    public function test_default_answer_is_the_vehicles_own_permit_with_totals_recomputed(): void
    {
        $result = app(PricingQueryService::class)->getPricing(strtolower($this->code), ['wef_date' => '2026-10-15']);

        $this->assertTrue($result->ok);
        $p = $result->get('pricing');
        $this->assertSame(['PRIVATE', 'snapshot'], [$p['permit'], $p['source']]);
        // gross = 10,00,000 + 600 + 2,000 + 18,880 + 1,00,000; TCS 1% of (ex − 20,000) as ex ≥ limit
        $this->assertSame(1121480.0, $p['gross']);
        $this->assertSame(9800.0, $p['tcs']['amount']);
        $this->assertSame(1121480.0 + 9800 - 20000, $p['on_road']);
        $this->assertSame([], $p['errors']);
    }

    public function test_selections_reprice_rsa_insurance_rto_and_conditional_discounts(): void
    {
        $p = app(PricingQueryService::class)->getPricing($this->code, [
            'wef_date' => '2026-10-15', 'rsa_years' => 2, 'insurance' => ['addons' => ['NIL_DEP', 'KEY']],
            'reg_type' => 'BH', 'include_cod' => true, 'exchange' => 'scrappage',
        ])->get('pricing');

        $this->assertSame(3000.0, $p['rsa']['selected_amount']);
        $this->assertSame(['NIL_DEP', 'KEY'], $p['insurance']['default']['addons']);
        $this->assertSame(10000 + 5000 + 1500 + round(11500 * 0.18) + 900, $p['insurance']['default']['total']);
        $this->assertFalse($p['insurance']['default']['frozen'], 'a changed combo is no longer the frozen default');
        $this->assertSame(80000.0, $p['rto']['total'], 'BH option');
        $this->assertSame(5600.0, $p['dealer_charges']['total'], 'COD added on request');
        $this->assertSame([15000.0, 35000.0], [$p['discounts']['exchange']['amount'], $p['discounts']['total']]);
        $this->assertSame($p['gross'] + $p['tcs']['amount'] - 35000, $p['on_road']);
    }

    public function test_unknown_selections_are_reported_not_applied(): void
    {
        $p = app(PricingQueryService::class)->getPricing($this->code, ['wef_date' => '2026-10-15', 'rsa_years' => 5, 'corporate' => 'CAT Z'])->get('pricing');

        $this->assertSame(2000.0, $p['rsa']['selected_amount']);
        $this->assertCount(2, $p['errors']);
    }

    public function test_the_snapshot_valid_on_the_date_is_served(): void
    {
        Snapshot::query()->where('model_code', $this->code)->update(['expired_on' => '2026-11-01', 'is_active' => false]);
        $this->snapshot('PRIVATE', '2026-11-01', 1100000);

        $service = app(PricingQueryService::class);
        $this->assertSame(1000000.0, $service->getPricing($this->code, ['wef_date' => '2026-10-20'])->get('pricing')['ex_showroom']);
        $this->assertSame(1100000.0, $service->getPricing($this->code, ['wef_date' => '2026-11-05'])->get('pricing')['ex_showroom']);
        $this->assertSame('NOT_FOUND', $service->getPricing($this->code, ['wef_date' => '2026-09-01'])->code);
        $this->assertSame('NOT_FOUND', $service->getPricing($this->code, ['channel' => 'csd', 'wef_date' => '2026-10-20'])->code);
    }

    public function test_held_lists_answer_on_hold_and_taxi_hold_only_blocks_the_passenger_price(): void
    {
        $holds = app(PricingHoldService::class);
        $service = app(PricingQueryService::class);

        $holds->hold(['TAXI']);
        $this->assertTrue($service->getPricing($this->code, ['wef_date' => '2026-10-15'])->ok);
        $held = $service->getPricing($this->code, ['wef_date' => '2026-10-15', 'permit' => 'PASSENGER']);
        $this->assertSame('ON_HOLD', $held->code);
        $this->assertTrue($held->get('pricing')['hold']);

        $holds->hold(['PV']);
        $this->assertSame('ON_HOLD', $service->getPricing($this->code, ['wef_date' => '2026-10-15'])->code);
    }

    public function test_api_answers_in_the_envelope_with_404_and_423(): void
    {
        $token = $this->deviceToken();

        $this->getJson("/api/v1/vehicle/pricing/{$this->code}?wef_date=2026-10-15&rsa_years=2", ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pricing.oem_code', $this->code)
            ->assertJsonPath('data.pricing.rsa.selected_amount', 3000);

        $this->getJson('/api/v1/vehicle/pricing/NOSUCHCODE01?wef_date=2026-10-15', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(404)->assertJsonPath('code', 'PRICING_NOT_FOUND');

        app(PricingHoldService::class)->hold(['PV']);
        $this->getJson("/api/v1/vehicle/pricing/{$this->code}?wef_date=2026-10-15", ['Authorization' => "Bearer {$token}"])
            ->assertStatus(423)
            ->assertJsonPath('code', 'PRICING_ON_HOLD')
            ->assertJsonPath('data.pricing.hold', true);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/vehicle/pricing/{$this->code}")->assertUnauthorized();
    }

    public function test_admin_lookup_needs_the_view_permission(): void
    {
        $user = $this->user();
        $this->actingAs($user, backpack_guard_name())->get(route('pricing.lookup'))->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('PRC_WKFL_VIEW', 'web'));
        $this->actingAs($user->fresh(), backpack_guard_name())
            ->get(route('pricing.lookup', ['oem_code' => $this->code, 'wef_date' => '2026-10-15', 'rsa_years' => 2]))
            ->assertOk()->assertSee($this->code)->assertSee('₹3,000');
    }

    private function user(): User
    {
        return User::create(['username' => 'prc_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
    }

    private function deviceToken(): string
    {
        $user = $this->user();
        $device = DeviceSession::query()->create(['user_id' => $user->id, 'device_id' => 'zq-'.uniqid(), 'device_name' => 'test', 'platform' => 'android', 'last_active_at' => now()]);

        return $user->createToken('test', ['device_id:'.$device->device_id])->plainTextToken;
    }
}
