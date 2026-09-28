<?php

namespace Tests\Feature\Pricing;

use App\Jobs\Vehicle\Pricing\Process\ImportAddonsJob;
use App\Models\User;
use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\AddonHistory;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Services\Vehicle\Pricing\Import\AddonDiscountWorkbookService;
use App\Services\Vehicle\Pricing\Import\PricingWorkbookReader;
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
 * DEC-073 step 5 / DEC-077: a ticked sheet replaces only its group; blank = no rule, 0 = a zero rule; model names
 * resolve to model codes ("Any" = all), unknown models and conflicting duplicate scopes are rejected; history is kept;
 * the export lists every model with blanks where nothing is stored and round-trips.
 */
class PricingAddonImportTest extends TestCase
{
    use DatabaseTransactions;

    private string $model;

    private string $modelCode;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        ImportSession::query()->active()->update(['status' => ImportSession::STATUS_CANCELLED, 'current_stage' => 'discarded']);
        $tag = strtoupper(substr(uniqid(), -5));
        $this->model = "Zq Alpha {$tag}";
        $variant = app(VehicleService::class)->createStubFromPriceList("ZQA{$tag}00WH", $this->model, 'AX', 'Price List PV')['variant'];
        $this->modelCode = $variant->model_code;
    }

    /** @param array<string, list<list<mixed>>> $sheets */
    private function book(array $sheets): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $book->createSheet()->setTitle($title)->fromArray($rows, null, 'A1', true);
        }
        $path = Storage::disk('local')->path('addons-'.uniqid().'.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function workbook(): AddonDiscountWorkbookService
    {
        return app(AddonDiscountWorkbookService::class);
    }

    public function test_import_replaces_only_ticked_groups_with_blank_zero_any_and_model_rules(): void
    {
        $oldRsa = app(AddonService::class)->create(['addon_type' => 'RSA', 'model_code' => $this->modelCode, 'tenure_years' => 1, 'amount' => 100, 'wef_date' => '2026-09-01']);
        $oldShield = app(AddonService::class)->create(['addon_type' => 'SHIELD', 'model_code' => $this->modelCode, 'amount' => 200, 'wef_date' => '2026-09-01']);
        $path = $this->book([
            'RSA' => [AddonDiscountWorkbookService::HEADERS['RSA'],
                ['PERSONAL', strtoupper($this->model), '3 Years', 2021, 3019.62, '', '', ''],        // synonyms + name → code
                ['PV', 'Any', 'NIL', '', '', '', '', ''],                                            // blank → no rule
                ['PV', 'Nonexistent Model', 'NIL', 999, '', '', '', ''],                             // unknown model
            ],
            'Shield' => [AddonDiscountWorkbookService::HEADERS['SHIELD'], [$this->model, 'Any', 'ANY', 'ANY', 'ANY', '3Y', 'Ext 1', 5000, '', '']],
            'Exchange' => [AddonDiscountWorkbookService::HEADERS['EXCHANGE'],
                [$this->model, 'Any', 'Exchange', 10000, 5000, ''],
                [$this->model, 'Any', 'Welcome', 0, 0, 0],                                           // explicit zero
                [$this->model, 'Any', 'Scrappage', '', '', ''],                                      // blank
            ],
            'Corporate' => [AddonDiscountWorkbookService::HEADERS['CORPORATE'],
                [$this->model, 'Any', 'CAT A', 3000, 0, 3000],
                [$this->model, 'Any', 'CAT A', 4000, 0, 4000],                                       // same scope, different → both rejected
            ],
            'Dealer Charges - Segment Wise' => [AddonDiscountWorkbookService::HEADERS['DEALER_CHARGES'], ['PV', 'Passenger', 'Any', 0, 0, 0, 0, 0]],
        ]);

        $result = $this->workbook()->import($path, ['RSA', 'EXCHANGE', 'CORPORATE', 'DEALER_CHARGES'], '2026-10-01');

        $this->assertSame([2, 1, 1], [$result['sheets']['RSA']['written'], $result['sheets']['RSA']['blank'], $result['sheets']['RSA']['rejected']]);
        $this->assertFalse((bool) $oldRsa->fresh()->is_active, 'the RSA group is replaced');
        $this->assertTrue((bool) $oldShield->fresh()->is_active, 'Shield was not ticked — untouched');
        $rsa = Addon::query()->where('addon_type', 'RSA')->where('is_active', true)->where('model_code', $this->modelCode)->orderBy('tenure_years')->get();
        $this->assertSame([['PV', 1, '2021.00', true], ['PV', 2, '3019.62', false]], $rsa->map(fn ($a) => [$a->segment, $a->tenure_years, $a->amount, (bool) $a->is_default])->all());
        $this->assertSame(2, AddonHistory::query()->whereIn('addon_id', $rsa->pluck('id'))->count());

        $exchange = Discount::query()->where('discount_type', 'EXCHANGE')->where('is_active', true)->where('model_code', $this->modelCode)->pluck('total_discount', 'scheme_name')->all();
        $this->assertSame(['Exchange' => '15000.00', 'Welcome' => '0.00'], $exchange, 'total = OEM + dealer when blank; 0 is a rule; blank is not');
        $this->assertSame(0, Discount::query()->where('discount_type', 'CORPORATE')->where('is_active', true)->where('model_code', $this->modelCode)->count());
        $this->assertSame(2, $result['sheets']['CORPORATE']['rejected']);
        $this->assertSame('0.00', DealerCharge::query()->where('is_active', true)->where('segment', 'PV')->where('permit', 'Passenger')->value('cod'), 'explicit "no charges" for PV Passenger');
        $reasons = implode(' | ', array_column($result['issues'], 'reason'));
        $this->assertStringContainsString('Model "Nonexistent Model" is not in the vehicle master', $reasons);
        $this->assertStringContainsString('same scope appears on rows 2, 3', $reasons);
    }

    public function test_export_lists_every_model_with_blanks_and_round_trips(): void
    {
        $path = $this->book(['Exchange' => [AddonDiscountWorkbookService::HEADERS['EXCHANGE'], [$this->model, 'Any', 'Loyalty', 7000, 3000, 10000]]]);
        $this->workbook()->import($path, ['EXCHANGE'], '2026-10-01');
        $out = Storage::disk('local')->path('export.xlsx');

        $counts = $this->workbook()->export($out, ['EXCHANGE', 'RSA']);

        $rows = [];
        foreach (app(PricingWorkbookReader::class)->rows($out, 'Exchange', 2) as $cells) {
            if (strtoupper((string) $cells[0]) === strtoupper($this->model)) {
                $rows[$cells[2]] = [$cells[3], $cells[5]];
            }
        }
        $this->assertSame(['Exchange', 'Welcome', 'Scrappage', 'Loyalty'], array_keys($rows), 'default schemes + the stored one');
        $this->assertSame([null, null], $rows['Welcome'], 'blank where nothing is stored');
        $this->assertEquals([7000, 10000], $rows['Loyalty']);
        $this->assertGreaterThan(0, $counts['RSA']);

        $again = $this->workbook()->import($out, ['EXCHANGE'], '2026-11-01');
        $this->assertSame(0, $again['sheets']['EXCHANGE']['rejected']);
        $this->assertSame('10000.00', Discount::query()->where('discount_type', 'EXCHANGE')->where('is_active', true)->where('model_code', $this->modelCode)->where('scheme_name', 'Loyalty')->value('total_discount'));
    }

    public function test_screen_queues_the_import_and_continue_keeps_stored_rows(): void
    {
        Queue::fake();
        $sessions = app(PricingSessionService::class);
        $session = $sessions->start(UploadedFile::fake()->create('Pricing.xlsx', 10), ['Price List PV'], '2026-10-01')->get('session');
        $sessions->advance($session, PricingStage::Addons);
        Permission::firstOrCreate(['name' => 'PRC_WKFL_VIEW', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'PRC_WKFL_MANAGE', 'guard_name' => 'web']);
        $user = User::create(['username' => 'pra_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);
        $this->app['auth']->guard('backpack')->setUser($user);

        $this->get(route('pricing.workflow.addons-form'))->assertOk()->assertSee('Download Addon-N-Discounts');
        $this->get(route('pricing.workflow.addons-export', ['sessionId' => $session->id, 'groups' => ['EXCHANGE']]))->assertOk()->assertDownload();
        $file = UploadedFile::fake()->createWithContent('Addon-N-Discounts.xlsx', (string) file_get_contents($this->book(['RSA' => [AddonDiscountWorkbookService::HEADERS['RSA']]])));
        $this->post(route('pricing.workflow.addons'), ['file' => $file, 'groups' => ['RSA'], 'wef_date' => '2026-10-01'])->assertRedirect(route('pricing.workflow.addons-form'));
        Queue::assertPushed(ImportAddonsJob::class, fn ($job) => $job->sessionId === $session->id && $job->groups === ['RSA']);

        $sessions->progress($session->fresh(), ['state' => 'done']);
        app(AddonService::class)->create(['addon_type' => 'RSA', 'amount' => 100]);  // stored rows exist → continuing keeps them
        $this->post(route('pricing.workflow.addons-continue'))->assertRedirect(route('pricing.workflow.rules-form'));
        $this->assertSame(PricingStage::Rules, $session->fresh()->stage());
    }
}
