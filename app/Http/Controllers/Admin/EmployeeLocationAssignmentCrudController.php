<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Employee;
use App\Models\Admin\Location;
use App\Models\Admin\Branch;

class EmployeeLocationAssignmentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Admin\EmployeeLocationAssignment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/employee-location-assignment');
        CRUD::setEntityNameStrings('location assignment', 'location assignments');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.employee-location-assignment.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.employee-location-assignment.list');

        $assignments = \App\Models\Admin\EmployeeLocationAssignment::with(['employee.person', 'location', 'branch'])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $assignments->map(function ($assignment, $index) {
            $employeeName = $assignment->employee && $assignment->employee->person
                ? trim($assignment->employee->person->first_name . ' ' . $assignment->employee->person->last_name)
                : '-';

            $mapped = [
                'id'              => $assignment->id,
                'serial_no'       => $index + 1,
                'employee_code'   => $assignment->employee->code ?? '-',
                'employee_name'   => $employeeName,
                'location_name'   => $assignment->location->name ?? '-',
                'branch_name'     => $assignment->branch->name ?? '— No Branch —',
                'from_date'       => $assignment->from_date?->format('d/m/Y'),
                'to_date'         => $assignment->to_date?->format('d/m/Y') ?? 'Ongoing',
                'is_current'      => $assignment->is_current ? 'Yes' : 'No',
            ];

            $editUrl = backpack_url("employee-location-assignment/{$assignment->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">
                        Edit
                    </a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.employee-location-assignment.list', [
            'title' => 'Employee Location Assignments',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',       'headerName' => 'S.No'],
                    ['field' => 'employee_code',   'headerName' => 'Employee Code'],
                    ['field' => 'employee_name',   'headerName' => 'Employee Name'],
                    ['field' => 'location_name',   'headerName' => 'Location'],
                    ['field' => 'branch_name',     'headerName' => 'Branch'],
                    ['field' => 'from_date',       'headerName' => 'From Date'],
                    ['field' => 'to_date',         'headerName' => 'To Date'],
                    ['field' => 'is_current',      'headerName' => 'Current'],
                    ['field' => 'action',          'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        return view('admin.employee-location-assignment.create', [
            'title' => 'Assign Location to Employee',
        ]);
    }

    public function edit($id)
    {
        $assignment = \App\Models\Admin\EmployeeLocationAssignment::with(['employee.person', 'location', 'branch'])
            ->findOrFail($id);

        return view('admin.employee-location-assignment.edit', [
            'title'       => 'Edit Location Assignment',
            'assignment'  => $assignment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $assignment = \App\Models\Admin\EmployeeLocationAssignment::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'location_id' => 'required|exists:locations,id',
            'branch_id'   => 'nullable|exists:branches,id',
            'from_date'   => 'required|date',
            'to_date'     => 'nullable|date|after_or_equal:from_date',
            'is_current'  => 'boolean',
        ]);

        $assignment->update($validated);

        \Alert::success('Location assignment updated successfully!')->flash();

        return redirect(backpack_url('employee-location-assignment'));
    }
}
