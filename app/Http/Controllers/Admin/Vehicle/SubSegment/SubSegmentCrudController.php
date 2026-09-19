<?php

namespace App\Http\Controllers\Admin\Vehicle\SubSegment;

use App\Http\Requests\SubSegmentRequest;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\VehicleModel;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class SubSegmentCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(SubSegment::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/sub-segment');
        CRUD::setEntityNameStrings('sub segment', 'sub segments');
    }

    /**
     * No dedicated "subsegment.*" permission exists in xlr8_iam_permissions —
     * this reuses "segment.*" throughout, since sub-segments are managed as
     * part of segment administration in this app (same as how
     * SegmentCrudController::edit() already reads/writes SubSegment rows).
     */
    protected function setupListOperation()
    {
        if (! backpack_user()->can('segment.view')) {
            abort(403, 'Unauthorized. You do not have permission to view sub segments.');
        }

        $this->crud->setListView('admin.sub-segment.list');
    }

    public function index()
    {
        if (! backpack_user()->can('segment.view')) {
            abort(403, 'Unauthorized. You do not have permission to view sub segments.');
        }

        $this->crud->setListView('admin.sub-segment.list');

        $subsegments = SubSegment::orderBy('id', 'desc')->get();

        $gridData = $subsegments->map(function ($item, $index) {
            $mapped = $item->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['segment'] = $item->segment?->name ?? '—';

            $editUrl = backpack_url("sub-segment/{$item->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'"
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
                    ['field' => 'serial_no',   'headerName' => 'S.No.'],
                    ['field' => 'code',        'headerName' => 'Code'],
                    ['field' => 'name',        'headerName' => 'Sub Segment Name'],
                    ['field' => 'segment',     'headerName' => 'Segment'],
                    ['field' => 'description', 'headerName' => 'Description'],
                    ['field' => 'is_active',   'headerName' => 'Active'],
                    ['field' => 'action',      'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function edit($id)
    {
        if (! backpack_user()->can('segment.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit sub segments.');
        }

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
            'title' => 'Edit Sub Segment - '.$subsegment->name,
            'subsegment' => $subsegment,
            'segments' => Segment::orderBy('name')->get(),
            'activeModels' => $activeModels,
        ]);
    }

    public function update(SubSegmentRequest $request, $id)
    {
        if (! backpack_user()->can('segment.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit sub segments.');
        }

        $subsegment = SubSegment::findOrFail($id);

        $validated = $request->validated();

        if (
            $subsegment->is_active == 1 &&
            ! $request->boolean('is_active')
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
        if (! backpack_user()->can('segment.create')) {
            abort(403, 'Unauthorized. You do not have permission to create sub segments.');
        }

        $this->crud->setCreateView('admin.sub-segment.create');

        return view('admin.sub-segment.create', [
            'title' => 'Add New Sub Segment',
        ]);
    }

    /**
     * Gates the default Backpack store() (no custom store() override exists —
     * see SegmentCrudController for the same pattern).
     */
    protected function setupCreateOperation()
    {
        if (! backpack_user()->can('segment.create')) {
            abort(403, 'Unauthorized. You do not have permission to create sub segments.');
        }
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('segment.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete sub segments.');
        }

        return $this->crud->delete($id);
    }

    public function getSubSegmentsBySegment($segmentCode)
    {
        return SubSegment::where('segment_code', $segmentCode)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
