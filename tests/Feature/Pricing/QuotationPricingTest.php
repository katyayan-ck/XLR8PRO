<?php

namespace Tests\Feature\Pricing;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\User;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Services\Sales\Quotation\QuotationPricingService;
use App\Services\Vehicle\Pricing\Engine\PricingContract;
use App\Services\Vehicle\Pricing\Engine\PricingQueryService;
use App\Services\Vehicle\Pricing\PricingHoldService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-082: the quotation reads published prices (no mock) — the adapter maps getPricing onto the screen's shape, a held
 * list or an unpriced vehicle cannot be saved, and the server re-validates the discount gate and TCS.
 */
class QuotationPricingTest extends TestCase
{
    use DatabaseTransactions;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        Hold::query()->update(['is_held' => false]);
        $this->code = 'ZQQ'.strtoupper(substr(uniqid(), -5)).'WH';
        $this->snapshot('PRIVATE');
        $this->snapshot('PASSENGER', 1100000);
    }

    private function snapshot(string $permit, float $ex = 1200000): void
    {
        $payload = PricingContract::normalize([
            'oem_code' => $this->code, 'price_list' => 'PV', 'vehicle_permit' => 'PRIVATE', 'permit' => $permit, 'taxi_price' => 'YES',
            'channel' => 'normal', 'vin_type' => 'NV', 'wef_date' => now()->subDay()->toDateString(), 'ex_showroom' => $ex,
            'dealer_charges' => ['fastag' => 600, 'trc' => 1000, 'rto_tape' => 1299, 'cod' => 5000, 'total' => 2899],
            'rsa' => ['selected_years' => 1, 'selected_amount' => 2000, 'options' => [['years' => 1, 'amount' => 2000.0], ['years' => 2, 'amount' => 3000.0]]],
            'discounts' => ['consumer_scheme' => 50000, 'cash' => 20000, 'rsa' => 2000, 'total' => 72000,
                'exchange' => ['options' => [['scheme' => 'Scrappage', 'total' => 15000.0]]], 'corporate' => ['options' => [['category' => 'CAT A', 'total' => 5000.0]]]],
            'insurance' => ['companies' => [['company' => 'ZQINS', 'default' => true, 'plans' => [[
                'plan' => '1+3', 'od' => 10000, 'od_heads_total' => 0, 'tp' => 5000, 'gst' => 2700, 'tp_gst' => 900, 'od_gst_pct' => 18,
                'addons' => [['code' => 'NIL_DEP', 'name' => 'Nil Depreciation', 'premium' => 1000, 'default' => true], ['code' => 'KEY', 'name' => 'Key Protect', 'premium' => 500, 'default' => false]],
            ]]]]],
            'rto' => ['total' => $permit === 'PRIVATE' ? 150000 : 120000],
            'tcs' => ['limit' => 1000000, 'rate' => 1],
        ]);
        Snapshot::query()->forceCreate([
            'model_code' => $this->code, 'variant_code' => $this->code, 'channel' => 'normal', 'vin_type' => 'NV', 'permit' => $permit, 'price_list' => 'PV',
            'vehicle_permit' => 'PRIVATE', 'wef_date' => now()->subDay()->toDateString(), 'is_active' => true, 'payload' => $payload,
        ]);
    }

    public function test_published_prices_map_onto_the_screen_shape_per_permit(): void
    {
        $result = app(QuotationPricingService::class)->forVehicle($this->code);

        $this->assertTrue($result->ok);
        $screen = $result->get('screen');
        $this->assertSame([['type' => 'Private', 'default' => true], ['type' => 'Passenger', 'default' => false]], $screen['permit']);
        $this->assertSame(1200000.0, $screen['receivables']['exShowroom']);
        $this->assertSame([150000.0, 120000.0], array_column($screen['receivables']['RTO']['TAX'], 'amount'), 'one RTO entry per published permit');
        $this->assertSame(1000.0, $screen['receivables']['RTO']['TRC']);
        $heads = $screen['receivables']['insurance'][0]['companies'][0]['price'];
        $this->assertSame(['Basic OD + TP', 17700.0, 'M'], array_values($heads[0]), 'OD + TP + GST');
        $this->assertSame(['Nil Depreciation', 1180.0, 'M'], array_values($heads[1]), 'default add-on is mandatory, GST-inclusive');
        $this->assertSame('O', $heads[2]['Nature']);
        $this->assertSame(0.0, $screen['receivables']['COD'], 'COD only when it is in the on-road total');
        $this->assertSame(['1 Year', '2 Year', 'No RSA'], array_column($screen['receivables']['rsa'], 'title'));
        $this->assertSame(50000.0, $screen['deductibles']['oem-schemes'][0]['amount']);
        $this->assertSame(['amount' => 2000.0, 'type' => 'CN1'], $screen['deductibles']['other-cash-discount'], 'RSA discount');
        $this->assertSame(['limit' => 1000000.0, 'rate' => 1.0], $screen['receivables']['tcs']);
        $this->assertTrue($screen['live']);

        $this->assertSame('NOT_FOUND', app(QuotationPricingService::class)->forVehicle('NOSUCHCODE01')->code);
        $queries = app(PricingQueryService::class);
        $this->assertNull($queries->holdMessage($this->code));
        app(PricingHoldService::class)->hold(['PV']);
        $this->assertNotNull($queries->holdMessage($this->code), 'booking guard sees the hold');
        $held = app(QuotationPricingService::class)->forVehicle($this->code);
        $this->assertSame('ON_HOLD', $held->code);
        $this->assertNotNull($held->get('screen'), 'held prices still show, with the reason');
    }

    public function test_server_revalidates_the_gate_and_tcs(): void
    {
        $service = app(QuotationPricingService::class);
        $tcs = ['limit' => 1000000.0, 'rate' => 1.0];
        $base = ['ex_showroom_price' => '1200000', 'cash_scheme_oem' => '50000', 'cash_scheme_oem_type' => 'INV_OE',
            'group_a_amount' => '50000', 'group_a_type' => 'INV_OE', 'dealer_discount' => '60000', 'dealer_discount_type' => 'CN1'];

        $ok = $service->validateSubmission($base + ['tcs' => '11500.00'], $tcs);
        $this->assertTrue($ok->ok, $ok->message);
        $this->assertSame([1150000.0, 11500.0], [$ok->get('invoice'), $ok->get('tcs')]);

        $this->assertSame('GATE', $service->validateSubmission(['dealer_discount' => '40000'] + $base + ['tcs' => '11500'], $tcs)->code, 'CN below the INV scheme');
        $this->assertSame('TCS', $service->validateSubmission($base + ['tcs' => '12000'], $tcs)->code);
        $this->assertTrue($service->validateSubmission(['ex_showroom_price' => '900000', 'tcs' => 'N/A'], $tcs)->ok, 'below the limit: no TCS');
    }

    public function test_endpoints_and_save_use_published_prices(): void
    {
        $user = User::create(['username' => 'quo_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $this->actingAs($user, backpack_guard_name());
        $this->getJson(route('sales.quotation.pricing', ['oem_code' => $this->code]))->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('SLS_QUOT_CREATE', 'web'));
        $this->actingAs($user->fresh(), backpack_guard_name());
        $this->getJson(route('sales.quotation.pricing', ['oem_code' => $this->code]))->assertOk()->assertJsonPath('screen.oem_code', $this->code);
        $this->getJson(route('sales.quotation.pricing', ['oem_code' => 'NOSUCHCODE01']))->assertNotFound();
        $this->getJson(route('sales.quotation.vehicle-options', ['level' => 'segment']))->assertOk();

        $enquiry = Enquiry::query()->withoutGlobalScopes()->latest('id')->first() ?? $this->markTestSkipped('No enquiry in the test copy.');
        $count = Quotation::query()->withoutGlobalScopes()->count();
        $this->post(route('sales.quotation.store'), ['enquiry_id' => $enquiry->id, 'ex_showroom_price' => '1200000'])->assertRedirect();
        $this->assertSame($count, Quotation::query()->withoutGlobalScopes()->count(), 'no vehicle chosen → not saved');

        app(PricingHoldService::class)->hold(['PV']);
        $this->getJson(route('sales.quotation.pricing', ['oem_code' => $this->code]))->assertStatus(423)->assertJsonPath('hold', true);
        $this->post(route('sales.quotation.store'), ['enquiry_id' => $enquiry->id, 'oem_code' => $this->code, 'ex_showroom_price' => '1200000', 'tcs' => '12000'])->assertRedirect();
        $this->assertSame($count, Quotation::query()->withoutGlobalScopes()->count(), 'held list → not saved');

        app(PricingHoldService::class)->reopen(['PV']);
        $this->post(route('sales.quotation.store'), ['enquiry_id' => $enquiry->id, 'oem_code' => $this->code, 'ex_showroom_price' => '1200000', 'tcs' => '12000'])->assertRedirect();
        $quotation = Quotation::query()->withoutGlobalScopes()->latest('id')->first();
        $this->assertSame($count + 1, Quotation::query()->withoutGlobalScopes()->count());
        $this->assertSame($this->code, $quotation->standard_data['pricing']['oem_code'], 'published pricing stored with the quotation');
        $this->assertSame(1200000.0, (float) $quotation->standard_data['pricing']['contract']['ex_showroom']);
    }

    public function test_create_page_renders_the_picker_without_mock_prices(): void
    {
        $enquiry = Enquiry::query()->withoutGlobalScopes()->latest('id')->first() ?? $this->markTestSkipped('No enquiry in the test copy.');
        $user = User::create(['username' => 'quo_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('SLS_QUOT_CREATE', 'web'));

        $this->actingAs($user, backpack_guard_name())->get(route('sales.quotation.create', ['id' => $enquiry->id]))
            ->assertOk()->assertSee('pick_colour')->assertDontSee('bev6Premium')->assertDontSee('mock_enquiry_no');
    }
}
