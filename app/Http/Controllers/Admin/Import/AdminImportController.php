<?php

namespace App\Http\Controllers\Admin\Import;

use Illuminate\Validation\ValidationException;
use App\Services\Vehicle\VariantService;
use App\Services\Vehicle\VehicleModelService;
use App\Services\Vehicle\SubSegmentService;
use App\Services\Vehicle\SegmentService;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Segment;
use App\Http\Controllers\Controller;

use Illuminate\Support\Collection;
use Revolution\Google\Sheets\Facades\Sheets;

class AdminImportController extends Controller
{
    // Admin Import Page
    public function admin()
    {
        return view('admin.import.admin');
    }
    /**
     * Bulk Google-Sheets import of vehicle master data (segments/sub-segments/
     * models/variants/colors). Reachable via routes/backpack/core.php's
     * `segment/import` route — unlike BrandCrudController::import(), this one is
     * real and used. Gated on segment.create as the closest existing permission
     * (this import creates segment/model/variant/color rows; there is no
     * dedicated "vehicle-import" permission in xlr8_iam_permissions).
     */
    public function import()
    {
        if (! backpack_user()->can('VEH_SEG_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to run this import.');
        }

        ini_set('max_execution_time', 300);

        $spreadsheetId = '1peFpdSoJwXDEVlHcp7M4vgWFexOcOg3qR-cpRZTxS4w';
        $sheetGid = '1898588560';

        $gscolarr = [
            'model_code' => 'Model Code',
            'oem_model' => 'OEM Model',
            'oem_variant' => 'OEM Variant',
            'segment' => 'Segment',
            'sub_segment' => 'Sub Segment',
            'fuel' => 'Fuel',
            'seating' => 'Seating',
            'wheels' => 'Wheels',
            'transmission' => 'Transmission',
            'drivetrain' => 'Drivetrain',
            'body_make' => 'Body Make',
            'body_type' => 'Body Type',
            'cc' => 'CC',
            'motor' => 'Motor',
            'gvw' => 'GVW',
            'gst_percent' => 'GST%',
            'permit' => 'Permit',
            'taxi_price' => 'Taxi Price',
            'custom_model' => 'Custom Model',
            'custom_variant' => 'Custom Variant',
            'display_name' => 'Display Name',
            'colour_name' => 'Colour Name',
            'status' => 'Status',
        ];

        try {
            \Log::info("Vehicle Import: Starting sheet GID={$sheetGid}");

            $values = Sheets::spreadsheet($spreadsheetId)
                ->sheetById($sheetGid)
                ->all();

            if (empty($values) || count($values) < 2) {
                \Alert::error('Sheet is empty.')->flash();

                return redirect()->back();
            }

            $gs_pos = array_fill_keys(array_keys($gscolarr), null);

            foreach ($values[0] as $key => $header) {
                $header = trim($header);
                foreach ($gscolarr as $dbField => $expectedHeader) {
                    if (strcasecmp($header, $expectedHeader) === 0) {
                        $gs_pos[$dbField] = $key;
                        break;
                    }
                }
            }

            $keyvalues = \DB::table('xlr8_utils_keyvalue')
                ->whereIn('keyword_code', ['FUEL_TYPE', 'BODY_MAKE', 'BODY_TYPE', 'PERMIT', 'VEHICLE_STATUS'])
                ->get()
                ->groupBy('keyword_code');

            $fuelMap = $this->buildKeyMap($keyvalues->get('FUEL_TYPE', collect()));
            $bodyMakeMap = $this->buildKeyMap($keyvalues->get('BODY_MAKE', collect()));
            $bodyTypeMap = $this->buildKeyMap($keyvalues->get('BODY_TYPE', collect()));
            $permitMap = $this->buildKeyMap($keyvalues->get('PERMIT', collect()));
            $statusMap = $this->buildKeyMap($keyvalues->get('VEHICLE_STATUS', collect()));

            $segmentMapping = [
                'LMM' => 'LMM',
                'BEV' => 'BEV',
                'PV' => 'PV',
                'CV' => 'CV',
            ];

            $now = now();

            $stats = [
                'segment' => 0,
                'subsegment' => 0,
                'model' => 0,
                'variant' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
            $errors = [];
            $segments = app(SegmentService::class);
            $subSegments = app(SubSegmentService::class);
            $models = app(VehicleModelService::class);
            $variants = app(VariantService::class);

            $seenSegments = [];
            $seenSubsegments = [];
            $seenModels = [];
            $seenVariants = [];

            \Log::info('=== Vehicle Import Started (Google Sheet, Color merged into Variant) ===', [
                'spreadsheet_id' => $spreadsheetId,
                'gid' => $sheetGid,
                'total_rows' => count($values) - 1,
            ]);

            foreach (array_slice($values, 1) as $rowIndex => $row) {
                $excelRow = $rowIndex + 2;

                $fullModelCode = trim($row[$gs_pos['model_code']] ?? '');
                $rawOemModel = trim($row[$gs_pos['oem_model']] ?? '');
                $oemVariant = trim($row[$gs_pos['oem_variant']] ?? '');
                $rawSegment = strtoupper(trim($row[$gs_pos['segment']] ?? ''));
                $rawSubSegment = strtoupper(trim($row[$gs_pos['sub_segment']] ?? ''));
                $fuelStr = strtoupper(trim($row[$gs_pos['fuel']] ?? ''));
                $seating = $row[$gs_pos['seating']] ?? null;
                $wheels = $row[$gs_pos['wheels']] ?? 4;
                $transmission = strtoupper(trim($row[$gs_pos['transmission']] ?? ''));
                $drivetrain = strtoupper(trim($row[$gs_pos['drivetrain']] ?? ''));
                $bodyMakeStr = strtoupper(trim($row[$gs_pos['body_make']] ?? ''));
                $bodyTypeStr = strtoupper(trim($row[$gs_pos['body_type']] ?? ''));
                $cc = $row[$gs_pos['cc']] ?? null;
                $gvw = $row[$gs_pos['gvw']] ?? null;
                $permitStr = strtoupper(trim($row[$gs_pos['permit']] ?? ''));
                $taxiPrice = strtoupper(trim($row[$gs_pos['taxi_price']] ?? 'NO'));
                $customModel = trim($row[$gs_pos['custom_model']] ?? '');
                $customVariant = trim($row[$gs_pos['custom_variant']] ?? '');
                $displayName = trim($row[$gs_pos['display_name']] ?? '');
                $colourName = trim($row[$gs_pos['colour_name']] ?? '');
                $statusStr = strtoupper(trim($row[$gs_pos['status']] ?? 'ACTIVE'));

                if (empty($fullModelCode) || empty($rawOemModel)) {
                    \Log::warning("Row {$excelRow} SKIPPED — Empty Model Code or OEM Model");
                    $stats['skipped']++;

                    continue;
                }

                // Variant code = the full OEM code; colour code = its last two characters (one row per colour).
                $variantCode = $fullModelCode;
                $colorCode = strtoupper(substr($fullModelCode, -2));
                $segmentCode = $segmentMapping[$rawSegment] ?? $rawSegment;

                $fuelTypeId = $this->getOrCreateKeyValue($fuelMap, 'FUEL_TYPE', $fuelStr, $now);
                $bodyMakeId = $this->getOrCreateKeyValue($bodyMakeMap, 'BODY_MAKE', $bodyMakeStr, $now);
                $bodyTypeId = $this->getOrCreateKeyValue($bodyTypeMap, 'BODY_TYPE', $bodyTypeStr, $now);
                $permitId = $this->getOrCreateKeyValue($permitMap, 'PERMIT', $permitStr, $now);
                $statusId = $this->getOrCreateKeyValue($statusMap, 'VEHICLE_STATUS', $statusStr, $now);

                // Every write goes through the entity service: same formats and rules as the admin forms (DEC-050).
                try {
                    $segment = $segments->normalise(['code' => $segmentCode])['code'] ?? null;
                    if ($segment && ! isset($seenSegments[$segment])) {
                        if (! Segment::where('code', $segment)->exists()) {
                            $segments->create(['code' => $segment, 'name' => $segment, 'is_active' => true]);
                            $stats['segment']++;
                        }
                        $seenSegments[$segment] = true;
                    }

                    $subSegment = $rawSubSegment !== '' ? ($subSegments->normalise(['code' => $rawSubSegment])['code'] ?? null) : null;
                    if ($subSegment && ! isset($seenSubsegments[$subSegment])) {
                        if (! SubSegment::where('code', $subSegment)->exists()) {
                            $subSegments->create(['segment_code' => $segment, 'code' => $subSegment, 'name' => $rawSubSegment, 'is_active' => true]);
                            $stats['subsegment']++;
                        }
                        $seenSubsegments[$subSegment] = true;
                    }

                    $modelCode = $models->normalise(['code' => $rawOemModel])['code'];
                    if (! isset($seenModels[$modelCode])) {
                        if (! VehicleModel::where('code', $modelCode)->exists()) {
                            $models->create([
                                'segment_code' => $segment,
                                'sub_segment_code' => $subSegment,
                                'code' => $modelCode,
                                'name' => $customModel ?: $rawOemModel,
                                'oem_name' => $rawOemModel,
                                'is_active' => true,
                            ]);
                            $stats['model']++;
                        }
                        $seenModels[$modelCode] = true;
                    }

                    $variantKey = "{$variantCode}|{$colorCode}";
                    if (isset($seenVariants[$variantKey]) || Variant::where('code', $variantCode)->where('color_code', $colorCode)->exists()) {
                        $stats['skipped']++;
                    } else {
                        $variants->create([
                            'segment_code' => $segment,
                            'sub_segment_code' => $subSegment,
                            'model_code' => $modelCode,
                            'code' => $variantCode,
                            'color' => $colourName,
                            'color_code' => $colorCode,
                            'oem_name' => $oemVariant,
                            'custom_name' => $customVariant,
                            'display_name' => $displayName,
                            'fuel_type_id' => $fuelTypeId,
                            'seating_capacity' => is_numeric($seating) ? (int) $seating : null,
                            'wheels' => is_numeric($wheels) ? (int) $wheels : 4,
                            'gvw' => is_numeric($gvw) ? (int) $gvw : null,
                            'cc_capacity' => $cc,
                            'transmission' => $transmission,
                            'drivetrain' => $drivetrain,
                            'body_make_id' => $bodyMakeId,
                            'body_type_id' => $bodyTypeId,
                            'permit_id' => $permitId,
                            'taxi_price' => $taxiPrice,
                            'status_id' => $statusId,
                            'is_csd' => false,
                            'is_active' => true,
                        ]);
                        $stats['variant']++;
                    }
                    $seenVariants[$variantKey] = true;
                } catch (ValidationException $e) {
                    $stats['errors']++;
                    $message = "Row {$excelRow} ({$fullModelCode}): ".implode(' ', $e->validator->errors()->all());
                    $errors[] = $message;
                    \Log::warning('Vehicle import row rejected', ['row' => $excelRow, 'errors' => $e->errors()]);
                }
            }

            \Log::info('=== Vehicle Import Completed (Google Sheet) ===', $stats);

            $summary = "Segments: {$stats['segment']} | Subsegments: {$stats['subsegment']} | Models: {$stats['model']} | Variants: {$stats['variant']} | Skipped: {$stats['skipped']} | Rejected: {$stats['errors']}";

            \Alert::success("Import Completed → {$summary}")->flash();
            if ($errors !== []) {
                \Alert::warning('Rejected rows: '.implode(' · ', array_slice($errors, 0, 10)).(count($errors) > 10 ? ' …' : ''))->flash();
            }
        } catch (\Exception $e) {
            \Log::error('Vehicle Import (Google Sheet) failed', ['error' => $e->getMessage()]);
            \Alert::error('Import failed: ' . $e->getMessage())->flash();
        }

        return redirect()->back();
    }

    private function buildKeyMap(Collection $rows): array
    {
        $map = [];
        foreach ($rows as $kv) {
            if (! empty($kv->key)) {
                $map[strtoupper(trim($kv->key))] = $kv->id;
            }
            if (! empty($kv->value)) {
                $map[strtoupper(trim($kv->value))] = $kv->id;
            }
            if (! empty($kv->code)) {
                $map[strtoupper(trim($kv->code))] = $kv->id;
            }
        }

        return $map;
    }

    private function getOrCreateKeyValue(&$map, string $keyword, ?string $value, $now)
    {
        if (empty($value)) {
            return null;
        }
        $upper = strtoupper(trim($value));

        if (! isset($map[$upper])) {
            $id = \DB::table('xlr8_utils_keyvalue')->insertGetId([
                'keyword_code' => $keyword,
                'key' => '',
                'code' => $upper,
                'value' => ucfirst(strtolower($value)),
                'status' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$upper] = $id;
            \Log::warning("Auto-created {$keyword}: {$value}");
        }

        return $map[$upper];
    }
}
