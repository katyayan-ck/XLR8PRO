<?php

namespace App\Http\Controllers\Admin\Org\Person;

use App\Http\Requests\PersonRequest;
use App\Models\Admin\Person;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class PersonCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Person::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/person');
        CRUD::setEntityNameStrings('person', 'persons');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('person.view')) {
            abort(403, 'Unauthorized. You do not have permission to view persons.');
        }

        $this->crud->setListView('admin.person.list');
    }

    public function index()
    {
        if (! backpack_user()->can('person.view')) {
            abort(403, 'Unauthorized. You do not have permission to view persons.');
        }

        $this->crud->setListView('admin.person.list');

        $persons = Person::select([
            'id',
            'person_code',
            'entity_type',
            'salutation',
            'first_name',
            'middle_name',
            'last_name',
            'display_name',
            'gender',
            'dob',
            'marital_status',
            'spouse_name',
            'occupation',
            'aadhaar_no',
            'pan_no',
            'tan_no',
            'gst_no',
            'extra_data',
        ])->orderBy('id', 'desc')->get();

        $gridData = $persons->map(function ($person, $index) {
            $mapped = $person->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['full_name'] = trim("{$person->first_name} {$person->middle_name} {$person->last_name}");

            $mapped['dob'] = $person->dob?->format('d/m/Y') ?? '—';

            $editUrl = backpack_url("person/{$person->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.person.list', [
            'title' => 'All Persons',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',      'headerName' => 'S.No.'],
                    ['field' => 'person_code',    'headerName' => 'Code'],
                    ['field' => 'entity_type',    'headerName' => 'Entity Type'],
                    ['field' => 'salutation',     'headerName' => 'Salutation'],
                    ['field' => 'full_name',      'headerName' => 'Full Name'],
                    ['field' => 'display_name',   'headerName' => 'Display Name'],
                    ['field' => 'gender',         'headerName' => 'Gender'],
                    ['field' => 'dob',            'headerName' => 'D.O.B.'],
                    ['field' => 'marital_status', 'headerName' => 'Marital Status'],
                    ['field' => 'spouse_name',    'headerName' => 'Spouse Name'],
                    ['field' => 'occupation',     'headerName' => 'Occupation'],
                    ['field' => 'aadhaar_no',     'headerName' => 'Aadhaar No.'],
                    ['field' => 'pan_no',         'headerName' => 'PAN No.'],
                    ['field' => 'tan_no',         'headerName' => 'TAN No.'],
                    ['field' => 'gst_no',         'headerName' => 'GST No.'],
                    ['field' => 'extra_data',     'headerName' => 'Extra Data'],
                    ['field' => 'action',         'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('person.create')) {
            abort(403, 'Unauthorized. You do not have permission to create persons.');
        }

        $this->crud->setCreateView('admin.person.create');

        return view('admin.person.create', ['title' => 'Add New Person']);
    }

    public function store(PersonRequest $request)
    {
        if (! backpack_user()->can('person.create')) {
            abort(403, 'Unauthorized. You do not have permission to create persons.');
        }

        $validated = $request->validated();

        Person::create($validated);

        \Alert::success('Person created successfully!')->flash();

        return redirect(backpack_url('person'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('person.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit persons.');
        }

        $this->crud->setEditView('admin.person.edit');
        $person = Person::findOrFail($id);

        return view('admin.person.edit', [
            'title' => 'Edit Person - '.($person->display_name ?? $person->first_name.' '.$person->last_name),
            'person' => $person,
        ]);
    }

    public function update(PersonRequest $request, $id)
    {
        if (! backpack_user()->can('person.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit persons.');
        }

        $person = Person::findOrFail($id);

        $validated = $request->validated();

        $person->update($validated);

        \Alert::success('Person updated successfully!')->flash();

        return redirect(backpack_url('person'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('person.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete persons.');
        }

        return $this->crud->delete($id);
    }
}
