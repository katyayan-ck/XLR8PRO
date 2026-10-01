<?php

namespace Tests\Feature\Pricing;

use App\Jobs\Vehicle\Pricing\Process\DetectPriceListsJob;
use App\Models\User;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Variant;
use App\Services\Vehicle\Pricing\Import\PriceListDetectService;
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
 * DEC-073 steps 0–2 through the screens: the gate, Start (price lists, WEF, holds) and Detect (stubs from price lists
 * only; LMM TZU colour NA; CSD never creates vehicles; duplicates counted once).
 */
class PricingProcessStartTest extends TestCase
{
    use DatabaseTransactions;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
        $this->tag = strtoupper(substr(uniqid(), -5));
    }

    /** See BUG-079 for why actingAs($user, 'backpack') can't be used here. */
    private function actingAsBackpackUser(User $user): static
    {
        $this->app['auth']->guard('backpack')->setUser($user);

        return $this;
    }

    /** @param list<string> $permissions */
    private function user(array $permissions): User
    {
        $user = User::create(['username' => 'prc_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    /** A Pricing workbook with PV (new + duplicate + known), LMM TZU (new) and CSD (unknown) rows. */
    private function workbook(string $knownCode): UploadedFile
    {
        $book = new Spreadsheet;
        $sheets = [
            'Price List PV' => [['Model Code', 'OEM Model', 'OEM Variant', 'Status', 'Ex-Showroom Price ORG'],
                ["ZQP{$this->tag}WH", 'ZETA PRO', 'ZX 7S', 'Live', 1200000],
                ["ZQP{$this->tag}WH", 'ZETA PRO', 'ZX 7S', 'Live', 1200000],
                [$knownCode, 'ZETA PRO', 'ZX 5S', 'Live', 1100000]],
            'Price List LMM TZU' => [['M.CODE', 'Material Description', 'Model Name', 'Status', 'Exshowroom Post Subsidy (PM E-DRIVE SUBSIDY)'],
                ["ZQT{$this->tag}01", 'TZU CARGO', 'TREO ZOR', 'Live', 350000]],
            'Price List CSD' => [['Model Code', 'OEM Model', 'OEM Variant', 'Status', 'Ex-Showroom Price ORG'],
                ["ZQC{$this->tag}RD", 'ZETA CSD', 'ZC', 'Live', 900000]],
        ];
        $book->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $book->createSheet()->setTitle($title)->fromArray($rows);
        }
        $path = tempnam(sys_get_temp_dir(), 'prc').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'Pricing.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function knownVariant(): string
    {
        $code = "ZQK{$this->tag}BL";
        app(VehicleService::class)->createStubFromPriceList($code, 'ZETA PRO', 'ZX 5S', 'Price List PV');

        return $code;
    }

    public function test_a_view_only_user_sees_the_process_but_cannot_start_or_discard_one(): void
    {
        $viewer = $this->user(['PRC_WKFL_VIEW']);

        $this->actingAsBackpackUser($viewer)->get(route('pricing.workflow.index'))->assertOk()->assertSee('No pricing process is open');
        $this->actingAsBackpackUser($viewer)->get(route('pricing.workflow.start-form'))->assertForbidden();
        $this->actingAsBackpackUser($viewer)->post(route('pricing.workflow.start'), [])->assertForbidden();
        $this->actingAsBackpackUser($viewer)->post(route('pricing.workflow.discard'))->assertForbidden();
    }

    public function test_start_opens_one_process_applies_holds_and_queues_detect(): void
    {
        Queue::fake();
        $manager = $this->user(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);

        $response = $this->actingAsBackpackUser($manager)->post(route('pricing.workflow.start'), [
            'file' => $this->workbook('ZQXNONE'), 'lists' => ['PV', 'LMM_TZU'], 'wef_date' => '2026-10-01', 'hold_lists' => ['TAXI'],
        ]);

        $response->assertRedirect(route('pricing.workflow.index'))->assertSessionHas('success');
        $session = ImportSession::query()->active()->latest('id')->firstOrFail();
        $this->assertSame(PricingStage::Detecting, $session->stage());
        $this->assertSame(['Price List PV', 'Price List LMM TZU'], $session->selected_sheets);
        $this->assertSame('2026-10-01', $session->wef_date->toDateString());
        $this->assertTrue(Hold::isHeld('TAXI'));
        Queue::assertPushed(DetectPriceListsJob::class, fn ($job) => $job->sessionId === $session->id);

        $this->actingAsBackpackUser($manager)->get(route('pricing.workflow.start-form'))
            ->assertRedirect(route('pricing.workflow.index'));
    }

    public function test_start_rejects_a_list_the_workbook_does_not_have_and_requires_wef(): void
    {
        $manager = $this->user(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);

        $this->actingAsBackpackUser($manager)->post(route('pricing.workflow.start'), [
            'file' => $this->workbook('ZQXNONE'), 'lists' => ['PV', 'BEV'], 'wef_date' => '2026-10-01',
        ])->assertSessionHasErrors('lists');
        $this->actingAsBackpackUser($manager)->post(route('pricing.workflow.start'), [
            'file' => $this->workbook('ZQXNONE'), 'lists' => ['PV'],
        ])->assertSessionHasErrors('wef_date');

        $this->assertNull(ImportSession::query()->active()->first());
    }

    public function test_detect_creates_incomplete_stubs_from_price_lists_only(): void
    {
        Queue::fake();
        $known = $this->knownVariant();
        $manager = $this->user(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);
        $this->actingAsBackpackUser($manager)->post(route('pricing.workflow.start'), [
            'file' => $this->workbook($known), 'lists' => ['PV', 'LMM_TZU', 'CSD'], 'wef_date' => '2026-10-01',
        ]);
        $session = ImportSession::query()->active()->latest('id')->firstOrFail();

        app()->call([new DetectPriceListsJob($session->id), 'handle']);

        $session->refresh();
        $this->assertSame(PricingStage::VehicleInfo, $session->stage());
        $this->assertEquals(['created' => 2, 'known' => 1, 'csd_unknown' => 1, 'duplicates' => 1, 'errors' => 0], data_get($session->stats, 'detect.totals'));
        $pv = Variant::where('code', "ZQP{$this->tag}WH")->firstOrFail();
        $this->assertSame(['WH', false, VehicleService::STATUS_INCOMPLETE], [$pv->color_code, (bool) $pv->is_active, $pv->statusKkv?->code]);
        $this->assertSame('NA', Variant::where('code', "ZQT{$this->tag}01")->value('color_code'));
        $this->assertFalse(Variant::where('code', "ZQC{$this->tag}RD")->exists(), 'CSD never creates vehicles');
        $this->assertSame('done', $session->progress['state']);

        $this->actingAsBackpackUser($manager)->getJson(route('pricing.workflow.status', $session->id))
            ->assertOk()->assertJsonPath('stage', 'vehicle_info')->assertJsonPath('totals.created', 2);
        $this->actingAsBackpackUser($manager)->get(route('pricing.workflow.index'))
            ->assertOk()->assertSee('Detect — new vehicles from the price lists')->assertSee('Price List LMM TZU');

        $this->actingAsBackpackUser($manager)->post(route('pricing.workflow.discard'))->assertSessionHas('success');
        $this->assertFalse(Variant::withTrashed()->where('code', "ZQP{$this->tag}WH")->exists(), 'discard removes the stubs');
        $this->assertTrue(Variant::where('code', $known)->exists(), 'vehicles from before the process stay');
    }

    public function test_the_start_screen_warns_about_old_format_vehicle_codes(): void
    {
        $detect = app(PriceListDetectService::class);
        $before = $detect->legacyCodeCount();
        app(VehicleService::class)->createStubFromPriceList("ZQF{$this->tag}WH", 'ZETA PRO', 'ZX', 'Price List PV');
        $this->assertSame($before, $detect->legacyCodeCount(), 'a full OEM code (with colour) is not old-format');

        $legacy = app(VehicleService::class)->createStubFromPriceList("ZQL{$this->tag}RD", 'ZETA PRO', 'ZX', 'Price List PV')['variant'];
        $legacy::query()->toBase()->where('id', $legacy->id)->update(['code' => "ZQL{$this->tag}"]); // pre-DEC-051 shape, past the entity rules
        $this->assertSame($before + 1, $detect->legacyCodeCount());

        $this->actingAsBackpackUser($this->user(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']))->get(route('pricing.workflow.start-form'))
            ->assertOk()->assertSee('use the old code format');
    }
}
