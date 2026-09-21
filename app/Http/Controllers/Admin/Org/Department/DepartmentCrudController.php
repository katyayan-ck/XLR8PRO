<?php

namespace App\Http\Controllers\Admin\Org\Department;

use App\Http\Requests\DepartmentRequest;
use App\Models\Admin\Department;
use App\Services\Org\DepartmentService;
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
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function __construct(private DepartmentService $departments)
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

    public function setup()
    {
        CRUD::setModel(Department::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/department');
        CRUD::setEntityNameStrings('department', 'xlr8_admin_department');
    }

    protected function setupListOperation()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.department.list');
    }

    public function index()
    {
        $this->authorizeManage();

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

            $imageUrl = $dept->getFirstMediaUrl('department_image');
            $mapped['image'] = $imageUrl
                ? '<img src="'.$imageUrl.'" style="height:36px;width:36px;object-fit:cover;border-radius:6px;">'
                : '<span class="text-muted">—</span>';

            $editUrl = backpack_url("org/department/{$dept->id}/edit");

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
                    ['field' => 'image',        'headerName' => 'Image'],
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
        $this->authorizeManage();

        return view('admin.department.create', [
            'title' => 'Add New Department',
        ]);
    }

    public function store(DepartmentRequest $request)
    {
        $this->authorizeManage();

        $this->departments->create($request->validated(), $request);

        \Alert::success('Department created successfully!')->flash();

        return redirect(backpack_url('org/department'));
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $this->crud->setEditView('admin.department.edit');

        $department = Department::findOrFail($id);

        return view('admin.department.edit', [
            'title' => 'Edit Department - '.$department->name,
            'department' => $department,
        ]);
    }

    public function update(DepartmentRequest $request, $id)
    {
        $this->authorizeManage();

        $department = Department::findOrFail($id);

        $result = $this->departments->update($department, $request->validated(), $request);

        if (! $result['ok']) {
            return back()->withInput()->withErrors([
                'is_active' => 'Cannot disable this department — it still has '.implode(' and ', $result['blockers']).'. Disable those first.',
            ]);
        }

        \Alert::success('Department updated successfully!')->flash();

        return redirect(backpack_url('org/department'));
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
