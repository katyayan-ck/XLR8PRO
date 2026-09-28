<?php

namespace Tests\Feature\Pricing;

use App\Models\User;
use App\Models\Vehicle\Pricing\CalcResult;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Models\Vehicle\Variant;
use App\Services\Platform\Settings\SettingsService;
use App\Services\Utils\SynonymService;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Services\Vehicle\Pricing\Addons\DealerChargeService;
use App\Services\Vehicle\Pricing\Engine\PricingContract;
use App\Services\Vehicle\Pricing\Engine\PricingFailure;
use App\Services\Vehicle\Pricing\Engine\RuleBook;
use App\Services\Vehicle\Pricing\Engine\SnapshotBuilder;
use App\Services\Vehicle\Pricing\Prices\PriceService;
use App\Services\Vehicle\Pricing\Rules\InsAddonRateService;
use App\Services\Vehicle\Pricing\Rules\InsBaseRuleService;
use App\Services\Vehicle\Pricing\Rules\InsDefaultService;
use App\Services\Vehicle\Pricing\Rules\InsIdvSlotService;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-073 step 9 / DEC-080: a snapshot's numbers follow the locked on-road rules with the user's decisions (consumer
 * scheme deducted, TCS on ex-showroom − discounts, 30% OD discount, 18% GST, COD by setting, accessories = the accessory
 * discount); a taxi-priced vehicle also gets a Passenger snapshot on the Taxi RTO rule; every contract key is present; the
 * queued run publishes, skips held lists, reports failures, retries and completes.
 */
