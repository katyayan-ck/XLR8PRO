<?php

namespace Tests\Feature\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsIdvSlot;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Pricing\TcsConfig;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use App\Services\Vehicle\Pricing\Rules\TcsConfigService;
use App\Services\Vehicle\Pricing\RulesWorkbookService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    public function test_the_rules_workbook_imports_through_the_services_and_expires_the_previous_set(): void
    {
        $old = app(RtoRuleService::class)->create(['permit' => 'Private', 'wheels' => 4, 'is_active' => true]);
        $session = ImportSession::query()->create([]);

        $book = new Spreadsheet;
        $rto = $book->getActiveSheet()->setTitle('RTO Rules');
        $rto->fromArray([
            ['Permit', 'Wheels', 'Tax Factor', 'Tax Slab', 'Surcharge', 'Hypothecation', 'Penalty'],
            ['Private', 'ANY', '0.08', '', '5%', '₹1,500', '-'],
            ['Taxi', 4, '', '12', '', '1000', 'see note'],
        ]);
        $premium = $book->createSheet()->setTitle('Insurance Premium');
        $premium->fromArray([
            ['Permit', 'Wheels', 'Fuel', 'Plan', 'Insu Co', 'OD Factor', 'TP Basic', 'IDV 1', 'IDV 2'],
            ['Private', 4, 'Petrol', '1+3', 'USGI', '0.0325', '2094', '95% of Invoice', '85% of Invoice'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'rules').'.xlsx';
        (new Xlsx($book))->save($path);

        $result = app(RulesWorkbookService::class)->importFile($path, $session, ['rto', 'insurance'], '2026-10-01');
        @unlink($path);

        $this->assertFalse($old->fresh()->is_active, 'previous active set expired');
        $this->assertSame('2026-10-01', $old->fresh()->expired_on->format('Y-m-d'));

        $this->assertSame(1, $result['RTO:RTO Rules']['written']);
        $this->assertStringContainsString('Row 3', $result['RTO:RTO Rules']['errors'][0], 'text in an amount is reported with its sheet row');
        $imported = RtoRule::where('import_session_id', $session->id)->first();
        $this->assertNull($imported->wheels);
        $this->assertSame(['0.080000', '5.00', '1500.00', '0.00'], [$imported->tax_factor, $imported->surcharge, $imported->hypothecation, $imported->penalty]);

        $base = InsBaseRule::where('import_session_id', $session->id)->first();
        $this->assertSame([1, 3], [$base->od_years, $base->tp_years]);
        $this->assertSame(['95.000', '85.000'], InsIdvSlot::where('base_rule_id', $base->id)->orderBy('year_no')->pluck('idv_pct')->all());
    }
}
