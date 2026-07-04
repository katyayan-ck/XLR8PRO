<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use \App\Models\Vehicle\SubSegment;
use \App\Models\Vehicle\Segment;
use App\Models\Vehicle\VehicleModel;

class SubSegmentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(SubSegment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/sub-segment');
        CRUD::setEntityNameStrings('sub segment', 'sub segments');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.sub-segment.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.sub-segment.list');

        $subsegments = SubSegment::orderBy('id', 'desc')->get();

        $gridData = $subsegments->map(function ($item, $index) {
            $mapped = $item->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['segment']   = $item->segment?->name ?? '—';

            $editUrl = backpack_url("sub-segment/{$item->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '"
                       class="btn btn-sm btn-primary py-1 px-2"
                       title="Edit">
                         Edit
                    </a>
                </div>
            ';

            $mapped['is_active'] = $item->is_active ? 'Active' : 'Inactive';

            return $mapped;
        })->values();

        return view('admin.sub-segment.list', [
            'title' => 'All Sub Segments',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',   'headerName' => 'S.No'],
                    ['field' => 'code',        'headerName' => 'Code'],
                    ['field' => 'name',        'headerName' => 'Sub Segment Name'],
                    ['field' => 'segment',     'headerName' => 'Segment'],
                    ['field' => 'description', 'headerName' => 'Description'],
                    ['field' => 'is_active',   'headerName' => 'Active'],
                    ['field' => 'action',      'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function edit($id)
{
    $this->crud->setEditView('admin.sub-segment.edit');

    $subsegment = SubSegment::with('segment')->findOrFail($id);

    $activeModels = VehicleModel::where(
        'sub_segment_code',
        $subsegment->code
    )
    ->where('is_active', 1)
    ->pluck('name')
    ->toArray();

    return view('admin.sub-segment.edit', [
        'title'      => 'Edit Sub Segment - ' . $subsegment->name,
        'subsegment' => $subsegment,
        'segments'   => Segment::orderBy('name')->get(),
        'activeModels' => $activeModels,
    ]);
}

    public function update(Request $request, $id)
{
    $subsegment = SubSegment::findOrFail($id);

    $validated = $request->validate([
        'segment_id'  => 'required|exists:xlr8_vehicle_segment,id',
        'name'        => 'required|string|max:255',
        'code'        => 'required|string|max:20|unique:xlr8_vehicle_subsegment,code,' . $id,
        'description' => 'nullable|string',
        'is_active'   => 'boolean',
    ]);

    if (
        $subsegment->is_active == 1 &&
        !$request->boolean('is_active')
    ) {

        $activeModelCount = VehicleModel::where(
            'sub_segment_code',
            $subsegment->code
        )
        ->where('is_active', 1)
        ->count();

        if ($activeModelCount > 0) {

            \Alert::error(
                "Cannot deactivate Sub Segment. {$activeModelCount} active Model(s) exist."
            )->flash();

            return redirect()->back()->withInput();
        }
    }

    $validated['is_active'] =
        $request->boolean('is_active');

    $subsegment->update($validated);

    \Alert::success(
        'Sub Segment updated successfully!'
    )->flash();

    return redirect(backpack_url('sub-segment'));
}

    public function create()
    {
        $this->crud->setCreateView('admin.sub-segment.create');

        return view('admin.sub-segment.create', [
            'title'    => 'Add New Sub Segment',
        ]);
    }

    public function getSubSegmentsBySegment($segmentCode)
{
    return SubSegment::where('segment_code', $segmentCode)
                ->orderBy('name')
                ->get(['id', 'name']);
}

}
