<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Person;
use App\Models\Core\Garage;

class GarageCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Garage::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/garage');
        CRUD::setEntityNameStrings('garage', 'garages');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.garage.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.garage.list');

        $garages = Garage::with('person')
            ->select([
                'id',
                'person_id',
                'name',
                'type',
                'address',
                'city',
                'state',
                'pincode',
                'mobile',
                'is_active'
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $garages->map(function ($garage, $index) {
            $mapped = $garage->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $garage->is_active;
            $mapped['person_name'] = $garage->person
                ? $garage->person->first_name . ' ' . $garage->person->last_name
                : '—';

            $editUrl = backpack_url("garage/{$garage->id}/edit");

            $mapped['action'] = '
            <div class="d-flex gap-2 justify-content-center">
                <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
            </div>
        ';
            return $mapped;
        })->values();

        return view('admin.garage.list', [
            'title' => 'All Garages',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No'],
                    ['field' => 'name',         'headerName' => 'Garage Name'],
                    ['field' => 'person_name',  'headerName' => 'Associated Person'],
                    ['field' => 'type',         'headerName' => 'Type'],           // ← Added
                    ['field' => 'address',      'headerName' => 'Address'],        // ← Added
                    ['field' => 'city',         'headerName' => 'City'],
                    ['field' => 'state',        'headerName' => 'State'],
                    ['field' => 'pincode',      'headerName' => 'Pincode'],        // ← Added
                    ['field' => 'mobile',       'headerName' => 'Mobile'],
                    ['field' => 'is_active',    'headerName' => 'Active'],
                    ['field' => 'action',       'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.garage.create');

        return view('admin.garage.create', [
            'title'   => 'Add New Garage',
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id' => 'nullable|exists:xlr8_admin_person,id',
            'name'      => 'required|string|max:255',
            'type'      => 'required|string|max:100',           // ← Required
            'address'   => 'required|string|max:255',           // ← Required
            'city'      => 'required|string|max:100',           // ← Required
            'state'     => 'required|string|max:100',           // ← Required
            'pincode'   => 'required|digits:6',                 // ← Required + 6 digits
            'mobile'    => 'required|digits:10',                // ← Required + 10 digits
            'is_active' => 'boolean',
        ]);

        Garage::create($validated);

        \Alert::success('Garage created successfully!')->flash();
        return redirect(backpack_url('garage'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.garage.edit');

        $garage = Garage::with('person')->findOrFail($id);

        return view('admin.garage.edit', [
            'title'   => 'Edit Garage',
            'garage'  => $garage,
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $garage = Garage::findOrFail($id);

        $validated = $request->validate([
            'person_id' => 'nullable|exists:xlr8_admin_person,id',
            'name'      => 'required|string|max:255',
            'type'      => 'required|string|max:100',
            'address'   => 'required|string|max:255',
            'city'      => 'required|string|max:100',
            'state'     => 'required|string|max:100',
            'pincode'   => 'required|digits:6',
            'mobile'    => 'required|digits:10',
            'is_active' => 'boolean',
        ]);

        $garage->update($validated);

        \Alert::success('Garage updated successfully!')->flash();
        return redirect(backpack_url('garage'));
    }
}
