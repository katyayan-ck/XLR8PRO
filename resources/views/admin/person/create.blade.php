@extends(backpack_view('blank'))

@section('title', 'Add New Person')

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
                    <h2 class="mb-0">Add New Person</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('person') }}">
                        @csrf

                        <div class="row">


                            {{-- Entity Type --}}
                            <div class="col-md-3 mb-3">
                                <label>Entity Type <span class="text-danger">*</span></label>
                                <select name="entity_type" class="form-control form-select" required>
                                    <option value="">Select</option>

                                    <option value="individual" {{ old('entity_type')=='individual' ? 'selected' : '' }}>
                                        Individual
                                    </option>

                                    <option value="legal_entity" {{ old('entity_type')=='legal_entity' ? 'selected' : ''
                                        }}>
                                        Legal Entity
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Salutation</label>
                                <select name="salutation" class="form-control form-select">
                                    <option value="">Select</option>
                                    <option value="Mr" {{ old('salutation')=='Mr' ? 'selected' : '' }}>Mr</option>
                                    <option value="Mrs" {{ old('salutation')=='Mrs' ? 'selected' : '' }}>Mrs</option>
                                    <option value="Ms" {{ old('salutation')=='Ms' ? 'selected' : '' }}>Ms</option>
                                    <option value="Dr" {{ old('salutation')=='Dr' ? 'selected' : '' }}>Dr</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control"
                                    value="{{ old('first_name') }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="form-control"
                                    value="{{ old('middle_name') }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Display Name</label>
                                <input type="text" name="display_name" class="form-control"
                                    value="{{ old('display_name') }}">
                            </div>


                            <div class="col-md-3 mb-3">
                                <label>Gender</label>
                                <select name="gender" class="form-control form-select">
                                    <option value="">Select</option>

                                    <option value="Male" {{ old('gender')=='Male' ? 'selected' : '' }}>
                                        Male
                                    </option>

                                    <option value="Female" {{ old('gender')=='Female' ? 'selected' : '' }}>
                                        Female
                                    </option>

                                    <option value="Other" {{ old('gender')=='Other' ? 'selected' : '' }}>
                                        Other
                                    </option>

                                    <option value="Prefer not to say" {{ old('gender')=='Prefer not to say' ? 'selected'
                                        : '' }}>
                                        Prefer not to say
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Date of Birth</label>
                                <input type="date" name="dob" class="form-control" value="{{ old('dob') }}">
                            </div>

                            {{-- marital status --}}
                            <div class="col-md-3 mb-3">
                                <label>Marital Status</label>
                                <select name="marital_status" class="form-control form-select">
                                    <option value="">Select</option>

                                    <option value="Single" {{ old('marital_status')=='Single' ? 'selected' : '' }}>
                                        Single
                                    </option>

                                    <option value="Married" {{ old('marital_status')=='Married' ? 'selected' : '' }}>
                                        Married
                                    </option>

                                    <option value="Divorced" {{ old('marital_status')=='Divorced' ? 'selected' : '' }}>
                                        Divorced
                                    </option>

                                    <option value="Widowed" {{ old('marital_status')=='Widowed' ? 'selected' : '' }}>
                                        Widowed
                                    </option>
                                </select>
                            </div>

                            {{-- spouse name --}}
                            <div class="col-md-3 mb-3">
                                <label>Spouse Name</label>
                                <input type="text" name="spouse_name" class="form-control"
                                    value="{{ old('spouse_name') }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Occupation <span class="text-danger">*</span></label>
                                <select name="occupation" class="form-control form-select" required>
                                    <option value="">-- Select Occupation --</option>

                                    <option value="Agriculture" {{ old('occupation')=='Agriculture' ? 'selected' : ''
                                        }}>
                                        Agriculture
                                    </option>

                                    <option value="Business" {{ old('occupation')=='Business' ? 'selected' : '' }}>
                                        Business
                                    </option>

                                    <option value="Salaried (Govt.)" {{ old('occupation')=='Salaried (Govt.)'
                                        ? 'selected' : '' }}>
                                        Salaried (Govt.)
                                    </option>

                                    <option value="Salaried (Pvt.)" {{ old('occupation')=='Salaried (Pvt.)' ? 'selected'
                                        : '' }}>
                                        Salaried (Pvt.)
                                    </option>

                                    <option value="Self Employed (Professional)" {{
                                        old('occupation')=='Self Employed (Professional)' ? 'selected' : '' }}>
                                        Self Employed (Professional)
                                    </option>

                                    <option value="Pensioner" {{ old('occupation')=='Pensioner' ? 'selected' : '' }}>
                                        Pensioner
                                    </option>

                                    <option value="Other" {{ old('occupation')=='Other' ? 'selected' : '' }}>
                                        Other
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Aadhaar No</label>
                                <input type="text" name="aadhaar_no" class="form-control"
                                    value="{{ old('aadhaar_no') }}" maxlength="12">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>PAN No</label>
                                <input type="text" name="pan_no" class="form-control" value="{{ old('pan_no') }}"
                                    maxlength="10">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>TAN No</label>
                                <input type="text" name="tan_no" class="form-control" value="{{ old('tan_no') }}"
                                    maxlength="10">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>GST No</label>
                                <input type="text" name="gst_no" class="form-control" value="{{ old('gst_no') }}"
                                    maxlength="15">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Extra Data (JSON)</label>
                                <textarea name="extra_data" class="form-control"
                                    rows="3">{{ old('extra_data') }}</textarea>
                            </div>


                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Person
                            </button>
                            <a href="{{ backpack_url('person') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection