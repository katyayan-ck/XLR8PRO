<?php

namespace Tests\Feature\Pricing;

use App\Jobs\Vehicle\Pricing\Process\ImportPricesJob;
use App\Models\User;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\PricingHistory;
use App\Services\Vehicle\Pricing\Import\PriceListImportService;
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
 * DEC-073 step 4 / DEC-076: the price import reads the user's ex-showroom column per list, the "with GST" schemes, the
 * NV / OV blocks (PV's repeated labels), LMM freight / handling, TZU final price, CSD channel; applies the WEF rules and
 * writes history; skips incomplete / unknown vehicles and rejects conflicting duplicates.
 */
class PricingPriceImportTest extends TestCase
{
    use DatabaseTransactions;

    private const PV_HEAD = ['Model Code', 'OEM Model', 'OEM Variant', 'Status', 'Asse Value with Freight', 'GST', 'GST Amount', 'MM Inv Amt', 'Dealer Margin', 'Dealer Handling', 'GST Amount 1', 'Ex-Showroom Price ORG',
        'OEM Scheme @ BNDP', 'OEM Scheme with GST', 'Dealer Cont @ BNDP', 'Dealer Contribution with GST', 'Total Consumer Scheme with GST (OEM + Dealer)', 'Cash Discount', 'Accessories Discount', 'Shield Discount', 'Check', 'Acc Dsc Elg', 'Sheld Disc Elg',
        'OEM Scheme @ BNDP', 'OEM Scheme with GST', 'Dealer Cont @ BNDP', 'Dealer Contribution with GST', 'Total Consumer Scheme with GST (OEM + Dealer)', 'Cash Discount', 'Accessories Discount', 'Shield Discount', 'Check', 'Acc Dsc Elg', 'Sheld Disc Elg'];

    private string $tag;

    private ImportSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
        $this->tag = strtoupper(substr(uniqid(), -5));
        $sessions = app(PricingSessionService::class);
        $this->session = $sessions->start(UploadedFile::fake()->create('Pricing.xlsx', 10), ['Price List PV'], '2026-10-01')->get('session');
        $sessions->advance($this->session, PricingStage::Prices);
    }

    private function vehicle(string $suffix, bool $complete = true): string
    {
        $code = "ZQP{$this->tag}{$suffix}";
        $vehicles = app(VehicleService::class);
        $variant = $vehicles->createStubFromPriceList($code, 'ZETA PRO', 'ZX', 'Price List PV')['variant'];
        if ($complete) {
            $vehicles->applyVehicleInfo($variant, ['segment' => 'PV', 'sub_segment' => 'PV', 'fuel' => 'DIESEL', 'seating' => '7', 'wheels' => '4', 'transmission' => 'Manual',
                'drivetrain' => 'RWD', 'body_make' => 'SUV', 'body_type' => 'COMPLETE', 'cc' => '2184', 'gst_percent' => '40', 'permit' => 'PRIVATE', 'taxi_price' => 'N',
                'custom_model' => 'Zeta Pro', 'custom_variant' => 'ZX', 'display_name' => 'Zeta Pro ZX', 'colour_name' => 'WHITE', 'status' => 'ACTIVE']);
        }

        return $code;
    }

    /** A PV row: ex-showroom, NV OEM scheme (with GST), OV OEM scheme. */
    private function pvRow(string $code, float $ex, float $nvScheme = 75000, float $ovScheme = 5000): array
    {
        return [$code, 'ZETA PRO', 'ZX', 'Live', 1557827, 40, 623131, 2180958, 68245, 1000, 27298, $ex,
            53571, $nvScheme, 17857, 25000, $nvScheme + 25000, 70000, 30000, 0, 0, 0.7, 1,
            3571, $ovScheme, 0, 0, $ovScheme, 10000, 0, 0, 0, 0.7, 1];
    }

    /** @param array<string, list<list<mixed>>> $sheets title => rows (header included) */
    private function book(array $sheets): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $book->createSheet()->setTitle($title)->fromArray($rows);
        }
        $path = Storage::disk('local')->path('prices-'.uniqid().'.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    /** @param list<string> $sheets */
    private function importPrices(string $path, array $sheets, string $wef): array
    {
        return app(PriceListImportService::class)->import($path, $sheets, $wef);
    }

    private function live(string $code, string $channel = 'normal'): ?Pricing
    {
        return Pricing::query()->where('model_code', $code)->where('channel', $channel)->where('is_active', true)->first();
    }

    public function test_pv_columns_nv_ov_blocks_and_skips(): void
    {
        $ok = $this->vehicle('WH');
        $stub = $this->vehicle('RD', complete: false);
        $conflict = $this->vehicle('BL');
        $path = $this->book(['Price List PV' => [self::PV_HEAD, $this->pvRow($ok, 2276500), $this->pvRow($ok, 2276500), $this->pvRow($stub, 900000),
            $this->pvRow("ZQX{$this->tag}ZZ", 800000), $this->pvRow($conflict, 1000000), $this->pvRow($conflict, 1100000)]]);

        $result = $this->importPrices($path, ['Price List PV'], '2026-10-01');

        $s = $result['sheets']['Price List PV'];
        $this->assertSame([4, 1, 1, 1, 1, 1], [$s['rows'], $s['inserted'], $s['duplicates'], $s['skipped_incomplete'], $s['skipped_unknown'], $s['conflicts']]);
        $p = $this->live($ok);
        $this->assertEquals(
            [2276500, 1557827, 40, 2180958, 69245, 75000, 25000, 70000, 30000, 5000, 10000, 0.7, 1],
            [(float) $p->ex_showroom_price, (float) $p->assessable_value_with_freight, (float) $p->gst_percent, (float) $p->mm_invoice_amount, (float) $p->dealer_margin,
                (float) $p->curr_oem_scheme, (float) $p->curr_dealer_cont, (float) $p->curr_cash_discount, (float) $p->curr_acc_discount,
                (float) $p->old_oem_scheme, (float) $p->old_cash_discount, (float) $p->curr_acc_elg, (float) $p->curr_shield_elg],
            'ex-showroom = "Ex-Showroom Price ORG"; schemes "with GST"; OV = the repeated block; margin + handling'
        );
        $this->assertSame(PricingHistory::ACTION_INSERT, PricingHistory::query()->where('model_code', $ok)->value('action'));
        $this->assertSame('PV', $p->price_list, 'the row remembers its list (DEC-079)');
        $this->assertNull($this->live($stub));
        $this->assertNull($this->live($conflict), 'conflicting duplicates write nothing');
        $this->assertStringContainsString('different amounts', collect($result['issues'])->firstWhere('code', $conflict)['reason']);
    }

    public function test_wef_rules_update_keep_expire_and_reject(): void
    {
        $code = $this->vehicle('WH');
        $v1 = $this->book(['Price List PV' => [self::PV_HEAD, $this->pvRow($code, 2276500)]]);
        $v2 = $this->book(['Price List PV' => [self::PV_HEAD, $this->pvRow($code, 2299000)]]);

        $this->importPrices($v1, ['Price List PV'], '2026-10-01');
        $this->importPrices($v2, ['Price List PV'], '2026-10-01');                                  // same WEF → update in place
        $this->assertSame(1, Pricing::query()->where('model_code', $code)->count());
        $this->assertEquals(2299000, (float) $this->live($code)->ex_showroom_price);

        $same = $this->importPrices($v2, ['Price List PV'], '2026-11-01');                          // new WEF, nothing changed → keep
        $this->assertSame(1, $same['totals']['unchanged']);
        $this->assertSame(1, Pricing::query()->where('model_code', $code)->count());

        $this->importPrices($v1, ['Price List PV'], '2026-12-01');                                  // new WEF, changed → expire + insert
        $rows = Pricing::query()->where('model_code', $code)->orderBy('wef_date')->get();
        $this->assertSame([false, '2026-12-01', true], [(bool) $rows[0]->is_active, $rows[0]->expired_on?->toDateString(), (bool) $rows[1]->is_active]);
        $this->assertEquals(2276500, (float) $rows[1]->ex_showroom_price);

        $older = $this->importPrices($v2, ['Price List PV'], '2026-10-15');                         // older than the live WEF → rejected
        $this->assertSame(1, $older['totals']['rejected']);
        $this->assertSame(['insert', 'update', 'unchanged', 'insert'], PricingHistory::query()->where('model_code', $code)->orderBy('id')->pluck('action')->all());
    }

    public function test_lmm_tzu_and_csd_use_the_columns_the_user_chose(): void
    {
        [$lmm, $tzu, $csd] = [$this->vehicle('L1'), $this->vehicle('T1'), $this->vehicle('C1')];
        $path = $this->book([
            'Price List LMM' => [
                ['City', 'OEM Model', 'OEM Variant', 'Model Code', 'Status', 'Assesable Value', 'Freight New', 'GST%', 'Cess%', 'GST Amount', 'LMM Inv Amt', 'New Dealer Margin', 'Extra Handling', 'GST Amount 1', 'Ex Showroom Price', 'Ex Showroom Price(Org)', 'VIN Scheme', '', 'OEM Scheme @ BNDP', 'Dealer Cont @ BNDP', 'Dealer Contribution with GST', 'Total Consumer Scheme with GST (OEM + Dealer)'],
                ['BIKANER', 'E ALFA', 'SOLT', $lmm, '', 151038, 4559, 5, 0, 7780, 163376, 10327, 500, 516, 174219, 177219, 10876, '', 10358.1, 2880, 3024, 13900],
            ],
            'Price List LMM TZU' => [
                ['City', 'M.CODE', 'Material Description', 'Model Name', 'Model Group', 'Status', 'Scheme', 'Before GST', 'New Base Price (Ex-Factory)', 'Freight', 'Diff/Adjustment', 'Assessable Value', 'GST on Assessable Value @ 5%', 'LMM Billing Price before (PM E-DRIVE SUBSIDY)', 'LMM Billing Price After (PM E-DRIVE SUBSIDY)', 'New Dealer Marging', 'Dealer GST @ 5%', 'Ex-Showroom Pre Subsidy', 'Battery Capacity', 'PM E-DRIVE SUBSIDY Subsidy', 'Maharashtra State Subsidy', 'Exshowroom Post Subsidy (PM E-DRIVE SUBSIDY)', 'Scheme', 'Final Transaction Price', '', 'OEM Scheme @ BNDP', 'Dealer Cont @ BNDP', 'Dealer Contribution with GST', 'Total Consumer Scheme with GST (OEM + Dealer)'],
                ['BIKANER', $tzu, 'TREO HRT', 'TREO HRT', 'TREO AUTO', '', 20000, 19048, '3,00,752', 11875, '-', '3,12,627', 15631, '3,28,258', '3,28,258', 19518, 976, '3,48,752', '', '', '', '3,48,752', 20000, '3,28,752', '', 19047.6, 0, 0, 20000],
            ],
            'Price List CSD' => [
                ['', '', '', '', '', '', '', '', '', '', '', '1- Basic Price to CSD'],
                ['Model Code', 'OEM Model', 'OEM Variant', 'Custom Model', 'Custom Variant', 'Display Name', 'Status', 'City', 'Asse Value with Freight', 'Retail Dealer Margin', 'DEALER MARGIN DISCOUNT', 'BASIC Price Including CSD Dealer Margin', 'CSD% Discount', 'CSD Discount', 'Market Scheme at BNDP', 'CSD Basic with Discount', 'GST %', 'GST Amount', 'CSD Final Price', 'Amount to be claimed by Dealer', 'GST @ 50% Discount', 'GST Amount on CSD Basic with Discount', 'CSD Profit @ 0.50%', 'CSD Final Price with GST Concession'],
                [$csd, 'BE6', 'THREE', 'NO', 'BE6', 'THREE', '', 'BIKANER', '23,29,012', 75750, 38200, '23,66,562', 0.27, 6288, 66667, '22,93,607', 5, '1,14,680', '24,08,288', 6603, 2.5, 57340, 11468, '23,62,415'],
            ],
        ]);

        $this->importPrices($path, ['Price List LMM', 'Price List LMM TZU', 'Price List CSD'], '2026-10-01');

        $l = $this->live($lmm);
        $this->assertEquals([177219, 155597, 10827, 10876, 3024, 0], [(float) $l->ex_showroom_price, (float) $l->assessable_value_with_freight, (float) $l->dealer_margin, (float) $l->curr_oem_scheme, (float) $l->curr_dealer_cont, (float) $l->old_oem_scheme]);
        $t = $this->live($tzu);
        $this->assertEquals([328752, 312627, 5.0, 0, 328258], [(float) $t->ex_showroom_price, (float) $t->assessable_value_with_freight, (float) $t->gst_percent, (float) $t->curr_oem_scheme, (float) $t->mm_invoice_amount]);
        $this->assertNull($this->live($csd), 'CSD writes only the csd channel');
        $c = $this->live($csd, 'csd');
        $this->assertEquals([2408288, 2329012, 5, 75750], [(float) $c->ex_showroom_price, (float) $c->assessable_value_with_freight, (float) $c->gst_percent, (float) $c->dealer_margin]);
    }

    public function test_screen_queues_the_import_and_continue_needs_a_run(): void
    {
        Queue::fake();
        Permission::firstOrCreate(['name' => 'PRC_WKFL_VIEW', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'PRC_WKFL_MANAGE', 'guard_name' => 'web']);
        $user = User::create(['username' => 'prp_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);
        $this->app['auth']->guard('backpack')->setUser($user);
        $file = UploadedFile::fake()->createWithContent('Pricing.xlsx', (string) file_get_contents($this->book(['Price List PV' => [self::PV_HEAD]])));

        $this->post(route('pricing.workflow.prices-continue'))->assertSessionHas('warning');
        $this->post(route('pricing.workflow.prices'), ['source' => 'upload', 'file' => $file, 'lists' => ['PV', 'BEV'], 'wef_date' => '2026-10-01'])->assertSessionHasErrors('lists');
        $this->post(route('pricing.workflow.prices'), ['source' => 'upload', 'file' => $file, 'lists' => ['PV'], 'wef_date' => '2026-10-01'])
            ->assertRedirect(route('pricing.workflow.prices-form'));
        Queue::assertPushed(ImportPricesJob::class, fn ($job) => $job->sessionId === $this->session->id && $job->sheets === ['Price List PV'] && $job->wefDate === '2026-10-01');

        app(PricingSessionService::class)->putStats($this->session, 'prices', ['run' => 1]);
        app(PricingSessionService::class)->progress($this->session->fresh(), ['state' => 'done']);
        $this->post(route('pricing.workflow.prices-continue'))->assertRedirect(route('pricing.workflow.addons-form'));
        $this->assertSame(PricingStage::Addons, $this->session->fresh()->stage());
    }
}
