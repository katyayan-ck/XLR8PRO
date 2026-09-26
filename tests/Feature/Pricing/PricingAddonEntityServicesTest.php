<?php

namespace Tests\Feature\Pricing;

use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\AddonDiscountImportService;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Services\Vehicle\Pricing\Addons\DealerChargeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * DEC-057: add-ons, discounts and dealer charges are written only through their entity services
 * (the Addon-N-Discounts workbook import uses them).
 */
class PricingAddonEntityServicesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_scope_is_stored_the_way_each_table_expects(): void
    {
        $charge = app(DealerChargeService::class)->create(['segment' => 'ANY', 'model_code' => 'all', 'incidental' => '₹2,500']);
        $this->assertSame(['ANY', null, '2500.00', '0.00'], [$charge->segment, $charge->model_code, $charge->incidental, $charge->fastag]);

        $addon = app(AddonService::class)->create(['addon_type' => 'rsa', 'model_code' => 'Any', 'amount' => '999']);
        $this->assertSame(['RSA', 'ANY'], [$addon->addon_type, $addon->model_code]);
    }

    public function test_a_dealer_charge_row_with_only_zero_amounts_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        app(DealerChargeService::class)->create(['segment' => 'SUV', 'incidental' => '0', 'fastag' => '-']);
    }

    public function test_the_addons_workbook_imports_through_the_services_and_expires_only_its_group(): void
    {
        $oldRsa = app(AddonService::class)->create(['addon_type' => 'RSA', 'amount' => 100]);
        $oldShield = app(AddonService::class)->create(['addon_type' => 'SHIELD', 'amount' => 200]);
        $session = ImportSession::query()->create([]);

        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Dealer Charges')->fromArray([
            ['Segment', 'Permit', 'Model', 'Incidental Charges', 'Fast Tag', 'TRC', 'RTO Tape', 'COD Charges'],
            ['ANY', 'Private', 'THAR', '2,500', '600', '', '-', ''],
            ['SUV', '', '', '0', '', '', '', ''],
        ]);
        $book->createSheet()->setTitle('RSA')->fromArray([
            ['Segment', 'Model', 'Standard Coverage', 'Std + 1 Year', 'Std + 2 Years'],
            ['SUV', 'ANY', '1 Year', '1200', '2100'],
        ]);
        $book->createSheet()->setTitle('Exchange')->fromArray([
            ['Model', 'Variant', 'Scheme Type', 'OEM Share', 'Dealer Share', 'Total'],
            ['THAR', '', 'Loyalty', '10000', '5000', ''],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'addons').'.xlsx';
        (new Xlsx($book))->save($path);

        $result = app(AddonDiscountImportService::class)->importFile($path, $session, ['DEALER_CHARGES', 'RSA', 'EXCHANGE'], '2026-10-01');
        @unlink($path);

        $this->assertSame([1, 1], [$result['DEALER_CHARGES']['written'], $result['DEALER_CHARGES']['skipped']], 'all-zero row skipped');
        $charge = DealerCharge::where('import_session_id', $session->id)->first();
        $this->assertSame(['ANY', 'THAR', '2500.00', '600.00', '0.00'], [$charge->segment, $charge->model_code, $charge->incidental, $charge->fastag, $charge->rto_tape]);

        $this->assertSame(2, $result['RSA']['written']);
        $rsa = Addon::where('import_session_id', $session->id)->orderBy('tenure_years')->get();
        $this->assertSame(['ANY', true, false], [$rsa[0]->model_code, (bool) $rsa[0]->is_default, (bool) $rsa[1]->is_default]);
        $this->assertFalse($oldRsa->fresh()->is_active, 'RSA import expires old RSA rows');
        $this->assertTrue($oldShield->fresh()->is_active, 'but not Shield rows (group expiry)');

        $discount = Discount::where('import_session_id', $session->id)->first();
        $this->assertSame(['EXCHANGE', 'Loyalty', '15000.00', '15000.00'], [$discount->discount_type, $discount->name, $discount->total_discount, $discount->amount]);
    }
}
