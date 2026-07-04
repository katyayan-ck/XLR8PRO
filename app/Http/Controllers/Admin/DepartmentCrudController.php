<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Department;
use App\Models\Admin\Division;

class DepartmentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Department::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/department');
        CRUD::setEntityNameStrings('department', 'xlr8_admin_department');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.department.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.department.list');

        $xlr8_admin_department = Department::select([
            'id',
            'code',
            'name',
            'description',
            'is_active'
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
                    <a href="' . $editUrl . '"
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
                    ['field' => 'serial_no',    'headerName' => 'S.No'],
                    ['field' => 'code',         'headerName' => 'Code'],
                    ['field' => 'name',         'headerName' => 'Department Name'],
                    ['field' => 'description',  'headerName' => 'Description'],
                    ['field' => 'is_active',    'headerName' => 'Is Active'],
                    ['field' => 'action',       'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        return view('admin.department.create', [
            'title'       => 'Add New Department',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|min:3|max:10|unique:xlr8_admin_department,code',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active'   => 'boolean',
        ]);

        $department = Department::create($validated);



        if ($request->hasFile('department_image')) {


            $department
                ->addMediaFromRequest('department_image')
                ->toMediaCollection('department_image');
        }

        Division::create([
            'dept_code' => $department->code,
            'code'      => $department->code,
            'name'      => $department->name,
            'is_active' => true,
        ]);


        \Alert::success('Department created successfully!')->flash();

        return redirect(backpack_url('department'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.department.edit');

        $department = Department::findOrFail($id);

        $activeDivisions = Division::where('dept_code', $department->code)
            ->where('is_active', 1)
            ->pluck('name')
            ->toArray();

        return view('admin.department.edit', [
            'title' => 'Edit Department - ' . $department->name,
            'department' => $department,
            'activeDivisions' => $activeDivisions
        ]);
    }

    // public function update(Request $request, $id)
    // {
    //     $department = Department::findOrFail($id);

    //     $validated = $request->validate([
    //         'code' => 'required|string|min:3|max:10|unique:xlr8_admin_department,code,' . $id,
    //         'name' => 'required|string|max:255',
    //         'description' => 'nullable|string',

    //         'department_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

    //         'is_active' => 'nullable|boolean',
    //     ]);

    //     $department->update($validated);

    //     if ($request->hasFile('department_image')) {

    //         $department
    //             ->addMediaFromRequest('department_image')
    //             ->toMediaCollection('department_image');
    //     }

    //     \Alert::success('Department updated successfully!')->flash();

    //     return redirect(backpack_url('department'));
    // }
    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $oldCode = $department->code;
        $oldName = $department->name;

        $validated = $request->validate([
            'code' => 'required|string|min:3|max:10|unique:xlr8_admin_department,code,' . $id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

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
                    'is_active' => 1
                ]);
        }

        Division::where('dept_code', $oldCode)
            ->update([
                'dept_code' => $validated['code']
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
}
