@extends(backpack_view('blank'))

@section('title', 'Add New Person Contact')

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
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">Add New Person Contact</h2>
                </div>
                <div class="card-body">

                    <form id="contactForm" method="POST" action="{{ backpack_url('person-contact') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label>Person <span class="text-danger">*</span></label>
                                <select name="person_code" class="form-control form-select" required>
                                    <option value="">Select Person</option>
                                    @foreach($persons as $p)
                                    <option value="{{ $p->person_code }}" {{ old('person_code')==$p->person_code ?
                                        'selected' : '' }}>
                                        {{ $p->full_name ?? trim($p->first_name.' '.$p->last_name) }}
                                        ({{ $p->person_code }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Contact Type <span class="text-danger">*</span></label>
                                <select name="data_type" id="data_type" class="form-control form-select" required>
                                    <option value="">Select Type</option>

                                    <option value="Mobile" {{ old('data_type')=='Mobile' ? 'selected' : '' }}>
                                        Mobile
                                    </option>

                                    <option value="Email" {{ old('data_type')=='Email' ? 'selected' : '' }}>
                                        Email
                                    </option>

                                    <option value="Landline" {{ old('data_type')=='Landline' ? 'selected' : '' }}>
                                        Landline
                                    </option>

                                    <option value="Fax" {{ old('data_type')=='Fax' ? 'selected' : '' }}>
                                        Fax
                                    </option>
                                </select>
                            </div>

                            {{-- Contact Type --}}
                            <div class="col-md-3 mb-3">
                                <label>Contact Category <span class="text-danger">*</span></label>
                                <select name="contact_type" id="contact_type" class="form-control form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="Primary" {{ old('contact_type')=='Primary' ? 'selected' : '' }}>
                                        Primary</option>
                                    <option value="Alternate" {{ old('contact_type')=='Alternate' ? 'selected' : '' }}>
                                        Alternate</option>
                                    <option value="Office" {{ old('contact_type')=='Office' ? 'selected' : '' }}>Office
                                    </option>
                                    <option value="Home" {{ old('contact_type')=='Home' ? 'selected' : '' }}>Home
                                    </option>
                                    <option value="Emergency" {{ old('contact_type')=='Emergency' ? 'selected' : '' }}>
                                        Emergency</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Contact Detail <span class="text-danger">*</span></label>
                                <input type="text" name="contact_detail" id="contact_detail" class="form-control"
                                    value="{{ old('contact_detail') }}" required>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Contact
                            </button>
                            <a href="{{ backpack_url('person-contact') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection