@php
$isViewMode = request()->routeIs('finance.view');
@endphp
@extends(backpack_view('blank'))

@section('title', 'Payout Details')

@push('head')
<link rel="stylesheet" href="{{ asset('plugins/select2/dist/css/select2.min.css') }}">
<style>
    .readonly-field {
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
        color: #495057;
        pointer-events: none;
        height: 38px;
    }

    .photo-preview {
        margin-top: 8px;
    }

    .photo-preview img {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
    }

    /* Custom readonly style for frozen fields */
    .field-frozen {
        background-color: #f8f9fa !important;
        pointer-events: none !important;
        color: #6c757d !important;
    }
</style>
@endpush

@section('content')



<div class="row align-items-end">
    <div class="col-lg-10">
    </div>
    <div class="col-lg-2">
        {{-- <nav class="breadcrumb-container" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ backpack_url('dashboard') }}"><i class="ik ik-home"></i>
                        Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ backpack_url('finance') }}">Finance</a></li>
                <li class="breadcrumb-item active">
                    {{ $isViewMode ? 'Payout View' : 'Payout Edit' }}
                </li>
            </ol>
        </nav> --}}
    </div>
</div>


<div class="row">
    <div class="col-md-12">
        <div class="card">

            <h2 style="margin-top: 12px;margin-left: 15px;">Customer Details</h2>

            <div class="card-body">
                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>Customer Name</label>
                        <input type="text" class="form-control readonly-field" value="{{ $booking->name ?? 'N/A' }}"
                            readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Model</label>
                        <input type="text" class="form-control readonly-field"
                            value="{{ $booking->model_code ?? 'N/A' }}" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Variant</label>
                        <input type="text" class="form-control readonly-field"
                            value="{{ $booking->variant_code ?? 'N/A' }}" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Invoice Date</label>
                        <input type="text" class="form-control readonly-field"
                            value="{{ $booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('d-m-Y') : ($booking->dealer_inv_date ? \Carbon\Carbon::parse($booking->dealer_inv_date)->format('d-m-Y') : 'N/A') }}"
                            readonly>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-sm-3 form-group">
                        <label>Address</label>
                        <input type="text" class="form-control readonly-field" value="N/A" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>City</label>
                        <input type="text" class="form-control readonly-field" value="N/A" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Tehsil</label>
                        <input type="text" class="form-control readonly-field" value="N/A" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>District</label>
                        <input type="text" class="form-control readonly-field" value="N/A" readonly>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-sm-3 form-group">
                        <label>Pin Code</label>
                        <input type="text" class="form-control readonly-field" value="N/A" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">

    <h2 style="margin-top: 12px;margin-left: 15px;">Finance Details (Read-Only)</h2>

    <div class="card-body">
        <div class="row">
            @php
            $iTypes = [
            1 => 'Financier Payment',
            2 => 'Delivery Order',
            3 => 'Sanction Letter',
            4 => 'Mail Communication',
            5 => 'Whatsapp Communication'
            ];
            @endphp

            <div class="col-sm-3 form-group">
                <label>Finance Mode</label>
                <input type="text" class="form-control readonly-field" value="{{ $finance->fin_mode ?? 'N/A' }}"
                    readonly>
            </div>

            <div class="col-sm-3 form-group">
                <label>Loan Status</label>
                <input type="text" class="form-control readonly-field" value="{{ $finance->loan_status ?? 'N/A' }}"
                    readonly>
            </div>

            <div class="col-sm-3 form-group">
                <label>Financier</label>
                <input type="text" class="form-control readonly-field"
                    value="{{ collect($data['financiers'] ?? [])->firstWhere('id', $finance->financier)['name'] ?? 'N/A' }}"
                    readonly>
            </div>

            <div class="col-sm-3 form-group">
                <label>Short Name</label>
                <input type="text" class="form-control readonly-field"
                    value="{{ collect($data['financiers'] ?? [])->firstWhere('id', $finance->financier)['short_name'] ?? 'N/A' }}"
                    readonly>
            </div>



            <div class="col-sm-3 form-group">
                <label>Case Status</label>
                <input type="text" class="form-control readonly-field" value="{{
                                   $finance->case_status == 1 ? 'In-Process' :
                                   ($finance->case_status == 2 ? 'In House Finance Done' :
                                   ($finance->case_status == 3 ? 'Case Lost' : 'N/A'))
                               }}" readonly>
            </div>

            <div class="col-sm-3 form-group">
                <label>Verification Status</label>
                <input type="text" class="form-control readonly-field" value="{{
                                   ($finance->verification_status ?? 1) == 2 ? 'Verified (Match)' :
                                   (($finance->verification_status ?? 1) == 3 ? 'Verified (Mismatch)' : 'Not Verified')
                               }}" readonly>
            </div>

            <hr>
            <div class="col-sm-3 form-group">
                <label>Instrument Type</label>
                <input type="text" class="form-control readonly-field"
                    value="{{ $iTypes[$finance->instrument_type ?? ''] ?? 'N/A' }}" readonly>
            </div>

            @if($finance->instrument_type == 2 && $finance->delivery_order_no)
            <div class="col-sm-3 form-group">
                <label>Delivery Order No.</label>
                <input type="text" class="form-control readonly-field" value="{{ $finance->delivery_order_no }}"
                    readonly>
            </div>
            @endif

            @if($finance->instrument_type == 1 && $finance->receipt_no)
            <div class="col-sm-3 form-group">
                <label>Receipt No.</label>
                <input type="text" class="form-control readonly-field" value="{{ $finance->receipt_no }}" readonly>
            </div>
            @endif

            <div class="col-sm-3 form-group">
                <label>Loan Amount (Dealer Entry)</label>
                <input type="text" class="form-control readonly-field"
                    value="₹{{ number_format($finance->loan_amount ?? 0, 2) }}" readonly>
            </div>

            <div class="col-sm-3 form-group">
                <label>Margin Money</label>
                <input type="text" class="form-control readonly-field"
                    value="₹{{ number_format($finance->margin ?? 0, 2) }}" readonly>
            </div>

            <div class="col-sm-3 form-group">
                <label>File Charge</label>
                <input type="text" class="form-control readonly-field"
                    value="₹{{ number_format($finance->file_charge ?? 0, 2) }}" readonly>
            </div>

            <div class="col-sm-3 form-group">
                <label>Net Payment Amount</label>
                <input type="text" class="form-control readonly-field"
                    value="₹{{ number_format(($finance->loan_amount ?? 0) + ($finance->margin ?? 0) - ($finance->file_charge ?? 0), 2) }}"
                    readonly>
            </div>



            <div class="col-sm-3 form-group">
                <label>Instrument Proof</label>

                @php
                $media = $finance->getFirstMedia('instrument_proof');
                $url = $media ? $media->getUrl() : null;
                $fileName = $media ? basename($url) : null;
                @endphp

                @if ($media)

                <div class="mt-2">

                    <span
                        class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2 instrument-chip"
                        style="cursor:pointer" data-url="{{ $url }}" data-name="{{ $fileName }}">

                        <i class="la la-paperclip"></i>
                        <span class="fw-medium small">{{ $fileName }}</span>

                    </span>

                </div>

                @else
                <p class="text-muted">No attachment uploaded.</p>
                @endif
            </div>

            <div class="col-sm-12 form-group d-flex align-items-end justify-content-end">
                <a href="{{ route('finance.retailedit', $booking->id) }}?from=payout" class="btn btn-primary btn-sm"
                    title="Edit Finance Details">
                    <i class="ik ik-edit mr-1"></i> Edit Finance Details
                </a>
            </div>
        </div>
    </div>
