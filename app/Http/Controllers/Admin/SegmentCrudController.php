<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use \App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;

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

            // Display Active/Inactive nicely
            $mapped['is_active'] = $segment->is_active ? 'Active' : 'Inactive';

            return $mapped;
        })->values();

        return view('admin.segment.list', [
            'title' => 'All Segments',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'code', 'headerName' => 'Code'],
                    ['field' => 'name', 'headerName' => 'Segment Name'],
                    // ['field' => 'description', 'headerName' => 'Description'],
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

        // Prevent deactivation if active Sub Segments exist
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
}
