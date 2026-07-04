<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;

class VehicleModelCrudController extends CrudController
{
    public function setup()
    {
        $this->crud->setModel(VehicleModel::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/vehicle-model');
        $this->crud->setEntityNameStrings('vehicle model', 'vehicle models');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.vehicle-model.list');
    }

    // ====================== LIST ======================
    public function index()
    {
        $this->crud->setListView('admin.vehicle-model.list');

        $models = VehicleModel::with(['segment', 'subSegment'])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $models->map(function ($model, $index) {
            $mapped = $model->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['segment'] = $model->segment?->name ?? '—';
            $mapped['sub_segment'] = $model->subSegment?->name ?? '—';

            $editUrl = backpack_url("vehicle-model/{$model->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            $mapped['is_active'] = $model->is_active ? 'Active' : 'Inactive';

            return $mapped;
        })->values();

        return view('admin.vehicle-model.list', [
            'title' => 'All Vehicle Models',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'segment', 'headerName' => 'Segment'],
                    ['field' => 'sub_segment', 'headerName' => 'Sub Segment'],
                    ['field' => 'name', 'headerName' => 'Model Name'],
                    ['field' => 'oem_name', 'headerName' => 'OEM Name'],
                    ['field' => 'custom_name', 'headerName' => 'Custom Name'],
                    ['field' => 'description', 'headerName' => 'Description'],
                    ['field' => 'is_active', 'headerName' => 'Active'],
                    ['field' => 'action', 'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    // ====================== CREATE ======================
    public function create()
    {
        return view('admin.vehicle-model.create', [
            'title' => 'Add New Vehicle Model',
            'segments' => Segment::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'sub_segment_id' => 'nullable|exists:xlr8_vehicle_subsegment,id',
            'code' => 'required|string|max:30|unique:xlr8_vehicle_model,code',
            'name' => 'nullable|string|max:255',
            'oem_name' => 'nullable|string|max:255|unique:xlr8_vehicle_model,oem_name',
            'is_active' => 'boolean',
        ]);

        if (!empty($validated['sub_segment_id'])) {
            $subSegment = SubSegment::find($validated['sub_segment_id']);
            $validated['sub_segment_code'] = $subSegment?->code;
        }

        VehicleModel::create($validated);

        \Alert::success('Vehicle Model created successfully!')->flash();

        return redirect(backpack_url('vehicle-model'));
    }

    // ====================== EDIT & UPDATE ======================
    public function edit($id)
    {
        $vehiclemodel = VehicleModel::with([
            'segment',
            'subSegment'
        ])->findOrFail($id);

        $activeVariants = Variant::where(
            'model_code',
            $vehiclemodel->code
        )
            ->where('is_active', 1)
            ->pluck('oem_name')
            ->toArray();

        return view('admin.vehicle-model.edit', [
            'title' => 'Edit Vehicle Model - ' . $vehiclemodel->name,
            'vehiclemodel' => $vehiclemodel,
            'segments' => Segment::orderBy('name')->get(),
            'activeVariants' => $activeVariants,
        ]);
    }

    public function update(Request $request, $id)
    {
        $vehiclemodel = VehicleModel::findOrFail($id);

        $validated = $request->validate([
            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'sub_segment_id' => 'nullable|exists:xlr8_vehicle_subsegment,id',
            'code' => 'required|string|max:30|unique:xlr8_vehicle_model,code,' . $id,
            'name' => 'nullable|string|max:255',
            'oem_name' => 'nullable|string|max:255|unique:xlr8_vehicle_model,oem_name,' . $id,
            'is_active' => 'boolean',
        ]);

        if (
            $vehiclemodel->is_active == 1 &&
            !$request->boolean('is_active')
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

        if (!empty($validated['sub_segment_id'])) {

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

        return redirect(backpack_url('vehicle-model'));
    }

    // ====================== AJAX ======================
    public function getSubSegmentsBySegment($segmentCode)
    {
        return SubSegment::where('segment_code', $segmentCode)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}