</div>


<div class="col-md-12 mt-4">
    <div class="card">

        <h2 style="margin-top: 12px;margin-left: 15px;">{{ __('Payout Details') }}</h2>


        <div class="card-body">
            <form id="payoutForm" method="POST" action="{{ route('payout.update', $booking->id) }}"
                enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="booking_id" value="{{ $booking->id }}">

                {{-- ROW 1 --}}
                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>Payout Category <span class="text-danger">*</span></label>
                        <select name="payout_category" id="payout_category" class="form-control form-select" required>
                            <option value="1" {{ old('payout_category', $finance->payout_category ?? 1) == 1 ?
                                'selected' : '' }}>Payout</option>
                            <option value="2" {{ old('payout_category', $finance->payout_category ?? 1) == 2 ?
                                'selected' : '' }}>No Payout</option>
                            <option value="4" {{ old('payout_category', $finance->payout_category ?? 1) == 4 ?
                                'selected' : '' }}>Cash</option>
                        </select>
                    </div>

                    <div class="col-sm-3 form-group" id="no_payout_reason_wrapper" style="display:none;">
                        <label>No Payout Reason <span class="text-danger">*</span></label>
                        <select name="no_payout_reason" id="no_payout_reason" class="form-control form-select" required>
                            <option value="">-- Select Reason --</option>
                            <option value="1" {{ old('no_payout_reason', $finance->nopayout_reason ?? '') ==
                                '1' ? 'selected' : '' }}>
                                Low Interest Rate
                            </option>
                            <option value="2" {{ old('no_payout_reason', $finance->nopayout_reason ?? '') ==
                                '2' ? 'selected' : '' }}>
                                Low Tenure Funding
                            </option>
                            <option value="3" {{ old('no_payout_reason', $finance->nopayout_reason ?? '') ==
                                '3' ? 'selected' : '' }}>
                                Nil Payout Model
                            </option>
                            <option value="4" {{ old('no_payout_reason', $finance->nopayout_reason ?? '') ==
                                '4' ? 'selected' : '' }}>
                                Out Of Territory
                            </option>
                            <option value="5" {{ old('no_payout_reason', $finance->nopayout_reason ?? '') ==
                                '5' ? 'selected' : '' }}>
                                Financier Sourcing
                            </option>
                            <option value="6" {{ old('no_payout_reason', $finance->nopayout_reason ?? '') ==
                                '6' ? 'selected' : '' }}>
                                Other
                            </option>
                        </select>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Receipt Number <span class="text-danger">*</span></label>
                        <input type="text" name="do_number" id="do_number" class="form-control"
                            value="{{ old('do_number', $finance->instrument_ref_no ?? '') }}" placeholder="e.g. DO12345"
                            required>
                        <!-- HIDDEN FIELD TO SEND DIFFERENCE -->
                        <input type="hidden" name="difference_no_gst" id="hidden_difference_no_gst" value="">
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Loan Amount (Dealer Entry) <span class="text-danger">*</span></label>
                        <input type="number" name="loan_amount" id="loan_amount" class="form-control calc-input"
                            value="{{ old('loan_amount', $finance->loan_amount ?? 0) }}" step="1" min="0" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>Expected Payout % <span class="text-danger">*</span></label>
                        <input type="number" name="expected_payout_pct" id="exp_payout_pct"
                            class="form-control calc-input"
                            value="{{ old('expected_payout_pct', $finance->expected_payout_pct ?? '') }}" step="0.0001"
                            min="0" placeholder="e.g. 1.5" required>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>GST Rate</label>
                        <input type="text" id="gst_pct" class="form-control readonly-field " value="18%" readonly
                            style="font-weight: bold; background-color: #e9ecef !important; cursor: not-allowed;">

                    </div>

                    <div class="col-sm-3 form-group">
                        <label>GST Included in Payout<span class="text-danger">*</span></label>
                        <select name="gst_included" id="gst_included" class="form-control form-select calc-input"
                            required>
                            <option value="0" {{ old('gst_included', $finance->gst_included) == 0 ? 'selected'
                                : '' }}>0 %</option>
                            <option value="0.5" {{ old('gst_included', $finance->gst_included) == 0.5 ?
                                'selected' : '' }}>50 %</option>
                            <option value="1" {{ old('gst_included', $finance->gst_included) == 1 ? 'selected'
                                : '' }}>100 %</option>
                        </select>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Expected Payout % without GST</label>
                        <input type="text" id="exp_payout_pct_no_gst" class="form-control readonly-field" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>Expected Payout Amount without GST</label>
                        <input type="text" id="exp_payout_amt_no_gst" class="form-control readonly-field" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>GST Amount</label>
                        <input type="text" id="gst_amount" class="form-control readonly-field" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Suggested Invoice Amount</label>
                        <input type="text" id="suggested_invoice_amt" class="form-control readonly-field" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Loan Amount (Fin Payout Sheet)</label>
                        <input type="text" class="form-control readonly-field"
                            value="₹{{ number_format($finance->fin_loan_amount ?? 0, 2) }}" readonly>
                    </div>
                </div>

                <hr>

                <h6 class="mt-3 mb-2">1<sup>st</sup> Invoice</h6>
                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>1<sup>st</sup> Invoice No. <span class="text-danger">*</span></label>
                        <input type="text" name="inv1_no" id="inv1_no" class="form-control"
                            value="{{ old('inv1_no', $finance->inv1_no ?? '') }}" required>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>1<sup>st</sup> Invoice in Name of <span class="text-danger">*</span></label>
                        <input type="text" name="inv1_name" id="inv1_name" class="form-control"
                            value="{{ old('inv1_name', $finance->inv1_name ?? '') }}" required>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>1<sup>st</sup> Financier Provisioning <small>with GST</small> <span
                                class="text-danger">*</span></label>
                        <input type="number" name="inv1_prov_gst" id="inv1_prov_gst" class="form-control calc-input"
                            step="0.01" min="0" value="{{ old('inv1_prov_gst', $finance->inv1_prov_gst ?? '') }}"
                            placeholder="0.00" required>
                    </div>
                </div>

                <h6 class="mt-4 mb-2">2<sup>nd</sup> Invoice</h6>
                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>2<sup>nd</sup> Invoice No. </label>
                        <input type="text" name="inv2_no" id="inv2_no" class="form-control"
                            value="{{ old('inv2_no', $finance->inv2_no ?? '') }}">
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>2<sup>nd</sup> Invoice in Name of </label>
                        <input type="text" name="inv2_name" id="inv2_name" class="form-control"
                            value="{{ old('inv2_name', $finance->inv2_name ?? '') }}">
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>2<sup>nd</sup> Financier Provisioning <small>with GST</small></label>
                        <input type="number" name="inv2_prov_gst" id="inv2_prov_gst" class="form-control calc-input"
                            step="0.01" min="0" value="{{ old('inv2_prov_gst', $finance->inv2_prov_gst ?? '') }}"
                            placeholder="0.00">
                    </div>
                </div>

                <hr>
                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>Total Provisioning (with GST)</label>
                        <input type="text" id="total_prov_gst" class="form-control readonly-field" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Total Provisioning (without GST)</label>
                        <input type="text" id="total_prov_no_gst" class="form-control readonly-field" readonly>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Consideration (without GST) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₹</span>
                            </div>
                            <input type="number" name="consideration_no_gst" id="consideration_no_gst"
                                class="form-control calc-input text-right" step="0.01" min="0"
                                value="{{ old('consideration_no_gst', $finance->consideration_no_gst ?? '0.00') }}"
                                placeholder="0.00" required>
                        </div>
                        <small class="form-text text-muted">Enter amount in rupees (up to 2 decimals
                            allowed)</small>
                    </div>

                    <div class="col-sm-3 form-group">
                        <label>Difference (without GST)</label>
                        <input type="text" id="difference_no_gst" class="form-control readonly-field" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>Provisioning % (without GST)</label>
                        <input type="text" id="prov_pct_no_gst" class="form-control readonly-field" readonly>
                    </div>
                </div>

                <!-- REMARKS (Always visible & required) -->
                <div class="col-sm-12 form-group mt-3">
                    <label>Remarks <span class="text-danger">*</span></label>
                    <textarea name="payout_remarks" id="payout_remarks" class="form-control" rows="3" required
                        placeholder="Enter payout remarks...">{{ old('payout_remarks', $finance->payout_remarks ?? '') }}</textarea>
                </div>

                <div class="col-sm-12 text-right mt-3">
                    @if(!$isViewMode)
                    <button type="submit" class="btn btn-success">
                        Save Payout
                    </button>
                    @endif
                </div>
            </form>
        </div>
    </div>



    <div class="card mt-4">
        <div class="card-body">
            @include('partials.entity-history', [
            'threads' => $bookingHistory ?? collect()
            ])
        </div>
    </div>
