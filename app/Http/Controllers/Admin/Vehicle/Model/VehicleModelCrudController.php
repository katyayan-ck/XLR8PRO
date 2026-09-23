<?php

namespace App\Http\Controllers\Admin\Vehicle\Model;

use App\Http\Requests\VehicleModelRequest;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use Backpack\CRUD\app\Http\Controllers\CrudController;

class VehicleModelCrudController extends CrudController
{
    /**
     * NOTE: unlike every other controller touched in this rollout, this one uses
     * no Backpack Operation traits at all and is wired via fully manual routes in
     * routes/backpack/core.php (not Route::crud()). Preserved that shape exactly.
     */
    public function setup()
    {
        $this->crud->setModel(VehicleModel::class);
        $this->crud->setRoute(config('backpack.base.route_prefix').'/vehicle/model');
        $this->crud->setEntityNameStrings('vehicle model', 'vehicle models');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('VEH_MDL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view vehicle models.');
        }

        $this->crud->setListView('admin.vehicle.vehicle-model.list');
    }

    public function index()
    {
        if (! backpack_user()->can('VEH_MDL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view vehicle models.');
        }

        $this->crud->setListView('admin.vehicle.vehicle-model.list');

        $models = VehicleModel::with(['segment', 'subSegment'])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $models->map(function ($model, $index) {
            $mapped = $model->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['segment'] = $model->segment?->name ?? '—';
            $mapped['sub_segment'] = $model->subSegment?->name ?? '—';

            $editUrl = backpack_url("vehicle/model/{$model->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            $mapped['is_active'] = $model->is_active ? 'Active' : 'Inactive';

            return $mapped;
        })->values();

        return view('admin.vehicle.vehicle-model.list', [
            'title' => 'All Vehicle Models',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'segment', 'headerName' => 'Segment'],
                    ['field' => 'sub_segment', 'headerName' => 'Sub Segment'],
                    ['field' => 'name', 'headerName' => 'Model Name'],
                    ['field' => 'oem_name', 'headerName' => 'OEM Name'],
                    ['field' => 'custom_name', 'headerName' => 'Custom Name'],
                    ['field' => 'description', 'headerName' => 'Description'],
                    ['field' => 'is_active', 'headerName' => 'Active'],
                    ['field' => 'action', 'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('VEH_MDL_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create vehicle models.');
        }

        return view('admin.vehicle.vehicle-model.create', [
            'title' => 'Add New Vehicle Model',
            'segments' => Segment::orderBy('name')->get(),
        ]);
    }

    public function store(VehicleModelRequest $request)
    {
        if (! backpack_user()->can('VEH_MDL_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create vehicle models.');
        }

        $validated = $request->validated();

        if (! empty($validated['sub_segment_id'])) {
            $subSegment = SubSegment::find($validated['sub_segment_id']);
            $validated['sub_segment_code'] = $subSegment?->code;
        }

        VehicleModel::create($validated);

        \Alert::success('Vehicle Model created successfully!')->flash();

        return redirect(backpack_url('vehicle/model'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('VEH_MDL_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit vehicle models.');
        }

        $vehiclemodel = VehicleModel::with([
            'segment',
            'subSegment',
        ])->findOrFail($id);

        $activeVariants = Variant::where(
            'model_code',
            $vehiclemodel->code
        )
            ->where('is_active', 1)
            ->pluck('oem_name')
            ->toArray();

        return view('admin.vehicle.vehicle-model.edit', [
            'title' => 'Edit Vehicle Model - '.$vehiclemodel->name,
            'vehiclemodel' => $vehiclemodel,
            'segments' => Segment::orderBy('name')->get(),
            'activeVariants' => $activeVariants,
        ]);
    }

    public function update(VehicleModelRequest $request, $id)
    {
        if (! backpack_user()->can('VEH_MDL_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit vehicle models.');
        }

        $vehiclemodel = VehicleModel::findOrFail($id);

        $validated = $request->validated();

        if (
            $vehiclemodel->is_active == 1 &&
            ! $request->boolean('is_active')
        ) {

            $activeVariantCount = Variant::where(
                'model_code',
                $vehiclemodel->code
            )
                ->where('is_active', 1)
                ->count();

            if ($activeVariantCount > 0) {

                \Alert::error(
                    "Cannot deactivate Vehicle Model. {$activeVariantCount} active Variant(s) exist."
                )->flash();

                return redirect()->back()->withInput();
            }
        }

        if (! empty($validated['sub_segment_id'])) {

            $subSegment = SubSegment::find(
                $validated['sub_segment_id']
            );

            $validated['sub_segment_code'] =
                $subSegment?->code;
        }

        $validated['is_active'] =
            $request->boolean('is_active');

        $vehiclemodel->update($validated);

        \Alert::success(
            'Vehicle Model updated successfully!'
        )->flash();

        return redirect(backpack_url('vehicle/model'));
    }

    /**
     * NOTE: routes/backpack/core.php registers a DELETE route pointing at
     *
     * @destroy, but no destroy() method has ever existed on this controller
     * (confirmed in the file before this session touched it) — that route
     * would throw "method does not exist" if hit. Not added here; this batch
     * only wires permissions/FormRequest onto what already exists. Flagged in
     * ai-findings-20-09-2026.md rather than silently inventing new behavior.
     */
    public function getSubSegmentsBySegment($segmentCode)
    {
        return SubSegment::where('segment_code', $segmentCode)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
