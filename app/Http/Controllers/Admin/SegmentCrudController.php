<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use \App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use Revolution\Google\Sheets\Facades\Sheets;

class SegmentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Segment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/segment');
        CRUD::setEntityNameStrings('segment', 'segments');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.segment.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.segment.list');

        $segments = Segment::orderBy('id', 'desc')->get();

        $gridData = $segments->map(function ($segment, $index) {
            $mapped = $segment->toArray();
            $mapped['serial_no'] = $index + 1;

            $editUrl = backpack_url("segment/{$segment->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '"
                       class="btn btn-sm btn-primary py-1 px-2"
                       title="Edit">
                         Edit
                    </a>
                </div>
            ';

            $mapped['is_active'] = $segment->is_active ? 'Active' : 'Inactive';

            return $mapped;
        })->values();

        return view('admin.segment.list', [
            'title' => 'All Segments',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'code', 'headerName' => 'Code'],
                    ['field' => 'name', 'headerName' => 'Segment Name'],
                    ['field' => 'is_active', 'headerName' => 'Active'],
                    ['field' => 'action', 'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.segment.edit');

        $segment = Segment::findOrFail($id);

        $activeSubSegments = SubSegment::where(
            'segment_code',
            $segment->code
        )
            ->where('is_active', 1)
            ->pluck('name')
            ->toArray();

        return view('admin.segment.edit', [
            'title' => 'Edit Segment - ' . $segment->name,
            'segment' => $segment,
            'activeSubSegments' => $activeSubSegments,
        ]);
    }

    public function update(Request $request, $id)
    {
        $segment = Segment::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:5|unique:xlr8_vehicle_segment,code,' . $id,
            'is_active' => 'boolean',
        ]);

        if (
            $segment->is_active == 1 &&
            !$request->boolean('is_active')
        ) {

            $activeSubSegmentCount = SubSegment::where(
                'segment_code',
                $segment->code
            )
                ->where('is_active', 1)
                ->count();

            if ($activeSubSegmentCount > 0) {

                \Alert::error(
                    "Cannot deactivate Segment. {$activeSubSegmentCount} active Sub Segment(s) exist."
                )->flash();

                return redirect()->back()->withInput();
            }
        }

        $validated['is_active'] = $request->boolean('is_active');

        $segment->update($validated);

        \Alert::success(
            'Segment updated successfully!'
        )->flash();

        return redirect(backpack_url('segment'));
    }

    public function create()
    {
        $this->crud->setCreateView('admin.segment.create');

        return view('admin.segment.create', [
            'title' => 'Add New Segment',
        ]);
    }


    public function import()
{
    ini_set('max_execution_time', 300);

    $spreadsheetId = '1peFpdSoJwXDEVlHcp7M4vgWFexOcOg3qR-cpRZTxS4w';
    $sheetGid      = '1898588560'; 

    $gscolarr = [
        'model_code'     => 'Model Code',
        'oem_model'      => 'OEM Model',
        'oem_variant'    => 'OEM Variant',
        'segment'        => 'Segment',
        'sub_segment'    => 'Sub Segment',
        'fuel'           => 'Fuel',
        'seating'        => 'Seating',
        'wheels'         => 'Wheels',
        'transmission'   => 'Transmission',
        'drivetrain'     => 'Drivetrain',
        'body_make'      => 'Body Make',
        'body_type'      => 'Body Type',
        'cc'             => 'CC',
        'motor'          => 'Motor',
        'gvw'            => 'GVW',
        'gst_percent'    => 'GST%',
        'permit'         => 'Permit',
        'taxi_price'     => 'Taxi Price',
        'custom_model'   => 'Custom Model',
        'custom_variant' => 'Custom Variant',
        'display_name'   => 'Display Name',
        'colour_name'    => 'Colour Name',
        'status'         => 'Status',
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

        $fuelMap     = $this->buildKeyMap($keyvalues->get('FUEL_TYPE', collect()));
        $bodyMakeMap = $this->buildKeyMap($keyvalues->get('BODY_MAKE', collect()));
        $bodyTypeMap = $this->buildKeyMap($keyvalues->get('BODY_TYPE', collect()));
        $permitMap   = $this->buildKeyMap($keyvalues->get('PERMIT', collect()));
        $statusMap   = $this->buildKeyMap($keyvalues->get('VEHICLE_STATUS', collect()));

        $segmentMapping = [
            'LMM' => 'LMM',
            'BEV' => 'BEV',
            'PV'  => 'PV',
            'CV'  => 'CV',
        ];

        $now = now();

        $stats = [
            'segment'    => 0,
            'subsegment' => 0,
            'model'      => 0,
            'variant'    => 0,
            'skipped'    => 0,
        ];

        $seenSegments    = [];
        $seenSubsegments = [];
        $seenModels      = [];
        $seenVariants    = [];

        \Log::info('=== Vehicle Import Started (Google Sheet, Color merged into Variant) ===', [
            'spreadsheet_id' => $spreadsheetId,
            'gid'            => $sheetGid,
            'total_rows'     => count($values) - 1,
        ]);

        foreach (array_slice($values, 1) as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;

            $fullModelCode = trim($row[$gs_pos['model_code']] ?? '');
            $rawOemModel   = trim($row[$gs_pos['oem_model']] ?? '');
            $oemVariant    = trim($row[$gs_pos['oem_variant']] ?? '');
            $rawSegment    = strtoupper(trim($row[$gs_pos['segment']] ?? ''));
            $rawSubSegment = strtoupper(trim($row[$gs_pos['sub_segment']] ?? ''));
            $fuelStr       = strtoupper(trim($row[$gs_pos['fuel']] ?? ''));
            $seating       = $row[$gs_pos['seating']] ?? null;
            $wheels        = $row[$gs_pos['wheels']] ?? 4;
            $transmission  = strtoupper(trim($row[$gs_pos['transmission']] ?? ''));
            $drivetrain    = strtoupper(trim($row[$gs_pos['drivetrain']] ?? ''));
            $bodyMakeStr   = strtoupper(trim($row[$gs_pos['body_make']] ?? ''));
            $bodyTypeStr   = strtoupper(trim($row[$gs_pos['body_type']] ?? ''));
            $cc            = $row[$gs_pos['cc']] ?? null;
            $gvw           = $row[$gs_pos['gvw']] ?? null;
            $permitStr     = strtoupper(trim($row[$gs_pos['permit']] ?? ''));
            $taxiPrice     = strtoupper(trim($row[$gs_pos['taxi_price']] ?? 'NO'));
            $customModel   = trim($row[$gs_pos['custom_model']] ?? '');
            $customVariant = trim($row[$gs_pos['custom_variant']] ?? '');
            $displayName   = trim($row[$gs_pos['display_name']] ?? '');
            $colourName    = trim($row[$gs_pos['colour_name']] ?? '');
            $statusStr     = strtoupper(trim($row[$gs_pos['status']] ?? 'ACTIVE'));

            if (empty($fullModelCode) || empty($rawOemModel)) {
                \Log::warning("Row {$excelRow} SKIPPED — Empty Model Code or OEM Model");
                $stats['skipped']++;
                continue;
            }

            $variantCode = substr($fullModelCode, 0, -2);
            $colorCode   = strtoupper(substr($fullModelCode, -2));

            $modelCode      = strtoupper(substr($rawOemModel, 0, 30));
            $segmentCode    = $segmentMapping[$rawSegment] ?? strtoupper(substr($rawSegment, 0, 5));
            $subSegmentCode = !empty($rawSubSegment) ? substr($rawSubSegment, 0, 15) : null;

            $fuelTypeId = $this->getOrCreateKeyValue($fuelMap, 'FUEL_TYPE', $fuelStr, $now);
            $bodyMakeId = $this->getOrCreateKeyValue($bodyMakeMap, 'BODY_MAKE', $bodyMakeStr, $now);
            $bodyTypeId = $this->getOrCreateKeyValue($bodyTypeMap, 'BODY_TYPE', $bodyTypeStr, $now);
            $permitId   = $this->getOrCreateKeyValue($permitMap, 'PERMIT', $permitStr, $now);
            $statusId   = $this->getOrCreateKeyValue($statusMap, 'VEHICLE_STATUS', $statusStr, $now);

            if ($segmentCode && !isset($seenSegments[$segmentCode])) {
                if (!\DB::table('xlr8_vehicle_segment')->where('code', $segmentCode)->exists()) {
                    \DB::table('xlr8_vehicle_segment')->insert([
                        'code'       => $segmentCode,
                        'name'       => ucfirst(strtolower($segmentCode)),
                        'is_active'  => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $stats['segment']++;
                }
                $seenSegments[$segmentCode] = true;
            }

            if ($subSegmentCode && $segmentCode) {
                $subKey = "{$segmentCode}|{$subSegmentCode}";
                if (!isset($seenSubsegments[$subKey])) {
                    if (!\DB::table('xlr8_vehicle_subsegment')
                        ->where('segment_code', $segmentCode)
                        ->where('code', $subSegmentCode)->exists()) {
                        \DB::table('xlr8_vehicle_subsegment')->insert([
                            'segment_code' => $segmentCode,
                            'code'         => $subSegmentCode,
                            'name'         => ucfirst(strtolower($subSegmentCode)),
                            'is_active'    => 1,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ]);
                        $stats['subsegment']++;
                    }
                    $seenSubsegments[$subKey] = true;
                }
            }

            if (!isset($seenModels[$modelCode])) {
                if (!\DB::table('xlr8_vehicle_model')
                    ->where('code', $modelCode)->exists()) {
                    \DB::table('xlr8_vehicle_model')->insert([
                        'segment_code'     => $segmentCode,
                        'sub_segment_code' => $subSegmentCode,
                        'code'             => $modelCode,
                        'name'             => $customModel ?: $modelCode,
                        'oem_name'         => strtoupper($rawOemModel),
                        'is_active'        => 1,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                    $stats['model']++;
                }
                $seenModels[$modelCode] = true;
            }

            $variantKey = "{$modelCode}|{$variantCode}|{$colorCode}";

            if (isset($seenVariants[$variantKey])) {
                \Log::info("Row {$excelRow} — SKIPPED, duplicate within this import: {$variantCode} | {$colorCode}");
                $stats['skipped']++;
            } else {
                $exists = \DB::table('xlr8_vehicle_variant')
                    ->where('model_code', $modelCode)
                    ->where('code', $variantCode)
                    ->where('color_code', $colorCode)
                    ->exists();

                if ($exists) {
                    \Log::info("Row {$excelRow} — SKIPPED, already exists in DB: {$variantCode} | {$colorCode}");
                    $stats['skipped']++;
                } else {
                    \DB::table('xlr8_vehicle_variant')->insert([
                        'segment_code'     => $segmentCode,
                        'sub_segment_code' => $subSegmentCode,
                        'model_code'       => $modelCode,
                        'code'             => $variantCode,
                        'oem_name'         => $oemVariant,
                        'custom_name'      => $customVariant ?: null,
                        'display_name'     => $displayName ?: null,
                        'color'            => $colourName ?: null,
                        'color_code'       => $colorCode,
                        'fuel_type_id'     => $fuelTypeId,
                        'seating_capacity' => is_numeric($seating) ? (int)$seating : null,
                        'wheels'           => is_numeric($wheels) ? (int)$wheels : 4,
                        'gvw'              => is_numeric($gvw) ? (int)$gvw : null,
                        'cc_capacity'      => !empty($cc) ? (string)$cc : null,
                        'transmission'     => $transmission ?: null,
                        'drivetrain'       => $drivetrain ?: null,
                        'body_make_id'     => $bodyMakeId,
                        'body_type_id'     => $bodyTypeId,
                        'permit_id'        => $permitId,
                        'taxi_price'       => $taxiPrice,
                        'status_id'        => $statusId,
                        'is_csd'           => 0,
                        'is_active'        => 1,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                    $stats['variant']++;
                    \Log::info("Row {$excelRow} — Variant+Color inserted: {$variantCode} | {$colorCode} ({$colourName})");
                }

                $seenVariants[$variantKey] = true;
            }
        }

        \Log::info('=== Vehicle Import Completed (Google Sheet) ===', $stats);

        $summary = "Segments: {$stats['segment']} | Subsegments: {$stats['subsegment']} | Models: {$stats['model']} | Variants: {$stats['variant']} | Skipped: {$stats['skipped']}";

        \Alert::success("Import Completed → {$summary}")->flash();

    } catch (\Exception $e) {
        \Log::error('Vehicle Import (Google Sheet) failed', ['error' => $e->getMessage()]);
        \Alert::error('Import failed: ' . $e->getMessage())->flash();
    }

    return redirect()->back();
}

private function buildKeyMap(\Illuminate\Support\Collection $rows): array
{
    $map = [];
    foreach ($rows as $kv) {
        if (!empty($kv->key)) {
            $map[strtoupper(trim($kv->key))] = $kv->id;
        }
        if (!empty($kv->value)) {
            $map[strtoupper(trim($kv->value))] = $kv->id;
        }
        if (!empty($kv->code)) {
            $map[strtoupper(trim($kv->code))] = $kv->id;
        }
    }
    return $map;
}

private function getOrCreateKeyValue(&$map, string $keyword, ?string $value, $now)
{
    if (empty($value)) return null;
    $upper = strtoupper(trim($value));

    if (!isset($map[$upper])) {
        $id = \DB::table('xlr8_utils_keyvalue')->insertGetId([
            'keyword_code' => $keyword,
            'key'          => '',
            'code'         => $upper,
            'value'        => ucfirst(strtolower($value)),
            'status'       => 1,
            'is_active'    => 1,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
        $map[$upper] = $id;
        \Log::warning("Auto-created {$keyword}: {$value}");
    }
    return $map[$upper];
}

}
