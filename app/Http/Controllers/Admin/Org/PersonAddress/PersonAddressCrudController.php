<?php

namespace App\Http\Controllers\Admin\Org\PersonAddress;

use App\Http\Requests\PersonAddressRequest;
use App\Models\Admin\Person;
use App\Models\Admin\PersonAddress;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class PersonAddressCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function search()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person addresses.');
        }

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person addresses.');
        }

        return $this->traitShowDetailsRow($id);
    }

    /**
     * No dedicated "ORG_PADR_*" permission exists — reuses "ORG_PRSN_*"
     * since this is a sub-resource of Person.
     */
    public function setup()
    {
        CRUD::setModel(PersonAddress::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/person-address');
        CRUD::setEntityNameStrings('person address', 'person addresses');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person addresses.');
        }

        $this->crud->setListView('admin.org.person-address.list');
    }

    public function index()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person addresses.');
        }

        $this->crud->setListView('admin.org.person-address.list');

        $addresses = PersonAddress::with('person')
            ->select([
                'id',
                'person_code',
                'address_type',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'pincode',
                'country',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $addresses->map(function ($address, $index) {
            $mapped = $address->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['type'] = $address->address_type;
            // "Primary" is encoded in address_type (see PersonAddress::makePrimary()).
            $mapped['is_primary'] = $address->address_type === 'Primary';
            $mapped['person_name'] = $address->person
                ? $address->person->first_name.' '.$address->person->last_name
                : '—';

            // Standalone create/edit is retired (DEC-037, BUG-154): edit on the Person screen,
            // whose inline address editing works.
            $mapped['action'] = $address->person
                ? '<div class="d-flex gap-2 justify-content-center"><a href="'.backpack_url("org/person/{$address->person->id}/edit")
                    .'" class="btn btn-sm btn-outline-primary py-1 px-2" title="Open person">Open person</a></div>'
                : '';

            return $mapped;
        })->values();

        return view('admin.org.person-address.list', [
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
                    ['field' => 'action',         'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('ORG_PRSN_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create person addresses.');
        }

        $this->crud->setCreateView('admin.org.person-address.create');

        return view('admin.org.person-address.create', [
            'title' => 'Add New Person Address',
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(PersonAddressRequest $request)
    {
        if (! backpack_user()->can('ORG_PRSN_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create person addresses.');
        }

        $validated = $request->validated();

        PersonAddress::create($validated);

        \Alert::success('Person Address created successfully!')->flash();

        return redirect(backpack_url('org/person-address'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('ORG_PRSN_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit person addresses.');
        }

        $this->crud->setEditView('admin.org.person-address.edit');

        $address = PersonAddress::with('person')->findOrFail($id);

        return view('admin.org.person-address.edit', [
            'title' => 'Edit Person Address',
            'address' => $address,
            'persons' => Person::select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(PersonAddressRequest $request, $id)
    {
        if (! backpack_user()->can('ORG_PRSN_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit person addresses.');
        }

        $address = PersonAddress::findOrFail($id);

        $validated = $request->validated();

        $address->update($validated);

        \Alert::success('Person Address updated successfully!')->flash();

        return redirect(backpack_url('org/person-address'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('ORG_PRSN_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete person addresses.');
        }

        return $this->crud->delete($id);
    }
}
