<?php

namespace Tests\Feature\Pricing;

use App\Models\Vehicle\Pricing\TcsConfig;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use App\Services\Vehicle\Pricing\Rules\TcsConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-056: pricing rules (RTO, TCS, insurance) are written only through their entity services —
 * the RTO / TCS screens and the Insurance + RTO rules workbook use the same field rules.
 */
class PricingRuleEntityServicesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rto_rule_fields_follow_the_spec_scope_and_amount_rules(): void
    {
        $rule = app(RtoRuleService::class)->create([
            'permit' => ' Private ',
            'wheels' => 'ANY',
            'surcharge' => '1.5% + 2',
            'hypothecation' => '₹1,500',
            'green_tax' => 'NA',
            'fitness' => '',
        ]);

        $this->assertNull($rule->wheels);
        $this->assertSame(['1.50', '1500.00', '0.00', '0.00'], [$rule->surcharge, $rule->hypothecation, $rule->green_tax, $rule->fitness]);
    }

    public function test_text_in_an_amount_is_rejected_not_zeroed(): void
    {
        $this->expectException(ValidationException::class);
        app(RtoRuleService::class)->create(['permit' => 'Private', 'penalty' => 'as applicable']);
    }

    public function test_only_one_tcs_configuration_is_active(): void
    {
        $tcs = app(TcsConfigService::class);
        $first = $tcs->saveCurrent(['limit_amount' => '10,00,000', 'rate_pct' => '1%']);
        $this->assertSame(['1000000.00', '1.00'], [$first->limit_amount, $first->rate_pct]);

        $second = $tcs->create(['limit_amount' => 500000, 'rate_pct' => 2]);

        $this->assertSame(1, TcsConfig::where('is_active', true)->count());
        $this->assertTrue($second->fresh()->is_active);
        $this->assertFalse($first->fresh()->is_active);
    }
}
