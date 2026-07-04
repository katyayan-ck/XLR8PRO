@extends(backpack_view('blank'))

@section('title', 'Add New Garage')

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
                    <h2 class="mb-0">Add New Garage</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('garage') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Garage Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Associated Person (Optional)</label>
                                <select name="person_id" class="form-control form-select">
                                    <option value="">Select Person</option>
                                    @foreach($persons as $p)
                                    <option value="{{ $p->id }}" {{ old('person_id')==$p->id ? 'selected' : '' }}>
                                        {{ $p->first_name }} {{ $p->last_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Type <span class="text-danger">*</span></label>
                                <input type="text" name="type" class="form-control" value="{{ old('type') }}" required>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label>Address <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control" value="{{ old('address') }}"
                                    required>
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
                                <label>Pincode <span class="text-danger">*</span> <small>(6 digits)</small></label>
                                <input type="text" name="pincode" class="form-control" value="{{ old('pincode') }}"
                                    required maxlength="6" pattern="[0-9]{6}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Mobile <span class="text-danger">*</span> <small>(10 digits)</small></label>
                                <input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}"
                                    required maxlength="10" pattern="[0-9]{10}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', true) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Garage
                            </button>
                            <a href="{{ backpack_url('garage') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection