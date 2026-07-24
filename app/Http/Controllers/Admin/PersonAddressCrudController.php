<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Person;
use App\Models\Admin\PersonAddress;

class PersonAddressCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(PersonAddress::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/person-address');
        CRUD::setEntityNameStrings('person address', 'person addresses');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.person-address.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.person-address.list');

        $addresses = PersonAddress::with('person')
            ->select([
                'id',
                'person_code',
                'type',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'pincode',
                'country',
                'is_primary'
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $addresses->map(function ($address, $index) {
            $mapped = $address->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_primary'] = $address->is_primary;
            $mapped['person_name'] = $address->person
                ? $address->person->first_name . ' ' . $address->person->last_name
                : '—';

            $editUrl = backpack_url("person-address/{$address->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';
            return $mapped;
        })->values();

        return view('admin.person-address.list', [
            'title' => 'All Person Addresses',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',      'headerName' => 'S.No.'],
                    ['field' => 'person_name',    'headerName' => 'Person'],
                    ['field' => 'type',           'headerName' => 'Type'],
                    ['field' => 'address_line_1', 'headerName' => 'Address Line 1'],
                    ['field' => 'address_line_2', 'headerName' => 'Address Line 2'],  
                    ['field' => 'city',           'headerName' => 'City'],
                    ['field' => 'state',          'headerName' => 'State'],
                    ['field' => 'pincode',        'headerName' => 'Pincode'],
                    ['field' => 'is_primary',     'headerName' => 'Primary'],
                    ['field' => 'action',         'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.person-address.create');

        return view('admin.person-address.create', [
            'title'   => 'Add New Person Address',
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id'      => 'required|exists:xlr8_admin_person,id',
            'type'           => 'required|in:residential,official,other',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city'           => 'required|string|max:100',
            'state'          => 'required|string|max:100',
            'pincode'        => 'nullable|digits:6',
            'country'        => 'nullable|string|max:100',
            'is_primary'     => 'boolean',
        ]);

        PersonAddress::create($validated);

        \Alert::success('Person Address created successfully!')->flash();
        return redirect(backpack_url('person-address'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.person-address.edit');

        $address = PersonAddress::with('person')->findOrFail($id);

        return view('admin.person-address.edit', [
            'title'   => 'Edit Person Address',
            'address' => $address,
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $address = PersonAddress::findOrFail($id);

        $validated = $request->validate([
            'person_id'      => 'required|exists:xlr8_admin_person,id',
            'type'           => 'required|in:residential,official,other',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city'           => 'required|string|max:100',
            'state'          => 'required|string|max:100',
            'pincode'        => 'nullable|digits:6',
            'country'        => 'nullable|string|max:100',
            'is_primary'     => 'boolean',
        ]);

        $address->update($validated);

        \Alert::success('Person Address updated successfully!')->flash();
        return redirect(backpack_url('person-address'));
    }
}
