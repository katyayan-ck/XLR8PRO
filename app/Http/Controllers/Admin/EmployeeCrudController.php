<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Person;
use App\Models\Admin\Designation;
use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Employee;

class EmployeeCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Employee::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/employee');
        CRUD::setEntityNameStrings('employee', 'employees');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.employee.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.employee.list');

        $employees = Employee::with(['person', 'designation', 'primaryBranch', 'primaryDepartment'])
            ->select([
                'id',
                'code',
                'person_id',
                'designation_id',
                'primary_branch_id',
                'primary_department_id',
                'joining_date',
                'resignation_date',
                'employment_type',
                'is_active'
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $employees->map(function ($emp, $index) {
            $mapped = $emp->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['person_name'] = $emp->person
                ? trim($emp->person->first_name . ' ' . $emp->person->last_name)
                : '—';
            $mapped['designation_name'] = $emp->designation?->name ?? '—';
            $mapped['branch_name'] = $emp->primaryBranch?->name ?? '—';
            $mapped['department_name'] = $emp->primaryDepartment?->name ?? '—';

            $editUrl = backpack_url("employee/{$emp->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';
            return $mapped;
        })->values();

        return view('admin.employee.list', [
            'title' => 'All Employees',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',         'headerName' => 'S.No'],
                    ['field' => 'code',              'headerName' => 'Employee Code'],
                    ['field' => 'person_name',       'headerName' => 'Employee Name'],
                    ['field' => 'designation_name',  'headerName' => 'Designation'],
                    ['field' => 'branch_name',       'headerName' => 'Branch'],
                    ['field' => 'department_name',   'headerName' => 'Department'],
                    ['field' => 'joining_date',      'headerName' => 'Joining Date'],
                    ['field' => 'is_active',         'headerName' => 'Active'],
                    ['field' => 'action',            'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.employee.create');

        return view('admin.employee.create', [
            'title'        => 'Add New Employee',
            'persons'      => Person::select('id', 'first_name', 'last_name')->orderBy('first_name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'branches'     => Branch::orderBy('name')->get(),
            'departments'  => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                  => 'required|string|unique:xlr8_admin_employee,code',
            'person_id'             => 'required|exists:xlr8_admin_person,id',
            'designation_id'        => 'required|exists:xlr8_admin_designation,id',
            'primary_branch_id'     => 'required|exists:xlr8_admin_branch,id',
            'primary_department_id' => 'required|exists:xlr8_admin_department,id',
            'joining_date'          => 'required|date',
            'resignation_date'      => 'nullable|date|after_or_equal:joining_date',
            'employment_type'       => 'required|in:permanent,contract,temporary,probation',
            'is_active'             => 'boolean',
        ]);

        Employee::create($validated);

        \Alert::success('Employee created successfully!')->flash();
        return redirect(backpack_url('employee'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.employee.edit');

        $employee = Employee::with(['person', 'designation', 'primaryBranch', 'primaryDepartment'])->findOrFail($id);

        return view('admin.employee.edit', [
            'title'        => 'Edit Employee',
            'employee'     => $employee,
            'persons'      => Person::select('id', 'first_name', 'last_name')->orderBy('first_name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'branches'     => Branch::orderBy('name')->get(),
            'departments'  => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'code'                  => 'required|string|unique:xlr8_admin_employee,code,' . $id,
            'person_id'             => 'required|exists:xlr8_admin_person,id',
            'designation_id'        => 'required|exists:xlr8_admin_designation,id',
            'primary_branch_id'     => 'required|exists:xlr8_admin_branch,id',
            'primary_department_id' => 'required|exists:xlr8_admin_department,id',
            'joining_date'          => 'required|date',
            'resignation_date'      => 'nullable|date|after_or_equal:joining_date',
            'employment_type'       => 'required|in:permanent,contract,temporary,probation',
            'is_active'             => 'boolean',
        ]);

        $employee->update($validated);

        \Alert::success('Employee updated successfully!')->flash();
        return redirect(backpack_url('employee'));
    }
}
