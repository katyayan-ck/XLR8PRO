<?php

namespace Tests\Feature\Pricing;

use App\Jobs\Vehicle\Pricing\Process\ImportRulesJob;
use App\Models\User;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Models\Vehicle\Pricing\InsIdvSlot;
use App\Models\Vehicle\Pricing\PermitMap;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Services\Vehicle\Pricing\Import\InsuranceWorkbookService;
use App\Services\Vehicle\Pricing\Import\RtoWorkbookService;
use App\Services\Vehicle\Pricing\Rules\InsBaseRuleService;
use App\Services\Vehicle\Pricing\Rules\InsDefaultService;
use App\Services\Vehicle\Pricing\Rules\PermitMapService;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-073 step 6 / DEC-078: the standalone RTO and Insurance workbooks import the reference layouts losslessly —
 * formulas and ranges checked, the previous set expired, the reference "IDV n" / typo labels read, companies resolved to
 * model codes — and the step cannot continue while a rule set is missing.
 */
class PricingRulesImportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
    }

    /** @param array<string, list<list<mixed>>> $sheets */
    private function book(array $sheets): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $book->createSheet()->setTitle($title)->fromArray($rows, null, 'A1', true);
        }
        $path = Storage::disk('local')->path('rules-'.uniqid().'.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function test_rto_rows_keep_their_formulas_and_ranges_and_replace_the_previous_set(): void
    {
        $old = app(RtoRuleService::class)->create(['permit' => 'Private', 'wheels' => 4, 'is_active' => true, 'wef_date' => '2026-09-01']);
        $head = array_values(RtoWorkbookService::COLUMNS);
        $path = $this->book(['RTO' => [$head,
            ['Private', 4, 'BH', null, null, null, 'Diesel', null, '0-999999', 1000, '% of Rounded Up Ass Value + Dlr Mgn WO GST', '(10% * 1.25 * 2) / 15', '0% of Tax', 1500, 2500, 600, 100, 0, 2500, 'Sum of All'],
            ['Private', 4, 'BH', null, null, null, 'Petrol', null, '1000000 - 200000', 1000, '% of Rounded Up Ass Value + Dlr Mgn WO GST', '(10% * 1.25 * 2) / 15', '0% of Tax', 1500, 2500, 600, 100, 0, 2500, 'Sum of All'],
            ['Passenger', 3, 'Regular', null, null, '<4', 'Diesel', null, null, 1000, 'Fixed', 3000, '12.5% of Tax', 1500, 500, 600, 100, 0, 0, 'Sum of All'],
            ['Goods', 4, 'Regular', 'Complete', 'lots', null, 'Diesel', null, null, 1000, '% of Rounded Up ESR', 0.1, '12.5% of Tax', 1500, 1500, 600, 100, 0, null, 'Sum of All'],
            ['Taxi', 4, 'Regular', null, null, '0-13', null, null, null, 1000, '% of Rounded Up ESR', '10% x Engine', '12.5% of Tax', 1500, 1500, 600, 100, 0, 0, 'Sum of All'],
        ]]);

        $result = app(RtoWorkbookService::class)->import($path, '2026-10-01');

        $this->assertSame([5, 3, 2, 1], [$result['rows'], $result['written'], $result['rejected'], $result['expired']]);
        $this->assertFalse((bool) $old->fresh()->is_active);
        $bh = RtoRule::query()->where('is_active', true)->where('reg_type', 'BH')->where('assessable_range', '0-999999')->firstOrFail();
        $this->assertSame(['(10% * 1.25 * 2) / 15', '0.016667', '1000.00', '2500.00'], [$bh->tax_slab, $bh->tax_factor, $bh->rto_tape, $bh->penalty]);
        $pass = RtoRule::query()->where('is_active', true)->where('permit', 'Passenger')->firstOrFail();
        $this->assertSame(['<4', 'Fixed', '3000.000000', '12.50', '12.5% of Tax'], [$pass->seater, $pass->tax_basis, $pass->tax_factor, $pass->surcharge, $pass->surcharge_formula]);
        $reasons = implode(' | ', array_column($result['issues'], 'reason'));
        $this->assertStringContainsString('"1000000 - 200000" runs backwards', $reasons);
        $this->assertStringContainsString('"lots" is not a range', $reasons);
        $this->assertStringContainsString('"ENGINE" is not a known variable', $reasons);
    }

    public function test_insurance_reference_layout_imports_and_round_trips(): void
    {
        $model = 'Zq Loader '.strtoupper(substr(uniqid(), -4));
        $code = app(VehicleService::class)->createStubFromPriceList('ZQL'.strtoupper(substr(uniqid(), -5)).'WH', $model, 'X', 'Price List CV')['variant']->model_code;
        $premiumHead = array_merge(['Inv', 'IDV 1', 'IDV 2', 'IDV 3', 'IDV 1', 'IDV 2', 'IDV 3', null, 'Insu Co.', 'Permit', 'Wheels', 'Fuel', 'CC', 'GVW', 'Seatng', 'Plan'],
            array_values(InsuranceWorkbookService::HEADS), array_values(InsuranceWorkbookService::ADDONS));
        $heads = [0.0126, '5% x OD', '15% x (OD + LPG)', 2539, '1214 x (Setat -1)', 60, 225, null, 50, null];
        $addons = array_merge([0.0035004085, 0.0010015718], array_fill(0, 8, null), [500, 999], array_fill(0, 5, null));
        $path = $this->book([
            'Insurance Co.' => [['Model', 'Permit', 'Insu Co. 1', 'Insu Co. 2', 'Insu Co. 3'], [strtoupper($model), 'Passanger', 'USGI', 'ICICI', null], ['Nobody Model', 'Goods', 'USGI', null, null]],
            'Insu Premium' => [$premiumHead, array_merge([999900, 949905, null, null, '95% of Invoice', '80% of Invoice', null, null, 'USGI', 'Passenger', 3, 'ICE', null, null, '1 to 7', '1+1'], $heads, $addons)],
            'Permit Map' => [['Vehicle Permit', 'Wheels', 'RTO Permit', 'Insurance Permit', 'Label'], ['PASSENGER', 4, 'Taxi', 'Passenger', 'Taxi 4W'], ['MISC', 'ANY', 'Ambulance', 'Misc', 'Misc']],
            'Rules' => [['RTO Permit', 'Insu Permit'], ['Taxi - T (4 Wheeler)', 'Passenger']],
        ]);
        $service = app(InsuranceWorkbookService::class);

        $result = $service->import($path, '2026-10-01');

        $this->assertSame(['USGI', 'ICICI'], InsDefault::getCompanies($code, 'Passenger'), 'model name → code; "Passanger" → Passenger; Co. 1 first');
        $this->assertSame(1, $result['sheets']['companies']['rejected'], 'unknown model rejected');
        $rule = InsBaseRule::query()->where('is_active', true)->where('company', 'USGI')->where('permit', 'Passenger')->where('wheels', 3)->firstOrFail();
        $this->assertSame(['1 to 7', '0.012600', '2539.00', '225.00'], [$rule->seating, $rule->od_factor, $rule->tp_basic, $rule->tp_pa_owner]);
        $this->assertSame('1214 x (Setat -1)', $rule->heads['tp_per_pass']);
        $this->assertSame(['95% of Invoice', '80% of Invoice'], InsIdvSlot::query()->where('base_rule_id', $rule->id)->orderBy('year_no')->pluck('idv_basis')->all(), 'the basis columns, not the calculator amounts');
        $rates = InsAddonRate::query()->where('base_rule_id', $rule->id)->pluck('rate_type', 'addon_slug')->all();
        $this->assertSame(['NIL_DEP' => 'idv_rate', 'CONSUMABLES' => 'idv_rate', 'KEY' => 'flat', 'RSA' => 'flat'], $rates);
        $this->assertSame('0.0035004085', InsAddonRate::query()->where('base_rule_id', $rule->id)->where('addon_slug', 'NIL_DEP')->value('rate_text'), 'exact rate kept');
        $this->assertSame('Taxi', app(PermitMapService::class)->resolve('PASSENGER', 4)?->rto_permit);
        $this->assertSame(2, PermitMap::query()->where('is_active', true)->count(), 'the permit map sheet replaced the seeded rows');

        $out = Storage::disk('local')->path('ins-export.xlsx');
        $service->export($out);
        $again = $service->import($out, '2026-11-01');
        $this->assertSame(0, $again['sheets']['premium']['rejected']);
        $rule2 = InsBaseRule::query()->where('is_active', true)->where('company', 'USGI')->where('permit', 'Passenger')->where('wheels', 3)->firstOrFail();
        $this->assertEquals($rule->heads, $rule2->heads, 'heads survive the round trip');
        $this->assertSame(4, InsAddonRate::query()->where('base_rule_id', $rule2->id)->count());
    }

    public function test_the_step_needs_both_rule_sets_before_continuing(): void
    {
        Queue::fake();
        $sessions = app(PricingSessionService::class);
        $session = $sessions->start(UploadedFile::fake()->create('Pricing.xlsx', 10), ['Price List PV'], '2026-10-01')->get('session');
        $sessions->advance($session, PricingStage::Rules);
        Permission::firstOrCreate(['name' => 'PRC_WKFL_VIEW', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'PRC_WKFL_MANAGE', 'guard_name' => 'web']);
        $user = User::create(['username' => 'prr_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);
        $this->app['auth']->guard('backpack')->setUser($user);
        RtoRule::query()->update(['is_active' => false]);
        InsBaseRule::query()->update(['is_active' => false]);

        $this->get(route('pricing.workflow.rules-form'))->assertOk()->assertSee('None stored — import required');
        $this->post(route('pricing.workflow.rules-continue'))->assertSessionHas('warning');

        $file = UploadedFile::fake()->createWithContent('RTO-Rules.xlsx', (string) file_get_contents($this->book(['RTO' => [array_values(RtoWorkbookService::COLUMNS)]])));
        $this->post(route('pricing.workflow.rules'), ['kind' => 'rto', 'file' => $file, 'wef_date' => '2026-10-01'])->assertRedirect(route('pricing.workflow.rules-form'));
        Queue::assertPushed(ImportRulesJob::class, fn ($job) => $job->kind === 'rto' && $job->sessionId === $session->id);

        $sessions->progress($session->fresh(), ['state' => 'done']);
        app(RtoRuleService::class)->create(['permit' => 'Private', 'is_active' => true]);
        app(InsBaseRuleService::class)->create(['company' => 'USGI', 'plan' => '1+3', 'permit' => 'Private', 'is_active' => true]);
        app(InsDefaultService::class)->create(['model_code' => 'ANY', 'insurance_company' => 'USGI', 'is_active' => true]);
        $this->get(route('pricing.workflow.rules-export', ['kind' => 'rto', 'sessionId' => $session->id]))->assertOk()->assertDownload();
        $this->post(route('pricing.workflow.rules-continue'))->assertRedirect(route('pricing.workflow.impact-summary-view', $session->id));
        $this->assertSame(PricingStage::Impact, $session->fresh()->stage());
    }
}
