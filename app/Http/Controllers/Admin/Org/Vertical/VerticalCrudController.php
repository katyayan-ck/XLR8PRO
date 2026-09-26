<?php

namespace App\Http\Controllers\Admin\Org\Vertical;

use App\Http\Requests\VerticalRequest;
use App\Models\Admin\Vertical;
use App\Services\Org\VerticalService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class VerticalCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function __construct(private VerticalService $verticals)
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
        CRUD::setModel(Vertical::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/vertical');
        CRUD::setEntityNameStrings('vertical', 'verticals');
    }

    protected function setupListOperation()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.org.vertical.list');
    }

    public function index()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.org.vertical.list');

        $verticals = Vertical::select([
            'id',
            'code',
            'vert_code',
            'name',
            'description',
            'is_active',
        ])->orderBy('id', 'desc')->get();

        $gridData = $verticals->map(function ($vertical, $index) {
            $mapped = $vertical->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $vertical->is_active ? 'Active' : 'Inactive';

            $imageUrl = $vertical->getFirstMediaUrl('vertical_image');
            $mapped['image'] = $imageUrl
                ? '<img src="'.$imageUrl.'" style="height:36px;width:36px;object-fit:cover;border-radius:6px;">'
                : '<span class="text-muted">—</span>';

            $editUrl = backpack_url("org/vertical/{$vertical->id}/edit");

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

        return view('admin.org.vertical.list', [
            'title' => 'All Verticals',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No.'],
                    ['field' => 'image',        'headerName' => 'Image'],
                    ['field' => 'code',         'headerName' => 'Code'],
                    ['field' => 'vert_code',    'headerName' => 'Vertical Code'],
                    ['field' => 'name',         'headerName' => 'Vertical Name'],
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

        // Updated view reference
        $this->crud->setCreateView('admin.org.vertical.form');

        return view('admin.org.vertical.form', [
            'title' => 'Add New Vertical',
        ]);
    }

    public function edit($id)
    {
        $this->authorizeManage();

        // Updated view reference
        $this->crud->setEditView('admin.org.vertical.form');

        $vertical = Vertical::findOrFail($id);

        return view('admin.org.vertical.form', [
            'title' => 'Edit Vertical - '.$vertical->name,
            'vertical' => $vertical,
        ]);
    }

    public function store(VerticalRequest $request)
    {
        $this->authorizeManage();

        $this->verticals->create($request->validated(), $request);

        \Alert::success('Vertical created successfully!')->flash();

        return redirect(backpack_url('org/vertical'));
    }

    public function update(VerticalRequest $request, $id)
    {
        $this->authorizeManage();

        $vertical = Vertical::findOrFail($id);

        $result = $this->verticals->update($vertical, $request->validated(), $request);

        if (! $result['ok']) {
            return back()->withInput()->withErrors([
                'is_active' => 'Cannot disable this vertical — it still has '.implode(' and ', $result['blockers']).'. Disable those first.',
            ]);
        }

        \Alert::success('Vertical updated successfully!')->flash();

        return redirect(backpack_url('org/vertical'));
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
