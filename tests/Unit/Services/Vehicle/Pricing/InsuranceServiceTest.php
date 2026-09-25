<?php

namespace Tests\Unit\Services\Vehicle\Pricing;

use App\Services\Vehicle\Pricing\InsuranceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsuranceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private InsuranceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InsuranceService::class);
    }

    /**
     * @return int the inserted base_rule id
     */
    private function makeBaseRule(array $overrides = []): int
    {
        return DB::table('xlr8_vehicle_pricing_ins_base_rules')->insertGetId(array_merge([
            'company' => 'USGI',
            'plan' => '1+3',
            'od_years' => 1,
            'tp_years' => 3,
            'permit' => 'Private',
            'fuel_type' => 'ICE',
            'wheels' => 4,
            'cc_range' => '0-1000',
            'gvw_range' => null,
            'seating' => null,
            'od_factor' => 0.03039,
            'tp_basic' => 6521,
            'is_active' => 1,
            'wef_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makeIdvSlot(int $baseRuleId, int $yearNo, string $basis): void
    {
        DB::table('xlr8_vehicle_pricing_ins_idv_slots')->insert([
            'base_rule_id' => $baseRuleId,
            'year_no' => $yearNo,
            'idv_basis' => $basis,
            'idv_pct' => (float) filter_var($basis, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_quote_computes_od_as_idv_sum_times_od_factor(): void
    {
        $ruleId = $this->makeBaseRule();
        $this->makeIdvSlot($ruleId, 1, '95% of Invoice');

        $result = $this->service->quote([
            'permit' => 'Private',
            'fuel' => 'ICE',
            'wheels' => 4,
            'cc' => 900,
            'invoice' => 500000,
        ]);

        $plan = $result['companies'][0]['plans'][0];
        $this->assertSame(475000.0, $plan['idv_sum']);
        $this->assertSame(14435.25, $plan['od']);
        $this->assertSame(6521.0, $plan['tp']);
        $this->assertSame(20956.25, $plan['standard_total']);
    }

    public function test_quote_sums_all_year_slots_for_a_multi_year_plan(): void
    {
        $ruleId = $this->makeBaseRule(['plan' => '3+3', 'od_years' => 3, 'od_factor' => 0]);
        $this->makeIdvSlot($ruleId, 1, '95% of Invoice');
        $this->makeIdvSlot($ruleId, 2, '80% of Invoice');
        $this->makeIdvSlot($ruleId, 3, '70% of Invoice');

        $result = $this->service->quote([
            'permit' => 'Private',
            'fuel' => 'ICE',
            'wheels' => 4,
            'cc' => 900,
            'invoice' => 500000,
        ]);

        $plan = $result['companies'][0]['plans'][0];
        // 500000 * (0.95 + 0.80 + 0.70) = 1,225,000
        $this->assertSame(1225000.0, $plan['idv_sum']);
    }

    public function test_quote_only_matches_the_cc_band_containing_the_actual_cc(): void
    {
        $this->makeBaseRule(['cc_range' => '0-1000']);
        $this->makeBaseRule(['cc_range' => '1001-1500', 'od_factor' => 0.03191, 'tp_basic' => 10640]);

        $result = $this->service->quote([
            'permit' => 'Private',
            'fuel' => 'ICE',
            'wheels' => 4,
            'cc' => 900,
            'invoice' => 500000,
        ]);

        $this->assertCount(1, $result['companies'][0]['plans']);
        $this->assertSame(6521.0, $result['companies'][0]['plans'][0]['tp']);
    }

    public function test_quote_excludes_a_cc_band_that_does_not_contain_the_actual_cc(): void
    {
        $this->makeBaseRule(['cc_range' => '1001-1500']);

        $result = $this->service->quote([
            'permit' => 'Private',
            'fuel' => 'ICE',
            'wheels' => 4,
            'cc' => 900,
            'invoice' => 500000,
        ]);

        $this->assertSame([], $result['companies']);
    }

    public function test_quote_filters_by_fuel_type(): void
    {
        $this->makeBaseRule(['fuel_type' => 'EV']);

        $result = $this->service->quote([
            'permit' => 'Private',
            'fuel' => 'ICE',
            'wheels' => 4,
            'cc' => 900,
            'invoice' => 500000,
        ]);

        $this->assertSame([], $result['companies']);
    }

    public function test_quote_resolves_default_company_from_ins_defaults_insurance_company_column(): void
    {
        $this->makeBaseRule(['company' => 'ICICI']);
        DB::table('xlr8_vehicle_pricing_ins_defaults')->insert([
            'model_code' => 'ANY',
            'permit' => 'Private',
            'insurance_company' => 'ICICI',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->quote([
            'permit' => 'Private',
            'fuel' => 'ICE',
            'wheels' => 4,
            'cc' => 900,
            'invoice' => 500000,
        ]);

        $this->assertSame('ICICI', $result['default_company']);
        $this->assertSame('1+3', $result['default_plan']);
    }

    public function test_quote_computes_nildep_addon_from_a_flat_addon_rate(): void
    {
        $ruleId = $this->makeBaseRule();
        $this->makeIdvSlot($ruleId, 1, '95% of Invoice');
        DB::table('xlr8_vehicle_pricing_ins_addon_rates')->insert([
            'insurance_company' => 'USGI',
            'permit' => 'Private',
            'addon_slug' => 'NILDEP',
            'addon_name' => 'Nil Depreciation',
            'rate_type' => 'FLAT',
            'rate_value' => 500,
            'applies_on' => 'BASE',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->quote([
            'permit' => 'Private',
            'fuel' => 'ICE',
            'wheels' => 4,
            'cc' => 900,
            'invoice' => 500000,
        ]);

        $plan = $result['companies'][0]['plans'][0];
        $this->assertSame(500.0, $plan['nildep']);
        $this->assertSame($plan['base'] + 500, $plan['standard_total']);
    }

    public function test_quote_returns_no_companies_when_nothing_matches(): void
    {
        $result = $this->service->quote([
            'permit' => 'Private',
            'fuel' => 'ICE',
            'wheels' => 4,
            'invoice' => 500000,
        ]);

        $this->assertSame([], $result['companies']);
        $this->assertSame(0.0, $result['selected_total']);
    }
}
