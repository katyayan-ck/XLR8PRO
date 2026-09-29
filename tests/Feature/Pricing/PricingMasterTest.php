<?php

namespace Tests\Feature\Pricing;

use App\Jobs\Vehicle\Pricing\ImportPricingMasterJob;
use App\Models\User;
use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\MasterImport;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\RecalcRun;
use App\Services\Vehicle\Pricing\Addons\DealerChargeService;
use App\Services\Vehicle\Pricing\Engine\PricingRecalcService;
use App\Services\Vehicle\Pricing\Prices\PriceService;
use App\Support\PricingMaster\MasterRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-083: every pricing master shares one list / CRUD / import / export kit — permission-gated, writes through the
 * entity service with WEF versioning, locked while a Pricing Process is open, and each change marks the automatic
 * recalculation.
 */
class PricingMasterTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
        $this->user = User::create(['username' => 'prm_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($this->user->fresh(), backpack_guard_name());
    }

    public function test_every_master_screen_is_permission_gated_and_renders(): void
    {
        $this->actingAs($this->user, backpack_guard_name());
        $this->get(route('pricing.masters.index', 'rsa'))->assertForbidden();

        foreach (MasterRegistry::all() as $key => $master) {
            $this->grant($master->permission().'_VIEW');
            $this->get(route('pricing.masters.index', $key))->assertOk()->assertSee($master->label())->assertDontSee('pm-file');
            $this->getJson(route('pricing.masters.rows', $key))->assertOk()->assertJsonStructure(['rows']);
            $this->get(route('pricing.masters.create', $key))->assertForbidden();
        }
        $this->get('/admin/pricing/masters/nope')->assertNotFound();
    }

    public function test_crud_versions_by_wef_and_marks_the_recalculation(): void
    {
        $this->grant('PRC_RSA_VIEW', 'PRC_RSA_MANAGE');
        $this->post(route('pricing.masters.store', 'rsa'), ['segment' => 'PV', 'model_code' => 'ZQMODEL', 'tenure_years' => 1, 'amount' => 1500, 'wef_date' => '2026-10-01'])
            ->assertRedirect(route('pricing.masters.index', 'rsa'));
        $row = Addon::query()->where('model_code', 'ZQMODEL')->sole();
        $this->assertSame(['RSA', 1500.0, true], [$row->addon_type, (float) $row->amount, (bool) $row->is_active], 'defaults applied');
        $this->assertTrue(RecalcRun::query()->where('created_at', '>=', now()->subMinute())->exists()
            || app(PricingRecalcService::class)->pending() !== [], 'a master save queues the recalculation (run at request end on the sync queue)');

        $this->put(route('pricing.masters.update', ['rsa', $row->id]), ['segment' => 'PV', 'model_code' => 'ZQMODEL', 'tenure_years' => 1, 'amount' => 1600, 'wef_date' => '2026-10-01'])->assertRedirect();
        $this->assertSame([1, 1600.0], [Addon::query()->where('model_code', 'ZQMODEL')->count(), (float) $row->fresh()->amount], 'same WEF edits in place');

        $this->put(route('pricing.masters.update', ['rsa', $row->id]), ['segment' => 'PV', 'model_code' => 'ZQMODEL', 'tenure_years' => 1, 'amount' => 1800, 'wef_date' => '2026-11-01'])->assertRedirect();
        $old = $row->fresh();
        $live = Addon::query()->where('model_code', 'ZQMODEL')->where('is_active', true)->sole();
        $this->assertSame([false, '2026-11-01'], [(bool) $old->is_active, $old->expired_on->toDateString()], 'new WEF keeps history');
        $this->assertSame([1800.0, '2026-11-01', 'PV'], [(float) $live->amount, $live->wef_date->toDateString(), $live->segment]);

        $this->delete(route('pricing.masters.destroy', ['rsa', $live->id]))->assertRedirect();
        $this->assertFalse((bool) $live->fresh()->is_active, 'removal expires a WEF row');
    }

    public function test_writes_are_refused_while_a_pricing_process_is_open(): void
    {
        $this->grant('PRC_DLRC_VIEW', 'PRC_DLRC_MANAGE');
        ImportSession::query()->create(['status' => ImportSession::STATUS_ACTIVE, 'current_stage' => 'addons', 'wef_date' => '2026-10-01']);

        $this->post(route('pricing.masters.store', 'dealer-charges'), ['segment' => 'PV', 'fastag' => 600, 'wef_date' => '2026-10-01'])
            ->assertRedirect()->assertSessionHas('warning');
        $this->assertFalse(DealerCharge::query()->where('fastag', 600)->where('segment', 'PV')->where('created_at', '>=', now()->subMinute())->exists());
        $this->get(route('pricing.masters.index', 'dealer-charges'))->assertOk()->assertSee('read-only');
    }

    public function test_one_row_per_record_export_and_import_round_trip(): void
    {
        $this->grant('PRC_DBRK_VIEW', 'PRC_DBRK_MANAGE');
        $code = 'ZQB'.strtoupper(substr(uniqid(), -5)).'WH';
        $price = app(PriceService::class)->create(['model_code' => $code, 'channel' => 'normal', 'price_list' => 'PV', 'wef_date' => '2026-10-01',
            'ex_showroom_price' => 1000000, 'curr_cash_discount' => 10000]);

        $master = MasterRegistry::get('discount-breakup');
        $path = tempnam(sys_get_temp_dir(), 'dbk').'.xlsx';
        $master->export($path);
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        $headers = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0];
        $cashCol = array_search('NV Cash', $headers, true);
        foreach ($sheet->getRowIterator(2) as $r) {
            if ((int) $sheet->getCell('A'.$r->getRowIndex())->getValue() === $price->id) {
                $sheet->setCellValue([$cashCol + 1, $r->getRowIndex()], 25000);
            }
        }
        (new Xlsx($book))->save($path);

        $this->post(route('pricing.masters.import', 'discount-breakup'), ['file' => new UploadedFile($path, 'breakup.xlsx', null, null, true), 'wef_date' => '2026-10-01'])->assertRedirect();
        $import = MasterImport::query()->where('master', 'discount-breakup')->latest('id')->first();
        $this->assertSame('done', $import->status, (string) $import->message);
        $this->assertSame(0, $import->result['rejected'], json_encode($import->result['issues']));
        $this->assertSame(25000.0, (float) Pricing::query()->whereKey($price->id)->value('curr_cash_discount'), 'same WEF updates the price row in place');
        $this->assertSame(1000000.0, (float) Pricing::query()->whereKey($price->id)->value('ex_showroom_price'));
        @unlink($path);
    }

    public function test_workbook_masters_round_trip_through_the_process_sheet(): void
    {
        $this->grant('PRC_DLRC_VIEW', 'PRC_DLRC_MANAGE');
        DealerCharge::query()->update(['is_active' => false]);
        app(DealerChargeService::class)->create(['segment' => 'PV', 'permit' => 'Private', 'fastag' => 600, 'trc' => 1000, 'wef_date' => '2026-10-01']);

        $response = $this->get(route('pricing.masters.export', 'dealer-charges'))->assertOk();
        $path = $response->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'dlc').'.xlsx';
        copy($path, $copy);
        $this->assertSame(['Dealer Charges - Segment Wise'], IOFactory::load($copy)->getSheetNames());

        $import = MasterImport::query()->create(['master' => 'dealer-charges', 'status' => 'queued', 'path' => Storage::disk('local')->putFileAs('pricing/masters', new File($copy), 'dlc.xlsx'),
            'wef_date' => '2026-11-01', 'created_by' => $this->user->id]);
        (new ImportPricingMasterJob($import->id))->handle();
        $import->refresh();
        $this->assertSame('done', $import->status, (string) $import->message);
        $live = DealerCharge::query()->where('is_active', true)->where('segment', 'PV')->where('permit', 'Private')->sole();
        $this->assertSame([600.0, '2026-11-01'], [(float) $live->fastag, $live->wef_date->toDateString()], 'the group is replaced at the WEF');
        @unlink($copy);
    }
}
