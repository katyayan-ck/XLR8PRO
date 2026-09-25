{{-- =========================== VIEW OTF BOOKING (READ-ONLY) =========================== --}}

@extends(backpack_view('blank'))

@section('title', 'View OTF Booking')

@push('after_styles')
    <style>
        .enquiry-card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: none;
            margin-bottom: 2rem;
            background: var(--tblr-card-bg);
        }
        .enquiry-card .card-header {
            background: var(--tblr-card-bg);
            border-bottom: 1px solid #edf2f9;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 1.25rem 1.5rem;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
            <h2 class="mb-0 fw-bold">View OTF Booking : {{ $otf->otf_no ?? $otf->id }}</h2>
            <a href="{{ url()->previous() }}" class="btn btn-secondary shadow-sm"><i class="la la-arrow-left"></i> Back to List</a>
        </div>

        <div class="d-flex flex-column">

            {{-- 1. OTF CREDENTIALS --}}
            <div class="card enquiry-card">
                <div class="card-header"><h4 class="mb-0 fw-bold">OTF Credentials</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Booking Number</label><input type="text" class="form-control" value="{{ $otf->id ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">OTF Number</label><input type="text" class="form-control" value="{{ $otf->otf_no ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">SO Number</label><input type="text" class="form-control" value="{{ $otf->so_no ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Invoice No.</label><input type="text" class="form-control" value="{{ $otf->invoice_no ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Evaluation ID</label><input type="text" class="form-control" value="{{ $otf->evaluation_no ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Booking Date</label><input type="text" class="form-control" value="{{ !empty($otf->booking_date) ? \Carbon\Carbon::parse($otf->booking_date)->format('d-M-Y') : '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Booking Status</label><input type="text" class="form-control" value="{{ $otf->status ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Cancellation Date</label><input type="text" class="form-control" value="{{ !empty($otf->cancellation_date) ? \Carbon\Carbon::parse($otf->cancellation_date)->format('d-M-Y') : '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                    </div>
                </div>
            </div>

            {{-- 2. CUSTOMER DETAILS --}}
            <div class="card enquiry-card">
                <div class="card-header"><h4 class="mb-0 fw-bold">Customer Details</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Customer Name</label><input type="text" class="form-control" value="{{ $otf->customer_name ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Customer Code</label><input type="text" class="form-control" value="{{ $otf->customer_code ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">PAN Number</label><input type="text" class="form-control" value="{{ $otf->customer_pan ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">TAN Number</label><input type="text" class="form-control" value="{{ $otf->customer_tan ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Aadhaar Number</label><input type="text" class="form-control" value="{{ $otf->customer_aadhar ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        
                        <div class="col-md-12 mt-2 mb-3"><hr></div>

                        <div class="col-md-6 mb-3"><label class="form-label">Customer Address</label><input type="text" class="form-control" value="{{ $otf->customer_address ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">City</label><input type="text" class="form-control" value="{{ $otf->customer_city ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Tehsil</label><input type="text" class="form-control" value="{{ $otf->customer_tehsil ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">District</label><input type="text" class="form-control" value="{{ $otf->customer_district ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                    </div>
                </div>
            </div>

            {{-- 3. VEHICLE DETAILS --}}
            <div class="card enquiry-card">
                <div class="card-header"><h4 class="mb-0 fw-bold">Vehicle Details</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">OEM Model Code</label><input type="text" class="form-control" value="{{ $otf->oem_code ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Segment</label><input type="text" class="form-control" value="{{ $vehicle['segment'] ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Model</label><input type="text" class="form-control" value="{{ $vehicle['model'] ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Variant</label><input type="text" class="form-control" value="{{ $vehicle['variant'] ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Color</label><input type="text" class="form-control" value="{{ $vehicle['color'] ?? '—' }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                    </div>
                </div>
            </div>

            {{-- 4. SALES CONSULTANT DETAILS --}}
            <div class="card enquiry-card">
                <div class="card-header"><h4 class="mb-0 fw-bold">Sales Consultant Details</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">SC Mile ID</label><input type="text" class="form-control" value="{{ $scMileIdStr }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">SC Name</label><input type="text" class="form-control" value="{{ $scDisplay }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">SC Branch</label><input type="text" class="form-control" value="{{ $scBranch }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">SC Location</label><input type="text" class="form-control" value="{{ $scLocation }}" readonly style="background-color: var(--tblr-bg-surface-secondary);"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection