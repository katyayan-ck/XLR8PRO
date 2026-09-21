<?php

namespace App\Http\Controllers\Admin\Org\Division;

use App\Http\Requests\DivisionRequest;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Services\Org\DivisionService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class DivisionCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function __construct(private DivisionService $divisions)
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
        CRUD::setModel(Division::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/division');
        CRUD::setEntityNameStrings('division', 'divisions');
    }

    protected function setupListOperation()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.division.list');
    }

    public function index()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.division.list');

        $divisions = Division::with('department')
            ->select([
                'id',
                'dept_code',
                'code',
                'name',
                'description',
                'is_active',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $divisions->map(function ($division, $index) {
            $mapped = $division->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $division->is_active ? 'Active' : 'Inactive';

            $mapped['department'] = $division->department?->name ?? '—';

            $imageUrl = $division->getFirstMediaUrl('division_image');
            $mapped['image'] = $imageUrl
                ? '<img src="'.$imageUrl.'" style="height:36px;width:36px;object-fit:cover;border-radius:6px;">'
                : '<span class="text-muted">—</span>';

            $editUrl = backpack_url("org/division/{$division->id}/edit");

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

        return view('admin.division.list', [
            'title' => 'All Divisions',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',     'headerName' => 'S.No.'],
                    ['field' => 'image',         'headerName' => 'Image'],
                    ['field' => 'dept_code',    'headerName' => 'Department'],
                    ['field' => 'code',          'headerName' => 'Code'],
                    ['field' => 'name',          'headerName' => 'Division Name'],
                    ['field' => 'description',   'headerName' => 'Description'],
                    ['field' => 'is_active',     'headerName' => 'Is Active'],
                    ['field' => 'action',        'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        $this->crud->setCreateView('admin.division.create');

        return view('admin.division.create', [
            'title' => 'Add New Division',
            'departments' => Department::where('is_active', 1)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(DivisionRequest $request)
    {
        $this->authorizeManage();

        $this->divisions->create($request->validated(), $request);

        \Alert::success('Division created successfully!')->flash();

        return redirect(backpack_url('org/division'));
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $this->crud->setEditView('admin.division.edit');

        $division = Division::with('department')->findOrFail($id);

        $departments = Department::where('is_active', 1)
            ->orWhere('code', $division->dept_code)
            ->orderBy('name')
            ->get();

        return view('admin.division.edit', [
            'title' => 'Edit Division - '.$division->name,
            'division' => $division,
            'departments' => $departments,
        ]);
    }

    public function update(DivisionRequest $request, $id)
    {
        $this->authorizeManage();

        $division = Division::findOrFail($id);

        $result = $this->divisions->update($division, $request->validated(), $request);

        if (! $result['ok']) {
            return back()->withInput()->withErrors([
                'is_active' => 'Cannot disable this division — it still has '.implode(' and ', $result['blockers']).'. Disable those first.',
            ]);
        }

        \Alert::success('Division updated successfully!')->flash();

        return redirect(backpack_url('org/division'));
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
