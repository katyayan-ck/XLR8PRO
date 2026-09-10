@extends(backpack_view('blank'))

@section('title', $title ?? 'Add RTO Rule')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card">
            <div class="card-header bg-gradient-primary">
                <h2 class="card-title mb-0 fw-bold text-black">{{ $title ?? 'Add RTO Rule' }}</h2>
            </div>
            <div class="card-body">
                <form action="{{ route('pricing.rto.store') }}" method="POST">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Code</label>
                            <input type="text" name="code" class="form-control" value="{{ old('code') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Permit <span class="text-danger">*</span></label>
                            <input type="text" name="permit" class="form-control" value="{{ old('permit', 'Private') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Wheels</label>
                            <input type="number" name="wheels" class="form-control" value="{{ old('wheels') }}" min="2" max="16">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Reg Type</label>
                            <input type="text" name="reg_type" class="form-control" value="{{ old('reg_type') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Body Type</label>
                            <input type="text" name="body_type" class="form-control" value="{{ old('body_type') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Fuel Type</label>
                            <input type="text" name="fuel_type" class="form-control" value="{{ old('fuel_type') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">GVW Range</label>
                            <input type="text" name="gvw_range" class="form-control" value="{{ old('gvw_range') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Seater</label>
                            <input type="text" name="seater" class="form-control" value="{{ old('seater') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">CC Range</label>
                            <input type="text" name="cc_range" class="form-control" value="{{ old('cc_range') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tax Factor</label>
                            <input type="number" step="0.000001" name="tax_factor" class="form-control" value="{{ old('tax_factor', 0) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tax Slab</label>
                            <input type="text" name="tax_slab" class="form-control" value="{{ old('tax_slab') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Registration Fee (TRC)</label>
                            <input type="number" step="0.01" name="registration_fee" class="form-control" value="{{ old('registration_fee', 0) }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Surcharge</label>
                            <input type="number" step="0.01" name="surcharge" class="form-control" value="{{ old('surcharge', 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Hypothecation</label>
                            <input type="number" step="0.01" name="hypothecation" class="form-control" value="{{ old('hypothecation', 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Green Tax</label>
                            <input type="number" step="0.01" name="green_tax" class="form-control" value="{{ old('green_tax', 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">RTO Tape</label>
                            <input type="number" step="0.01" name="rto_tape" class="form-control" value="{{ old('rto_tape', 0) }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Fitness</label>
                            <input type="number" step="0.01" name="fitness" class="form-control" value="{{ old('fitness', 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Penalty</label>
                            <input type="number" step="0.01" name="penalty" class="form-control" value="{{ old('penalty', 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Duplicate Tax Card</label>
                            <input type="number" step="0.01" name="duplicate_tax_card" class="form-control" value="{{ old('duplicate_tax_card', 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">WEF Date</label>
                            <input type="date" name="wef_date" class="form-control" value="{{ old('wef_date', date('Y-m-d')) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Expired On</label>
                            <input type="date" name="expired_on" class="form-control" value="{{ old('expired_on') }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                <label class="form-check-label fw-bold" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('pricing.rto.index') }}" class="btn btn-outline-secondary">Back</a>
                        <button type="submit" class="btn btn-primary px-4">Save Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection