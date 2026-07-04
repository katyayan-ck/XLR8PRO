<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use \App\Models\Vehicle\Variant;
use \App\Models\Vehicle\Brand;
use \App\Models\Vehicle\Segment;
use \App\Models\Vehicle\VehicleModel;
use \App\Models\Vehicle\SubSegment;
use App\Services\OrgService;
use App\Models\Vehicle\Color;


class VariantCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Variant::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/variant');
        CRUD::setEntityNameStrings('variant', 'variants');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.variant.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.variant.list');

        $variants = Variant::with([
            'segment',
            'subSegment',
            'vehicleModel',
            'permit',
            'fuelType',
            'bodyType',
            'bodyMake',
            'statusKkv'
        ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $variants->map(function ($item, $index) {

            $mapped = $item->toArray();

            $mapped['serial_no'] = $index + 1;

            $mapped['segment'] = $item->segment?->name ?? '—';
            $mapped['sub_segment'] = $item->subSegment?->name ?? '—';
            $mapped['model'] = $item->vehicleModel?->name ?? '—';

            $mapped['permit'] = $item->permit?->value ?? '-';
            $mapped['fuel_type'] = $item->fuelType?->value ?? '-';
            $mapped['body_type'] = $item->bodyType?->value ?? '-';
            $mapped['body_make'] = $item->bodyMake?->value ?? '-';
            $mapped['status'] = $item->statusKkv?->value ?? '-';

            $mapped['oem_name'] = $item->oem_name;
            $mapped['custom_name'] = $item->custom_name;
            $mapped['display_name'] = $item->display_name;

            $mapped['is_csd'] = $item->is_csd ? 'Yes' : 'No';
            $mapped['is_active'] = $item->is_active ? 'Active' : 'Inactive';

            $editUrl = backpack_url("variant/{$item->id}/edit");

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

        return view('admin.variant.list', [
            'title' => 'All Variants',
            'gridConfig' => [
                'columns' => [

                    ['field' => 'serial_no', 'headerName' => 'S.No'],

                    ['field' => 'segment', 'headerName' => 'Segment'],
                    ['field' => 'sub_segment', 'headerName' => 'Sub Segment'],
                    ['field' => 'model', 'headerName' => 'Model'],

                    ['field' => 'code', 'headerName' => 'Variant Code'],
                    ['field' => 'oem_name', 'headerName' => 'OEM Name'],
                    ['field' => 'custom_name', 'headerName' => 'Custom Name'],
                    ['field' => 'display_name', 'headerName' => 'Display Name'],

                    ['field' => 'taxi_price', 'headerName' => 'Taxi Price'],

                    ['field' => 'permit', 'headerName' => 'Permit'],
                    ['field' => 'fuel_type', 'headerName' => 'Fuel Type'],
                    ['field' => 'body_type', 'headerName' => 'Body Type'],
                    ['field' => 'body_make', 'headerName' => 'Body Make'],
                    ['field' => 'status', 'headerName' => 'Status'],

                    ['field' => 'seating_capacity', 'headerName' => 'Seats'],
                    ['field' => 'wheels', 'headerName' => 'Wheels'],
                    ['field' => 'gvw', 'headerName' => 'GVW'],
                    ['field' => 'cc_capacity', 'headerName' => 'CC Capacity'],

                    ['field' => 'transmission', 'headerName' => 'Transmission'],
                    ['field' => 'drivetrain', 'headerName' => 'Drivetrain'],

                    ['field' => 'is_csd', 'headerName' => 'CSD'],
                    ['field' => 'csd_index', 'headerName' => 'CSD Index'],

                    ['field' => 'is_active', 'headerName' => 'Active'],

                    ['field' => 'action', 'headerName' => 'Actions'],
                ],

                'data' => $gridData
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([

            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'sub_segment_code' => 'required|exists:xlr8_vehicle_subsegment,code',
            'model_code' => 'required|exists:xlr8_vehicle_model,code',

            'code' => 'required|string|max:100|unique:xlr8_vehicle_variant,code',
            'oem_name' => 'required|string|max:255',
            'custom_name' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'taxi_price' => 'required|string|max:10',

            'permit_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'fuel_type_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'body_type_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'body_make_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'status_id' => 'nullable|exists:xlr8_utils_keyvalue,id',

            'seating_capacity' => 'nullable|integer|min:1',
            'wheels' => 'nullable|integer|min:1',
            'gvw' => 'nullable|integer|min:0',

            'cc_capacity' => 'nullable|string|max:255',
            'transmission' => 'nullable|string|max:255',
            'drivetrain' => 'nullable|string|max:255',

            'is_csd' => 'nullable|boolean',
            'csd_index' => 'nullable|string|max:255',

            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_csd'] = $request->boolean('is_csd');
        $validated['is_active'] = $request->boolean('is_active');

        Variant::create($validated);

        \Alert::success('Variant created successfully!')->flash();

        return redirect(backpack_url('variant'));
    }

    public function edit($id)
    {
        $variant = Variant::findOrFail($id);

        $subSegments = SubSegment::where(
            'segment_code',
            $variant->segment_code
        )
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $models = VehicleModel::where(
            'segment_code',
            $variant->segment_code
        )
            ->where(
                'sub_segment_code',
                $variant->sub_segment_code
            )
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $activeColors = Color::where(
            'variant_code',
            $variant->code
        )
            ->where('is_active', 1)
            ->pluck('name')
            ->toArray();

        return view('admin.variant.edit', [

            'variant' => $variant,

            'activeColors' => $activeColors,

            'segments' => OrgService::segments(),

            'subSegments' => $subSegments,

            'models' => $models,

            'permits' =>
                OrgService::getKeyValuesByCode('PERMIT'),

            'fuelTypes' =>
                OrgService::getKeyValuesByCode('FUEL_TYPE'),

            'bodyTypes' =>
                OrgService::getKeyValuesByCode('BODY_TYPE'),

            'bodyMakes' =>
                OrgService::getKeyValuesByCode('BODY_MAKE'),

            'statuses' =>
                OrgService::getKeyValuesByCode('VEHICLE_STATUS'),
        ]);
    }
    public function update(Request $request, $id)
    {
        $variant = Variant::findOrFail($id);

        $validated = $request->validate([

            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'sub_segment_code' => 'required|exists:xlr8_vehicle_subsegment,code',
            'model_code' => 'required|exists:xlr8_vehicle_model,code',

            'code' => 'required|string|max:100|unique:xlr8_vehicle_variant,code,' . $id,

            'oem_name' => 'required|string|max:255',
            'custom_name' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',

            'taxi_price' => 'required|string|max:10',

            'permit_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'fuel_type_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'body_type_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'body_make_id' => 'nullable|exists:xlr8_utils_keyvalue,id',
            'status_id' => 'nullable|exists:xlr8_utils_keyvalue,id',

            'seating_capacity' => 'nullable|integer',
            'wheels' => 'nullable|integer',
            'gvw' => 'nullable|integer',

            'cc_capacity' => 'nullable|string|max:255',
            'transmission' => 'nullable|string|max:255',
            'drivetrain' => 'nullable|string|max:255',

            'is_csd' => 'nullable|boolean',
            'csd_index' => 'nullable|string|max:255',

            'is_active' => 'nullable|boolean',
        ]);

        // Prevent deactivation if active Colors exist
        if (
            $variant->is_active == 1 &&
            !$request->boolean('is_active')
        ) {

            $activeColorCount = Color::where(
                'variant_code',
                $variant->code
            )
                ->where('is_active', 1)
                ->count();

            if ($activeColorCount > 0) {

                \Alert::error(
                    "Cannot deactivate Variant. {$activeColorCount} active Color(s) exist."
                )->flash();

                return redirect()->back()->withInput();
            }
        }

        $validated['is_csd'] =
            $request->boolean('is_csd');

        $validated['is_active'] =
            $request->boolean('is_active');

        $variant->update($validated);

        \Alert::success(
            'Variant updated successfully!'
        )->flash();

        return redirect(backpack_url('variant'));
    }


    public function create()
    {
        $this->crud->setCreateView('admin.variant.create');

        return view('admin.variant.create', [

            'title' => 'Add New Variant',

            'segments' => OrgService::segments(),

            'permits' =>
                OrgService::getKeyValuesByCode('PERMIT'),

            'fuelTypes' =>
                OrgService::getKeyValuesByCode('FUEL_TYPE'),

            'bodyTypes' =>
                OrgService::getKeyValuesByCode('BODY_TYPE'),

            'bodyMakes' =>
                OrgService::getKeyValuesByCode('BODY_MAKE'),

            'statuses' =>
                OrgService::getKeyValuesByCode('VEHICLE_STATUS'),
        ]);
    }

    public function getSubSegments(Request $request)
    {
        return SubSegment::where(
            'segment_code',
            $request->segment_code
        )
            ->where('is_active', 1)
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name'
            ]);
    }

    public function getModels(Request $request)
    {
        return VehicleModel::where(
            'segment_code',
            $request->segment_code
        )
            ->where(
                'sub_segment_code',
                $request->sub_segment_code
            )
            ->where('is_active', 1)
            ->orderBy('name')
            ->get([
                'code',
                'name'
            ]);
    }
}
