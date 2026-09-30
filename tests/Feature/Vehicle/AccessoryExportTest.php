<?php

namespace Tests\Feature\Vehicle;

use App\Models\Core\ExportLog;
use App\Models\Vehicle\Accessory;
use App\Services\Vehicle\Accessories\AccessoryItemService;
use App\Services\Vehicle\Accessories\AccessoryScopeService;
use App\Services\Vehicle\AccessoryExportService;
use App\Services\Vehicle\VehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `php artisan vehicle-accessories:export` (AccessoryExportService): scoped rows carry the model / variant names and
 * the run is logged in `export_logs` (BUG-221 — the names were read from columns that do not exist).
 */
class AccessoryExportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_scoped_accessory_exports_with_model_and_variant_names_and_is_logged(): void
    {
        Storage::fake('public');
        $code = 'ZQA'.strtoupper(substr(uniqid(), -5)).'WH';
        $vehicles = app(VehicleService::class);
        $vehicles->createStubFromPriceList($code, 'ZETA EXPORT', 'Z8', 'Price List PV');
        $variant = $vehicles->findByOemCode($code);
        $vehicles->applyVehicleInfo($variant, ['segment' => 'PV', 'sub_segment' => 'PV', 'fuel' => 'DIESEL', 'seating' => '7', 'wheels' => '4',
            'transmission' => 'Manual', 'drivetrain' => 'RWD', 'body_make' => 'SUV', 'body_type' => 'COMPLETE', 'cc' => '2184', 'gst_percent' => '40', 'permit' => 'PRIVATE',
            'taxi_price' => 'N', 'custom_model' => 'Zeta', 'custom_variant' => 'Z8', 'display_name' => 'Zeta Z8 Export', 'colour_name' => 'WHITE', 'status' => 'ACTIVE']);
        $variant = $vehicles->findByOemCode($code);
        $partNo = 'ZQP'.strtoupper(substr(uniqid(), -6));
        app(AccessoryItemService::class)->create(['part_no' => $partNo, 'type' => Accessory::ALL_TYPES[0], 'item' => 'Zeta mud flaps', 'ndp' => 800, 'mrp' => 1000]);
        app(AccessoryScopeService::class)->create(['part_no' => $partNo, 'segment_code' => 'PV', 'model_code' => $variant->model_code, 'variant_code' => $code]);

        $result = app(AccessoryExportService::class)->store('exports/test/accessories.xlsx', ['part_no' => $partNo], userId: 1);

        $this->assertSame(1, $result['rows']);
        Storage::disk('public')->assertExists('exports/test/accessories.xlsx');
        $row = app(AccessoryExportService::class)->rows(true, ['part_no' => $partNo])[0];
        $this->assertSame('Zeta Z8 Export', $row['Variant']);
        $this->assertNotSame('', $row['MODEL']);
        $this->assertSame('ACTIVE', $row['STATUS']);
        $log = ExportLog::query()->findOrFail($result['log_id']);
        $this->assertSame(['success', 'custom', 1, 'exports/test/accessories.xlsx'], [$log->getAttribute('status'), $log->getAttribute('export_type'),
            (int) $log->getAttribute('total_records'), $log->getAttribute('file_path')]);
    }
}
