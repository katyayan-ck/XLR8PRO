<?php

namespace Tests\Feature\Pricing;

use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\AccessoryScope;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\InsAddon;
use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Variant;
use App\Services\Utils\SynonymService;
use App\Services\Vehicle\Pricing\Engine\PricingRecalcService;
use App\Services\Vehicle\Pricing\Engine\RuleBook;
use App\Services\Vehicle\Pricing\Engine\SnapshotBuilder;
use App\Services\Vehicle\Pricing\Import\InsuranceWorkbookService;
use App\Services\Vehicle\Pricing\PricingSyncStamp;
use App\Services\Vehicle\Pricing\Rules\InsCompanyService;
use App\Services\Vehicle\Pricing\Rules\InsDefaultService;
use App\Support\PricingMaster\MasterRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * DEC-083 phases C–D: Insurance Rules (heads / IDV slots / add-on rates in one form), segment + permit company
 * preferences and the add-on master drive the engine; RTO rules round-trip their workbook; accessories import / export
 * the typed sheets, keep the permit (BUG-204) and never recalculate.
 */
class InsuranceAccessoryMastersTest extends TestCase
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
        $this->code = 'ZQI'.strtoupper(substr(uniqid(), -5)).'WH';
        $this->vehicleAndRules();
    }

    /** @return array<string, mixed> the PRIVATE NV payload built from the live rules */
    private function payload(): array
    {
        $variant = Variant::with('vehicleModel')->where('code', $this->code)->firstOrFail();
        $prices = Pricing::query()->where('model_code', $this->code)->where('is_active', true)->get()->keyBy('channel')->all();

        return collect((new SnapshotBuilder(new RuleBook(app(SynonymService::class))))->build($variant, $prices, '2026-10-01'))
            ->first(fn ($s) => $s['permit'] === 'PRIVATE' && $s['vin_type'] === 'NV')['payload'];
    }

    public function test_insurance_rule_form_writes_heads_slots_and_rates_and_rejects_bad_formulas(): void
    {
        app(InsCompanyService::class)->create(['code' => 'ZQTWO', 'name' => 'Zq Two General']);
        $master = MasterRegistry::get('insurance-rules');
        $rule = $master->save(['company' => 'ZQTWO', 'plan' => '1+3', 'permit' => 'Private', 'wheels' => 4, 'fuel_type' => 'ICE', 'cc_range' => '>1500',
            'head_od_factor' => '0.03', 'head_tp_basic' => '24596', 'head_tp_per_pass' => '100 x (Seat -1)', 'idv_1' => '95% of Invoice',
            'addon_NIL_DEP' => '0.004', 'addon_KEY' => '300', 'wef_date' => '2026-10-01']);

        $this->assertEqualsCanonicalizing(['od_factor' => 0.03, 'tp_basic' => 24596, 'tp_per_pass' => '100 x (Seat -1)'], $rule->heads);
        $this->assertSame([1, 95.0], [$rule->idvSlots->first()->year_no, (float) $rule->idvSlots->first()->idv_pct]);
        $rates = InsAddonRate::query()->where('base_rule_id', $rule->id)->where('is_active', true)->get()->keyBy('addon_slug');
        $this->assertSame(['idv_rate', 'flat'], [$rates['NIL_DEP']->rate_type, $rates['KEY']->rate_type]);
        $this->assertSame('0.004', $master->row($rule)['addon_NIL_DEP'] !== null ? (string) (float) $master->row($rule)['addon_NIL_DEP'] : null);

        $this->expectException(ValidationException::class);
        $master->save(['company' => 'ZQTWO', 'plan' => '1+1', 'permit' => 'Private', 'head_tp_basic' => 'rm -rf', 'wef_date' => '2026-10-01']);
    }

    public function test_segment_preference_orders_companies_and_the_addon_master_sets_the_default_combo(): void
    {
        $this->assertSame('ZQINS', $this->payload()['insurance']['default']['company'], 'fixture: model ANY row');

        app(InsCompanyService::class)->create(['code' => 'ZQTWO', 'name' => 'Zq Two General']);
        MasterRegistry::get('insurance-rules')->save(['company' => 'ZQTWO', 'plan' => '1+3', 'permit' => 'Private', 'wheels' => 4, 'fuel_type' => 'ICE', 'cc_range' => '>1500',
            'head_od_factor' => '0.03', 'head_tp_basic' => '24596', 'idv_1' => '95% of Invoice', 'addon_NIL_DEP' => '0.0035', 'addon_CONSUMABLES' => '0.001', 'wef_date' => '2026-10-01']);
        app(InsDefaultService::class)->create(['segment' => 'PV', 'model_code' => 'ANY', 'permit' => 'Private', 'insurance_company' => 'ZQTWO', 'priority' => 1, 'is_default' => true]);
        $this->assertSame('ZQTWO', $this->payload()['insurance']['default']['company'], 'the segment preference beats the ANY rows');

        InsAddon::query()->where('code', 'CONSUMABLES')->update(['is_default' => false]);
        $this->assertSame(['NIL_DEP'], $this->payload()['insurance']['default']['addons'], 'the add-on master decides the default combo');
    }

    public function test_the_insurance_workbook_never_touches_segment_preferences(): void
    {
        $pref = app(InsDefaultService::class)->create(['segment' => 'PV', 'model_code' => 'ANY', 'permit' => 'Private', 'insurance_company' => 'ZQINS', 'priority' => 1]);
        $workbook = app(InsuranceWorkbookService::class);
        $path = tempnam(sys_get_temp_dir(), 'ins').'.xlsx';
        $workbook->export($path, ['companies']);
        $this->assertSame(['Insurance Co.'], IOFactory::load($path)->getSheetNames());
        $workbook->import($path, '2026-10-01', null, ['companies']);
        $this->assertTrue((bool) $pref->fresh()->is_active, 'BUG-205');
        $this->assertSame(1, InsDefault::query()->where('is_active', true)->whereNotNull('segment')->where('id', $pref->id)->count());
        @unlink($path);
    }

    public function test_rto_rules_round_trip_their_workbook(): void
    {
        $master = MasterRegistry::get('rto-rules');
        $path = tempnam(sys_get_temp_dir(), 'rto').'.xlsx';
        $written = $master->export($path);
        $this->assertGreaterThanOrEqual(2, $written);
        $result = $master->import($path, '2026-11-01');
        $this->assertSame([$written, 0], [$result['written'], $result['rejected']], json_encode($result['issues']));
        $this->assertSame($written, RtoRule::query()->where('is_active', true)->whereDate('wef_date', '2026-11-01')->count());
        @unlink($path);
    }

    public function test_accessories_round_trip_typed_sheets_keep_the_permit_and_never_recalculate(): void
    {
        $recalc = app(PricingRecalcService::class);
        $recalc->run();   // clear the fixture's own marks
        $part = 'ZQ'.strtoupper(substr(uniqid(), -8));
        $master = MasterRegistry::get('accessories');
        $master->save(['part_no' => $part, 'type' => Accessory::TYPE_GPS_VLTD, 'item' => 'Tracker', 'mrp' => 5499, 'status' => '1']);
        MasterRegistry::get('accessory-scopes')->save(['part_no' => $part, 'segment_code' => 'PV', 'permit' => 'Private', 'status' => '1']);
        $this->assertSame([], $recalc->pending(), 'accessories never recalculate');
        $this->assertTrue(app(PricingSyncStamp::class)->isPending(), 'but the app must re-sync');

        $path = tempnam(sys_get_temp_dir(), 'acc').'.xlsx';
        $master->export($path);
        $this->assertContains('GPS VLTD', IOFactory::load($path)->getSheetNames());
        $result = $master->import($path);
        $this->assertGreaterThanOrEqual(1, $result['written']);
        $this->assertSame(['PV', 'Private'], [AccessoryScope::query()->where('part_no', $part)->value('segment_code'), AccessoryScope::query()->where('part_no', $part)->value('permit')], 'BUG-204');
        $this->assertSame(5499.0, (float) Accessory::query()->where('part_no', $part)->value('mrp'));
        @unlink($path);
    }
}