</div>


</div>
<div class="modal fade" id="attachmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="modalFileName">File Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center">
                <iframe id="modalFilePreview" style="width:100%; height:70vh;" frameborder="0">
                </iframe>
            </div>

            <div class="modal-footer">
                <a id="modalDownload" class="btn btn-success" download>
                    <i class="la la-download"></i> Download
                </a>

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

@endsection

@push('after_scripts')
<script>
    const GST_RATE = 0.18;
    const CURRENCY = '₹';
    const $ = (id) => document.getElementById(id);

    const el = {
        loan_amount:           $('loan_amount'),
        exp_payout_pct:        $('exp_payout_pct'),
        gst_included:          $('gst_included'),
        exp_payout_pct_no_gst: $('exp_payout_pct_no_gst'),
        exp_payout_amt_no_gst: $('exp_payout_amt_no_gst'),
        gst_amount:            $('gst_amount'),
        suggested_invoice_amt: $('suggested_invoice_amt'),
        inv1_prov_gst:         $('inv1_prov_gst'),
        inv2_prov_gst:         $('inv2_prov_gst'),
        total_prov_gst:        $('total_prov_gst'),
        total_prov_no_gst:     $('total_prov_no_gst'),
        consideration_no_gst:  $('consideration_no_gst'),
        difference_no_gst:     $('difference_no_gst'),
        prov_pct_no_gst:       $('prov_pct_no_gst'),
    };

    const fmtSpecial = (num) => (num == null || num === '' || num == 0) ? '' : num;

    const fmtCurrency = (num) => {
    if (isNaN(num)) return `${CURRENCY}0.00`;
    const abs = Math.abs(num);
    const formatted = `${CURRENCY}` + abs.toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
    });
    return num < 0 ? `-${formatted}` : formatted; };
    const fmtPercent = (num) => isNaN(num) ? '0.0000 %' : (num * 100).toFixed(4) + ' %';

    const setDisplay = (elem, value, isCurrency = true) => {
        if (!elem) return;
        elem.value = isCurrency ? fmtCurrency(value) : fmtPercent(value);
    };


    function calculateLive() {
    const C = parseFloat(el.loan_amount?.value) || 0;
    const D_percent = parseFloat(el.exp_payout_pct?.value) || 0;
    const D = D_percent / 100;
    const F = parseFloat(el.gst_included?.value) || 0;

    const G = D / (1 + GST_RATE * F);
    setDisplay(el.exp_payout_pct_no_gst, G, false);

    const H = C * G;
    setDisplay(el.exp_payout_amt_no_gst, H);

    const I = H * GST_RATE;
    setDisplay(el.gst_amount, I);

    const J = H + I;
    setDisplay(el.suggested_invoice_amt, J);

    const M = parseFloat(el.inv1_prov_gst?.value) || 0;
    const P = parseFloat(el.inv2_prov_gst?.value) || 0;
    const Q = M + P;
    setDisplay(el.total_prov_gst, Q);

    const R = Q / (1 + GST_RATE);
    setDisplay(el.total_prov_no_gst, R);

    // Consideration (without GST)
    const S = parseFloat(el.consideration_no_gst?.value) || 0;

    const T = R - H + S;

    const diffField = el.difference_no_gst;
    if (diffField) {
    const formatted = fmtCurrency(T);
    diffField.value = formatted;
    diffField.style.color = T < 0 ? 'red' : 'inherit' ; diffField.style.fontWeight=T < 0 ? 'bold' : 'normal' ; }
    const U=C> 0 ? (R / C) : 0;
    setDisplay(el.prov_pct_no_gst, U, false);
    const hiddenDiff = document.getElementById('hidden_difference_no_gst');
    if (hiddenDiff) {
    hiddenDiff.value = T.toFixed(2); // e.g., -1500.00
    }
    el.inv1_prov_gst.value = fmtSpecial(M);
    el.inv2_prov_gst.value = fmtSpecial(P);
    }

    const triggerCalc = () => setTimeout(calculateLive, 50);
    document.querySelectorAll('.calc-input').forEach(input => {
        input.addEventListener('input', triggerCalc);
        input.addEventListener('change', triggerCalc);
    });

    const payoutCat = $('payout_category');
    const reasonWrapper = $('no_payout_reason_wrapper');
    const reasonField = $('no_payout_reason');
    const form = $('payoutForm');
    const submitBtn = form.querySelector('button[type="submit"]');

    function togglePayoutMode() {
        const val = payoutCat.value;

        if (val === '2') {
            reasonWrapper.style.display = 'block';
            reasonField.required = true;
        } else {
            reasonWrapper.style.display = 'none';
            reasonField.required = false;
        }

        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(el => {
            const isAllowed = el.id === 'payout_category' ||
                             el.id === 'no_payout_reason' ||
                             el.id === 'payout_remarks';

            if (val === '1') {
                el.disabled = false;
                el.readOnly = false;
                el.classList.remove('field-frozen');
            } else {
                el.disabled = false;  // Allow value to be sent
                el.readOnly = !isAllowed;
                if (!isAllowed) {
                    el.classList.add('field-frozen');
                } else {
                    el.classList.remove('field-frozen');
                }
            }
        });

        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
    }

    payoutCat.addEventListener('change', togglePayoutMode);
    document.addEventListener('DOMContentLoaded', () => {
        calculateLive();
        togglePayoutMode();
    });
    document.addEventListener('click', function (e) {

    const chip = e.target.closest('.instrument-chip');
    if (!chip) return;

    const url = chip.getAttribute('data-url');
    const name = chip.getAttribute('data-name');

    document.getElementById('modalFileName').innerText = name;
    document.getElementById('modalDownload').setAttribute('href', url);
    document.getElementById('modalFilePreview').setAttribute('src', url);

    const modal = new bootstrap.Modal(document.getElementById('attachmentModal'));
    modal.show();
    @if($isViewMode)
<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll(
        '#payoutForm input, #payoutForm select, #payoutForm textarea'
    ).forEach(function(el){

        if(el.type !== 'hidden'){
            el.disabled = true;
            el.readOnly = true;
            el.classList.add('field-frozen');
        }

    });

});
</script>
@endif
});
</script>
@endpush