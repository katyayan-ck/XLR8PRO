@extends(backpack_view('blank'))

@section('title', 'Edit Person - ' . ($person->display_name ?? $person->first_name . ' ' . $person->last_name))

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

    .readonly-value {
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 10px 15px;
        min-height: 42px;
        display: flex;
        align-items: center;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">Edit Person Information</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('person/' . $person->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            <div class="col-md-3 mb-3">
                                <label>Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" value="{{ $person->person_code }}" readonly>
                            </div>

                            {{-- Entity Type --}}
                            <div class="col-md-3 mb-3">
                                <label>Entity Type <span class="text-danger">*</span></label>
                                <select name="entity_type" class="form-control form-select" required>
                                    <option value="">Select</option>

                                    <option value="individual" {{ old('entity_type', $person->entity_type) ==
                                        'individual' ? 'selected' : '' }}>
                                        Individual
                                    </option>

                                    <option value="legal_entity" {{ old('entity_type', $person->entity_type) ==
                                        'legal_entity' ? 'selected' : '' }}>
                                        Legal Entity
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Salutation</label>
                                <select name="salutation" class="form-control form-select">
                                    <option value="">Select</option>
                                    <option value="Mr" {{ old('salutation', $person->salutation) == 'Mr' ? 'selected' :
                                        '' }}>Mr</option>
                                    <option value="Mrs" {{ old('salutation', $person->salutation) == 'Mrs' ? 'selected'
                                        : '' }}>Mrs</option>
                                    <option value="Ms" {{ old('salutation', $person->salutation) == 'Ms' ? 'selected' :
                                        '' }}>Ms</option>
                                    <option value="Dr" {{ old('salutation', $person->salutation) == 'Dr' ? 'selected' :
                                        '' }}>Dr</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control"
                                    value="{{ old('first_name', $person->first_name) }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="form-control"
                                    value="{{ old('middle_name', $person->middle_name) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Last Name </label>
                                <input type="text" name="last_name" class="form-control"
                                    value="{{ old('last_name', $person->last_name) }}">
                            </div>

                            {{-- display name --}}
                            <div class="col-md-3 mb-3">
                                <label>Display Name</label>
                                <input type="text" name="display_name" class="form-control"
                                    value="{{ old('display_name', $person->display_name) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Gender</label>
                                <select name="gender" class="form-control form-select">
                                    <option value="">Select</option>

                                    <option value="Male" {{ old('gender', $person->gender) == 'Male' ? 'selected' : ''
                                        }}>
                                        Male
                                    </option>

                                    <option value="Female" {{ old('gender', $person->gender) == 'Female' ? 'selected' :
                                        '' }}>
                                        Female
                                    </option>

                                    <option value="Other" {{ old('gender', $person->gender) == 'Other' ? 'selected' : ''
                                        }}>
                                        Other
                                    </option>

                                    <option value="Prefer not to say" {{ old('gender', $person->gender) == 'Prefer not
                                        to say' ? 'selected' : '' }}>
                                        Prefer not to say
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Date of Birth</label>
                                <input type="date" name="dob" class="form-control"
                                    value="{{ old('dob', $person->dob?->format('Y-m-d')) }}">
                            </div>

                            {{-- martial status --}}
                            <div class="col-md-3 mb-3">
                                <label>Marital Status</label>
                                <select name="marital_status" class="form-control form-select">
                                    <option value="">Select</option>

                                    <option value="Single" {{ old('marital_status', $person->marital_status) == 'Single'
                                        ? 'selected' : '' }}>
                                        Single
                                    </option>

                                    <option value="Married" {{ old('marital_status', $person->marital_status) ==
                                        'Married' ? 'selected' : '' }}>
                                        Married
                                    </option>

                                    <option value="Divorced" {{ old('marital_status', $person->marital_status) ==
                                        'Divorced' ? 'selected' : '' }}>
                                        Divorced
                                    </option>

                                    <option value="Widowed" {{ old('marital_status', $person->marital_status) ==
                                        'Widowed' ? 'selected' : '' }}>
                                        Widowed
                                    </option>
                                </select>
                            </div>

                            {{-- spouse name --}}
                            <div class="col-md-3 mb-3">
                                <label>Spouse Name</label>
                                <input type="text" name="spouse_name" class="form-control"
                                    value="{{ old('spouse_name', $person->spouse_name) }}">
                            </div>


                            <div class="col-md-4 mb-3">
                                <label>Occupation</label>
                                <select name="occupation" class="form-control form-select">
                                    <option value="">-- Select Occupation --</option>

                                    <option value="Agriculture" {{ old('occupation', $person->occupation) ==
                                        'Agriculture' ? 'selected' : '' }}>
                                        Agriculture
                                    </option>

                                    <option value="Business" {{ old('occupation', $person->occupation) == 'Business' ?
                                        'selected' : '' }}>
                                        Business
                                    </option>

                                    <option value="Salaried (Govt.)" {{ old('occupation', $person->occupation) ==
                                        'Salaried (Govt.)' ? 'selected' : '' }}>
                                        Salaried (Govt.)
                                    </option>

                                    <option value="Salaried (Pvt.)" {{ old('occupation', $person->occupation) ==
                                        'Salaried (Pvt.)' ? 'selected' : '' }}>
                                        Salaried (Pvt.)
                                    </option>

                                    <option value="Self Employed (Professional)" {{ old('occupation', $person->
                                        occupation) == 'Self Employed (Professional)' ? 'selected' : '' }}>
                                        Self Employed (Professional)
                                    </option>

                                    <option value="Pensioner" {{ old('occupation', $person->occupation) == 'Pensioner' ?
                                        'selected' : '' }}>
                                        Pensioner
                                    </option>

                                    <option value="Other" {{ old('occupation', $person->occupation) == 'Other' ?
                                        'selected' : '' }}>
                                        Other
                                    </option>
                                </select>
                            </div>

                            {{-- aadhar number --}}
                            <div class="col-md-4 mb-3">
                                <label>Aadhaar No</label>
                                <input type="text" name="aadhaar_no" class="form-control"
                                    value="{{ old('aadhaar_no', $person->aadhaar_no) }}">
                            </div>

                            {{-- pan number --}}
                            <div class="col-md-4 mb-3">
                                <label>PAN No</label>
                                <input type="text" name="pan_no" class="form-control"
                                    value="{{ old('pan_no', $person->pan_no) }}">
                            </div>

                            {{-- tan number --}}
                            <div class="col-md-4 mb-3">
                                <label>TAN No</label>
                                <input type="text" name="tan_no" class="form-control"
                                    value="{{ old('tan_no', $person->tan_no) }}">
                            </div>

                            {{-- gst number --}}
                            <div class="col-md-4 mb-3">
                                <label>GST No</label>
                                <input type="text" name="gst_no" class="form-control"
                                    value="{{ old('gst_no', $person->gst_no) }}">
                            </div>

                            {{-- extra data --}}
                            <div class="col-md-4 mb-3">
                                <label>Extra Data</label>
                                <input type="text" name="extra_data" class="form-control"
                                    value="{{ old('extra_data', $person->extra_data) }}">
                            </div>




                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Person
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