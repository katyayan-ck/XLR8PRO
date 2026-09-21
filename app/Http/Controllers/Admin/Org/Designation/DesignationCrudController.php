<?php

namespace App\Http\Controllers\Admin\Org\Designation;

use App\Http\Requests\DesignationRequest;
use App\Models\Admin\Designation;
use App\Services\IAM\PermissionTreeService;
use App\Services\Org\DesignationService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class DesignationCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function __construct(
        private DesignationService $designations,
        private PermissionTreeService $permissionTree,
    ) {
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

    public function setup()
    {
        CRUD::setModel(Designation::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/designation');
        CRUD::setEntityNameStrings('designation', 'designations');
    }

    protected function setupListOperation()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.designation.list');
    }

    public function index()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.designation.list');

        $designations = Designation::with('parentDesignation')
            ->select([
                'id', 'code', 'name', 'description', 'rank', 'is_top_mgmt', 'parent_desig_code', 'is_active',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $designations->map(function ($desig, $index) {
            $mapped = $desig->toArray();

            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $desig->is_active ? 'Active' : 'Inactive';
            $mapped['is_top_mgmt'] = $desig->is_top_mgmt ? 'Yes' : 'No';

            $rankMap = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E'];
            $mapped['rank'] = $rankMap[$desig->rank] ?? '-';
            $mapped['reports_to'] = $desig->parentDesignation?->name ?? '-';

            $imageUrl = $desig->getFirstMediaUrl('designation_image');
            $mapped['image'] = $imageUrl
                ? '<img src="'.$imageUrl.'" style="height:36px;width:36px;object-fit:cover;border-radius:6px;">'
                : '<span class="text-muted">—</span>';

            $editUrl = backpack_url("org/designation/{$desig->id}/edit");
            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.designation.list', [
            'title' => 'All Designations',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'image', 'headerName' => 'Image'],
                    ['field' => 'code', 'headerName' => 'Code'],
                    ['field' => 'name', 'headerName' => 'Designation Name'],
                    ['field' => 'description', 'headerName' => 'Description'],
                    ['field' => 'rank', 'headerName' => 'Rank'],
                    ['field' => 'parent_desig_code', 'headerName' => 'Reports To'],
                    ['field' => 'is_top_mgmt', 'headerName' => 'Top Management'],
                    ['field' => 'is_active', 'headerName' => 'Is Active'],
                    ['field' => 'action', 'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        return view('admin.designation.create', [
            'title' => 'Add New Designation',
            'designations' => Designation::orderBy('name')->get(),
        ]);
    }

    public function store(DesignationRequest $request)
    {
        $this->authorizeManage();

        $designation = $this->designations->create($request->validated(), $request);

        \Alert::success('Designation created successfully!')->flash();

        return redirect(backpack_url('org/designation'));
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $this->crud->setEditView('admin.designation.edit');

        $designation = Designation::findOrFail($id);

        return view('admin.designation.edit', [
            'title' => 'Edit Designation - '.$designation->name,
            'designation' => $designation,
            'designations' => Designation::orderBy('name')->get(),
            'permissionTree' => $this->permissionTree->buildTree(),
            'assignedPermissions' => $this->designations->currentPermissionCodes($designation),
        ]);
    }

    public function update(DesignationRequest $request, $id)
    {
        $this->authorizeManage();

        $designation = Designation::findOrFail($id);

        $result = $this->designations->update($designation, $request->validated(), $request);

        if (! $result['ok']) {
            return back()->withInput()->withErrors([
                'is_active' => 'Cannot disable this designation — it still has '.implode(' and ', $result['blockers']).'. Disable those first.',
            ]);
        }

        \Alert::success('Designation updated successfully!')->flash();

        return redirect(backpack_url('org/designation'));
    }

    public function updatePermissions(Request $request, $id)
    {
        $this->authorizeManage();

        $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $designation = Designation::findOrFail($id);
        $this->designations->syncPermissions($designation, $request->input('permissions', []));

        return response()->json([
            'message' => "Permissions updated for '{$designation->name}'.",
            'permissions' => $this->designations->currentPermissionCodes($designation),
        ]);
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
