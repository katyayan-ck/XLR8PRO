<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Vehicle\Brand;


class BrandCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Vehicle\Brand::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/brand');
        CRUD::setEntityNameStrings('brand', 'brands');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.brand.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.brand.list');

        $brands = \App\Models\Vehicle\Brand::select([
            'id',
            'code',
            'name',
            
            'is_active'
        ])->orderBy('id', 'desc')->get();

        $gridData = $brands->map(function ($brand, $index) {
            $mapped = $brand->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $brand->is_active ? 'Active' : 'Inactive';

            $editUrl = backpack_url("brand/{$brand->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '"
                       class="btn btn-sm btn-primary py-1 px-2"
                       title="Edit">
                         Edit
                    </a>
                </div>
            ';
            return $mapped;
        })->values();

        return view('admin.brand.list', [
            'title' => 'All Brands',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No.'],
                    ['field' => 'code',         'headerName' => 'Code'],
                    ['field' => 'name',         'headerName' => 'Brand Name'],
                   
                    ['field' => 'is_active',    'headerName' => 'Active'],
                    ['field' => 'action',       'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.brand.edit');

        $brand = Brand::findOrFail($id);

        return view('admin.brand.edit', [
            'title' => 'Edit Brand - ' . $brand->name,
            'brand' => $brand,
        ]);
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|size:5|unique:xlr8_vehicle_brand,code,' . $id,
            'is_active'   => 'boolean',
        ]);

        $brand->update($validated);

        \Alert::success('Brand updated successfully!')->flash();

        return redirect(backpack_url('brand'));
    }

    public function create()
    {
        $this->crud->setCreateView('admin.brand.create');

        return view('admin.brand.create', [
            'title' => 'Add New Brand',
        ]);
    }

        public function import(Request $request)
{
    ini_set('max_execution_time', 300);
 
    if (!$request->hasFile('excel_file')) {
        \Alert::error('No file uploaded!')->flash();
        return redirect()->back();
    }
 
    $file = $request->file('excel_file');
    if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) {
        \Alert::error('Only Excel files (.xlsx, .xls) allowed')->flash();
        return redirect()->back();
    }
 
    try {
        $reader      = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($file->getPathname());
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
 
        if (count($rows) < 2) {
            \Alert::error('Excel file is empty.')->flash();
            return redirect()->back();
        }
 
        $keyvalues = \DB::table('xlr8_utils_keyvalue')
            ->whereIn('keyword_code', ['FUEL_TYPE', 'BODY_MAKE', 'BODY_TYPE', 'PERMIT', 'VEHICLE_STATUS'])
            ->get()
            ->groupBy('keyword_code');

        $fuelMap     = $this->buildKeyMap($keyvalues->get('FUEL_TYPE',     collect()));
        $bodyMakeMap = $this->buildKeyMap($keyvalues->get('BODY_MAKE',     collect()));
        $bodyTypeMap = $this->buildKeyMap($keyvalues->get('BODY_TYPE',     collect()));
        $permitMap   = $this->buildKeyMap($keyvalues->get('PERMIT',        collect()));
        $statusMap   = $this->buildKeyMap($keyvalues->get('VEHICLE_STATUS', collect()));
 
        $segmentMapping = [
            'LMM'        => 'LMM',
            'BEV'        => 'BEV',
            'PV'         => 'PERSL',
            'CV'         => 'COMML',
            'COMMERCIAL' => 'COMML',
            'PERSONAL'   => 'PERSL',
            'COMML'      => 'COMML',
            'PERSL'      => 'PERSL',
        ];
 
        $brandCode = 'MHD';
        $now       = now();
        $stats     = [
            'segment'    => 0,
            'subsegment' => 0,
            'model'      => 0,
            'variant'    => 0,
            'color'      => 0,
            'skipped'    => 0,
        ];
 
        $seenSegments    = [];
        $seenSubsegments = [];
        $seenModels      = [];
        $seenVariants    = [];
        $seenColors      = [];
 
        \Log::info('=== Vehicle Import Started ===', [
            'file'       => $file->getClientOriginalName(),
            'total_rows' => count($rows) - 1,
        ]);
 
        foreach (array_slice($rows, 1) as $rowIndex => $row) {
            $excelRow = $rowIndex + 2; 
 
            $fullModelCode  = trim($row[0]  ?? '');  
            $rawOemModel    = trim($row[1]  ?? '');   
            $oemVariant     = trim($row[2]  ?? '');
            $rawSegment     = strtoupper(trim($row[3]  ?? ''));
            $rawSubSegment  = strtoupper(trim($row[4]  ?? ''));
            $fuelStr        = strtoupper(trim($row[5]  ?? ''));
            $seating        = $row[6]  ?? null;
            $wheels         = $row[7]  ?? 4;
            $transmission   = strtoupper(trim($row[8]  ?? ''));
            $drivetrain     = strtoupper(trim($row[9]  ?? ''));
            $bodyMakeStr    = strtoupper(trim($row[10] ?? ''));
            $bodyTypeStr    = strtoupper(trim($row[11] ?? ''));
            $cc             = $row[12] ?? null;
            $gvw            = $row[13] ?? null;
            $permitStr      = strtoupper(trim($row[14] ?? ''));
            $taxiPrice      = strtoupper(trim($row[15] ?? 'NO'));
            $customModel    = trim($row[16] ?? '');
            $customVariant  = trim($row[17] ?? '');
            $displayName    = trim($row[18] ?? '');
            $colourName     = trim($row[19] ?? '');
            $statusStr      = strtoupper(trim($row[20] ?? 'ACTIVE'));
 
            if (empty($fullModelCode) || empty($rawOemModel)) {
                \Log::warning("Row {$excelRow} SKIPPED — Empty Model Code or OEM Model", [
                    'model_code' => $fullModelCode,
                    'oem_model'  => $rawOemModel,
                ]);
                $stats['skipped']++;
                continue;
            }
 
            if (strlen($fullModelCode) < 3) {
                \Log::warning("Row {$excelRow} SKIPPED — Model Code too short to derive variant/colour", [
                    'model_code' => $fullModelCode,
                ]);
                $stats['skipped']++;
                continue;
            }
 
            try {
                $variantCode = substr($fullModelCode, 0, -2);  
                $colorCode   = strtoupper(substr($fullModelCode, -2)); 
 
                $modelCode      = strtoupper(substr($rawOemModel, 0, 30));
                $segmentCode    = $segmentMapping[$rawSegment] ?? strtoupper(substr($rawSegment, 0, 5));
                $subSegmentCode = !empty($rawSubSegment) ? substr($rawSubSegment, 0, 15) : null;
 
               
                $fuelTypeId = $fuelMap[$fuelStr] ?? null;
                if (!empty($fuelStr) && $fuelTypeId === null) {
                    \Log::warning("Row {$excelRow} — Auto-creating fuel: [{$fuelStr}]");
                    $fuelTypeId = \DB::table('xlr8_utils_keyvalue')->insertGetId([
                        'keyword_code' => 'FUEL_TYPE',
                        'key'          => '',
                        'code'         => $fuelStr,
                        'value'        => ucfirst(strtolower($fuelStr)),
                        'status'       => 1,
                        'is_active'    => 1,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                    $fuelMap[$fuelStr] = $fuelTypeId;
                }
                
                
                $bodyMakeId = $bodyMakeMap[$bodyMakeStr] ?? null;
                if (!empty($bodyMakeStr) && $bodyMakeId === null) {
                    \Log::warning("Row {$excelRow} — Auto-creating body_make: [{$bodyMakeStr}]");
                    $bodyMakeId = \DB::table('xlr8_utils_keyvalue')->insertGetId([
                        'keyword_code' => 'BODY_MAKE',
                        'key'          => '',
                        'code'         => $bodyMakeStr,
                        'value'        => ucfirst(strtolower($bodyMakeStr)),
                        'status'       => 1,
                        'is_active'    => 1,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                    $bodyMakeMap[$bodyMakeStr] = $bodyMakeId;
                }
                
                $bodyTypeId = $bodyTypeMap[$bodyTypeStr] ?? null;
                if (!empty($bodyTypeStr) && $bodyTypeId === null) {
                    \Log::warning("Row {$excelRow} — Auto-creating body_type: [{$bodyTypeStr}]");
                    $bodyTypeId = \DB::table('xlr8_utils_keyvalue')->insertGetId([
                        'keyword_code' => 'BODY_TYPE',
                        'key'          => '',
                        'code'         => $bodyTypeStr,
                        'value'        => ucfirst(strtolower($bodyTypeStr)),
                        'status'       => 1,
                        'is_active'    => 1,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                    $bodyTypeMap[$bodyTypeStr] = $bodyTypeId;
                }
                
              
                $permitId = $permitMap[$permitStr] ?? null;
                if (!empty($permitStr) && $permitId === null) {
                    \Log::warning("Row {$excelRow} — Auto-creating permit: [{$permitStr}]");
                    $permitId = \DB::table('xlr8_utils_keyvalue')->insertGetId([
                        'keyword_code' => 'PERMIT',
                        'key'          => '',
                        'code'         => $permitStr,
                        'value'        => ucfirst(strtolower($permitStr)),
                        'status'       => 1,
                        'is_active'    => 1,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                    $permitMap[$permitStr] = $permitId;
                }
                
               
                $statusId = $statusMap[$statusStr] ?? null;
                if (!empty($statusStr) && $statusId === null) {
                    \Log::warning("Row {$excelRow} — Auto-creating status: [{$statusStr}]");
                    $statusId = \DB::table('xlr8_utils_keyvalue')->insertGetId([
                        'keyword_code' => 'VEHICLE_STATUS',
                        'key'          => '',
                        'code'         => $statusStr,
                        'value'        => ucfirst(strtolower($statusStr)),
                        'status'       => 1,
                        'is_active'    => 1,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                    $statusMap[$statusStr] = $statusId;
                }
                
                if ($segmentCode && !isset($seenSegments[$segmentCode])) {
                    if (!\DB::table('xlr8_vehicle_segment')
                        ->where('brand_code', $brandCode)
                        ->where('code', $segmentCode)
                        ->exists()
                    ) {
                        \DB::table('xlr8_vehicle_segment')->insert([
                            'brand_code' => $brandCode,
                            'code'       => $segmentCode,
                            'name'       => ucfirst(strtolower($segmentCode)),
                            'is_active'  => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $stats['segment']++;
                        \Log::info("Row {$excelRow} — Segment inserted: [{$segmentCode}]");
                    }
                    $seenSegments[$segmentCode] = true;
                }
 
                if ($subSegmentCode && $segmentCode) {
                    $subKey = "{$segmentCode}|{$subSegmentCode}";
                    if (!isset($seenSubsegments[$subKey])) {
                        if (!\DB::table('xlr8_vehicle_subsegment')
                            ->where('brand_code', $brandCode)
                            ->where('segment_code', $segmentCode)
                            ->where('code', $subSegmentCode)
                            ->exists()
                        ) {
                            \DB::table('xlr8_vehicle_subsegment')->insert([
                                'brand_code'   => $brandCode,
                                'segment_code' => $segmentCode,
                                'code'         => $subSegmentCode,
                                'name'         => ucfirst(strtolower($subSegmentCode)),
                                'is_active'    => 1,
                                'created_at'   => $now,
                                'updated_at'   => $now,
                            ]);
                            $stats['subsegment']++;
                            \Log::info("Row {$excelRow} — Subsegment inserted: [{$subSegmentCode}] under [{$segmentCode}]");
                        }
                        $seenSubsegments[$subKey] = true;
                    }
                }
 
                if (!isset($seenModels[$modelCode])) {
                    if (!\DB::table('xlr8_vehicle_model')
                        ->where('brand_code', $brandCode)
                        ->where('code', $modelCode)
                        ->exists()
                    ) {
                        \DB::table('xlr8_vehicle_model')->insert([
                            'brand_code'       => $brandCode,
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
                        \Log::info("Row {$excelRow} — Model inserted: [{$modelCode}]");
                    }
                    $seenModels[$modelCode] = true;
                }
 
                if (!isset($seenVariants[$variantCode])) {
                    if (!\DB::table('xlr8_vehicle_variant')
                        ->where('brand_code', $brandCode)
                        ->where('code', $variantCode)
                        ->exists()
                    ) {
                        \DB::table('xlr8_vehicle_variant')->insert([
                            'brand_code'       => $brandCode,
                            'segment_code'     => $segmentCode,
                            'sub_segment_code' => $subSegmentCode,
                            'model_code'       => $modelCode,
                            'code'             => $variantCode,
                            'oem_name'         => $oemVariant,
                            'custom_name'      => $customVariant  ?: null,
                            'display_name'     => $displayName    ?: null,
                            'fuel_type_id'     => $fuelTypeId,
                            'seating_capacity' => is_numeric($seating) ? (int) $seating : null,
                            'wheels'           => is_numeric($wheels)  ? (int) $wheels  : 4,
                            'gvw'              => is_numeric($gvw)     ? (int) $gvw     : null,
                            'cc_capacity'      => !empty($cc)          ? (string) $cc   : null,
                            'transmission'     => $transmission ?: null,
                            'drivetrain'       => $drivetrain   ?: null,
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
                        \Log::info("Row {$excelRow} — Variant inserted: [{$variantCode}] for model [{$modelCode}]");
                    }
                    $seenVariants[$variantCode] = true;
                }
 
                $colorKey = "{$modelCode}|{$variantCode}|{$colorCode}";
                if (!isset($seenColors[$colorKey])) {
                    if (!\DB::table('xlr8_vehicle_color')
                        ->where('model_code',   $modelCode)
                        ->where('variant_code', $variantCode)
                        ->where('code',         $colorCode)
                        ->exists()
                    ) {
                        \DB::table('xlr8_vehicle_color')->insert([
                            'brand_code'       => $brandCode,
                            'segment_code'     => $segmentCode,
                            'sub_segment_code' => $subSegmentCode,
                            'model_code'       => $modelCode,
                            'variant_code'     => $variantCode,
                            'code'             => $colorCode,
                            'name'             => $colourName ?: $colorCode,
                            'is_active'        => 1,
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ]);
                        $stats['color']++;
                        \Log::info("Row {$excelRow} — Colour inserted: [{$colorCode}] ({$colourName}) for variant [{$variantCode}]");
                    }
                    $seenColors[$colorKey] = true;
                }
 
            } catch (\Exception $e) {
                $stats['skipped']++;
                \Log::error("Row {$excelRow} FAILED — " . $e->getMessage(), [
                    'full_model_code' => $fullModelCode,
                    'oem_model'       => $rawOemModel,
                    'oem_variant'     => $oemVariant,
                    'segment'         => $rawSegment,
                    'exception'       => $e->getMessage(),
                    'trace'           => $e->getTraceAsString(),
                ]);
            }
        }
 
        \Log::info('=== Vehicle Import Completed ===', $stats);
 
        $summary = implode(' | ', [
            "Segments: {$stats['segment']}",
            "Subsegments: {$stats['subsegment']}",
            "Models: {$stats['model']}",
            "Variants: {$stats['variant']}",
            "Colours: {$stats['color']}",
            "Skipped: {$stats['skipped']}",
        ]);
 
        if ($stats['skipped'] > 0) {
            \Alert::warning("Import done with errors → {$summary}<br>Check laravel.log for failed rows.")->flash();
        } else {
            \Alert::success("Import Completed → {$summary}")->flash();
        }
 
    } catch (\Exception $e) {
        \Log::error('Vehicle Import — fatal error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
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
}
