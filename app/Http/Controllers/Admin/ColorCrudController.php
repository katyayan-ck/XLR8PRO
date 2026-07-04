<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\Variant;
use App\Services\OrgService;
use \App\Models\Vehicle\Color;

class ColorCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Color::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/color');
        CRUD::setEntityNameStrings('color', 'colors');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.color.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.color.list');

        $colors = Color::with([
            'segment',
            'subSegment',
            'vehicleModel',
            'variant'
        ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $colors->map(function ($color, $index) {

            $mapped = $color->toArray();

            $mapped['serial_no'] = $index + 1;

            $mapped['segment'] =
                $color->segment?->name ?? '—';

            $mapped['sub_segment'] =
                $color->subSegment?->name ?? '—';

            $mapped['model'] =
                $color->vehicleModel?->name ?? '—';

            $mapped['variant'] =
                $color->variant?->oem_name ?? '—';

            $mapped['is_active'] =
                $color->is_active ? 'Active' : 'Inactive';

            $editUrl =
                backpack_url("color/{$color->id}/edit");

            $mapped['action'] = '
            <div class="d-flex gap-2 justify-content-center">
                <a href="' . $editUrl . '"
                   class="btn btn-sm btn-primary py-1 px-2">
                    Edit
                </a>
            </div>
        ';

            return $mapped;

        })->values();

        return view('admin.color.list', [

            'title' => 'All Colors',

            'gridConfig' => [

                'columns' => [

                    ['field' => 'serial_no', 'headerName' => 'S.No'],

                    ['field' => 'segment', 'headerName' => 'Segment'],
                    ['field' => 'sub_segment', 'headerName' => 'Sub Segment'],
                    ['field' => 'model', 'headerName' => 'Model'],
                    ['field' => 'variant', 'headerName' => 'Variant'],

                    ['field' => 'code', 'headerName' => 'Color Code'],
                    ['field' => 'name', 'headerName' => 'Color Name'],
                    ['field' => 'hex_code', 'headerName' => 'Hex Code'],

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

            'segment_code' =>
                'required|exists:xlr8_vehicle_segment,code',

            'sub_segment_code' =>
                'required|exists:xlr8_vehicle_subsegment,code',

            'model_code' =>
                'required|exists:xlr8_vehicle_model,code',

            'variant_code' =>
                'required|exists:xlr8_vehicle_variant,code',

            'code' =>
                'required|max:5|unique:xlr8_vehicle_color,code',

            'name' =>
                'required|max:255',

            'hex_code' =>
                'nullable|max:255',

            'is_active' =>
                'nullable|boolean',
        ]);

        $validated['is_active'] =
            $request->boolean('is_active');

        $validated['image'] = null;

        Color::create($validated);

        \Alert::success(
            'Color created successfully!'
        )->flash();

        return redirect(
            backpack_url('color')
        );
    }

    public function edit($id)
{
    $this->crud->setEditView('admin.color.edit');

    $color = Color::findOrFail($id);

    return view('admin.color.edit', [
        'title'    => 'Edit Color - ' . $color->name,
        'color'    => $color,
        'segments' => OrgService::segments(),
    ]);
}

    public function update(Request $request, $id)
{
    $color = Color::findOrFail($id);

    $validated = $request->validate([

        'segment_code' =>
            'required|exists:xlr8_vehicle_segment,code',

        'sub_segment_code' =>
            'required|exists:xlr8_vehicle_subsegment,code',

        'model_code' =>
            'required|exists:xlr8_vehicle_model,code',

        'variant_code' =>
            'required|exists:xlr8_vehicle_variant,code',

        'code' =>
            'required|max:5|unique:xlr8_vehicle_color,code,' . $id,

        'name' =>
            'required|max:255',

        'hex_code' =>
            'nullable|max:255',

        'is_active' =>
            'nullable|boolean',
    ]);

    $validated['is_active'] =
        $request->boolean('is_active');

    $color->update($validated);

    \Alert::success('Color updated successfully!')
        ->flash();

    return redirect(backpack_url('color'));
}

    public function create()
    {
        $this->crud->setCreateView('admin.color.create');

        return view('admin.color.create', [
            'title' => 'Add New Color',
            'segments' => OrgService::segments(),
        ]);
    }
    public function getSubSegments(Request $request)
    {
        $segmentCode = $request->segment_code;

        $subSegments = SubSegment::where(
            'segment_code',
            $segmentCode
        )
            ->where('is_active', 1)
            ->orderBy('name')
            ->get([
                'code',
                'name'
            ]);

        return response()->json($subSegments);
    }
    public function getModels(Request $request)
    {
        $subSegmentCode = $request->sub_segment_code;

        $models = VehicleModel::where(
            'sub_segment_code',
            $subSegmentCode
        )
            ->where('is_active', 1)
            ->orderBy('name')
            ->get([
                'code',
                'name'
            ]);

        return response()->json($models);
    }
    public function getVariants(Request $request)
    {
        $modelCode = $request->model_code;

        $variants = Variant::where(
            'model_code',
            $modelCode
        )
            ->where('is_active', 1)
            ->orderBy('oem_name')
            ->get([
                'code',
                'oem_name'
            ]);

        return response()->json($variants);
    }
}
