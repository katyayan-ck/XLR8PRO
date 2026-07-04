<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\Person;
use App\Models\Admin\PersonContact;
use Illuminate\Validation\Rule;

class PersonContactCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(PersonContact::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/person-contact');
        CRUD::setEntityNameStrings('person contact', 'person contacts');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.person-contact.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.person-contact.list');

        $contacts = PersonContact::with('person')
            ->select([
                'id',
                'person_code',
                'data_type',
                'contact_type',
                'contact_detail',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $contacts->map(function ($contact, $index) {
            $mapped = $contact->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['person_name'] =
                $contact->person?->full_name
                ?? $contact->person?->display_name
                ?? $contact->person_code;

            $mapped['person_code'] = $contact->person_code;
            $editUrl = backpack_url("person-contact/{$contact->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';
            return $mapped;
        })->values();

        return view('admin.person-contact.list', [
            'title' => 'All Person Contacts',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No'],
                    ['field' => 'person_name',  'headerName' => 'Person'],
                    ['field' => 'person_code',  'headerName' => 'Person Code'],
                    ['field' => 'data_type',         'headerName' => 'Type'],
                    ['field' => 'contact_detail',         'headerName' => 'Contact Detail'],
                    ['field' => 'contact_type', 'headerName' => 'Contact Type'],
                    ['field' => 'action',       'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.person-contact.create');

        return view('admin.person-contact.create', [
            'title'   => 'Add New Person Contact',
            'persons' => Person::select(
                'person_code',
                'first_name',
                'last_name',
                'display_name'
            )
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_code' => 'required|exists:xlr8_admin_person,person_code',

            'data_type' => 'required|in:Mobile,Email,Landline,Fax',

            'contact_type' => [
                'required',
                'in:Primary,Alternate,Office,Home,Emergency',

                Rule::unique('xlr8_admin_person_contacts')
                    ->where(function ($query) use ($request) {
                        return $query
                            ->where('person_code', $request->person_code)
                            ->where('data_type', $request->data_type)
                            ->where('contact_type', $request->contact_type)
                            ->whereNull('deleted_at');
                    }),
            ],

            'contact_detail' => 'required|string|max:100',
        ], [
            'contact_type.unique' =>
            'This contact type already exists for the selected person and data type.',
        ]);

        PersonContact::create($validated);

        \Alert::success('Person Contact created successfully!')->flash();

        return redirect(backpack_url('person-contact'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.person-contact.edit');

        $contact = PersonContact::with('person')->findOrFail($id);

        return view('admin.person-contact.edit', [
            'title'   => 'Edit Person Contact',
            'contact' => $contact,
            'persons' => Person::select(
                'person_code',
                'first_name',
                'last_name',
                'display_name'
            )
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $contact = PersonContact::findOrFail($id);

        $validated = $request->validate([
            'person_code' => 'required|exists:xlr8_admin_person,person_code',

            'data_type' => 'required|in:Mobile,Email,Landline,Fax',

            'contact_type' => [
                'required',
                'in:Primary,Alternate,Office,Home,Emergency',

                Rule::unique('xlr8_admin_person_contacts')
                    ->ignore($contact->id)
                    ->where(function ($query) use ($request) {
                        return $query
                            ->where('person_code', $request->person_code)
                            ->where('data_type', $request->data_type)
                            ->where('contact_type', $request->contact_type)
                            ->whereNull('deleted_at');
                    }),
            ],

            'contact_detail' => 'required|string|max:100',
        ], [
            'contact_type.unique' =>
            'This contact type already exists for the selected person and data type.',
        ]);

        $contact->update($validated);

        \Alert::success('Person Contact updated successfully!')->flash();

        return redirect(backpack_url('person-contact'));
    }
}
