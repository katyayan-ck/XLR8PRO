<?php

namespace App\Http\Controllers\Admin\Org\PersonContact;

use App\Models\Admin\Person;
use App\Models\Admin\PersonContact;
use App\Services\Person\PersonContactService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class PersonContactCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    /** All writes go through PersonContactService, which owns every field rule (DEC-050/053). */
    public function __construct(private PersonContactService $contacts)
    {
        parent::__construct();
    }

    public function search()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person contacts.');
        }

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person contacts.');
        }

        return $this->traitShowDetailsRow($id);
    }

    /**
     * No dedicated "ORG_PCNT_*" permission exists — reuses "ORG_PRSN_*"
     * since this is a sub-resource of Person.
     */
    public function setup()
    {
        CRUD::setModel(PersonContact::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/person-contact');
        CRUD::setEntityNameStrings('person contact', 'person contacts');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person contacts.');
        }

        $this->crud->setListView('admin.org.person-contact.list');
    }

    public function index()
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view person contacts.');
        }

        $this->crud->setListView('admin.org.person-contact.list');

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
            $editUrl = backpack_url("org/person-contact/{$contact->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.org.person-contact.list', [
            'title' => 'All Person Contacts',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No.'],
                    ['field' => 'person_name',  'headerName' => 'Person'],
                    ['field' => 'person_code',  'headerName' => 'Person Code'],
                    ['field' => 'data_type',         'headerName' => 'Type'],
                    ['field' => 'contact_detail',         'headerName' => 'Contact Detail'],
                    ['field' => 'contact_type', 'headerName' => 'Contact Type'],
                    ['field' => 'action',       'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('ORG_PRSN_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create person contacts.');
        }

        $this->crud->setCreateView('admin.org.person-contact.create');

        return view('admin.org.person-contact.create', [
            'title' => 'Add New Person Contact',
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
        if (! backpack_user()->can('ORG_PRSN_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create person contacts.');
        }

        $this->contacts->create($request->all());

        \Alert::success(__('org.flash.person_contact_created_successfully'))->flash();

        return redirect(backpack_url('org/person-contact'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('ORG_PRSN_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit person contacts.');
        }

        $this->crud->setEditView('admin.org.person-contact.edit');

        $contact = PersonContact::with('person')->findOrFail($id);

        return view('admin.org.person-contact.edit', [
            'title' => 'Edit Person Contact',
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
        if (! backpack_user()->can('ORG_PRSN_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit person contacts.');
        }

        $this->contacts->update(PersonContact::findOrFail($id), $request->all());

        \Alert::success(__('org.flash.person_contact_updated_successfully'))->flash();

        return redirect(backpack_url('org/person-contact'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('ORG_PRSN_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete person contacts.');
        }

        return $this->crud->delete($id);
    }
}
