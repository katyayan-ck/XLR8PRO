<?php

namespace App\Http\Controllers\Admin\Org\Person;

use App\Models\Admin\Person;
use App\Models\Admin\PersonAddress;
use App\Models\Admin\PersonBankingDetail;
use App\Models\Admin\PersonContact;
use App\Services\Person\PersonAddressService;
use App\Services\Person\PersonBankingService;
use App\Services\Person\PersonContactService;
use App\Services\Person\PersonRecordService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Integrated Person screen: create with the bare minimum (name + one primary
 * mobile — person_code is derived automatically, Aadhaar first, then PAN,
 * then a generated PERS-XXXXXX fallback), then manage every other facet of
 * the person — contacts, addresses, banking, media — from a single edit
 * screen. Every write goes through the person entity services
 * (App\Services\Person\*Service, DEC-050/053), which own all field rules.
 */
class PersonCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function __construct(
        private PersonRecordService $persons,
        private PersonContactService $contacts,
        private PersonAddressService $addresses,
        private PersonBankingService $banking,
    ) {
        parent::__construct();
    }

    public function search()
    {
        $this->authorizeView();

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        $this->authorizeView();

        return $this->traitShowDetailsRow($id);
    }

    public function setup()
    {
        CRUD::setModel(Person::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/person');
        CRUD::setEntityNameStrings('person', 'persons');
    }

    protected function setupListOperation()
    {
        $this->authorizeView();

        $this->crud->setListView('admin.org.person.list');
    }

    public function index()
    {
        $this->authorizeView();

        $this->crud->setListView('admin.org.person.list');

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
        ])->orderBy('id', 'desc')->get();

        $gridData = $persons->map(function ($person, $index) {
            $mapped = $person->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['full_name'] = trim("{$person->first_name} {$person->middle_name} {$person->last_name}");
            $mapped['dob'] = site_date($person->dob, '—');
            $mapped['primary_mobile'] = $person->primary_mobile ?? '—';
            $mapped['primary_email'] = $person->primary_email ?? '—';

            $editUrl = backpack_url("org/person/{$person->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.org.person.list', [
            'title' => 'All Persons',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',      'headerName' => 'S.No.'],
                    ['field' => 'person_code',    'headerName' => 'Code'],
                    ['field' => 'full_name',      'headerName' => 'Full Name'],
                    ['field' => 'primary_mobile', 'headerName' => 'Primary Mobile'],
                    ['field' => 'primary_email',  'headerName' => 'Primary Email'],
                    ['field' => 'entity_type',    'headerName' => 'Entity Type'],
                    ['field' => 'gender',         'headerName' => 'Gender'],
                    ['field' => 'dob',            'headerName' => 'D.O.B.'],
                    ['field' => 'marital_status', 'headerName' => 'Marital Status'],
                    ['field' => 'occupation',     'headerName' => 'Occupation'],
                    ['field' => 'aadhaar_no',     'headerName' => 'Aadhaar No.'],
                    ['field' => 'pan_no',         'headerName' => 'PAN No.'],
                    ['field' => 'action',         'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        $this->authorizeManage();

        return view('admin.org.person.create', ['title' => 'Add New Person']);
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        // Screen policy: a person is created here with a primary mobile (its format is the
        // contact service's rule). Every field rule lives in PersonRecordService (DEC-053).
        $request->validate(['mobile' => 'required'], ['mobile.required' => 'A primary mobile number is required.']);

        $person = $this->persons->create($request->all());

        \Alert::success(__('org.flash.person_created_successfully_add_more_contacts'))->flash();

        return redirect(backpack_url("org/person/{$person->id}/edit"));
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $this->crud->setEditView('admin.org.person.edit');

        $person = Person::with(['contacts', 'addresses', 'bankingDetails'])->findOrFail($id);

        return view('admin.org.person.edit', [
            'title' => 'Edit Person - '.($person->display_name ?: $person->full_name),
            'person' => $person,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeManage();

        $this->persons->update(Person::findOrFail($id), $request->all());

        \Alert::success(__('org.flash.person_updated_successfully'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit"));
    }

    public function destroy($id)
    {
        $this->authorizeManage();

        return $this->crud->delete($id);
    }

    // ─────────────────────────────────────────────────────────────
    // CONTACTS (Mobile / Email / Landline / Fax — Primary/Alternate/Office/Home/Emergency)
    // ─────────────────────────────────────────────────────────────

    public function storeContact(Request $request, $id)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $this->contacts->upsert(['person_code' => $person->person_code] + $request->all());

        \Alert::success(__('org.flash.contact_saved'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#contacts');
    }

    public function updateContact(Request $request, $id, $contactId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $contact = PersonContact::where('person_code', $person->person_code)->findOrFail($contactId);
        $this->contacts->update($contact, $request->all());

        \Alert::success(__('org.flash.contact_updated'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#contacts');
    }

    public function destroyContact($id, $contactId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $this->contacts->delete(PersonContact::where('person_code', $person->person_code)->findOrFail($contactId));

        \Alert::success(__('org.flash.contact_removed'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#contacts');
    }

    public function primaryContact($id, $contactId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonContact::where('person_code', $person->person_code)->findOrFail($contactId)->makesPrimary();

        \Alert::success(__('org.flash.primary_contact_updated'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#contacts');
    }

    public function storeAddress(Request $request, $id)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $this->addresses->upsert(['person_code' => $person->person_code] + $request->all());

        \Alert::success(__('org.flash.address_saved'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#addresses');
    }

    public function updateAddress(Request $request, $id, $addressId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $address = PersonAddress::where('person_code', $person->person_code)->findOrFail($addressId);
        $this->addresses->update($address, $request->all());

        \Alert::success(__('org.flash.address_updated'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#addresses');
    }

    public function destroyAddress($id, $addressId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $this->addresses->delete(PersonAddress::where('person_code', $person->person_code)->findOrFail($addressId));

        \Alert::success(__('org.flash.address_removed'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#addresses');
    }

    public function primaryAddress($id, $addressId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonAddress::where('person_code', $person->person_code)->findOrFail($addressId)->makePrimary();

        \Alert::success(__('org.flash.primary_address_updated'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#addresses');
    }

    public function storeBanking(Request $request, $id)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $this->banking->upsert(['person_code' => $person->person_code] + $request->all());

        \Alert::success(__('org.flash.banking_detail_saved'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#banking');
    }

    public function updateBanking(Request $request, $id, $bankingId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $banking = PersonBankingDetail::where('person_code', $person->person_code)->findOrFail($bankingId);
        $this->banking->update($banking, $request->all());

        \Alert::success(__('org.flash.banking_detail_updated'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#banking');
    }

    public function destroyBanking($id, $bankingId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $this->banking->delete(PersonBankingDetail::where('person_code', $person->person_code)->findOrFail($bankingId));

        \Alert::success(__('org.flash.banking_detail_removed'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#banking');
    }

    public function primaryBanking($id, $bankingId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonBankingDetail::where('person_code', $person->person_code)->findOrFail($bankingId)->makePrimary();

        \Alert::success(__('org.flash.primary_bank_account_updated'))->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#banking');
    }

    private function authorizeView(): void
    {
        if (! backpack_user()->can('ORG_PRSN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view persons.');
        }
    }

    private function authorizeManage(): void
    {
        if (! backpack_user()->can('ORG_PRSN_CREATE') && ! backpack_user()->can('ORG_PRSN_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to manage persons.');
        }
    }
}
