<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Employee;
use App\Models\Admin\Vertical;

class EmployeeVerticalAssignmentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Admin\EmployeeVerticalAssignment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/employee-vertical-assignment');
        CRUD::setEntityNameStrings('vertical assignment', 'vertical assignments');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.employee-vertical-assignment.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.employee-vertical-assignment.list');

        $assignments = \App\Models\Admin\EmployeeVerticalAssignment::with(['employee.person', 'vertical'])
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
                'vertical_name'   => $assignment->vertical->name ?? '-',
                'from_date'       => $assignment->from_date?->format('d/m/Y'),
                'to_date'         => $assignment->to_date?->format('d/m/Y') ?? 'Ongoing',
                'is_current'      => $assignment->is_current ? 'Yes' : 'No',
            ];

            $editUrl = backpack_url("employee-vertical-assignment/{$assignment->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">
                        Edit
                    </a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.employee-vertical-assignment.list', [
            'title' => 'Employee Vertical Assignments',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',       'headerName' => 'S.No'],
                    ['field' => 'employee_code',   'headerName' => 'Employee Code'],
                    ['field' => 'employee_name',   'headerName' => 'Employee Name'],
                    ['field' => 'vertical_name',   'headerName' => 'Vertical'],
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
        return view('admin.employee-vertical-assignment.create', [
            'title' => 'Assign Vertical to Employee',
        ]);
    }

    public function edit($id)
    {
        $assignment = \App\Models\Admin\EmployeeVerticalAssignment::with(['employee.person', 'vertical'])
            ->findOrFail($id);

        return view('admin.employee-vertical-assignment.edit', [
            'title'       => 'Edit Vertical Assignment',
            'assignment'  => $assignment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $assignment = \App\Models\Admin\EmployeeVerticalAssignment::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'vertical_id' => 'required|exists:verticals,id',
            'from_date'   => 'required|date',
            'to_date'     => 'nullable|date|after_or_equal:from_date',
            'is_current'  => 'boolean',
        ]);

        $assignment->update($validated);

        \Alert::success('Vertical assignment updated successfully!')->flash();

        return redirect(backpack_url('employee-vertical-assignment'));
    }
}
