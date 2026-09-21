<?php

namespace App\Http\Controllers\Admin\Vehicle\Color;

use App\Http\Requests\ColorRequest;
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

class ColorCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Color::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/vehicle/color');
        CRUD::setEntityNameStrings('color', 'colors');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('VEH_CLR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view colors.');
        }

        $this->crud->setListView('admin.color.list');
    }

    public function index()
    {
        if (! backpack_user()->can('VEH_CLR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view colors.');
        }

        $this->crud->setListView('admin.color.list');

        $colors = Color::with([
            'segment',
            'subSegment',
            'vehicleModel',
            'variant',
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
                backpack_url("vehicle/color/{$color->id}/edit");

            $mapped['action'] = '
            <div class="d-flex gap-2 justify-content-center">
                <a href="'.$editUrl.'"
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

                    ['field' => 'serial_no', 'headerName' => 'S.No.'],

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

                'data' => $gridData,
            ],
        ]);
    }

    public function store(ColorRequest $request)
    {
        if (! backpack_user()->can('VEH_CLR_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create colors.');
        }

        $validated = $request->validated();

        $validated['is_active'] =
            $request->boolean('is_active');

        $validated['image'] = null;

        Color::create($validated);

        \Alert::success(
            'Color created successfully!'
        )->flash();

        return redirect(
            backpack_url('vehicle/color')
        );
    }

    public function edit($id)
    {
        if (! backpack_user()->can('VEH_CLR_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit colors.');
        }

        $this->crud->setEditView('admin.color.edit');

        $color = Color::findOrFail($id);

        return view('admin.color.edit', [
            'title' => 'Edit Color - '.$color->name,
            'color' => $color,
            'segments' => OrgService::segments(),
        ]);
    }

    public function update(ColorRequest $request, $id)
    {
        if (! backpack_user()->can('VEH_CLR_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit colors.');
        }

        $color = Color::findOrFail($id);

        $validated = $request->validated();

        $validated['is_active'] =
            $request->boolean('is_active');

        $color->update($validated);

        \Alert::success('Color updated successfully!')
            ->flash();

        return redirect(backpack_url('vehicle/color'));
    }

    public function create()
    {
        if (! backpack_user()->can('VEH_CLR_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create colors.');
        }

        $this->crud->setCreateView('admin.color.create');

        return view('admin.color.create', [
            'title' => 'Add New Color',
            'segments' => OrgService::segments(),
        ]);
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('VEH_CLR_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete colors.');
        }

        return $this->crud->delete($id);
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
                'name',
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
                'name',
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
                'oem_name',
            ]);

        return response()->json($variants);
    }
}
