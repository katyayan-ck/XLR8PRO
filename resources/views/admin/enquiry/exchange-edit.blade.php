@extends(backpack_view('blank'))

@section('title', 'Process Exchange / Scrappage')

@push('after_styles')
    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .form-control:focus, .form-select:focus {
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
                    <h2 class="mb-0">Process Exchange / Scrappage (Enquiry: XENQ-{{ $enquiry->id }})</h2>
                </div>
                
                <div class="card-body">
                    {{-- Customer & Vehicle Basics (Read-only reference) --}}
                    <div class="row bg-light p-3 rounded border mb-4">
                        <div class="col-md-3 mb-2"><label>Customer Name:</label> <input class="form-control" disabled value="{{ trim(($enquiry->first_name ?? '').' '.($enquiry->last_name ?? '')) }}"></div>
                        <div class="col-md-3 mb-2"><label>Mobile:</label> <input class="form-control" disabled value="{{ $enquiry->mobile }}"></div>
                        <div class="col-md-3 mb-2"><label>Segment:</label> <input class="form-control" disabled value="{{ $enquiry->segment_code }}"></div>
                        <div class="col-md-3 mb-2"><label>Model:</label> <input class="form-control" disabled value="{{ $enquiry->model_code }}"></div>
                    </div>

                    <form method="POST" action="{{ backpack_url('enquiry/'.$enquiry->id.'/exchange-update') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Brand Make <span class="text-danger">*</span></label>
                                <select name="brand_make" class="form-control form-select" required>
                                    <option value="">Select Make</option>
                                    @foreach ($existing_car_oems as $item)
                                        <option value="{{ $item['code'] }}" {{ old('brand_make', $enquiry->brand_make ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Brand Model <span class="text-danger">*</span></label>
                                <input type="text" name="brand_model" class="form-control" value="{{ old('brand_model', $enquiry->brand_model ?? '') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Vehicle Registration No. <span class="text-danger">*</span></label>
                                <input type="text" name="vehicle_no" class="form-control" value="{{ old('vehicle_no', $enquiry->vehicle_no ?? '') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Manufacturing Year <span class="text-danger">*</span></label>
                                <input type="number" name="make_year" class="form-control" value="{{ old('make_year', $enquiry->make_year ?? '') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Odometer Reading <span class="text-danger">*</span></label>
                                <input type="number" name="odo_reading" class="form-control" value="{{ old('odo_reading', $enquiry->odo_reading ?? '') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Expected Price</label>
                                <input type="number" name="expected_price" id="expected_price" class="form-control" value="{{ old('expected_price', $enquiry->expected_price ?? '') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Offered Price</label>
                                <input type="number" name="offered_price" id="offered_price" class="form-control" value="{{ old('offered_price', $enquiry->offered_price ?? '') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Exchange Bonus</label>
                                <input type="number" name="exchange_bonus" id="exchange_bonus" class="form-control" value="{{ old('exchange_bonus', $enquiry->exchange_bonus ?? '') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Price Gap</label>
                                <input type="text" id="difference" class="form-control" readonly>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Reason for Case Lost <small class="text-muted">(If dropped)</small></label>
                                <input type="text" name="lost_reason" class="form-control" placeholder="Enter reason if lost" value="{{ old('lost_reason', $enquiry->lost_reason ?? '') }}">
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="la la-save"></i> Save Valuation Data
                            </button>
                            <a href="{{ url()->previous() }}" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const exp = document.getElementById('expected_price');
            const off = document.getElementById('offered_price');
            const bon = document.getElementById('exchange_bonus');
            const diff = document.getElementById('difference');

            function calculateDiff() {
                const expected = parseFloat(exp.value) || 0;
                const offered = parseFloat(off.value) || 0;
                const bonus = parseFloat(bon.value) || 0;
                diff.value = Math.round(expected - offered - bonus);
            }

            [exp, off, bon].forEach(input => input.addEventListener('input', calculateDiff));
            calculateDiff(); // Initial load
        });
    </script>
@endpush