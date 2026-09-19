<?php

namespace App\Http\Controllers\Admin\Org\Branch;

use App\Http\Controllers\Admin\Traits\ScopedCrud;
use App\Http\Requests\BranchRequest;
use App\Models\Admin\Branch;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class BranchCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use ScopedCrud;
    use UpdateOperation;

    protected function getScopeType(): string
    {
        return 'branch';
    }

    public function setup()
    {
        CRUD::setModel(Branch::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/branch');
        CRUD::setEntityNameStrings('branch', 'branches');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('branch.view')) {
            abort(403, 'Unauthorized. You do not have permission to view branches.');
        }

        $this->crud->setListView('admin.branch.list');
    }

    public function index()
    {
        if (! backpack_user()->can('branch.view')) {
            abort(403, 'Unauthorized. You do not have permission to view branches.');
        }

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
            'is_active',
        ])->orderBy('id', 'desc')->get();

        $gridData = $branches->map(function ($branch, $index) {
            $mapped = $branch->toArray();
            $mapped['serial_no'] = $index + 1;

            $mapped['is_active'] = $branch->is_active ? 'Active' : 'Inactive';
            $mapped['is_head_office'] = $branch->is_head_office ? 'Yes' : 'No';

            $editUrl = backpack_url("branch/{$branch->code}/edit");

            $mapped['action'] = '
            <div class="d-flex gap-2 justify-content-center">
                <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
            </div>';

            return $mapped;
        })->values();

        return view('admin.branch.list', [
            'title' => 'All Branches',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
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
                    ['field' => 'action', 'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function edit($code)
    {
        if (! backpack_user()->can('branch.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit branches.');
        }

        $this->crud->setEditView('admin.branch.edit');

        $branch = Branch::where('code', $code)->firstOrFail();

        return view('admin.branch.edit', [
            'title' => 'Edit Branch - '.$branch->name,
            'branch' => $branch,
            'headOffice' => Branch::where('is_head_office', true)->first(),
        ]);
    }

    public function update(BranchRequest $request, $code)
    {
        if (! backpack_user()->can('branch.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit branches.');
        }

        $branch = Branch::where('code', $code)->firstOrFail();

        $validated = $request->validated();

        if ($request->is_head_office) {
            Branch::where('id', '!=', $branch->id)
                ->where('is_head_office', true)
                ->update([
                    'is_head_office' => false,
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
        if (! backpack_user()->can('branch.create')) {
            abort(403, 'Unauthorized. You do not have permission to create branches.');
        }

        $headOffice = Branch::where('is_head_office', true)
            ->first();

        return view('admin.branch.create', [
            'title' => 'Add New Branch',
            'headOffice' => $headOffice,
        ]);
    }

    public function store(BranchRequest $request)
    {
        if (! backpack_user()->can('branch.create')) {
            abort(403, 'Unauthorized. You do not have permission to create branches.');
        }

        $validated = $request->validated();

        if ($request->is_head_office) {
            Branch::where('is_head_office', true)
                ->update([
                    'is_head_office' => false,
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

    public function destroy($id)
    {
        if (! backpack_user()->can('branch.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete branches.');
        }

        return $this->crud->delete($id);
    }

    protected function setupCreateOperation()
    {
        if (! backpack_user()->can('branch.create')) {
            abort(403, 'Unauthorized. You do not have permission to create branches.');
        }

        $this->defineFields();
    }

    protected function setupUpdateOperation()
    {
        if (! backpack_user()->can('branch.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit branches.');
        }

        $this->defineFields();
    }

    protected function defineFields(): void
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
}
