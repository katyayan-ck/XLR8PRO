@extends(backpack_view('blank'))

@section('title', $title ?? 'TCS Configuration')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header bg-gradient-primary">
                <h2 class="card-title mb-0 fw-bold text-black">{{ $title ?? 'TCS Configuration' }}</h2>
            </div>
            <div class="card-body">
                <form action="{{ route('pricing.tcs.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="form-label fw-bold">TCS Limit Amount (₹)</label>
                        <input type="number" name="limit_amount" class="form-control form-control-lg"
                               step="0.01" min="0"
                               value="{{ old('limit_amount', $tcs->limit_amount) }}" required>
                        <div class="form-text">TCS applies when Financier Invoice ≥ this limit. Default ₹10,00,000</div>
                        @error('limit_amount')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">TCS Rate (%)</label>
                        <input type="number" name="rate_pct" class="form-control form-control-lg"
                               step="0.01" min="0" max="100"
                               value="{{ old('rate_pct', $tcs->rate_pct) }}" required>
                        <div class="form-text">Default 1%</div>
                        @error('rate_pct')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                               {{ old('is_active', $tcs->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="is_active">Active</label>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('pricing.workflow.index') }}" class="btn btn-outline-secondary">Back</a>
                        <button type="submit" class="btn btn-primary px-4">Save TCS Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection