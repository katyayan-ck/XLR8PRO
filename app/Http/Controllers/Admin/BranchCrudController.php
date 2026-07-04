<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Branch;   // ← Correct model

class BranchCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \App\Http\Controllers\Admin\Traits\ScopedCrud;

    protected function getScopeType(): string
    {
        return 'branch';
    }

    public function setup()
    {
        CRUD::setModel(Branch::class);                    // ← Fixed
        CRUD::setRoute(config('backpack.base.route_prefix') . '/branch');
        CRUD::setEntityNameStrings('branch', 'branches');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.branch.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.branch.list');

        $branches = Branch::select([
            'id',
            'code',
            'name',
            'description',
            'phone',
            'email',
            'address',
            'city',
            'pincode',
            'latitude',
            'longitude',
            'is_head_office',
            'is_active'
        ])->orderBy('id', 'desc')->get();

        $gridData = $branches->map(function ($branch, $index) {
            $mapped = $branch->toArray();
            $mapped['serial_no'] = $index + 1;

            $mapped['is_active'] = $branch->is_active ? 'Active' : 'Inactive';
            $mapped['is_head_office'] = $branch->is_head_office ? 'Yes' : 'No';

            $editUrl = backpack_url("branch/{$branch->code}/edit");

            $mapped['action'] = '
            <div class="d-flex gap-2 justify-content-center">
                <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
            </div>';

            return $mapped;
        })->values();

        return view('admin.branch.list', [
            'title' => 'All Branches',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'code', 'headerName' => 'Code'],
                    ['field' => 'name', 'headerName' => 'Branch Name'],
                    ['field' => 'description', 'headerName' => 'Description'],
                    ['field' => 'phone', 'headerName' => 'Phone'],
                    ['field' => 'email', 'headerName' => 'Email'],
                    ['field' => 'address', 'headerName' => 'Address'],
                    ['field' => 'city', 'headerName' => 'City'],
                    ['field' => 'pincode', 'headerName' => 'Pincode'],
                    ['field' => 'latitude', 'headerName' => 'Latitude'],
                    ['field' => 'longitude', 'headerName' => 'Longitude'],
                    ['field' => 'is_head_office', 'headerName' => 'Head Office'],
                    ['field' => 'is_active', 'headerName' => 'Is Active'],
                    ['field' => 'action', 'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function edit($code)
    {
        $this->crud->setEditView('admin.branch.edit');

        $branch = Branch::where('code', $code)->firstOrFail();

        return view('admin.branch.edit', [
            'title'  => 'Edit Branch - ' . $branch->name,
            'branch' => $branch,
            'headOffice' => Branch::where('is_head_office', true)->first()
        ]);
    }

    public function update(Request $request, $code)
    {
        $branch = Branch::where('code', $code)->firstOrFail();   // ← Fixed

        $validated = $request->validate([
            'code' => 'required|string|min:3|max:10|unique:xlr8_admin_branch,code,' . $branch->id,
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',     // ✅
            'phone'          => 'nullable|string',
            'email'          => 'nullable|email',
            'address'        => 'nullable|string',     // ✅
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|digits:6',
            'latitude'  => 'nullable|numeric|between:-90,90',    // ✅
            'longitude' => 'nullable|numeric|between:-180,180',    // ✅
            'is_head_office' => 'boolean',
            'is_active'      => 'boolean',
        ]);

        if ($request->is_head_office) {

            Branch::where('id', '!=', $branch->id)
                ->where('is_head_office', true)
                ->update([
                    'is_head_office' => false
                ]);
        }
        $branch->update($validated);
        if ($request->hasFile('branch_image')) {

            $branch
                ->addMediaFromRequest('branch_image')
                ->toMediaCollection('branch_image');
        }

        \Alert::success('Branch updated successfully!')->flash();

        return redirect(backpack_url('branch'));
    }

    public function create()
    {
        $headOffice = Branch::where('is_head_office', true)
            ->first();

        return view('admin.branch.create', [
            'title' => 'Add New Branch',
            'headOffice' => $headOffice
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|min:3|max:10|unique:xlr8_admin_branch,code',
            'name' => 'required|string|max:255',

            'description' => 'nullable|string',
            'phone' => 'nullable|digits:10',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|digits:6',

            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',

            'branch_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'is_head_office' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->is_head_office) {

            Branch::where('is_head_office', true)
                ->update([
                    'is_head_office' => false
                ]);
        }

        $branch = Branch::create($validated);

        if ($request->hasFile('branch_image')) {

            $branch
                ->addMediaFromRequest('branch_image')
                ->toMediaCollection('branch_image');
        }

        \Alert::success('Branch created successfully!')->flash();

        return redirect(backpack_url('branch'));
    }


    protected function setupCreateOperation()
    {
        CRUD::field('code');
        CRUD::field('name');
        CRUD::field('description')->type('textarea');
        CRUD::field('phone');
        CRUD::field('email');
        CRUD::field('address')->type('textarea');
        CRUD::field('city');
        CRUD::field('pincode');
        CRUD::field('latitude');
        CRUD::field('longitude');
        CRUD::field('is_head_office')->type('checkbox');
        CRUD::field('is_active')->type('checkbox');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