class PricingCalculationTest extends TestCase
{
    use DatabaseTransactions;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
        Hold::query()->update(['is_held' => false]);
        foreach ([RtoRule::class, InsBaseRule::class] as $rules) {
            $rules::query()->update(['is_active' => false]);   // only this test's rules are live
        }
        $this->code = 'ZQC'.strtoupper(substr(uniqid(), -5)).'WH';
        $this->vehicleAndRules();
    }

    private function vehicleAndRules(bool $taxi = true): void
    {
        $vehicles = app(VehicleService::class);
        $vehicles->createStubFromPriceList($this->code, 'ZETA CALC', 'Z8', 'Price List PV');
        $vehicles->applyVehicleInfo($vehicles->findByOemCode($this->code), ['segment' => 'PV', 'sub_segment' => 'PV', 'fuel' => 'DIESEL', 'seating' => '7', 'wheels' => '4',
            'transmission' => 'Manual', 'drivetrain' => 'RWD', 'body_make' => 'SUV', 'body_type' => 'COMPLETE', 'cc' => '2184', 'gst_percent' => '40', 'permit' => 'PRIVATE',
            'taxi_price' => $taxi ? 'Y' : 'N', 'custom_model' => 'Zeta', 'custom_variant' => 'Z8', 'display_name' => 'Zeta Z8', 'colour_name' => 'WHITE', 'status' => 'ACTIVE']);
        app(PriceService::class)->create(['model_code' => $this->code, 'channel' => 'normal', 'price_list' => 'PV', 'wef_date' => '2026-10-01',
            'ex_showroom_price' => 2276500, 'assessable_value_with_freight' => 1557827, 'dealer_margin' => 68245,
            'curr_oem_scheme' => 75000, 'curr_dealer_cont' => 25000, 'curr_cash_discount' => 70000, 'curr_acc_discount' => 30000, 'old_cash_discount' => 10000]);

        $rto = app(RtoRuleService::class);
        $rto->create(['permit' => 'Private', 'wheels' => 4, 'reg_type' => 'Regular', 'fuel_type' => 'Diesel', 'cc_range' => '>1200', 'tax_basis' => '% of Rounded Up ESR',
            'tax_slab' => '0.12', 'tax_factor' => 0.12, 'surcharge' => '12.5% of Tax', 'surcharge_formula' => '12.5% of Tax', 'hypothecation' => 1500, 'green_tax' => 7500,
            'registration_fee' => 600, 'duplicate_tax_card' => 100, 'rto_tape' => 1000, 'wef_date' => '2026-10-01']);
        $rto->create(['permit' => 'Taxi', 'wheels' => 4, 'reg_type' => 'Regular', 'seater' => '0-13', 'tax_basis' => '% of Rounded Up ESR', 'tax_slab' => '0.1', 'tax_factor' => 0.1,
            'surcharge' => '12.5% of Tax', 'surcharge_formula' => '12.5% of Tax', 'hypothecation' => 1500, 'green_tax' => 1500, 'registration_fee' => 600, 'duplicate_tax_card' => 100, 'wef_date' => '2026-10-01']);

        $base = app(InsBaseRuleService::class);
        $private = $base->create(['company' => 'ZQINS', 'plan' => '1+3', 'permit' => 'Private', 'wheels' => 4, 'fuel_type' => 'ICE', 'cc_range' => '>1500', 'od_factor' => 0.03343,
            'tp_basic' => 24596, 'tp_pa_owner' => 632, 'heads' => ['od_factor' => 0.03343, 'tp_basic' => 24596, 'tp_pa_owner' => 632], 'wef_date' => '2026-10-01']);
        $passenger = $base->create(['company' => 'ZQINS', 'plan' => '1+1', 'permit' => 'Passenger', 'wheels' => 4, 'fuel_type' => 'ICE', 'cc_range' => '>1500', 'seating' => '1 to 7',
            'od_factor' => 0.0351, 'heads' => ['od_factor' => 0.0351, 'tp_basic' => 10523, 'tp_per_pass' => '1117 x (Seat -1)', 'tp_pa_owner' => 225], 'wef_date' => '2026-10-01']);
        foreach ([$private, $passenger] as $rule) {
            app(InsIdvSlotService::class)->create(['base_rule_id' => $rule->id, 'year_no' => 1, 'idv_basis' => '95% of Invoice']);
        }
        foreach ([['NIL_DEP', '0.0035'], ['CONSUMABLES', '0.001'], ['KEY', '300']] as [$slug, $rate]) {
            app(InsAddonRateService::class)->create(['base_rule_id' => $private->id, 'insurance_company' => 'ZQINS', 'permit' => 'Private', 'addon_slug' => $slug,
                'rate_type' => (float) $rate < 1 ? 'idv_rate' : 'flat', 'rate_value' => $rate, 'rate_text' => $rate, 'wef_date' => '2026-10-01']);
        }
        app(InsDefaultService::class)->create(['model_code' => 'ANY', 'permit' => 'Private', 'insurance_company' => 'ZQINS', 'priority' => 1, 'is_default' => true]);
        app(DealerChargeService::class)->create(['segment' => 'PV', 'permit' => 'Private', 'model_code' => $vehicles->findByOemCode($this->code)->model_code,
            'fastag' => 600, 'trc' => 1000, 'rto_tape' => 1299, 'cod' => 42000, 'wef_date' => '2026-10-01']);
        app(AddonService::class)->create(['addon_type' => 'RSA', 'segment' => 'PV', 'model_code' => $vehicles->findByOemCode($this->code)->model_code, 'tenure_years' => 1, 'amount' => 2021, 'wef_date' => '2026-10-01']);
        app(AddonService::class)->create(['addon_type' => 'RSA', 'segment' => 'PV', 'model_code' => $vehicles->findByOemCode($this->code)->model_code, 'tenure_years' => 2, 'amount' => 3019, 'wef_date' => '2026-10-01']);
    }

    /** @return list<array<string, mixed>> */
    private function build(): array
    {
        $variant = Variant::with('vehicleModel')->where('code', $this->code)->firstOrFail();
        $prices = Pricing::query()->where('model_code', $this->code)->where('is_active', true)->get()->keyBy('channel')->all();

        return (new SnapshotBuilder(new RuleBook(app(SynonymService::class))))->build($variant, $prices, '2026-10-01');
    }

    public function test_on_road_follows_the_agreed_rules_to_the_rupee(): void
    {
        $snapshots = collect($this->build())->keyBy(fn ($s) => $s['permit'].'|'.$s['vin_type']);

        $nv = $snapshots['PRIVATE|NV']['payload'];
        $this->assertSame(317095.0, $nv['rto']['total'], '12% of ESR rounded up (22,77,000) + 12.5% surcharge + fees');
        $this->assertSame(1000.0, $nv['rto']['outside_state_trc'], 'shown, not in the total');
        $this->assertEquals(['company' => 'ZQINS', 'plan' => '1+3', 'od' => 50609.0, 'tp' => 25228.0, 'addons_total' => 9732.0, 'gst' => 15402.0, 'total' => 100971.0],
            array_intersect_key($nv['insurance']['default'], array_flip(['company', 'plan', 'od', 'tp', 'addons_total', 'gst', 'total'])), 'IDV 95%, 30% OD discount, NilDep + Consumables, 18% GST');
        $this->assertSame(['NIL_DEP', 'CONSUMABLES'], $nv['insurance']['default']['addons']);
        $this->assertSame([2899.0, false, 42000.0], [$nv['dealer_charges']['total'], $nv['dealer_charges']['cod_in_total'], $nv['dealer_charges']['cod']], 'COD shown, not added (setting off)');
        $this->assertSame([1, 2021.0], [$nv['rsa']['selected_years'], $nv['rsa']['selected_amount']]);
        $this->assertSame([200000.0, 100000.0], [$nv['discounts']['total'], $nv['discounts']['consumer_scheme']]);
        $this->assertSame([30000.0, 30000.0], [$nv['accessories']['amount'], $nv['accessories']['discount']], 'default accessories = the accessory discount');
        $this->assertSame(20765.0, $nv['tcs']['amount'], '1% of (ex-showroom − discounts)');
        $this->assertSame(2550251.0, $nv['on_road']);
        $this->assertSame($nv['gross'] + $nv['tcs']['amount'] - $nv['discounts']['total'], $nv['on_road']);

        $ov = $snapshots['PRIVATE|OV']['payload'];
        $this->assertSame([10000.0, 0.0], [$ov['discounts']['cash'], $ov['discounts']['consumer_scheme']], 'OV uses the old-VIN block');

        $taxi = $snapshots['PASSENGER|NV'];
        $this->assertSame(['Taxi', 'Passenger'], [$taxi['rto_permit'], $taxi['insu_permit']], 'taxi → RTO Taxi, insurance Passenger (DEC-073)');
        $this->assertSame(227700.0 + round(227700 * 0.125) + 3700, $taxi['payload']['rto']['total']);
        $this->assertSame(10523.0 + 1117 * 6 + 225, $taxi['payload']['insurance']['default']['tp'], 'TP per passenger × (seats − 1)');
    }

    public function test_every_contract_key_is_present_and_cod_follows_the_setting(): void
    {
        $payload = $this->build()[0]['payload'];
        $this->assertSame($this->keys(PricingContract::defaults()), array_intersect($this->keys(PricingContract::defaults()), $this->keys($payload)));

        app(SettingsService::class)->set('pricing.dealer_charges.include_cod', true);
        $withCod = $this->build()[0]['payload'];
        $this->assertSame([44899.0, true], [$withCod['dealer_charges']['total'], $withCod['dealer_charges']['cod_in_total']]);
        $this->assertSame($payload['on_road'] + 42000, $withCod['on_road']);
    }

    public function test_a_vehicle_without_a_matching_rto_rule_fails_with_the_reason(): void
    {
        RtoRule::query()->update(['is_active' => false]);
        $this->expectException(PricingFailure::class);
        $this->expectExceptionMessage('No RTO rule matches (Private, 4W, DIESEL');
        $this->build();
    }

    public function test_the_queued_run_publishes_skips_held_lists_retries_and_completes(): void
    {
        $sessions = app(PricingSessionService::class);
        $session = $sessions->start(UploadedFile::fake()->create('Pricing.xlsx', 10), ['Price List PV'], '2026-10-01')->get('session');
        $sessions->advance($session, PricingStage::HoldCheck);
        Permission::firstOrCreate(['name' => 'PRC_WKFL_VIEW', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'PRC_WKFL_MANAGE', 'guard_name' => 'web']);
        $user = User::create(['username' => 'prc_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);
        $this->app['auth']->guard('backpack')->setUser($user);
        Hold::putOnHold('TAXI');

        $this->post(route('pricing.workflow.calculate-start'))->assertRedirect(route('pricing.workflow.summary', $session->id));   // sync queue: runs now

        $session->refresh();
        $this->assertSame(PricingStage::Summary, $session->stage());
        $this->assertNotNull($session->published_at);
        $result = CalcResult::query()->where('import_session_id', $session->id)->where('model_code', $this->code)->firstOrFail();
        $this->assertSame([CalcResult::PUBLISHED, 2], [$result->status, $result->snapshots], 'TAXI on hold: NV + OV Private only');
        $this->assertSame(['PRIVATE'], Snapshot::query()->where('model_code', $this->code)->where('is_active', true)->distinct()->pluck('permit')->all());
        $this->assertSame(2550251.0, (float) Snapshot::query()->where('model_code', $this->code)->where('vin_type', 'NV')->firstOrFail()->payload['on_road']);
        $this->get(route('pricing.workflow.summary', $session->id))->assertOk()->assertSee('Published vehicles')->assertSee('Mark complete');

        // a failure is recorded, then retried once the rule exists
        CalcResult::query()->where('import_session_id', $session->id)->where('model_code', $this->code)->update(['status' => CalcResult::FAILED, 'message' => 'test']);
        $this->post(route('pricing.workflow.retry-failed'))->assertSessionHas('success');
        $this->assertSame(CalcResult::PUBLISHED, CalcResult::query()->where('import_session_id', $session->id)->where('model_code', $this->code)->value('status'));

        $this->post(route('pricing.workflow.discard'))->assertSessionHas('warning');   // published: no discard
        $this->post(route('pricing.workflow.complete'), ['reopen_lists' => ['TAXI']])->assertRedirect(route('pricing.workflow.index'));
        $this->assertSame(PricingStage::Completed, $session->fresh()->stage());
        $this->assertFalse(Hold::isHeld('TAXI'));
        $this->assertNull($sessions->gate(), 'the gate is released');
    }

    /** @return list<string> dotted keys of the associative levels */
    private function keys(array $a, string $prefix = ''): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            $out[] = $prefix.$k;
            if (is_array($v) && $v !== [] && ! array_is_list($v)) {
                $out = array_merge($out, $this->keys($v, $prefix.$k.'.'));
            }
        }

        return $out;
    }
}
