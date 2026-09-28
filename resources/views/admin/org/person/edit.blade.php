@extends(backpack_view('blank'))

@section('title', 'Edit Person - ' . ($person->display_name ?: $person->full_name))

@push('after_styles')
<style>
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
    }

    .row-card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: .75rem 1rem;
        margin-bottom: .6rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .row-card.is-primary {
        border-color: #0d6efd;
        background: #f5f9ff;
    }

    .row-card-actions {
        display: flex;
        gap: .4rem;
        flex-wrap: wrap;
    }

    .row-card-edit {
        width: 100%;
        margin-top: .75rem;
        padding-top: .75rem;
        border-top: 1px dashed #dee2e6;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ═══════════════════════ CORE INFO + MEDIA ═══════════════════════ --}}
    <div class="card">
        <div class="card-header text-black d-flex justify-content-between align-items-center">
            <h2 class="mb-0">{{ $person->display_name ?: $person->full_name }}</h2>
            <span class="badge text-bg-secondary fs-6">{{ $person->person_code }}</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ backpack_url('org/person/' . $person->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label>Person Code</label>
                        <input type="text" class="form-control" value="{{ $person->person_code }}" readonly disabled>
                        <div class="form-text">Immutable — derived once from Aadhaar/PAN at creation.</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="display_name" class="form-control"
                            value="{{ old('display_name', $person->display_name) }}" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label>Entity Type</label>
                        <select name="entity_type" class="form-control form-select">
                            <option value="individual" {{ old('entity_type', $person->entity_type) == 'individual' ? 'selected' : '' }}>Individual</option>
                            <option value="legal_entity" {{ old('entity_type', $person->entity_type) == 'legal_entity' ? 'selected' : '' }}>Legal Entity</option>
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label>Salutation</label>
                        <select name="salutation" class="form-control form-select">
                            <option value="">Select</option>
                            @foreach (['Mr', 'Mrs', 'Ms', 'Dr'] as $sal)
                                <option value="{{ $sal }}" {{ old('salutation', $person->salutation) == $sal ? 'selected' : '' }}>{{ $sal }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label>Gender</label>
                        <select name="gender" class="form-control form-select">
                            <option value="">Select</option>
                            @foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $g)
                                <option value="{{ $g }}" {{ old('gender', $person->gender) == $g ? 'selected' : '' }}>{{ $g }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $person->first_name) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" value="{{ old('middle_name', $person->middle_name) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $person->last_name) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>D.O.B.</label>
                        <input type="date" name="dob" class="form-control" value="{{ old('dob', $person->dob?->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Marital Status</label>
                        <select name="marital_status" class="form-control form-select">
                            <option value="">Select</option>
                            @foreach (['Single', 'Married', 'Divorced', 'Widowed'] as $ms)
                                <option value="{{ $ms }}" {{ old('marital_status', $person->marital_status) == $ms ? 'selected' : '' }}>{{ $ms }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Spouse Name</label>
                        <input type="text" name="spouse_name" class="form-control" value="{{ old('spouse_name', $person->spouse_name) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Occupation</label>
                        <input type="text" name="occupation" class="form-control" value="{{ old('occupation', $person->occupation) }}">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Aadhaar No.</label>
                        <input type="text" name="aadhaar_no" class="form-control" value="{{ old('aadhaar_no', $person->aadhaar_no) }}" maxlength="12">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>PAN No.</label>
                        <input type="text" name="pan_no" class="form-control" value="{{ old('pan_no', $person->pan_no) }}" maxlength="10">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>TAN No.</label>
                        <input type="text" name="tan_no" class="form-control" value="{{ old('tan_no', $person->tan_no) }}" maxlength="15">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>GST No.</label>
                        <input type="text" name="gst_no" class="form-control" value="{{ old('gst_no', $person->gst_no) }}" maxlength="20">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Profile Photo</label>
                        @if ($person->getFirstMediaUrl('profile_photos'))
                            <div class="mb-2 d-flex align-items-center gap-3">
                                <img src="{{ $person->getFirstMediaUrl('profile_photos') }}" style="height:70px;width:70px;object-fit:cover;border-radius:50%;border:1px solid #dee2e6;">
                                <div class="form-check">
                                    <input type="checkbox" name="remove_profile_photo" value="1" class="form-check-input" id="remove_profile_photo">
                                    <label class="form-check-label text-danger" for="remove_profile_photo">Remove current photo</label>
                                </div>
                            </div>
                        @endif
                        <input type="file" name="profile_photo" class="form-control" accept="image/jpeg,image/png">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Identity Documents</label>
                        @if ($person->getMedia('identity_documents')->isNotEmpty())
                            <div class="mb-2">
                                @foreach ($person->getMedia('identity_documents') as $doc)
                                    <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1">
                                        <a href="{{ $doc->getUrl() }}" target="_blank" class="text-truncate" style="max-width: 70%;">
                                            <i class="la la-file"></i> {{ $doc->file_name }}
                                        </a>
                                        <div class="form-check mb-0">
                                            <input type="checkbox" name="remove_identity_documents[]" value="{{ $doc->id }}" class="form-check-input" id="remove_doc_{{ $doc->id }}">
                                            <label class="form-check-label text-danger small" for="remove_doc_{{ $doc->id }}">Remove</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <input type="file" name="identity_documents[]" class="form-control" multiple accept=".pdf,image/jpeg,image/png">
                    </div>
                </div>

                <button type="submit" class="btn btn-success px-5">
                    <i class="la la-save"></i> Save Person Info
                </button>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════ CONTACTS ═══════════════════════ --}}
    <div class="card" id="contacts">
        <div class="card-header text-black">
            <h2 class="mb-0">Contacts</h2>
        </div>
        <div class="card-body">
            @forelse ($person->contacts as $contact)
                <div class="row-card {{ $contact->contact_type === 'Primary' ? 'is-primary' : '' }}">
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge text-bg-{{ $contact->data_type === 'Mobile' ? 'success' : 'info' }}">{{ $contact->data_type }}</span>
                        <span class="badge text-bg-light border">{{ $contact->contact_type }}</span>
                        <strong>{{ $contact->contact_detail }}</strong>
                    </div>
                    <div class="row-card-actions">
                        @if ($contact->contact_type !== 'Primary')
                            <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/contacts/' . $contact->id . '/primary') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">Make Primary</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/contacts/' . $contact->id) }}" class="d-inline" onsubmit="return confirm('Remove this contact?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </div>

                    <details class="row-card-edit">
                        <summary class="small text-muted" style="cursor:pointer;">Edit fields</summary>
                        <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/contacts/' . $contact->id) }}" class="mt-2">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <select name="data_type" class="form-control form-select form-select-sm">
                                        @foreach (\App\Models\Admin\PersonContact::DATA_TYPES as $dt)
                                            <option value="{{ $dt }}" {{ $contact->data_type === $dt ? 'selected' : '' }}>{{ $dt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <select name="contact_type" class="form-control form-select form-select-sm">
                                        @foreach (\App\Models\Admin\PersonContact::CONTACT_TYPES as $ct)
                                            <option value="{{ $ct }}" {{ $contact->contact_type === $ct ? 'selected' : '' }}>{{ $ct }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <input type="text" name="contact_detail" class="form-control form-control-sm" value="{{ $contact->contact_detail }}" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </form>
                    </details>
                </div>
            @empty
                <p class="text-muted">No contacts yet.</p>
            @endforelse

            <details class="mt-2">
                <summary class="btn btn-sm btn-outline-success" style="cursor:pointer; display:inline-block;">
                    <i class="la la-plus"></i> Add Contact
                </summary>
                <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/contacts') }}" class="mt-3">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="small">Type</label>
                            <select name="data_type" class="form-control form-select form-select-sm">
                                @foreach (\App\Models\Admin\PersonContact::DATA_TYPES as $dt)
                                    <option value="{{ $dt }}">{{ $dt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="small">Contact Type</label>
                            <select name="contact_type" class="form-control form-select form-select-sm">
                                @foreach (\App\Models\Admin\PersonContact::CONTACT_TYPES as $ct)
                                    <option value="{{ $ct }}">{{ $ct }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="small">Detail</label>
                            <input type="text" name="contact_detail" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-success">Add</button>
                </form>
            </details>
        </div>
    </div>

    {{-- ═══════════════════════ ADDRESSES ═══════════════════════ --}}
    <div class="card" id="addresses">
        <div class="card-header text-black">
            <h2 class="mb-0">Addresses</h2>
        </div>
        <div class="card-body">
            @php $usedAddressTypes = $person->addresses->pluck('address_type')->all(); @endphp

            @foreach ($person->addresses as $address)
                <div class="row-card {{ $address->address_type === 'Primary' ? 'is-primary' : '' }}">
                    <div>
                        <span class="badge text-bg-{{ $address->address_type === 'Primary' ? 'primary' : 'light border' }}">{{ $address->address_type }}</span>
                        <span class="ms-2">{{ $address->full_address ?: '—' }}</span>
                    </div>
                    <div class="row-card-actions">
                        @if ($address->address_type !== 'Primary')
                            <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/addresses/' . $address->id . '/primary') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">Make Primary</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/addresses/' . $address->id) }}" class="d-inline" onsubmit="return confirm('Remove this address?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </div>

                    <details class="row-card-edit">
                        <summary class="small text-muted" style="cursor:pointer;">Edit fields</summary>
                        <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/addresses/' . $address->id) }}" class="mt-2">
                            @csrf
                            @method('PUT')
                            @include('admin.org.person.partials.address-fields', ['address' => $address, 'types' => \App\Models\Admin\PersonAddress::ADDRESS_TYPES])
                            <button type="submit" class="btn btn-sm btn-primary mt-2">Save</button>
                        </form>
                    </details>
                </div>
            @endforeach

            @php $unusedAddressTypes = array_diff(\App\Models\Admin\PersonAddress::ADDRESS_TYPES, $usedAddressTypes); @endphp
            @if (count($unusedAddressTypes))
                <details class="mt-2">
                    <summary class="btn btn-sm btn-outline-success" style="cursor:pointer; display:inline-block;">
                        <i class="la la-plus"></i> Add Address
                    </summary>
                    <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/addresses') }}" class="mt-3">
                        @csrf
                        @include('admin.org.person.partials.address-fields', ['address' => null, 'types' => $unusedAddressTypes])
                        <button type="submit" class="btn btn-sm btn-success mt-2">Add</button>
                    </form>
                </details>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════ BANKING ═══════════════════════ --}}
    <div class="card" id="banking">
        <div class="card-header text-black">
            <h2 class="mb-0">Banking</h2>
        </div>
        <div class="card-body">
            @php $usedAccountTypes = $person->bankingDetails->pluck('account_type')->all(); @endphp

            @foreach ($person->bankingDetails as $bank)
                <div class="row-card {{ $bank->account_type === 'Primary' ? 'is-primary' : '' }}">
                    <div>
                        <span class="badge text-bg-{{ $bank->account_type === 'Primary' ? 'primary' : 'light border' }}">{{ $bank->account_type }}</span>
                        <span class="ms-2">{{ $bank->bank_name }} — {{ $bank->masked_account }} ({{ $bank->account_nature }})</span>
                        @if ($bank->is_verified)
                            <span class="badge text-bg-success ms-1">Verified</span>
                        @endif
                    </div>
                    <div class="row-card-actions">
                        @if ($bank->account_type !== 'Primary')
                            <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/banking/' . $bank->id . '/primary') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">Make Primary</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/banking/' . $bank->id) }}" class="d-inline" onsubmit="return confirm('Remove this bank account?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </div>

                    <details class="row-card-edit">
                        <summary class="small text-muted" style="cursor:pointer;">Edit fields</summary>
                        <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/banking/' . $bank->id) }}" class="mt-2">
                            @csrf
                            @method('PUT')
                            @include('admin.org.person.partials.banking-fields', ['bank' => $bank, 'types' => \App\Models\Admin\PersonBankingDetail::ACCOUNT_TYPES])
                            <button type="submit" class="btn btn-sm btn-primary mt-2">Save</button>
                        </form>
                    </details>
                </div>
            @endforeach

            @php $unusedAccountTypes = array_diff(\App\Models\Admin\PersonBankingDetail::ACCOUNT_TYPES, $usedAccountTypes); @endphp
            @if (count($unusedAccountTypes))
                <details class="mt-2">
                    <summary class="btn btn-sm btn-outline-success" style="cursor:pointer; display:inline-block;">
                        <i class="la la-plus"></i> Add Bank Account
                    </summary>
                    <form method="POST" action="{{ backpack_url('org/person/' . $person->id . '/banking') }}" class="mt-3">
                        @csrf
                        @include('admin.org.person.partials.banking-fields', ['bank' => null, 'types' => $unusedAccountTypes])
                        <button type="submit" class="btn btn-sm btn-success mt-2">Add</button>
                    </form>
                </details>
            @endif
        </div>
    </div>

    <div class="mb-4">
        <a href="{{ backpack_url('org/person') }}" class="btn btn-secondary">Back to Persons</a>
    </div>
</div>
@endsection
