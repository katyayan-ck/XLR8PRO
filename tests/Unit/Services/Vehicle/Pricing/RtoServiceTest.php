<?php

namespace Tests\Unit\Services\Vehicle\Pricing;

use App\Services\Vehicle\Pricing\RtoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RtoServiceTest extends TestCase
{
    use DatabaseTransactions;

    private RtoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RtoService::class);
    }

    private function makeRule(array $overrides = []): int
    {
        return DB::table('xlr8_vehicle_pricing_rto_rules')->insertGetId(array_merge([
            'permit' => 'Goods',
            'wheels' => 4,
            'fuel_type' => 'DIESEL',
            'gvw_range' => '0-3000',
            'tax_basis' => '% of Rounded Up ESR',
            'tax_slab' => '0.1',
            'tax_factor' => 0,
            'surcharge' => 0,
            'surcharge_formula' => '12.5% of Tax',
            'hypothecation' => 1500,
            'green_tax' => 1500,
            'registration_fee' => 600,
            'duplicate_tax_card' => 100,
            'fitness' => 0,
            'penalty' => 0,
            'rto_tape' => 1000,
            'is_active' => 1,
            'wef_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_quote_computes_tax_from_percent_of_rounded_up_esr_pattern(): void
    {
        $this->makeRule();

        $result = $this->service->quote([
            'permit' => 'Goods',
            'wheels' => 4,
            'fuel' => 'DIESEL',
            'ex_showroom' => 500000,
        ]);

        $this->assertSame(50000.0, $result['tax']);
    }

    public function test_quote_computes_surcharge_from_percent_of_tax_pattern(): void
    {
        $this->makeRule();

        $result = $this->service->quote([
            'permit' => 'Goods',
            'wheels' => 4,
            'fuel' => 'DIESEL',
            'ex_showroom' => 500000,
        ]);

        // tax = 50000, surcharge = 50000 * 12.5% = 6250
        $this->assertSame(6250.0, $result['surcharge']);
    }

    public function test_quote_sums_all_nine_charge_heads_into_total(): void
    {
        $this->makeRule();

        $result = $this->service->quote([
            'permit' => 'Goods',
            'wheels' => 4,
            'fuel' => 'DIESEL',
            'ex_showroom' => 500000,
        ]);

        // 50000 + 6250 + 1500 + 1500 + 600 + 100 + 0 + 0 + 1000
        $this->assertSame(60950.0, $result['total']);
    }

    public function test_quote_zeroes_tax_and_logs_a_warning_for_an_unrecognized_tax_basis(): void
    {
        $this->makeRule(['tax_basis' => 'Some Unknown Formula']);
        Log::shouldReceive('warning')
            ->once()
            ->with('RtoService: unrecognized tax_basis, tax set to 0', \Mockery::type('array'));

        $result = $this->service->quote([
            'permit' => 'Goods',
            'wheels' => 4,
            'fuel' => 'DIESEL',
            'ex_showroom' => 500000,
        ]);

        $this->assertSame(0.0, $result['tax']);
    }

    public function test_quote_treats_a_blank_tax_basis_with_a_flat_tax_factor_as_the_tax(): void
    {
        $this->makeRule(['tax_basis' => null, 'tax_factor' => 3000, 'surcharge_formula' => null, 'surcharge' => 375]);

        $result = $this->service->quote([
            'permit' => 'Goods',
            'wheels' => 4,
            'fuel' => 'DIESEL',
            'ex_showroom' => 500000,
        ]);

        $this->assertSame(3000.0, $result['tax']);
        $this->assertSame(375.0, $result['surcharge']);
    }

    public function test_quote_matches_gvw_within_a_range_band(): void
    {
        $this->makeRule(['gvw_range' => '3001-16500']);

        $matching = $this->service->quote([
            'permit' => 'Goods',
            'wheels' => 4,
            'fuel' => 'DIESEL',
            'gvw' => 5000,
            'ex_showroom' => 500000,
        ]);
        $this->assertSame(50000.0, $matching['tax']);

        $nonMatching = $this->service->quote([
            'permit' => 'Goods',
            'wheels' => 4,
            'fuel' => 'DIESEL',
            'gvw' => 20000,
            'ex_showroom' => 500000,
        ]);
        $this->assertSame(0.0, $nonMatching['total']);
    }

    public function test_quote_returns_empty_result_when_no_rule_matches(): void
    {
        $result = $this->service->quote([
            'permit' => 'Private',
            'wheels' => 2,
            'fuel' => 'PETROL',
            'ex_showroom' => 500000,
        ]);

        $this->assertSame(0.0, $result['total']);
        $this->assertSame([], $result['permit_options']);
    }
}
