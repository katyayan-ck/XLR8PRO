<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\Vehicle\ModelSpec;
use App\Models\Vehicle\SpecItem;
use App\Models\Vehicle\TrimFeature;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\Content\SpecItemService;
use App\Services\Vehicle\Content\VehicleContentWorkbookService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * DEC-092 Phase 3: specifications / features workbooks — our format round-trips by codes, the OEM sample format is
 * matched by name (a trim by the start of its name), unknown items are added, blanks keep, "-NA-" = not applicable,
 * unmatched columns are reported.
 */
class VehicleContentWorkbookTest extends TestCase
{
    use DatabaseTransactions;

    private Variant $variant;

    private VehicleModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->variant = Variant::query()->whereNotNull('model_code')->whereNotNull('display_name')->first() ?? $this->markTestSkipped('No variant in the test copy.');
        $this->model = VehicleModel::query()->where('code', $this->variant->model_code)->firstOrFail();
    }

    /** @param  array<string, list<list<mixed>>>  $sheets */
    private function book(array $sheets): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $book->createSheet()->setTitle($title)->fromArray($rows);
        }
        $path = tempnam(sys_get_temp_dir(), 'vc').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function test_the_oem_specification_sheet_is_matched_by_model_name(): void
    {
        $path = $this->book(['PERSONAL' => [
            ['Head', 'SubHead', $this->model->name, 'NO SUCH MODEL ZQ'],
            ['ENGINE ZQ', 'DISPLACEMENT ZQ', '1493 CC', '999'],
            ['BRAKES ZQ', 'FRONT ZQ', '-NA-', 'DISC'],
            ['BRAKES ZQ', 'REAR ZQ', '', 'DRUM'],
        ]]);

        $report = app(VehicleContentWorkbookService::class)->importSpecs($path);

        $this->assertSame('sample', $report['format']);
        $this->assertSame(['PERSONAL: NO SUCH MODEL ZQ'], $report['unmatched']);
        $this->assertCount(3, $report['items_added']);
        $values = ModelSpec::query()->where('model_code', $this->model->code)->get()->keyBy(fn ($r) => SpecItem::query()->where('code', $r->spec_item_code)->value('name'));
        $this->assertSame('1493 CC', $values['Displacement Zq']->value ?? $values['DISPLACEMENT ZQ']->value);
        $this->assertSame('N/A', ($values['Front Zq'] ?? $values['FRONT ZQ'])->value);
        $this->assertFalse(isset($values['Rear Zq']) || isset($values['REAR ZQ']), 'a blank cell writes nothing');
    }

    public function test_the_oem_feature_sheet_matches_a_trim_by_the_start_of_its_name(): void
    {
        $name = (string) $this->variant->display_name;
        $short = mb_substr($name, 0, max(6, mb_strlen($name) - 4));
        $ambiguous = Variant::query()->where('model_code', $this->model->code)->where('code', '!=', $this->variant->code)
            ->where('display_name', 'like', $short.'%')->exists();
        $column = $ambiguous ? $name : $short;
        $path = $this->book([$this->model->name => [
            ['Head Group', 'Head', $column],
            ['SAFETY ZQ', 'Airbags zq', 'Yes'],
            ['undefined', 'Cup holder zq', '---'],
        ]]);

        $report = app(VehicleContentWorkbookService::class)->importFeatures($path);

        $this->assertSame([], $report['unmatched']);
        $values = TrimFeature::query()->where('variant_code', $this->variant->code)->pluck('value')->all();
        $this->assertContains('Yes', $values);
        $this->assertContains('No', $values);
    }

    public function test_our_workbook_round_trips_by_codes(): void
    {
        $item = app(SpecItemService::class)->create(['category' => 'Tyres Zq', 'name' => 'Size Zq']);
        $service = app(VehicleContentWorkbookService::class);
        $path = tempnam(sys_get_temp_dir(), 'vc').'.xlsx';
        $service->exportSpecs($path);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName((string) $this->model->segment_code);
        $header = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0];
        $col = array_search($this->model->code.' · '.$this->model->name, $header, true);
        $this->assertNotFalse($col);
        foreach ($sheet->getRowIterator(2) as $row) {
            if ($sheet->getCell('D'.$row->getRowIndex())->getValue() === $item->code) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1).$row->getRowIndex(), '205/65 R16');
            }
        }
        (new Xlsx($book))->save($path);

        $report = $service->importSpecs($path);

        $this->assertSame('workbook', $report['format']);
        $this->assertSame('205/65 R16', ModelSpec::query()->where('model_code', $this->model->code)->where('spec_item_code', $item->code)->value('value'));
    }

    public function test_import_and_export_over_http_need_the_permissions(): void
    {
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
        $this->get(route('vehicle.content.export', 'features'))->assertOk()->assertDownload();

        $file = new UploadedFile($this->book(['PERSONAL' => [['Head', 'SubHead', $this->model->name], ['ENGINE ZQ', 'TORQUE ZQ', '300 Nm']]]), 's.xlsx', null, null, true);
        $this->post(route('vehicle.content.import'), ['kind' => 'specs', 'file' => $file])
            ->assertRedirect(route('vehicle.content.index'))->assertSessionHas('content_import');
    }
}
