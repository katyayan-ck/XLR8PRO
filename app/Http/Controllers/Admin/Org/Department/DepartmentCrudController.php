<?php

namespace App\Http\Controllers\Admin\Org\Department;

use App\Http\Requests\DepartmentRequest;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class DepartmentCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Department::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/department');
        CRUD::setEntityNameStrings('department', 'xlr8_admin_department');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('department.view')) {
            abort(403, 'Unauthorized. You do not have permission to view departments.');
        }

        $this->crud->setListView('admin.department.list');
    }

    public function index()
    {
        if (! backpack_user()->can('department.view')) {
            abort(403, 'Unauthorized. You do not have permission to view departments.');
        }

        $this->crud->setListView('admin.department.list');

        $xlr8_admin_department = Department::select([
            'id',
            'code',
            'name',
            'description',
            'is_active',
        ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $xlr8_admin_department->map(function ($dept, $index) {
            $mapped = $dept->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $dept->is_active ? 'Active' : 'Inactive';

            $editUrl = backpack_url("department/{$dept->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'"
                       class="btn btn-sm btn-primary py-1 px-2"
                       title="Edit">
                         Edit
                    </a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.department.list', [
            'title' => 'All Departments',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No.'],
                    ['field' => 'code',         'headerName' => 'Code'],
                    ['field' => 'name',         'headerName' => 'Department Name'],
                    ['field' => 'description',  'headerName' => 'Description'],
                    ['field' => 'is_active',    'headerName' => 'Is Active'],
                    ['field' => 'action',       'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('department.create')) {
            abort(403, 'Unauthorized. You do not have permission to create departments.');
        }

        return view('admin.department.create', [
            'title' => 'Add New Department',
        ]);
    }

    public function store(DepartmentRequest $request)
    {
        if (! backpack_user()->can('department.create')) {
            abort(403, 'Unauthorized. You do not have permission to create departments.');
        }

        $validated = $request->validated();

        $department = Department::create($validated);

        if ($request->hasFile('department_image')) {
            $department
                ->addMediaFromRequest('department_image')
                ->toMediaCollection('department_image');
        }

        Division::create([
            'dept_code' => $department->code,
            'code' => $department->code,
            'name' => $department->name,
            'is_active' => true,
        ]);

        \Alert::success('Department created successfully!')->flash();

        return redirect(backpack_url('department'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('department.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit departments.');
        }

        $this->crud->setEditView('admin.department.edit');

        $department = Department::findOrFail($id);

        $activeDivisions = Division::where('dept_code', $department->code)
            ->where('is_active', 1)
            ->pluck('name')
            ->toArray();

        return view('admin.department.edit', [
            'title' => 'Edit Department - '.$department->name,
            'department' => $department,
            'activeDivisions' => $activeDivisions,
        ]);
    }

    public function update(DepartmentRequest $request, $id)
    {
        if (! backpack_user()->can('department.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit departments.');
        }

        $department = Department::findOrFail($id);

        $oldCode = $department->code;

        $validated = $request->validated();

        $activeDivisions = Division::where(
            'dept_code',
            $department->code
        )
            ->where('is_active', 1)
            ->pluck('name')
            ->toArray();

        if (
            $department->is_active == 1 &&
            ($validated['is_active'] ?? 0) == 0 &&
            count($activeDivisions) > 0
        ) {
            return redirect()
                ->back()
                ->withInput()
                ->with('division_blocked', $activeDivisions);
        }
        $oldStatus = $department->is_active;

        $department->update($validated);

        if (
            $oldStatus == 0 &&
            ($validated['is_active'] ?? 0) == 1
        ) {
            Division::where('dept_code', $oldCode)
                ->update([
                    'is_active' => 1,
                ]);
        }

        Division::where('dept_code', $oldCode)
            ->update([
                'dept_code' => $validated['code'],
            ]);

        Division::where('dept_code', $validated['code'])
            ->where('code', $oldCode)
            ->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
            ]);

        if ($request->hasFile('department_image')) {
            $department
                ->addMediaFromRequest('department_image')
                ->toMediaCollection('department_image');
        }

        \Alert::success('Department updated successfully!')->flash();

        return redirect(backpack_url('department'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('department.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete departments.');
        }

        return $this->crud->delete($id);
    }
}
