@extends(backpack_view('blank'))

@section('header')
<div class="container-fluid">

</div>
@endsection

@section('content')
<div class="container-fluid">
    @include(backpack_view('inc.alerts'))

    <div class="text-center my-4">
        <button class="btn btn-success btn-sm me-2" id="expandAll">
            <i class="la la-expand-arrows-alt"></i> Expand All
        </button>
        <button class="btn btn-danger btn-sm" id="collapseAll">
            <i class="la la-compress-arrows-alt"></i> Collapse All
        </button>
    </div>

    <div class="row">
        <div class="col-12">

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Payment Details</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold  ">Customer Type</label>
                            <input type="text" class="form-control" value="{{ $booking->b_type ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Customer Category</label>
                            <input type="text" class="form-control" value="{{ $booking->b_cat ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Booking Date</label>
                            <input type="text" class="form-control"
                                value="{{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d-M-Y') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Collection Type</label>
                            <input type="text" class="form-control" value="{{ match((int)($booking->col_type ?? 0)) {
                                       1 => 'Receipt',
                                       2 => 'Field Collection (By Sales Team)',
                                       3 => 'Field Collection (By DSA)',
                                       4 => 'Used Car Purchased',
                                       default => 'N/A'
                                   } }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Collected By</label>
                            <input type="text" class="form-control" value="{{ $data['collector_name'] ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">{{ $booking->col_type == 4 ? 'Received Amount' : 'Booking
                                Amount' }}</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format($booking->booking_amount ?? 0) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">{{ $booking->col_type == 4 ? 'Voucher No.' : 'Receipt No.'
                                }}</label>
                            <input type="text" class="form-control" value="{{ $booking->receipt_no ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">{{ $booking->col_type == 4 ? 'Voucher Date' : 'Receipt Date'
                                }}</label>
                            <input type="text" class="form-control"
                                value="{{ $booking->receipt_date ? \Carbon\Carbon::parse($booking->receipt_date)->format('d-M-Y') : 'N/A' }}"
                                readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Customer Details</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold  ">{{ $booking->b_cat != 'Firm' ? 'Customer Name' : 'Firm Name'
                                }}</label>
                            <input type="text" class="form-control" value="{{ $booking->name ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Care Of</label>
                            <input type="text" class="form-control" value="{{ match((int)($booking->care_of_type ?? 0)) {
                                       1 => 'Son of',
                                       2 => 'Daughter of',
                                       3 => 'Married',
                                       4 => 'Guardian Name',
                                       5 => 'Owned By',
                                       default => 'N/A'
                                   } }} : {{ $booking->care_of ?? '' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Mobile</label>
                            <input type="text" class="form-control"
                                value="{{ (auth()->id() == $booking->created_by || auth()->user()?->hasRole('Super Admin')) ? $booking->mobile : '*********' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Alternate Mobile</label>
                            <input type="text" class="form-control"
                                value="{{ (auth()->id() == $booking->created_by || auth()->user()?->hasRole('Super Admin')) ? ($booking->alt_mobile ?? 'N/A') : '*********' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Gender</label>
                            <input type="text" class="form-control" value="{{ $booking->gender ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Occupation</label>
                            <input type="text" class="form-control" value="{{ $booking->occ ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">PAN No.</label>
                            <input type="text" class="form-control" value="{{ $booking->pan_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Aadhaar No.</label>
                            <input type="text" class="form-control" value="{{ $booking->adhar_no ?: 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Branch</label>
                            <input type="text" class="form-control" value="{{ $data['branch'] ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Location</label>
                            <input type="text" class="form-control" value="{{ $data['location'] ?? 'N/A' }}" readonly>
                        </div>
                    </div>

                    <h3 class="mt-4 mb-3">Referred By Details</h3>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class=" ">Referred By (Customer Name)</label>
                            <input type="text" class="form-control" value="{{ $booking->r_name ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">Mobile No.</label>
                            <input type="text" class="form-control" value="{{ $booking->r_mobile ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">Existing Model</label>
                            <input type="text" class="form-control" value="{{ $booking->r_model ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">Variant</label>
                            <input type="text" class="form-control" value="{{ $booking->r_variant ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">Chassis / Regn. No.</label>
                            <input type="text" class="form-control" value="{{ $booking->r_chassis ?? 'N/A' }}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Purchase Type Details</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold  ">Purchase Type</label>
                            <input type="text" class="form-control" value="{{ $booking->buyer_type ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Brand (Make 1)</label>
                            <input type="text" class="form-control" value="{{ $data['make1'] ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Model & Variant 1</label>
                            <input type="text" class="form-control" value="{{ $booking->vh1_detail ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Brand (Make 2)</label>
                            <input type="text" class="form-control" value="{{ $data['make2'] ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Model & Variant 2</label>
                            <input type="text" class="form-control" value="{{ $booking->vh2_detail ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold  ">Vehicle Registration No.</label>
                            <input type="text" class="form-control" value="{{ $booking->registration_no ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold  ">Manufacturing Year</label>
                            <input type="text" class="form-control" value="{{ $booking->make_year ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold  ">Odometer Reading</label>
                            <input type="text" class="form-control" value="{{ $booking->odo_reading ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Used Vehicle Expected Price</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format($booking->expected_price ?? 0) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Used Vehicle Offered Price</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format($booking->offered_price ?? 0) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">New Vehicle Exchange Bonus</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format($booking->exchange_bonus ?? 0) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Price Gap</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format(($booking->expected_price ?? 0) - (($booking->offered_price ?? 0) + ($booking->exchange_bonus ?? 0))) }}"
                                readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Vehicle Details</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold  ">Segment</label>
                            <input type="text" class="form-control" value="{{ $booking->segment_name ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Model</label>
                            <input type="text" class="form-control" value="{{ $booking->model_code ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Variant</label>
                            <input type="text" class="form-control" value="{{ $booking->variant_code ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Color</label>
                            <input type="text" class="form-control" value="{{ $booking->color_code ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Seating</label>
                            <input type="text" class="form-control" value="{{ $booking->seating ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Accessories</label>
                            <textarea class="form-control" rows="2"
                                readonly>{{ $booking->accessories ? str_replace(',', ', ', $booking->accessories) : 'N/A' }}</textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Accessories Pack Amount</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format($booking->apack_amount ?? 0) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Allotted Chassis Number</label>
                            <input type="text" class="form-control" value="{{ $data['bchasis'] ?? 'N/A' }}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Booking Type & Source</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold  ">Booking Mode</label>
                            <input type="text" class="form-control" value="{{ $booking->b_mode ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Online Book Ref No.</label>
                            <input type="text" class="form-control" value="{{ $booking->online_bk_ref_no ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold  ">Sales Consultant</label>
                            <input type="text" class="form-control"
                                value="{{ collect($data['saleconsultants'] ?? [])->firstWhere('id', $booking->consultant)['name'] ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Booking Source</label>
                            <input type="text" class="form-control" value="{{ $booking->b_source ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Selected DSA</label>
                            <input type="text" class="form-control" value="{{ $dsaname ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Delivery Date Type</label>
                            <input type="text" class="form-control" value="{{ $booking->del_type ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Expected Delivery Date</label>
                            <input type="text" class="form-control"
                                value="{{ $booking->del_date ? \Carbon\Carbon::parse($booking->del_date)->format('d-M-Y') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">CPD Date</label>
                            <input type="text" class="form-control" value="{{ $booking->cpd ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Finance Mode</label>
                            <input type="text" class="form-control" value="{{ $booking->fin_mode ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Financier</label>
                            <input type="text" class="form-control" value="{{ $booking->financier ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Loan File Status</label>
                            <input type="text" class="form-control" value="{{ $booking->loan_status ?? 'N/A' }}"
                                readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">OEM Booking & Invoice Info</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold  ">OEM Invoice Number</label>
                            <input type="text" class="form-control"
                                value="{{ !empty(trim($booking->inv_no ?? '')) ? $booking->inv_no : 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">OEM Invoice Date</label>
                            <input type="text" class="form-control"
                                value="{{ $booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('d-M-Y') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Dealer Invoice Number</label>
                            <input type="text" class="form-control" value="{{ $booking->dealer_inv_no ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Dealer Invoice Date</label>
                            <input type="text" class="form-control"
                                value="{{ $booking->dealer_inv_date ? \Carbon\Carbon::parse($booking->dealer_inv_date)->format('d-M-Y') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">SAP Booking No.</label>
                            <input type="text" class="form-control" value="{{ $booking->sap_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Sales Force Booking Number (DMS No.)</label>
                            <input type="text" class="form-control" value="{{ $booking->dms_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">DMS OTF No.</label>
                            <input type="text" class="form-control" value="{{ $booking->dms_otf ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">DMS OTF Date</label>
                            <input type="text" class="form-control"
                                value="{{ $booking->otf_date ? \Carbon\Carbon::parse($booking->otf_date)->format('d-M-Y') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">DMS SO No.</label>
                            <input type="text" class="form-control" value="{{ $booking->dms_so ?? 'N/A' }}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Insurance Data</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold  ">Insurance Source</label>
                            <input type="text" class="form-control"
                                value="{{ $insurance && $insurance->source ? collect(['1' => 'By Dealer (OEM Portal)', '2' => 'By Dealer (Agency)', '3' => 'By Owner (Self)'])->get($insurance->source, 'N/A') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold  ">Insurance Company</label>
                            <input type="text" class="form-control"
                                value="{{ $insurance && $insurance->insurer ? collect($data['insurances'] ?? [])->firstWhere('id', $insurance->insurer)['name'] ?? 'N/A' : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Insurance Short Name</label>
                            <input type="text" class="form-control"
                                value="{{ $insurance && $insurance->insurer ? collect($data['insurances'] ?? [])->firstWhere('id', $insurance->insurer)['short_name'] ?? 'N/A' : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Policy No.</label>
                            <input type="text" class="form-control" value="{{ $insurance?->pol_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Policy Date</label>
                            <input type="text" class="form-control"
                                value="{{ $insurance?->pol_date ? \Carbon\Carbon::parse($insurance->pol_date)->format('d-M-Y') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Policy Type</label>
                            <input type="text" class="form-control" value="{{ match((int)($insurance?->pol_type ?? 0)) {
                                       1 => 'Standard',
                                       2 => 'Nil Dep',
                                       3 => 'Base(Nil Dep + Consumables)',
                                       4 => 'Higher(Nil Dep + Consumables + Add Ons)',
                                       default => 'N/A'
                                   } }}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">RTO Data</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold  ">Trade Used</label>
                            <input type="text" class="form-control"
                                value="{{ $rto ? ($data['trade_used_map'][$rto->trade_used ?? ''] ?? 'N/A') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Sale Type</label>
                            <input type="text" class="form-control"
                                value="{{ $rto ? ($data['sale_type_map'][$rto->sale_type ?? ''] ?? 'N/A') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Permit</label>
                            <input type="text" class="form-control"
                                value="{{ $rto ? ($data['permit_map'][$rto->permit ?? ''] ?? 'N/A') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Body Type</label>
                            <input type="text" class="form-control"
                                value="{{ $rto ? ($data['body_type_map'][$rto->body_type ?? ''] ?? 'N/A') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Registration Type</label>
                            <input type="text" class="form-control"
                                value="{{ $rto ? ($data['registration_type_map'][$rto->rgn_type ?? ''] ?? 'N/A') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Registration No. Type</label>
                            <input type="text" class="form-control"
                                value="{{ $rto ? ($data['reg_no_type_map'][$rto->rgn_no_type ?? ''] ?? 'N/A') : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">TRC Number</label>
                            <input type="text" class="form-control" value="{{ $rto?->trc_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">TRC Payment Bank Ref No.</label>
                            <input type="text" class="form-control" value="{{ $rto?->trc_payment_no ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Application No.</label>
                            <input type="text" class="form-control" value="{{ $rto?->app_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Tax Payment Bank Ref No.</label>
                            <input type="text" class="form-control"
                                value="{{ $rto?->tax_payment_bank_ref_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Vehicle Registration No.</label>
                            <input type="text" class="form-control" value="{{ $rto?->vh_rgn_no ?? 'N/A' }}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Financier Payment / Delivery Order Details</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="fw-bold  ">Customer Name</label>
                            <input type="text" class="form-control" value="{{ $booking->name ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold  ">Fin Mode (Primary)</label>
                            <input type="text" class="form-control" value="{{ $booking->fin_mode ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold  ">Financier</label>
                            <input type="text" class="form-control"
                                value="{{ collect($data['financiers'] ?? [])->firstWhere('id', $booking->financier)['name'] ?? 'N/A' }}"
                                readonly>
                        </div>
                    </div>

                    @if($finance)
                    <h6 class="mt-4 mb-3">Processed Finance Details</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class=" ">Finance Mode</label>
                            <input type="text" class="form-control" value="{{ $finance->fin_mode ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class=" ">Financier</label>
                            <input type="text" class="form-control"
                                value="{{ $finance->financier ? collect($data['financiers'] ?? [])->firstWhere('id', $finance->financier)['name'] ?? 'N/A' : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-2">
                            <label class=" ">Short Name</label>
                            <input type="text" class="form-control"
                                value="{{ $finance->financier ? collect($data['financiers'] ?? [])->firstWhere('id', $finance->financier)['short_name'] ?? 'N/A' : 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">Loan Status</label>
                            <input type="text" class="form-control" value="{{ $finance->loan_status ?? 'N/A' }}"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">Loan Amount</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format($finance->loan_amount ?? 0) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">Margin Money</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format($finance->margin ?? 0) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">File Charge</label>
                            <input type="text" class="form-control  "
                                value="₹ {{ number_format($finance->file_charge ?? 0) }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class=" ">Net Payment Amount</label>
                            <input type="text" class="form-control   fw-bold"
                                value="₹ {{ number_format(($finance->loan_amount ?? 0) + ($finance->margin ?? 0) - ($finance->file_charge ?? 0)) }}"
                                readonly>
                        </div>
                        <div class="col-md-4">
                            <label class=" ">Remarks</label>
                            <textarea class="form-control" rows="2" readonly>{{ $finance->remark ?? 'N/A' }}</textarea>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Delivery Photographs</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    @if($delivery)
                    <div class="row g-3">
                        @php
                        $photos = [
                        'delivery_ceremony_with_customer' => 'Delivery Ceremony (With Customer)',
                        'bonnet' => 'Bonnet',
                        'windshield_glass' => 'Windshield Glass',
                        'vehicle_driver_side' => 'Vehicle (Driver Side)',
                        'vehicle_co_driver_side' => 'Vehicle (Co Driver Side)',
                        'vehicle_rear_side' => 'Vehicle (Rear Side)',
                        'tire_front_driver_side' => 'Tyre (Front Driver Side)',
                        'tire_front_co_driver_side' => 'Tyre (Front Co Driver Side)',
                        'tire_rear_driver_side' => 'Tyre (Rear Driver Side)',
                        'tire_rear_co_driver_side' => 'Tyre (Rear Co Driver Side)',
                        'stepney' => 'Stepney',
                        'foot_rest_driver_side' => 'Foot Rest (Driver Side)',
                        'foot_rest_co_driver_side' => 'Foot Rest (Co Driver Side)',
                        'tool_kit' => 'Tool Kit',
                        'vehicle_chassis_no_photo' => 'Vehicle Chassis No.',
                        'chassis_no_screenshot_invoice' => 'Chassis No. Screenshot (Invoice)',
                        'chassis_no_screenshot_insurance' => 'Chassis No. Screenshot (Insurance)',
                        ];
                        @endphp

                        @foreach($photos as $key => $label)
                        <div class="col-md-3 text-center mb-3">
                            <strong class="d-block mb-1  ">{{ $label }}</strong>
                            @if($media = $delivery->getFirstMedia($key))
                            <a href="{{ $media->getUrl() }}" target="_blank">
                                <img src="{{ $media->getUrl('thumb100') }}" alt="{{ $label }}"
                                    style="max-width:160px; max-height:120px; object-fit:cover; border:1px solid #ccc;">
                            </a>
                            @else
                            <div class="bg-light p-3 border">No photo</div>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <label class="fw-bold  ">Chassis No. Verified</label>
                        <div>
                            <input type="checkbox" disabled {{ $delivery->verification == 1 ? 'checked' : '' }}>
                            <label>{{ $delivery->verification == 1 ? 'Verified' : 'Not Verified' }}</label>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="fw-bold  ">Remarks</label>
                        <textarea class="form-control" rows="3" readonly>{{ $delivery->remarks ?? 'N/A' }}</textarea>
                    </div>
                    @else
                    <p class="text-muted text-center">No delivery details available.</p>
                    @endif
                </div>
            </div>


            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Booking Journey</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>

                <div class="card-body">

                    @forelse($bookingHistory as $history)

                    <div class="border rounded p-3 mb-3">

                        {{-- Top Row --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>
                                    {{ $history->title ?? 'Activity' }}
                                </strong>
                            </div>

                            <small class="text-muted">
                                {{ $history->created_at?->format('d M Y h:i A') }}
                            </small>
                        </div>

                        {{-- Message --}}
                        <div class="mb-2">
                            {{ $history->body ?? '-' }}
                        </div>

                        {{-- User --}}
                        <div class="text-muted small">
                            Done By:
                            <strong>
                                {{ $history->actor->username ?? $history->actor->name ?? 'System' }}
                            </strong>
                        </div>

                        {{-- Media --}}
                        @if($history->media && $history->media->count())

                        <div class="mt-3">
                            @foreach($history->media as $media)

                            <a href="{{ $media->getUrl() }}" target="_blank"
                                class="btn btn-sm btn-outline-primary me-2 mb-2">

                                View Attachment

                            </a>

                            @endforeach
                        </div>

                        @endif

                    </div>

                    @empty

                    <div class="text-center text-muted py-4">
                        No booking history found
                    </div>

                    @endforelse

                </div>
            </div>

            <!-- 12. Add Remarks -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-blue text-white position-relative">
                    <h4 class="mb-0">Add Remarks</h4>
                    <span class="toggle" onclick="toggleCard(this)">−</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('booking/followup') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="id" value="{{ $booking->id }}">
                        <div class="row g-3">
                            <div class="col-md-9">
                                <label class="fw-bold  ">Remarks <span class="text-danger">*</span></label>
                                <input type="text" name="remark" class="form-control" required>
                            </div>

                            <div class="col-md-3">
                                <label class="fw-bold">Attachment</label>

                                <input type="file" name="fdoc" id="attachmentInput" accept="image/*,.pdf"
                                    class="form-control" onchange="handleAttachment(this)">

                                <div id="attachmentPreview" class="mt-3"></div>
                            </div>

                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-success">Add Remarks</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-5">
                <a href="{{ backpack_url('booking/invoiced') }}" class="btn btn-secondary btn-lg px-5">
                    <i class="la la-arrow-left me-2"></i> Back to Invoiced Bookings
                </a>
            </div>

        </div>
    </div>
</div>
<div class="modal fade" id="attachmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="modalFileName"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center">
                <iframe id="modalFilePreview" style="width:100%; height:500px;" frameborder="0">
                </iframe>
            </div>

            <div class="modal-footer">
                <a id="modalDownload" class="btn btn-success" download>
                    Download
                </a>

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>


@push('after_scripts')
<script>
    function handleAttachment(input) {

        const previewDiv = document.getElementById('attachmentPreview');
        previewDiv.innerHTML = '';

        if (input.files && input.files[0]) {

            const file = input.files[0];
            const fileURL = URL.createObjectURL(file);

            previewDiv.innerHTML = `
                <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                    style="cursor:pointer"
                    onclick="openAttachmentModal('${fileURL}', '${file.name.replace(/'/g,"\\'")}')">

                    <i class="la la-paperclip"></i>

                    <span class="fw-medium small">
                        ${file.name.length > 28 ? file.name.substring(0,25)+'...' : file.name}
                    </span>

                </span>
            `;
        }
    }

function openAttachmentModal(url, name) {

    document.getElementById('modalFileName').innerText = name;
    document.getElementById('modalDownload').href = url;
    document.getElementById('modalFilePreview').src = url;

    const modal = new bootstrap.Modal(
        document.getElementById('attachmentModal')
    );

    modal.show();
}







    function toggleCard(el) {
    const card = el.closest('.card');
    card.classList.toggle('collapsed');
    el.textContent = card.classList.contains('collapsed') ? '+' : '−';
}

document.getElementById('expandAll')?.addEventListener('click', () => {
    document.querySelectorAll('.card').forEach(card => card.classList.remove('collapsed'));
    document.querySelectorAll('.toggle').forEach(t => t.textContent = '−');
});

document.getElementById('collapseAll')?.addEventListener('click', () => {
    document.querySelectorAll('.card').forEach(card => card.classList.add('collapsed'));
    document.querySelectorAll('.toggle').forEach(t => t.textContent = '+');
});

document.querySelectorAll('.card:not(:first-child)').forEach(card => card.classList.add('collapsed'));
</script>

<style>
    .card.collapsed .card-body {
        display: none;
    }

    .toggle {
        position: absolute;
        top: 9px;
        right: 15px;
        width: 32px;
        height: 32px;
        background: rgba(255, 255, 255, 0.25);
        color: white;
        border-radius: 50%;
        text-align: center;
        line-height: 28px;
        font-size: 1.4rem;
        cursor: pointer;
    }

    .toggle:hover {
        background: rgba(255, 255, 255, 0.4);
    }

    .card-header {
        position: relative;
        padding-right: 60px !important;
    }

    .modal-backdrop {
        display: none !important;
    }
</style>
@endpush
@endsection