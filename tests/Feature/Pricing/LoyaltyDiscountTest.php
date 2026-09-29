<?php

namespace Tests\Feature\Pricing;

use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Variant;
use App\Services\Utils\SynonymService;
use App\Services\Vehicle\Pricing\Addons\DiscountService;
use App\Services\Vehicle\Pricing\Engine\PricingContract;
use App\Services\Vehicle\Pricing\Engine\PricingQueryService;
use App\Services\Vehicle\Pricing\Engine\RuleBook;
use App\Services\Vehicle\Pricing\Engine\SnapshotBuilder;
use App\Services\Vehicle\Pricing\Import\AddonDiscountWorkbookService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * DEC-083: Loyalty is a conditional discount like Exchange — scheme per model, OEM + dealer share — offered in the
 * snapshot (never in the default total), selectable through getPricing, and round-tripped by the Loyalty sheet.
 */
class LoyaltyDiscountTest extends TestCase
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
        Discount::query()->where('discount_type', 'LOYALTY')->update(['is_active' => false]);
        $this->code = 'ZQY'.strtoupper(substr(uniqid(), -5)).'WH';
        $this->vehicleAndRules();
    }

    private function modelCode(): string
    {
        return (string) Variant::query()->where('code', $this->code)->value('model_code');
    }

    public function test_loyalty_is_offered_not_deducted_and_selectable(): void
    {
        app(DiscountService::class)->create(['discount_type' => 'LOYALTY', 'model_code' => $this->modelCode(), 'scheme_name' => 'Loyalty', 'name' => 'Loyalty',
            'oem_share' => 5000, 'dealer_share' => 3000, 'is_conditional' => true, 'wef_date' => '2026-10-01']);
        $variant = Variant::with('vehicleModel')->where('code', $this->code)->firstOrFail();
        $prices = Pricing::query()->where('model_code', $this->code)->where('is_active', true)->get()->keyBy('channel')->all();
        $payload = collect((new SnapshotBuilder(new RuleBook(app(SynonymService::class))))->build($variant, $prices, '2026-10-01'))
            ->firstWhere(fn ($s) => $s['permit'] === 'PRIVATE' && $s['vin_type'] === 'NV')['payload'];

        $this->assertSame([['scheme' => 'Loyalty', 'oem' => 5000.0, 'dealer' => 3000.0, 'total' => 8000.0]], $payload['discounts']['loyalty']['options']);
        $this->assertSame(0.0, $payload['discounts']['loyalty']['amount'], 'conditional: not in the default on-road');

        $applied = app(PricingQueryService::class)->apply(PricingContract::normalize($payload), ['loyalty' => 'loyalty']);
        $this->assertSame(8000.0, $applied['discounts']['loyalty']['amount']);
        $this->assertSame($payload['on_road'] - 8000.0 + ($applied['tcs']['amount'] - $payload['tcs']['amount']), $applied['on_road']);
    }

    public function test_the_loyalty_sheet_round_trips(): void
    {
        app(DiscountService::class)->create(['discount_type' => 'LOYALTY', 'model_code' => $this->modelCode(), 'scheme_name' => 'Loyalty', 'name' => 'Loyalty',
            'oem_share' => 4000, 'dealer_share' => 1000, 'is_conditional' => true, 'wef_date' => '2026-10-01']);
        $workbook = app(AddonDiscountWorkbookService::class);
        $path = tempnam(sys_get_temp_dir(), 'loy').'.xlsx';
        $this->assertGreaterThan(0, $workbook->export($path, ['LOYALTY'])['LOYALTY']);
        $this->assertSame(['Loyalty'], IOFactory::load($path)->getSheetNames());

        $result = $workbook->import($path, ['LOYALTY'], '2026-11-01');
        $this->assertSame(1, $result['sheets']['LOYALTY']['written'], json_encode($result['issues']));
        $live = Discount::query()->where('discount_type', 'LOYALTY')->where('is_active', true)->where('model_code', $this->modelCode())->sole();
        $this->assertSame([5000.0, '2026-11-01'], [(float) $live->total_discount, $live->wef_date->toDateString()]);
        @unlink($path);
    }
}
