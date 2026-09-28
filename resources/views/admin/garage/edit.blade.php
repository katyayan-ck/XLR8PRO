@extends(backpack_view('blank'))

@section('title', 'Edit Garage')

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
                    <h2 class="mb-0">Edit Garage Information</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('garage/' . $garage->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <!-- Read Only -->
                            <div class="col-md-12 mb-4">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Garage ID</label>
                                        <div class="readonly-value">{{ $garage->id }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Created At</label>
                                        <div class="readonly-value">
                                            {{ $garage->created_at?->format('d-m-Y H:i') ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Garage Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $garage->name) }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Associated Person (Optional)</label>
                                <select name="person_id" class="form-control form-select">
                                    <option value="">Select Person</option>
                                    @foreach($persons as $p)
                                    <option value="{{ $p->id }}" {{ old('person_id', $garage->person_id) == $p->id ?
                                        'selected' : '' }}>
                                        {{ $p->first_name }} {{ $p->last_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Type <span class="text-danger">*</span></label>
                                <input type="text" name="type" class="form-control"
                                    value="{{ old('type', $garage->type) }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control"
                                    value="{{ old('address', $garage->address) }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control"
                                    value="{{ old('city', $garage->city) }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>State <span class="text-danger">*</span></label>
                                <input type="text" name="state" class="form-control"
                                    value="{{ old('state', $garage->state) }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Pincode <span class="text-danger">*</span> <small class="text-muted">(6 digits
                                        only)</small></label>
                                <input type="text" name="pincode" class="form-control"
                                    value="{{ old('pincode', $garage->pincode) }}" required maxlength="6"
                                    pattern="[0-9]{6}" title="Pincode must be exactly 6 digits">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Mobile <span class="text-danger">*</span> <small class="text-muted">(10 digits
                                        only)</small></label>
                                <input type="text" name="mobile" class="form-control"
                                    value="{{ old('mobile', $garage->mobile) }}" required maxlength="10"
                                    pattern="[0-9]{10}" title="Mobile number must be exactly 10 digits">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', $garage->is_active) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Garage
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