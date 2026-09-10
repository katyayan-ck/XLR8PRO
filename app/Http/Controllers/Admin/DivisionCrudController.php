<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Department;
use App\Models\Admin\Division;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class DivisionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Division::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/division');
        CRUD::setEntityNameStrings('division', 'divisions');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.division.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.division.list');

        $divisions = Division::with('department')
            ->select([
                'id',
                'dept_code',
                'code',
                'name',
                'description',
                'is_active'
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $divisions->map(function ($division, $index) {
            $mapped = $division->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $division->is_active ? 'Active' : 'Inactive';

            $mapped['department'] = $division->department?->name ?? '—';

            $editUrl = backpack_url("division/{$division->id}/edit");

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

        return view('admin.division.list', [
            'title' => 'All Divisions',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',     'headerName' => 'S.No.'],
                    ['field' => 'dept_code',    'headerName' => 'Department'],
                    ['field' => 'code',          'headerName' => 'Code'],
                    ['field' => 'name',          'headerName' => 'Division Name'],
                    ['field' => 'description',   'headerName' => 'Description'],
                    ['field' => 'is_active',     'headerName' => 'Is Active'],
                    ['field' => 'action',        'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.division.create');

        return view('admin.division.create', [
            'title'       => 'Add New Division',
            'departments' => Department::where('is_active', 1)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dept_code' => 'required|exists:xlr8_admin_department,code',

            'code' => 'required|string|min:3|max:10|unique:xlr8_admin_division,code',

            'name' => 'required|string|max:255',

            'description' => 'nullable|string',

            'division_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'is_active' => 'nullable|boolean',
        ]);

        $division = Division::create($validated);

        if ($request->hasFile('division_image')) {

            $division
                ->addMediaFromRequest('division_image')
                ->toMediaCollection('division_image');
        }

        \Alert::success('Division created successfully!')->flash();

        return redirect(backpack_url('division'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.division.edit');

        $division = Division::with('department')->findOrFail($id);

        $departments = Department::where('is_active', 1)
            ->orWhere('code', $division->dept_code)
            ->orderBy('name')
            ->get();

        return view('admin.division.edit', [
            'title'       => 'Edit Division - ' . $division->name,
            'division'    => $division,
            'departments' => $departments,
        ]);
    }

    public function update(Request $request, $id)
    {
        $division = Division::findOrFail($id);

        $validated = $request->validate([
            'dept_code' => 'required|exists:xlr8_admin_department,code',

            'code' => 'required|string|min:3|max:10|unique:xlr8_admin_division,code,' . $id,

            'name' => 'required|string|max:255',

            'description' => 'nullable|string',

            'division_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'is_active' => 'nullable|boolean',
        ]);

        $department = Department::where(
            'code',
            $validated['dept_code']
        )->first();

        if (
            $department &&
            !$department->is_active &&
            ($validated['is_active'] ?? 0) == 1
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'is_active' =>
                    'Division cannot be activated because its Department is inactive.'
                ]);
        }

        $division->update($validated);
        if ($request->hasFile('division_image')) {

            $division
                ->addMediaFromRequest('division_image')
                ->toMediaCollection('division_image');
        }

        \Alert::success('Division updated successfully!')->flash();

        return redirect(backpack_url('division'));
    }
}
