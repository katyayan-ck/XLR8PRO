<?php

namespace App\Http\Controllers\Admin\Vehicle\Segment;

use App\Http\Requests\SegmentRequest;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class SegmentCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Segment::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/vehicle/segment');
        CRUD::setEntityNameStrings('segment', 'segments');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('VEH_SEG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view segments.');
        }

        $this->crud->setListView('admin.vehicle.segment.list');
    }

    public function index()
    {
        if (! backpack_user()->can('VEH_SEG_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view segments.');
        }

        $this->crud->setListView('admin.vehicle.segment.list');

        $segments = Segment::orderBy('id', 'desc')->get();

        $gridData = $segments->map(function ($segment, $index) {
            $mapped = $segment->toArray();
            $mapped['serial_no'] = $index + 1;

            $editUrl = backpack_url("vehicle/segment/{$segment->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'"
                       class="btn btn-sm btn-primary py-1 px-2"
                       title="Edit">
                         Edit
                    </a>
                </div>
            ';

            $mapped['is_active'] = $segment->is_active ? 'Active' : 'Inactive';

            return $mapped;
        })->values();

        return view('admin.vehicle.segment.list', [
            'title' => 'All Segments',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'code', 'headerName' => 'Code'],
                    ['field' => 'name', 'headerName' => 'Segment Name'],
                    ['field' => 'is_active', 'headerName' => 'Active'],
                    ['field' => 'action', 'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function edit($id)
    {
        if (! backpack_user()->can('VEH_SEG_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit segments.');
        }

        $this->crud->setEditView('admin.vehicle.segment.edit');

        $segment = Segment::findOrFail($id);

        $activeSubSegments = SubSegment::where(
            'segment_code',
            $segment->code
        )
            ->where('is_active', 1)
            ->pluck('name')
            ->toArray();

        return view('admin.vehicle.segment.edit', [
            'title' => 'Edit Segment - '.$segment->name,
            'segment' => $segment,
            'activeSubSegments' => $activeSubSegments,
        ]);
    }

    public function update(SegmentRequest $request, $id)
    {
        if (! backpack_user()->can('VEH_SEG_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit segments.');
        }

        $segment = Segment::findOrFail($id);

        $validated = $request->validated();

        if (
            $segment->is_active == 1 &&
            ! $request->boolean('is_active')
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

        // The code is the key children reference (variants, pricing, enquiries): never re-saved (DEC-048).
        unset($validated['code']);

        $segment->update($validated);

        \Alert::success(
            'Segment updated successfully!'
        )->flash();

        return redirect(backpack_url('vehicle/segment'));
    }

    public function create()
    {
        if (! backpack_user()->can('VEH_SEG_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create segments.');
        }

        $this->crud->setCreateView('admin.vehicle.segment.create');

        return view('admin.vehicle.segment.create', [
            'title' => 'Add New Segment',
        ]);
    }

    /**
     * Validated create (the Backpack default store() saved unvalidated input: a duplicate
     * or missing code was a 500, BUG-170).
     */
    public function store(SegmentRequest $request)
    {
        if (! backpack_user()->can('VEH_SEG_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create segments.');
        }

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');

        Segment::create($validated);

        \Alert::success('Segment created successfully!')->flash();

        return redirect(backpack_url('vehicle/segment'));
    }

    protected function setupCreateOperation()
    {
        if (! backpack_user()->can('VEH_SEG_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create segments.');
        }
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('VEH_SEG_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete segments.');
        }

        return $this->crud->delete($id);
    }
}
