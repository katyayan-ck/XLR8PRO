<?php

namespace App\Http\Controllers\Admin\Org\Branch;

use App\Http\Controllers\Admin\Traits\ScopedCrud;
use App\Http\Requests\BranchRequest;
use App\Models\Admin\Branch;
use App\Services\Org\BranchService;
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
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use ScopedCrud;
    use UpdateOperation;

    public function __construct(private BranchService $branches)
    {
        parent::__construct();
    }

    public function search()
    {
        $this->authorizeManage();

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        $this->authorizeManage();

        return $this->traitShowDetailsRow($id);
    }

    protected function getScopeType(): string
    {
        return 'branch';
    }

    public function setup()
    {
        CRUD::setModel(Branch::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/branch');
        CRUD::setEntityNameStrings('branch', 'branches');
    }

    protected function setupListOperation()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.org.branch.list');
    }

    public function index()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.org.branch.list');

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

            $imageUrl = $branch->getFirstMediaUrl('branch_image');
            $mapped['image'] = $imageUrl
                ? '<img src="'.$imageUrl.'" style="height:36px;width:36px;object-fit:cover;border-radius:6px;">'
                : '<span class="text-muted">—</span>';

            $editUrl = backpack_url("org/branch/{$branch->code}/edit");

            $mapped['action'] = '
            <div class="d-flex gap-2 justify-content-center">
                <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
            </div>';

            return $mapped;
        })->values();

        return view('admin.org.branch.list', [
            'title' => 'All Branches',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'image', 'headerName' => 'Image'],
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

    // public function edit($code)
    // {
    //     $this->authorizeManage();

    //     $this->crud->setEditView('admin.org.branch.edit');

    //     $branch = Branch::where('code', $code)->firstOrFail();

    //     return view('admin.org.branch.edit', [
    //         'title' => 'Edit Branch - '.$branch->name,
    //         'branch' => $branch,
    //         'headOffice' => Branch::where('is_head_office', true)->first(),
    //     ]);
    // }

    public function update(BranchRequest $request, $code)
    {
        $this->authorizeManage();

        $branch = Branch::where('code', $code)->firstOrFail();

        $result = $this->branches->update($branch, $request->validated(), $request);

        if (! $result['ok']) {
            return back()->withInput()->withErrors([
                'is_active' => 'Cannot disable this branch — it still has '.implode(' and ', $result['blockers']).'. Disable those first.',
            ]);
        }

        \Alert::success('Branch updated successfully!')->flash();

        return redirect(backpack_url('org/branch'));
    }

    public function create()
    {
        $this->authorizeManage();

        $headOffice = Branch::where('is_head_office', true)->first();

        // Updated view reference
        $this->crud->setCreateView('admin.org.branch.form');

        return view('admin.org.branch.form', [
            'title' => 'Add New Branch',
            'headOffice' => $headOffice,
        ]);
    }

    public function edit($code)
    {
        $this->authorizeManage();

        // Updated view reference
        $this->crud->setEditView('admin.org.branch.form');

        $branch = Branch::where('code', $code)->firstOrFail();

        return view('admin.org.branch.form', [
            'title' => 'Edit Branch - '.$branch->name,
            'branch' => $branch,
            'headOffice' => Branch::where('is_head_office', true)->first(),
        ]);
    }

    public function store(BranchRequest $request)
    {
        $this->authorizeManage();

        $this->branches->create($request->validated(), $request);

        \Alert::success('Branch created successfully!')->flash();

        return redirect(backpack_url('org/branch'));
    }

    public function destroy($id)
    {
        $this->authorizeManage();

        return $this->crud->delete($id);
    }

    private function authorizeManage(): void
    {
        if (! backpack_user()->can('ORG_ENTITY_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to manage org entities.');
        }
    }
}
