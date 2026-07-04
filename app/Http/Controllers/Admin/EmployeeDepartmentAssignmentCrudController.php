<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Employee;
use App\Models\Admin\Department;
use App\Models\Admin\EmployeeDepartmentAssignment;

class EmployeeDepartmentAssignmentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(EmployeeDepartmentAssignment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/employee-department-assignment');
        CRUD::setEntityNameStrings('department assignment', 'department assignments');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.employee-department-assignment.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.employee-department-assignment.list');

        $assignments = EmployeeDepartmentAssignment::with(['employee.person', 'department'])
            ->select([
                'id',
                'employee_id',
                'department_id',
                'from_date',
                'to_date',
                'is_current'
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $assignments->map(function ($assign, $index) {
            $mapped = $assign->toArray();
            $mapped['serial_no'] = $index + 1;

            $mapped['employee_name'] = $assign->employee && $assign->employee->person
                ? trim($assign->employee->person->first_name . ' ' . $assign->employee->person->last_name)
                : '—';

            $mapped['department_name'] = $assign->department?->name ?? '—';

            // Format dates to dd/mm/yyyy
            $mapped['from_date'] = $assign->from_date?->format('d/m/Y') ?? '—';
            $mapped['to_date']   = $assign->to_date?->format('d/m/Y') ?? '—';

            $mapped['is_current'] = $assign->is_current ? 'Active' : 'Inactive';

            $editUrl = backpack_url("employee-department-assignment/{$assign->id}/edit");

            $mapped['action'] = '
            <div class="d-flex gap-2 justify-content-center">
                <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
            </div>
        ';
            return $mapped;
        })->values();

        return view('admin.employee-department-assignment.list', [
            'title' => 'Employee Department Assignments',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',        'headerName' => 'S.No'],
                    ['field' => 'employee_name',    'headerName' => 'Employee'],
                    ['field' => 'department_name',  'headerName' => 'Department'],
                    ['field' => 'from_date',        'headerName' => 'From Date'],
                    ['field' => 'to_date',          'headerName' => 'To Date'],
                    ['field' => 'is_current',       'headerName' => 'Current'],
                    ['field' => 'action',           'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.employee-department-assignment.create');

        return view('admin.employee-department-assignment.create', [
            'title'       => 'Add New Department Assignment',
            'employees'   => Employee::with('person')
                ->orderBy('code')
                ->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'   => 'required|exists:xlr8_admin_emp_department_pivot,id',
            'department_id' => 'required|exists:xlr8_admin_department,id',
            'from_date'     => 'required|date',
            'to_date'       => 'nullable|date|after_or_equal:from_date',
            'is_current'    => 'boolean',
        ]);

        EmployeeDepartmentAssignment::create($validated);

        \Alert::success('Department Assignment created successfully!')->flash();
        return redirect(backpack_url('employee-department-assignment'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.employee-department-assignment.edit');

        $assignment = EmployeeDepartmentAssignment::with(['employee.person', 'department'])->findOrFail($id);

        return view('admin.employee-department-assignment.edit', [
            'title'       => 'Edit Department Assignment',
            'assignment'  => $assignment,
            'employees'   => Employee::with('person')
                ->orderBy('code')
                ->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $assignment = EmployeeDepartmentAssignment::findOrFail($id);

        $validated = $request->validate([
            'employee_id'   => 'required|exists:xlr8_admin_employee,id',
            'department_id' => 'required|exists:xlr8_admin_department,id',
            'from_date'     => 'required|date',
            'to_date'       => 'nullable|date|after_or_equal:from_date',
            'is_current'    => 'boolean',
        ]);

        $assignment->update($validated);

        \Alert::success('Department Assignment updated successfully!')->flash();
        return redirect(backpack_url('employee-department-assignment'));
    }
}
