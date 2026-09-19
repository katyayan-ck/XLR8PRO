<?php

namespace App\Http\Controllers\Admin\Org\Vertical;

use App\Http\Requests\VerticalRequest;
use App\Models\Admin\Vertical;
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
    use ListOperation;
    use UpdateOperation;

    /**
     * No dedicated "vertical.*" permission exists in xlr8_iam_permissions —
     * reuses "division.*", the closest sibling org-hierarchy concept, same
     * reasoning as SubSegmentCrudController reusing "segment.*".
     */
    public function setup()
    {
        CRUD::setModel(Vertical::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/vertical');
        CRUD::setEntityNameStrings('vertical', 'verticals');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('division.view')) {
            abort(403, 'Unauthorized. You do not have permission to view verticals.');
        }

        $this->crud->setListView('admin.vertical.list');
    }

    public function index()
    {
        if (! backpack_user()->can('division.view')) {
            abort(403, 'Unauthorized. You do not have permission to view verticals.');
        }

        $this->crud->setListView('admin.vertical.list');

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

            $editUrl = backpack_url("vertical/{$vertical->id}/edit");

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

        return view('admin.vertical.list', [
            'title' => 'All Verticals',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No.'],
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
        if (! backpack_user()->can('division.create')) {
            abort(403, 'Unauthorized. You do not have permission to create verticals.');
        }

        $this->crud->setCreateView('admin.vertical.create');

        return view('admin.vertical.create', [
            'title' => 'Add New Vertical',
        ]);
    }

    public function store(VerticalRequest $request)
    {
        if (! backpack_user()->can('division.create')) {
            abort(403, 'Unauthorized. You do not have permission to create verticals.');
        }

        $validated = $request->validated();

        $validated['vert_code'] = $validated['code'];

        $vertical = Vertical::create($validated);

        if ($request->hasFile('vertical_image')) {
            $vertical
                ->addMediaFromRequest('vertical_image')
                ->toMediaCollection('vertical_image');
        }

        \Alert::success('Vertical created successfully!')->flash();

        return redirect(backpack_url('vertical'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('division.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit verticals.');
        }

        $this->crud->setEditView('admin.vertical.edit');

        $vertical = Vertical::findOrFail($id);

        return view('admin.vertical.edit', [
            'title' => 'Edit Vertical - '.$vertical->name,
            'vertical' => $vertical,
        ]);
    }

    public function update(VerticalRequest $request, $id)
    {
        if (! backpack_user()->can('division.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit verticals.');
        }

        $vertical = Vertical::findOrFail($id);

        $validated = $request->validated();

        $validated['vert_code'] = $validated['code'];

        $vertical->update($validated);

        if ($request->hasFile('vertical_image')) {
            $vertical
                ->addMediaFromRequest('vertical_image')
                ->toMediaCollection('vertical_image');
        }

        \Alert::success('Vertical updated successfully!')->flash();

        return redirect(backpack_url('vertical'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('division.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete verticals.');
        }

        return $this->crud->delete($id);
    }
}
