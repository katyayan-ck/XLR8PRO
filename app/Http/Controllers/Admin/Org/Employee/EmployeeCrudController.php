<?php

namespace App\Http\Controllers\Admin\Org\Employee;

use App\Http\Requests\EmployeeRequest;
use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Designation;
use App\Models\Admin\Employee;
use App\Models\Admin\Person;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class EmployeeCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function search()
    {
        if (! backpack_user()->can('ORG_EMPL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view employees.');
        }

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        if (! backpack_user()->can('ORG_EMPL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view employees.');
        }

        return $this->traitShowDetailsRow($id);
    }

    public function setup()
    {
        CRUD::setModel(Employee::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/employee');
        CRUD::setEntityNameStrings('employee', 'employees');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('ORG_EMPL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view employees.');
        }

        $this->crud->setListView('admin.org.employee.list');
    }

    public function index()
    {
        if (! backpack_user()->can('ORG_EMPL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view employees.');
        }

        $this->crud->setListView('admin.org.employee.list');

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
                'is_active',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $employees->map(function ($emp, $index) {
            $mapped = $emp->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['person_name'] = $emp->person
                ? trim($emp->person->first_name.' '.$emp->person->last_name)
                : '—';
            $mapped['designation_name'] = $emp->designation?->name ?? '—';
            $mapped['branch_name'] = $emp->primaryBranch?->name ?? '—';
            $mapped['department_name'] = $emp->primaryDepartment?->name ?? '—';

            $editUrl = backpack_url("org/employee/{$emp->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.org.employee.list', [
            'title' => 'All Employees',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',         'headerName' => 'S.No.'],
                    ['field' => 'code',              'headerName' => 'Employee Code'],
                    ['field' => 'person_name',       'headerName' => 'Employee Name'],
                    ['field' => 'designation_name',  'headerName' => 'Designation'],
                    ['field' => 'branch_name',       'headerName' => 'Branch'],
                    ['field' => 'department_name',   'headerName' => 'Department'],
                    ['field' => 'joining_date',      'headerName' => 'Joining Date'],
                    ['field' => 'is_active',         'headerName' => 'Active'],
                    ['field' => 'action',            'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('ORG_EMPL_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create employees.');
        }

        $this->crud->setCreateView('admin.org.employee.create');

        return view('admin.org.employee.create', [
            'title' => 'Add New Employee',
            'persons' => Person::select('id', 'first_name', 'last_name')->orderBy('first_name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(EmployeeRequest $request)
    {
        if (! backpack_user()->can('ORG_EMPL_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create employees.');
        }

        $validated = $request->validated();

        Employee::create($validated);

        \Alert::success('Employee created successfully!')->flash();

        return redirect(backpack_url('org/employee'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('ORG_EMPL_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit employees.');
        }

        $this->crud->setEditView('admin.org.employee.edit');

        $employee = Employee::with(['person', 'designation', 'primaryBranch', 'primaryDepartment'])->findOrFail($id);

        return view('admin.org.employee.edit', [
            'title' => 'Edit Employee',
            'employee' => $employee,
            'persons' => Person::select('id', 'first_name', 'last_name')->orderBy('first_name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(EmployeeRequest $request, $id)
    {
        if (! backpack_user()->can('ORG_EMPL_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit employees.');
        }

        $employee = Employee::findOrFail($id);

        $validated = $request->validated();

        $employee->update($validated);

        \Alert::success('Employee updated successfully!')->flash();

        return redirect(backpack_url('org/employee'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('ORG_EMPL_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete employees.');
        }

        return $this->crud->delete($id);
    }
}
