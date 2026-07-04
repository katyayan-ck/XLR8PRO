<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Vertical;

class VerticalCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Vertical::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/vertical');
        CRUD::setEntityNameStrings('vertical', 'verticals');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.vertical.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.vertical.list');

        $verticals = Vertical::select([
            'id',
            'code',
            'vert_code',
            'name',
            'description',
            'is_active'
        ])->orderBy('id', 'desc')->get();

        $gridData = $verticals->map(function ($vertical, $index) {
            $mapped = $vertical->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $vertical->is_active ? 'Active' : 'Inactive';


            $editUrl = backpack_url("vertical/{$vertical->id}/edit");

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

        return view('admin.vertical.list', [
            'title' => 'All Verticals',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No'],
                    ['field' => 'code',         'headerName' => 'Code'],
                    ['field' => 'vert_code',    'headerName' => 'Vertical Code'],
                    ['field' => 'name',         'headerName' => 'Vertical Name'],
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
        $this->crud->setCreateView('admin.vertical.create');

        return view('admin.vertical.create', [
            'title' => 'Add New Vertical',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|min:3|max:10|unique:xlr8_admin_vertical,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'vertical_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
        ]);

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
        $this->crud->setEditView('admin.vertical.edit');

        $vertical = Vertical::findOrFail($id);

        return view('admin.vertical.edit', [
            'title'    => 'Edit Vertical - ' . $vertical->name,
            'vertical' => $vertical,
        ]);
    }

    public function update(Request $request, $id)
    {

        $vertical = Vertical::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|min:3|max:10|unique:xlr8_admin_vertical,code,' . $id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'vertical_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
        ]);

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
}
