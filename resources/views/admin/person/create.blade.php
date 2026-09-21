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
        <div class="col-lg-8 offset-lg-2">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">Add New Person</h2>
                    <p class="mb-0 text-muted">Only a name and one primary mobile number are required to get
                        started — everything else (identity numbers, addresses, banking, additional contacts)
                        can be added on the next screen. The person code is generated automatically (from Aadhaar,
                        then PAN, then a system-generated code) and can never be changed afterwards.</p>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ backpack_url('org/person') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" name="display_name" class="form-control"
                                    value="{{ old('display_name') }}" required autofocus>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Primary Mobile <span class="text-danger">*</span></label>
                                <input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}"
                                    maxlength="10" pattern="[0-9]{10}" inputmode="numeric" required>
                                <div id="mobileError" class="text-danger mt-1"></div>
                            </div>
                        </div>

                        <details class="mb-3">
                            <summary class="text-primary" style="cursor:pointer;">More details (optional)</summary>

                            <div class="row mt-3">
                                <div class="col-md-3 mb-3">
                                    <label>Entity Type</label>
                                    <select name="entity_type" class="form-control form-select">
                                        <option value="individual" {{ old('entity_type', 'individual') == 'individual' ? 'selected' : '' }}>Individual</option>
                                        <option value="legal_entity" {{ old('entity_type') == 'legal_entity' ? 'selected' : '' }}>Legal Entity</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Salutation</label>
                                    <select name="salutation" class="form-control form-select">
                                        <option value="">Select</option>
                                        @foreach (['Mr', 'Mrs', 'Ms', 'Dr'] as $sal)
                                            <option value="{{ $sal }}" {{ old('salutation') == $sal ? 'selected' : '' }}>{{ $sal }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Gender</label>
                                    <select name="gender" class="form-control form-select">
                                        <option value="">Select</option>
                                        @foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $g)
                                            <option value="{{ $g }}" {{ old('gender') == $g ? 'selected' : '' }}>{{ $g }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>D.O.B.</label>
                                    <input type="date" name="dob" class="form-control" value="{{ old('dob') }}">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Aadhaar No.</label>
                                    <input type="text" name="aadhaar_no" class="form-control"
                                        value="{{ old('aadhaar_no') }}" maxlength="12">
                                    <div class="form-text">Used as the person code if present — takes priority over PAN.</div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>PAN No.</label>
                                    <input type="text" name="pan_no" class="form-control" value="{{ old('pan_no') }}"
                                        maxlength="10">
                                    <div class="form-text">Used as the person code when Aadhaar isn't given.</div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Occupation</label>
                                    <input type="text" name="occupation" class="form-control" value="{{ old('occupation') }}">
                                </div>
                            </div>
                        </details>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Person
                            </button>
                            <a href="{{ backpack_url('org/person') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $('input[name="mobile"]').on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });

    $('form').on('submit', function (e) {
        const mobile = $('input[name="mobile"]').val().trim();
        if (mobile.length !== 10) {
            e.preventDefault();
            $('#mobileError').text('Mobile number must be exactly 10 digits.');
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Mobile number must be exactly 10 digits.' });
        }
    });
</script>
@endpush
