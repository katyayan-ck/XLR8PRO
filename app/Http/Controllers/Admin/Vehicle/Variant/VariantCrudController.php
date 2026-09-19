<?php

namespace App\Http\Controllers\Admin\Vehicle\Variant;

use App\Http\Requests\VariantRequest;
use App\Models\Vehicle\Color;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\OrgService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class VariantCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Variant::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/variant');
        CRUD::setEntityNameStrings('variant', 'variants');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('variant.view')) {
            abort(403, 'Unauthorized. You do not have permission to view variants.');
        }

        $this->crud->setListView('admin.variant.list');
    }

    public function index()
    {
        if (! backpack_user()->can('variant.view')) {
            abort(403, 'Unauthorized. You do not have permission to view variants.');
        }

        $this->crud->setListView('admin.variant.list');

        $variants = Variant::with([
            'segment',
            'subSegment',
            'vehicleModel',
            'permit',
            'fuelType',
            'bodyType',
            'bodyMake',
            'statusKkv',
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
                <a href="'.$editUrl.'"
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

                    ['field' => 'serial_no', 'headerName' => 'S.No.'],

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

                'data' => $gridData,
            ],
        ]);
    }

    public function store(VariantRequest $request)
    {
        if (! backpack_user()->can('variant.create')) {
            abort(403, 'Unauthorized. You do not have permission to create variants.');
        }

        $validated = $request->validated();

        $validated['is_csd'] = $request->boolean('is_csd');
        $validated['is_active'] = $request->boolean('is_active');

        Variant::create($validated);

        \Alert::success('Variant created successfully!')->flash();

        return redirect(backpack_url('variant'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('variant.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit variants.');
        }

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

    public function update(VariantRequest $request, $id)
    {
        if (! backpack_user()->can('variant.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit variants.');
        }

        $variant = Variant::findOrFail($id);

        $validated = $request->validated();

        if (
            $variant->is_active == 1 &&
            ! $request->boolean('is_active')
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
        if (! backpack_user()->can('variant.create')) {
            abort(403, 'Unauthorized. You do not have permission to create variants.');
        }

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

    public function destroy($id)
    {
        if (! backpack_user()->can('variant.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete variants.');
        }

        return $this->crud->delete($id);
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
                'name',
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
                'name',
            ]);
    }
}
