<?php

namespace App\Http\Controllers\Admin\Org\Employee;

use App\Models\Admin\Employee;
use App\Services\OrgService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class EmployeeCrudController extends CrudController
{
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }

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

        $employees = Employee::with(['person', 'designation'])
            ->select([
                'id',
                'code',
                'person_code',
                'designation_code',
                'primary_branch_code',
                'primary_dept_code',
                'joining_date',
                'employment_type',
                'employment_status',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $employees->map(function ($emp, $index) {
            $mapped = $emp->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['joining_date'] = site_date($emp->joining_date);
            $mapped['person_name'] = $emp->person
                ? trim($emp->person->first_name.' '.$emp->person->last_name)
                : '—';
            $mapped['designation_name'] = $emp->designation?->name ?? '—';
            $mapped['branch_name'] = $emp->primary_branch_code ? OrgService::branchName($emp->primary_branch_code) : '—';
            $mapped['department_name'] = $emp->primary_dept_code ? OrgService::departmentName($emp->primary_dept_code) : '—';
            $mapped['is_active'] = $emp->employment_status === 'active';

            // Standalone create/edit is retired (DEC-037, BUG-154): employees are maintained via
            // Users → Bulk import / the User screen; person details via the Person screen.
            $mapped['action'] = $emp->person
                ? '<div class="d-flex gap-2 justify-content-center"><a href="'.backpack_url("org/person/{$emp->person->id}/edit")
                    .'" class="btn btn-sm btn-outline-primary py-1 px-2" title="Open person">Open person</a></div>'
                : '';

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

    public function destroy($id)
    {
        if (! backpack_user()->can('ORG_EMPL_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete employees.');
        }

        return $this->crud->delete($id);
    }
}
