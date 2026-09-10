@extends(backpack_view('blank'))

@section('title', 'Add New Person Address')

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
                    <h2 class="mb-0">Add New Person Address</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('person-address') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Person <span class="text-danger">*</span></label>
                                <select name="person_id" class="form-control form-select" required>
                                    <option value="">Select Person</option>
                                    @foreach($persons as $p)
                                    <option value="{{ $p->id }}" {{ old('person_id')==$p->id ? 'selected' : '' }}>
                                        {{ $p->first_name }} {{ $p->last_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Address Type <span class="text-danger">*</span></label>
                                <select name="type" class="form-control form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="residential" {{ old('type')=='residential' ? 'selected' : '' }}>
                                        Residential</option>
                                    <option value="official" {{ old('type')=='official' ? 'selected' : '' }}>Official
                                    </option>
                                    <option value="other" {{ old('type')=='other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" name="address_line_1" class="form-control"
                                    value="{{ old('address_line_1') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Address Line 2</label>
                                <input type="text" name="address_line_2" class="form-control"
                                    value="{{ old('address_line_2') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" value="{{ old('city') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>State <span class="text-danger">*</span></label>
                                <input type="text" name="state" class="form-control" value="{{ old('state') }}"
                                    required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Pincode <small class="text-muted">(6 digits)</small></label>
                                <input type="text" name="pincode" class="form-control" value="{{ old('pincode') }}"
                                    maxlength="6" pattern="[0-9]{6}"
                                    title="Please enter exactly 6 digit  Numberical Number">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Country</label>
                                <input type="text" name="country" class="form-control"
                                    value="{{ old('country', 'India') }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Is Primary Address?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_primary" value="0">
                                    <input type="checkbox" name="is_primary" value="1" class="form-check-input" {{
                                        old('is_primary') ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Address
                            </button>
                            <a href="{{ backpack_url('person-address') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection