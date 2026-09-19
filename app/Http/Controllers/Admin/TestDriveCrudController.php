<?php

namespace App\Http\Controllers\Admin;

use App\Models\CRM\TestDrive;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;
use Carbon\Carbon;

class TestDriveCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(TestDrive::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/testdrive');
        CRUD::setEntityNameStrings('test drive', 'test drives');
    }

    public function index()
    {
        $this->crud->hasAccessOrFail('list');
        $this->crud->setListView('admin.testdrive.list');

        $testdrives = TestDrive::orderBy('id', 'desc')->get();

        $gridData = $testdrives->map(function ($td, $index) {
            $editUrl = backpack_url("testdrive/{$td->id}/edit");
            
            return [
                'serial_no' => $index + 1,
                'test_drive_no' => $td->test_drive_no ?? '—',
                'enquiry_no' => $td->enquiry_no ?? '—',
                'customer_name' => $td->customer_name ?? '—',
                'customer_phone' => $td->customer_phone ?? '—',
                'model' => $td->model ?? '—',
                'variant' => $td->variant ?? '—',
                'stage' => $td->stage ?? '—',
                'sc_code' => $td->sc_code ?? '—',
                'scheduled_td_start_time' => $td->scheduled_td_start_time ? Carbon::parse($td->scheduled_td_start_time)->format('d-M-Y H:i') : '—',
                'actual_td_start_time' => $td->actual_td_start_time ? Carbon::parse($td->actual_td_start_time)->format('d-M-Y H:i') : '—',
                'action' => '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Process / Edit</a>'
            ];
        })->values();

        $columns = [
            ['field' => 'serial_no', 'headerName' => 'S.No.', 'pinned' => 'left', 'width' => 80],
            ['field' => 'test_drive_no', 'headerName' => 'Test Drive No.', 'pinned' => 'left', 'width' => 150],
            ['field' => 'enquiry_no', 'headerName' => 'Enquiry No.', 'width' => 150],
            ['field' => 'customer_name', 'headerName' => 'Customer Name', 'width' => 180],
            ['field' => 'customer_phone', 'headerName' => 'Phone', 'width' => 130],
            ['field' => 'model', 'headerName' => 'Model', 'width' => 160],
            ['field' => 'variant', 'headerName' => 'Variant', 'width' => 180],
            ['field' => 'stage', 'headerName' => 'Stage', 'width' => 140],
            ['field' => 'sc_code', 'headerName' => 'SC Code', 'width' => 140],
            ['field' => 'scheduled_td_start_time', 'headerName' => 'Scheduled Start', 'width' => 160],
            ['field' => 'actual_td_start_time', 'headerName' => 'Actual Start', 'width' => 160],
            ['field' => 'action', 'headerName' => 'Action', 'pinned' => 'right', 'width' => 140, 'cellRenderer' => 'htmlRenderer', 'sortable' => false, 'filter' => false]
        ];

        return view('admin.testdrive.list', [
            'title' => 'Test Drive Dashboard',
            'gridConfig' => [
                'data' => $gridData,
                'columns' => $columns
            ]
        ]);
    }

    public function create()
    {
        $this->crud->hasAccessOrFail('create');
        return view('admin.testdrive.create', ['title' => 'Schedule Test Drive']);
    }

    public function edit($id)
    {
        $this->crud->hasAccessOrFail('update');
        $entry = TestDrive::findOrFail($id);
        return view('admin.testdrive.create', [
            'title' => 'Update Test Drive',
            'entry' => $entry
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->getValidationRules());
        $validated['test_drive_no'] = 'TD-' . strtoupper(uniqid());
        $validated['td_created_date'] = now()->toDateString();
        $validated['created_by'] = backpack_user()->id;

        TestDrive::create($validated);
        Alert::success('Test Drive Scheduled successfully.')->flash();
        return redirect(backpack_url('testdrive'));
    }

    public function update(Request $request, $id)
    {
        $testDrive = TestDrive::findOrFail($id);
        $validated = $request->validate($this->getValidationRules());
        $validated['updated_by'] = backpack_user()->id;

        $testDrive->update($validated);
        Alert::success('Test Drive Updated successfully.')->flash();
        return redirect(backpack_url('testdrive'));
    }

    private function getValidationRules()
    {
        return [
            'enquiry_no' => 'required|max:50',
            'customer_name' => 'required|max:200',
            'customer_phone' => 'required|max:15',
            'model' => 'nullable|max:150',
            'model_code' => 'nullable|max:50',
            'variant' => 'nullable|max:150',
            'variant_code' => 'nullable|max:50',
            'stage' => 'required|max:100',
            'sc_code' => 'nullable|max:200',
            'sc_mile_id' => 'nullable|max:100',
            'scheduled_td_start_time' => 'nullable|date',
            'scheduled_td_end_time' => 'nullable|date',
            'actual_td_start_time' => 'nullable|date',
            'actual_td_end_time' => 'nullable|date',
        ];
    }
}