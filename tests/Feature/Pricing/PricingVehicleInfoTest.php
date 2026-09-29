<?php

namespace Tests\Feature\Pricing;

use App\Jobs\Vehicle\Pricing\Process\ImportVehicleInfoJob;
use App\Models\User;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\Variant;
use App\Services\Vehicle\Pricing\Import\PricingWorkbookReader;
use App\Services\Vehicle\Pricing\Import\VehicleInfoWorkbookService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-073 step 3: the Vehicle Info export lists every vehicle with its missing fields and lookup codes; the import fills
 * the masters, keeps incomplete vehicles out of Active, rejects unknown codes / lookup values, normalises GST (0.28 → 28)
 * and transmission (At → Automatic), and its summary drives the screen.
 */
class PricingVehicleInfoTest extends TestCase
{
    use DatabaseTransactions;

    private const HEAD = ['Model Code', 'OEM Model', 'OEM Variant', 'Segment', 'Sub Segment', 'Fuel', 'Seating', 'Wheels', 'Transmission', 'Drivetrain', 'Body Make', 'Body Type', 'CC', 'Motor', 'GVW', 'GST%', 'Permit', 'Taxi Price', 'Custom Model', 'Custom Variant', 'Display Name', 'Colour Name', 'Status', 'Shield Pack'];

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
        $sessions->advance($this->session, PricingStage::VehicleInfo);
    }

    private function stub(string $suffix): string
    {
        $code = "ZQV{$this->tag}{$suffix}";
        app(VehicleService::class)->createStubFromPriceList($code, 'ZETA PRO', 'ZX 7S', 'Price List PV');

        return $code;
    }

    /** @param  array<string, string>  $overrides */
    private function row(string $code, array $overrides = []): array
    {
        $row = array_combine(self::HEAD, [$code, 'ZETA PRO', 'ZX 7S', 'PV', 'PV', 'DIESEL', '7', '4', 'At', 'RWD', 'SUV', 'COMPLETE', '2184', '', '', '0.28', 'PRIVATE', 'N', 'Zeta Pro', 'ZX 7 Seater', 'Zeta Pro ZX 7S', 'DEEP FOREST', 'ACTIVE', '']);

        return array_values(array_merge($row, $overrides));
    }

    /** @param list<list<string>> $rows */
    private function sheet(array $rows): string
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Vehicle Info')->fromArray(array_merge([self::HEAD], $rows));
        $relative = "pricing/{$this->session->id}/vi-test.xlsx";
        Storage::disk('local')->makeDirectory("pricing/{$this->session->id}");
        (new Xlsx($book))->save(Storage::disk('local')->path($relative));

        return $relative;
    }

    private function actingAsBackpackUser(User $user): static
    {
        $this->app['auth']->guard('backpack')->setUser($user);

        return $this;
    }

    /** @param list<string> $permissions */
    private function user(array $permissions): User
    {
        $user = User::create(['username' => 'prv_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    public function test_import_completes_vehicles_keeps_incomplete_ones_out_of_active_and_rejects_bad_rows(): void
    {
        $complete = $this->stub('WH');
        $partial = $this->stub('RD');
        $badFuel = $this->stub('BL');
        $path = $this->sheet([
            $this->row($complete),
            $this->row($partial, ['Transmission' => '', 'Display Name' => '']),
            $this->row($badFuel, ['Fuel' => 'HYDROGEN']),
            $this->row("ZQV{$this->tag}ZZ"),
        ]);

        app()->call([new ImportVehicleInfoJob($this->session->id, $path), 'handle']);

        $round = $this->session->fresh()->stats['vehicle_info'];
        $this->assertSame([4, 1, 1, 1, 1, 1], [$round['rows'], $round['completed'], $round['newly_completed'], $round['incomplete'], $round['rejected'], $round['unknown']]);
        $done = Variant::where('code', $complete)->firstOrFail();
        $this->assertSame([true, 'ACTIVE', 'Automatic', 28.0, 'NO'], [(bool) $done->is_active, $done->statusKkv?->code, $done->transmission, (float) $done->gst_percent, $done->taxi_price]);
        $half = Variant::where('code', $partial)->firstOrFail();
        $this->assertSame([false, 'INCOMPLETE'], [(bool) $half->is_active, $half->statusKkv?->code]);
        $reasons = collect($round['issues'])->pluck('reason', 'code');
        $this->assertStringContainsString('Missing: Transmission, Display Name — kept Incomplete, not Active.', $reasons[$partial]);
        $this->assertStringContainsString('Unknown Fuel', $reasons[$badFuel]);
        $this->assertStringContainsString('new vehicles come only from the price lists', $reasons["ZQV{$this->tag}ZZ"]);
        $this->assertFalse(Variant::where('code', "ZQV{$this->tag}ZZ")->exists());
        $this->assertSame('done', $this->session->fresh()->progress['state']);
    }

    /** Owner request 30-09: imported rows must relate to existing masters — nothing is created from the sheet. */
    public function test_import_rejects_values_that_are_not_in_the_masters(): void
    {
        $segment = $this->stub('WH');
        $drivetrain = $this->stub('RD');
        $label = $this->stub('BL');
        $path = $this->sheet([
            $this->row($segment, ['Segment' => 'NOSUCHSEG', 'Sub Segment' => 'NOSUCHSEG']),
            $this->row($drivetrain, ['Drivetrain' => 'HOVER']),
            $this->row($label, ['Transmission' => 'MANUAL', 'Drivetrain' => 'Fwd']),
        ]);

        app()->call([new ImportVehicleInfoJob($this->session->id, $path), 'handle']);

        $reasons = collect($this->session->fresh()->stats['vehicle_info']['issues'])->pluck('reason', 'code');
        $this->assertStringContainsString('Unknown Segment "NOSUCHSEG"', $reasons[$segment]);
        $this->assertFalse(Segment::where('code', 'NOSUCHSEG')->exists(), 'no master is created from the sheet');
        $this->assertStringContainsString('Unknown Drivetrain "HOVER"', $reasons[$drivetrain]);
        $saved = Variant::where('code', $label)->firstOrFail();
        $this->assertSame(['Manual', 'FWD'], [$saved->transmission, $saved->drivetrain], 'a code or a label from the master is accepted');
    }

    /** Owner request 30-09: every lookup column is a dropdown fed from the masters (hidden Lists sheet, codes). */
    public function test_export_carries_master_dropdowns_on_a_hidden_lists_sheet(): void
    {
        $this->stub('WH');
        $out = Storage::disk('local')->path('vi-dropdowns.xlsx');

        app(VehicleInfoWorkbookService::class)->export($out);

        $book = IOFactory::load($out);
        $lists = $book->getSheetByName('Lists');
        $this->assertNotNull($lists);
        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $lists->getSheetState());
        foreach (['LST_SEGMENT', 'LST_FUEL', 'LST_TRANSMISSION', 'LST_DRIVETRAIN', 'LST_BODY_MAKE', 'LST_BODY_TYPE', 'LST_PERMIT', 'LST_TAXI_PRICE', 'LST_STATUS', 'SUB_PV'] as $name) {
            $this->assertNotNull($book->getNamedRange($name), "named range {$name}");
        }
        $formulas = collect($book->getSheetByName('Vehicle Info')->getDataValidationCollection())->map(fn ($v) => $v->getFormula1())->values()->all();
        $this->assertContains('LST_SEGMENT', array_map(fn ($f) => ltrim((string) $f, '='), $formulas));
        $this->assertNotEmpty(array_filter($formulas, fn ($f) => str_contains((string) $f, 'INDIRECT("SUB_"')), 'sub-segment follows the segment');
        $fuelCodes = array_filter(array_map(fn ($r) => $r[1] ?? null, $lists->toArray(null, false, false, false)));
        $this->assertContains('DIESEL', $fuelCodes);
    }

    public function test_export_lists_every_vehicle_with_lookup_codes_and_missing_fields(): void
    {
        $complete = $this->stub('WH');
        $stub = $this->stub('RD');
        app()->call([new ImportVehicleInfoJob($this->session->id, $this->sheet([$this->row($complete)])), 'handle']);
        $out = Storage::disk('local')->path('vi-export.xlsx');

        $summary = app(VehicleInfoWorkbookService::class)->export($out);

        $this->assertSame(Variant::count(), $summary['rows']);
        $rows = [];
        foreach (app(PricingWorkbookReader::class)->rows($out, 'Vehicle Info') as $cells) {
            $rows[$cells[0]] = $cells;
        }
        $this->assertSame('Missing Fields', end($rows['Model Code']));
        [$fuel, $permit, $bodyMake, $gst, $status, $missing] = [$rows[$complete][5], $rows[$complete][16], $rows[$complete][10], $rows[$complete][15], $rows[$complete][22], $rows[$complete][24] ?? null];
        $this->assertSame(['DIESEL', 'PRIVATE', 'SUV', 28, 'ACTIVE', null], [$fuel, $permit, $bodyMake, (int) $gst, $status, $missing]);
        $this->assertSame('INCOMPLETE', $rows[$stub][22]);
        $this->assertStringContainsString('Fuel', (string) $rows[$stub][24]);
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('vi.xlsx', (string) file_get_contents(Storage::disk('local')->path($this->sheet([$this->row($this->stub('WH'))]))));
    }

    public function test_a_view_only_user_can_download_but_not_import(): void
    {
        $viewer = $this->user(['PRC_WKFL_VIEW']);

        $this->actingAsBackpackUser($viewer)->get(route('pricing.workflow.vehicle-info-form'))->assertOk()->assertSee('Download Vehicle Info')->assertDontSee('Import Vehicle Info');
        $this->actingAsBackpackUser($viewer)->post(route('pricing.workflow.vehicle-info-import'), ['file' => $this->upload()])->assertForbidden();
        $this->actingAsBackpackUser($viewer)->post(route('pricing.workflow.vehicle-info-continue'))->assertForbidden();
    }

    public function test_screen_imports_through_the_queue_and_continue_moves_to_prices(): void
    {
        Queue::fake();
        $manager = $this->user(['PRC_WKFL_VIEW', 'PRC_WKFL_MANAGE']);
        $upload = $this->upload();

        $this->actingAsBackpackUser($manager)->post(route('pricing.workflow.vehicle-info-import'), ['file' => $upload])
            ->assertRedirect(route('pricing.workflow.vehicle-info-form'))->assertSessionHas('success');
        Queue::assertPushed(ImportVehicleInfoJob::class, fn ($job) => $job->sessionId === $this->session->id);
        $this->assertSame('running', $this->session->fresh()->progress['state']);
        $this->actingAsBackpackUser($manager)->post(route('pricing.workflow.vehicle-info-continue'))->assertSessionHas('warning');

        app(PricingSessionService::class)->progress($this->session->fresh(), ['state' => 'done']);
        $this->actingAsBackpackUser($manager)->post(route('pricing.workflow.vehicle-info-continue'))->assertRedirect(route('pricing.workflow.prices-form'));
        $this->assertSame(PricingStage::Prices, $this->session->fresh()->stage());
    }
}
