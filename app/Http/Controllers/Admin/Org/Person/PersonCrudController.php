<?php

namespace App\Http\Controllers\Admin\Org\Person;

use App\Http\Requests\PersonRequest;
use App\Models\Admin\Person;
use App\Models\Admin\PersonAddress;
use App\Models\Admin\PersonBankingDetail;
use App\Models\Admin\PersonContact;
use App\Services\PersonService;
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
 * screen. All person/contact/address/banking mutations go through
 * PersonService, per .ai/rules/person-user.md.
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

        $this->crud->setListView('admin.person.list');
    }

    public function index()
    {
        $this->authorizeView();

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
        ])->orderBy('id', 'desc')->get();

        $gridData = $persons->map(function ($person, $index) {
            $mapped = $person->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['full_name'] = trim("{$person->first_name} {$person->middle_name} {$person->last_name}");
            $mapped['dob'] = $person->dob?->format('d/m/Y') ?? '—';
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

        return view('admin.person.list', [
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

        return view('admin.person.create', ['title' => 'Add New Person']);
    }

    public function store(PersonRequest $request)
    {
        $this->authorizeManage();

        $validated = $request->validated();

        $person = PersonService::upsert([
            'entity_type' => $validated['entity_type'] ?? 'individual',
            'display_name' => $validated['display_name'],
            'salutation' => $validated['salutation'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'dob' => $validated['dob'] ?? null,
            'marital_status' => $validated['marital_status'] ?? null,
            'spouse_name' => $validated['spouse_name'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'aadhaar_no' => $validated['aadhaar_no'] ?? null,
            'pan_no' => $validated['pan_no'] ?? null,
            'tan_no' => $validated['tan_no'] ?? null,
            'gst_no' => $validated['gst_no'] ?? null,
            'contacts' => [[
                'data_type' => 'Mobile',
                'contact_type' => 'Primary',
                'contact_detail' => $validated['mobile'],
                'is_primary' => true,
            ]],
        ]);

        \Alert::success('Person created successfully! Add more contacts, addresses, or banking details below.')->flash();

        return redirect(backpack_url("org/person/{$person->id}/edit"));
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $this->crud->setEditView('admin.person.edit');

        $person = Person::with(['contacts', 'addresses', 'bankingDetails'])->findOrFail($id);

        return view('admin.person.edit', [
            'title' => 'Edit Person - '.($person->display_name ?: $person->full_name),
            'person' => $person,
        ]);
    }

    public function update(PersonRequest $request, $id)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);

        $validated = $request->validated();

        PersonService::upsert([
            'person_code' => $person->person_code,
            'entity_type' => $validated['entity_type'] ?? $person->entity_type,
            'display_name' => $validated['display_name'],
            'salutation' => $validated['salutation'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'dob' => $validated['dob'] ?? null,
            'marital_status' => $validated['marital_status'] ?? null,
            'spouse_name' => $validated['spouse_name'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'aadhaar_no' => $validated['aadhaar_no'] ?? null,
            'pan_no' => $validated['pan_no'] ?? null,
            'tan_no' => $validated['tan_no'] ?? null,
            'gst_no' => $validated['gst_no'] ?? null,
        ]);

        $this->syncMedia($request, $person);

        \Alert::success('Person updated successfully!')->flash();

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

        $validated = $request->validate([
            'data_type' => 'required|in:'.implode(',', PersonContact::DATA_TYPES),
            'contact_type' => 'required|in:'.implode(',', PersonContact::CONTACT_TYPES),
            'contact_detail' => 'required|string|max:100',
        ]);

        PersonService::upsertContact($person->person_code, $validated);

        \Alert::success('Contact saved.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#contacts');
    }

    public function updateContact(Request $request, $id, $contactId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $contact = PersonContact::where('person_code', $person->person_code)->findOrFail($contactId);

        $validated = $request->validate([
            'data_type' => 'required|in:'.implode(',', PersonContact::DATA_TYPES),
            'contact_type' => 'required|in:'.implode(',', PersonContact::CONTACT_TYPES),
            'contact_detail' => 'required|string|max:100',
        ]);

        $contact->update($validated);
        if ($validated['contact_type'] === 'Primary') {
            $contact->makesPrimary();
        }

        \Alert::success('Contact updated.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#contacts');
    }

    public function destroyContact($id, $contactId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonContact::where('person_code', $person->person_code)->findOrFail($contactId)->delete();

        \Alert::success('Contact removed.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#contacts');
    }

    public function primaryContact($id, $contactId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonContact::where('person_code', $person->person_code)->findOrFail($contactId)->makesPrimary();

        \Alert::success('Primary contact updated.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#contacts');
    }

    // ─────────────────────────────────────────────────────────────
    // ADDRESSES (one slot per address_type — Primary/Office/Home/Alternate/Permanent)
    // ─────────────────────────────────────────────────────────────

    public function storeAddress(Request $request, $id)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);

        $validated = $this->validateAddress($request);

        PersonService::upsertAddress($person->person_code, $validated);

        \Alert::success('Address saved.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#addresses');
    }

    public function updateAddress(Request $request, $id, $addressId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $address = PersonAddress::where('person_code', $person->person_code)->findOrFail($addressId);

        $validated = $this->validateAddress($request);

        $address->update($validated);
        if ($validated['address_type'] === 'Primary') {
            $address->makePrimary();
        }

        \Alert::success('Address updated.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#addresses');
    }

    public function destroyAddress($id, $addressId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonAddress::where('person_code', $person->person_code)->findOrFail($addressId)->delete();

        \Alert::success('Address removed.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#addresses');
    }

    public function primaryAddress($id, $addressId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonAddress::where('person_code', $person->person_code)->findOrFail($addressId)->makePrimary();

        \Alert::success('Primary address updated.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#addresses');
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'address_type' => 'required|in:'.implode(',', PersonAddress::ADDRESS_TYPES),
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'landmark' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'taluka' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'pincode' => 'nullable|digits:6',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // BANKING (one slot per account_type — Primary/Secondary/Joint/Trust)
    // ─────────────────────────────────────────────────────────────

    public function storeBanking(Request $request, $id)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);

        $validated = $this->validateBanking($request);

        PersonService::upsertBanking($person->person_code, $validated);

        \Alert::success('Banking detail saved.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#banking');
    }

    public function updateBanking(Request $request, $id, $bankingId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        $banking = PersonBankingDetail::where('person_code', $person->person_code)->findOrFail($bankingId);

        $validated = $this->validateBanking($request);

        $banking->update($validated);
        if ($validated['account_type'] === 'Primary') {
            $banking->makePrimary();
        }

        \Alert::success('Banking detail updated.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#banking');
    }

    public function destroyBanking($id, $bankingId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonBankingDetail::where('person_code', $person->person_code)->findOrFail($bankingId)->delete();

        \Alert::success('Banking detail removed.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#banking');
    }

    public function primaryBanking($id, $bankingId)
    {
        $this->authorizeManage();

        $person = Person::findOrFail($id);
        PersonBankingDetail::where('person_code', $person->person_code)->findOrFail($bankingId)->makePrimary();

        \Alert::success('Primary bank account updated.')->flash();

        return redirect(backpack_url("org/person/{$id}/edit").'#banking');
    }

    private function validateBanking(Request $request): array
    {
        return $request->validate([
            'account_type' => 'required|in:'.implode(',', PersonBankingDetail::ACCOUNT_TYPES),
            'bank_name' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:34',
            'account_holder_name' => 'nullable|string|max:255',
            'ifsc_code' => 'nullable|string|max:11',
            'micr_code' => 'nullable|string|max:20',
            'account_nature' => 'nullable|in:'.implode(',', PersonBankingDetail::ACCOUNT_NATURES),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // MEDIA (profile photo + identity documents)
    // ─────────────────────────────────────────────────────────────

    private function syncMedia(Request $request, Person $person): void
    {
        if ($request->boolean('remove_profile_photo')) {
            $person->clearMediaCollection('profile_photos');
        }

        if ($request->hasFile('profile_photo')) {
            $person->addMediaFromRequest('profile_photo')->toMediaCollection('profile_photos');
        }

        if ($request->hasFile('identity_documents')) {
            foreach ((array) $request->file('identity_documents') as $file) {
                $person->addMedia($file)->toMediaCollection('identity_documents');
            }
        }

        foreach ((array) $request->input('remove_identity_documents', []) as $mediaId) {
            $person->media()->where('id', $mediaId)->where('collection_name', 'identity_documents')->first()?->delete();
        }
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
