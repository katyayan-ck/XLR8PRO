@extends(backpack_view('blank'))

@section('title', 'Add New Banking Detail')

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
                    <h2 class="mb-0">Add New Banking Detail</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('person-banking-detail') }}">
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
                                <label>Bank Name <span class="text-danger">*</span></label>
                                <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name') }}"
                                    required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Account Holder Name <span class="text-danger">*</span></label>
                                <input type="text" name="account_holder_name" class="form-control"
                                    value="{{ old('account_holder_name') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Account Number <span class="text-danger">*</span> <small>(Numeric
                                        only)</small></label>
                                <input type="text" name="account_number" class="form-control"
                                    value="{{ old('account_number') }}" required pattern="[0-9]+" maxlength="20">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>IFSC Code <span class="text-danger">*</span> <small>(e.g.
                                        SBIN0001234)</small></label>
                                <input type="text" name="ifsc_code" class="form-control text-uppercase"
                                    value="{{ old('ifsc_code') }}" required maxlength="11"
                                    pattern="^[A-Z]{4}0[A-Z0-9]{6}$" title="Invalid IFSC Format">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Account Type <span class="text-danger">*</span></label>
                                <select name="account_type" class="form-control form-select" required>
                                    <option value="savings" {{ old('account_type')=='savings' ? 'selected' : '' }}>
                                        Savings</option>
                                    <option value="current" {{ old('account_type')=='current' ? 'selected' : '' }}>
                                        Current</option>
                                    <option value="fd" {{ old('account_type')=='fd' ? 'selected' : '' }}>Fixed Deposit
                                    </option>
                                    <option value="rd" {{ old('account_type')=='rd' ? 'selected' : '' }}>Recurring
                                        Deposit</option>
                                    <option value="other" {{ old('account_type')=='other' ? 'selected' : '' }}>Other
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Branch Name</label>
                                <input type="text" name="branch_name" class="form-control"
                                    value="{{ old('branch_name') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Swift Code</label>
                                <input type="text" name="swift_code" class="form-control"
                                    value="{{ old('swift_code') }}">
                            </div>

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Primary?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_primary" value="0">
                                    <input type="checkbox" name="is_primary" value="1" class="form-check-input" {{
                                        old('is_primary') ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Verified?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_verified" value="0">
                                    <input type="checkbox" name="is_verified" value="1" class="form-check-input" {{
                                        old('is_verified') ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Banking Detail
                            </button>
                            <a href="{{ backpack_url('person-banking-detail') }}"
                                class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection