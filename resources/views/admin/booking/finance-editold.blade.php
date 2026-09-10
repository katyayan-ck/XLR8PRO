@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h2>
        <i class="la la-money-check text-primary"></i>
        Edit Finance Details
        <small class="d-none d-md-inline">Update Finance & Loan Information</small>
    </h2>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap">
                <h3 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Finance Details Edit
                </h3>
            </div>

            <div class="card-body">

                <div class="card bg-light border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Booking & Customer Information (Read-only)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-3">
                                <label class="form-label">Customer Name</label>
                                <input type="text" class="form-control" value="{{ $booking->name ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">Model / Variant</label>
                                <input type="text" class="form-control"
                                    value="{{ $booking->model_code ?? 'N/A' }} / {{ $booking->variant_code ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">Finance Mode (Primary)</label>
                                <input type="text" class="form-control" value="{{ $booking->fin_mode ?? 'N/A' }}"
                                    readonly>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">Financier (Primary)</label>
                                <input type="text" class="form-control"
                                    value="{{ collect($data['financiers'] ?? [])->firstWhere('id', $booking->financier)['name'] ?? 'N/A' }}"
                                    readonly>
                            </div>

                        </div>
                    </div>
                </div>

                <form id="financeForm" method="POST" action="{{ route('finance.update', $booking->id) }}"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Finance & Loan Details</h5>
                        </div>

                        <div class="card-body">

                            <div class="row g-3">

                                <div class="col-sm-3">
                                    <label class="form-label">Finance Mode <span class="text-danger">*</span></label>
                                    <select name="fin_mode" id="fin_mode" class="form-control select2" required>
                                        <option value="">-- Select --</option>
                                        <option value="In-house" {{ old('fin_mode', $finance->fin_mode ?? '') ==
                                            'In-house' ? 'selected' : '' }}>In-house</option>
                                        <option value="Cash" {{ old('fin_mode', $finance->fin_mode ?? '') == 'Cash' ?
                                            'selected' : '' }}>Cash</option>
                                        <option value="Customer Self" {{ old('fin_mode', $finance->fin_mode ?? '') ==
                                            'Customer Self' ? 'selected' : '' }}>Customer Self</option>
                                        <option value="Yet To Decide" {{ old('fin_mode', $finance->fin_mode ?? '') ==
                                            'Yet To Decide' ? 'selected' : '' }}>Yet To Decide</option>
                                        <option value="Purchase Plan Cancelled" {{ old('fin_mode', $finance->fin_mode ??
                                            '') == 'Purchase Plan Cancelled' ? 'selected' : '' }}>Purchase Plan
                                            Cancelled</option>
                                    </select>
                                    @error('fin_mode') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-sm-3">
                                    <label class="form-label">Loan Status <span class="text-danger">*</span></label>
                                    <select name="loan_status" id="loan_status_box" class="form-control" required>
                                        <option value="Pending" {{ old('loan_status', $finance->loan_status ?? '') ==
                                            'Pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="Complete" {{ old('loan_status', $finance->loan_status ?? '') ==
                                            'Complete' ? 'selected' : '' }}>Complete</option>
                                    </select>
                                    @error('loan_status') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-sm-3" id="financier_wrapper">
                                    <label class="form-label">Financier <span class="text-danger">*</span></label>
                                    <select name="financier" id="financier_select" class="form-control select2">
                                        <option value="">-- Select Financier --</option>
                                        @foreach($data['financiers'] ?? [] as $fin)
                                        <option value="{{ $fin['id'] }}" data-short="{{ $fin['short_name'] ?? '' }}" {{
                                            old('financier', $finance->financier ?? '') == $fin['id'] ? 'selected' : ''
                                            }}>
                                            {{ $fin['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('financier') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-sm-3" id="financier_short_wrapper">
                                    <label class="form-label">Short Name</label>
                                    <input type="text" class="form-control" id="financier_short_name" readonly
                                        value="{{ collect($data['financiers'] ?? [])->firstWhere('id', $finance->financier)['short_name'] ?? '' }}">
                                </div>

                                <div class="col-sm-3">
                                    <label class="form-label">Case Status <span class="text-danger">*</span></label>
                                    <select name="case_status" id="case_status" class="form-control" required>
                                        <option value="1" {{ old('case_status', $finance->case_status ?? 1) == 1 ?
                                            'selected' : '' }}>In-Process</option>
                                        <option value="2" {{ old('case_status', $finance->case_status ?? 1) == 2 ?
                                            'selected' : '' }}>In House Finance Done</option>
                                        <option value="3" id="case_lost_option" {{ old('case_status', $finance->
                                            case_status ?? 1) == 3 ? 'selected' : '' }}>Case Lost</option>
                                    </select>
                                    @error('case_status') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-sm-3">
                                    <label class="form-label">Verification Status</label>
                                    <input type="text" id="verification_status_display" class="form-control" readonly
                                        value="Please select Finance Mode">
                                    <input type="hidden" name="verification_status" id="verification_status_hidden"
                                        value="1">
                                </div>

                                <div class="col-sm-6" id="case_lost_reason_wrapper" style="display:none;">
                                    <label class="form-label">Case Lost Reason</label>
                                    <input type="text" class="form-control" id="case_lost_reason_display" readonly>
                                    <input type="hidden" name="case_lost_reason" id="case_lost_reason_hidden" value="">
                                </div>


                                <div class="col-sm-3 finance-field" id="instrument_type_wrapper" style="display:none;">
                                    <label class="form-label">Instrument Type</label>
                                    <select name="instrument_type" id="instrument_type" class="form-control">
                                        <option value="">-- Select --</option>
                                        <option value="1" {{ old('instrument_type', $finance->instrument_type ?? '') ==
                                            1 ? 'selected' : '' }}>Financier Payment</option>
                                        <option value="2" {{ old('instrument_type', $finance->instrument_type ?? '') ==
                                            2 ? 'selected' : '' }}>Delivery Order</option>
                                        <option value="3" {{ old('instrument_type', $finance->instrument_type ?? '') ==
                                            3 ? 'selected' : '' }}>Sanction Letter</option>
                                        <option value="4" {{ old('instrument_type', $finance->instrument_type ?? '') ==
                                            4 ? 'selected' : '' }}>Mail Communication</option>
                                        <option value="5" {{ old('instrument_type', $finance->instrument_type ?? '') ==
                                            5 ? 'selected' : '' }}>Whatsapp Communication</option>
                                    </select>
                                </div>

                                <div class="col-sm-3 finance-field" id="instrument_ref_no_wrapper"
                                    style="display:none;">
                                    <label class="form-label" id="instrument_ref_label">Reference No.</label>
                                    <input type="text" class="form-control" name="instrument_ref_no"
                                        id="instrument_ref_no"
                                        value="{{ old('instrument_ref_no', $finance->instrument_ref_no ?? '') }}">
                                </div>

                                <div class="col-sm-6 finance-field" id="instrument_proof_wrapper" style="display:none;">
                                    <label class="form-label">Instrument Proof</label>
                                    <input type="file" class="form-control" name="instrument_proof"
                                        id="instrument_proof" accept="image/jpeg,image/png,application/pdf"
                                        onchange="previewInstrumentProof(this)">
                                    @if($finance && $finance->getFirstMediaUrl('instrument_proof'))
                                    <div class="current-file-info mt-2">
                                        Current: <a href="{{ $finance->getFirstMediaUrl('instrument_proof') }}"
                                            target="_blank">
                                            {{ basename($finance->getFirstMediaUrl('instrument_proof')) }}
                                        </a>
                                    </div>
                                    @endif
                                    <div id="instrument_preview_container" class="mt-2 position-relative">
                                        <img id="instrumentImg" src="" width="150"
                                            style="display:none; border:1px solid #ddd;">
                                        <iframe id="instrumentPdf" width="150" height="150"
                                            style="display:none;"></iframe>
                                        <button type="button" id="instrumentClear" class="btn btn-danger btn-sm"
                                            style="position:absolute; top:-8px; right:-8px; display:none;"
                                            onclick="clearInstrumentProof()">X</button>
                                    </div>
                                    <small>Max 2 MB (JPG, PNG, PDF)</small>
                                </div>

                                <div class="col-sm-3 finance-field" id="loan_amount_wrapper" style="display:none;">
                                    <label class="form-label">Loan Amount</label>
                                    <input type="number" name="loan_amount" id="loan_amount"
                                        class="form-control calc-field"
                                        value="{{ old('loan_amount', $finance->loan_amount ?? '') }}">
                                </div>

                                <div class="col-sm-3 finance-field" id="margin_money_wrapper" style="display:none;">
                                    <label class="form-label">Margin Money (by Financier)</label>
                                    <input type="number" name="margin_money" id="margin_money"
                                        class="form-control calc-field"
                                        value="{{ old('margin_money', $finance->margin ?? '') }}">
                                </div>

                                <div class="col-sm-3 finance-field" id="file_charge_wrapper" style="display:none;">
                                    <label class="form-label">File Charge (deducted)</label>
                                    <input type="number" name="file_charge" id="file_charge"
                                        class="form-control calc-field"
                                        value="{{ old('file_charge', $finance->file_charge ?? '') }}">
                                </div>

                                <div class="col-sm-3 finance-field" id="payment_amount_wrapper" style="display:none;">
                                    <label class="form-label">Payment Amount</label>
                                    <input type="text" id="payment_amount" class="form-control" readonly>
                                </div>

                                <div class="col-sm-12">
                                    <label class="form-label">Remark <span class="text-danger">*</span></label>
                                    <textarea name="remark" class="form-control" rows="4"
                                        required>{{ old('remark', '') }}</textarea>
                                    @error('remark') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>

                            </div>

                        </div>

                        <div class="card-footer bg-white text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="la la-save"></i> Save Finance Details
                            </button>
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                                <i class="la la-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
@endsection

@push('after_styles')
<link rel="stylesheet" href="{{ asset('plugins/select2/dist/css/select2.min.css') }}">
<style>
    .is-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 5px rgba(40, 167, 69, 0.5);
    }

    .text-danger {
        color: #dc3545;
    }

    .readonly-field,
    input[readonly],
    select[readonly] {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }

    .finance-field {
        display: none;
    }
</style>
@endpush

@push('after_styles')
<link rel="stylesheet" href="{{ asset('plugins/select2/dist/css/select2.min.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .is-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 5px rgba(40, 167, 69, 0.5);
    }

    .text-danger {
        color: #dc3545;
    }

    .readonly-field,
    input[readonly],
    select[readonly] {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }

    .finance-field {
        display: none;
    }

    #instrument_preview_container {
        min-height: 160px;
        position: relative;
        display: inline-block;
    }

    #instrumentImg,
    #instrumentPdf {
        max-width: 100%;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #f8f9fa;
    }

    #instrumentClear {
        position: absolute;
        top: -10px;
        right: -10px;
        z-index: 10;
        width: 24px;
        height: 24px;
        line-height: 16px;
        font-size: 14px;
        padding: 0;
    }

    .current-file-info {
        font-size: 0.9rem;
        margin-top: 8px;
        color: #555;
    }

    .current-file-info a {
        color: #007bff;
        text-decoration: underline;
    }

    .current-file-info a:hover {
        color: #0056b3;
    }

    .select2-container--default .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px);
        padding: 0.375rem 0.75rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.5em + 0.75rem);
    }
</style>
@endpush

@push('after_scripts')
<script src="{{ asset('plugins/select2/dist/js/select2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    (function($) {
    'use strict';

    const originalFinMode = "{{ $booking->fin_mode ?? '' }}".trim();
    let currentMediaUrl   = "{{ $finance && $finance->getFirstMediaUrl('instrument_proof') ? $finance->getFirstMediaUrl('instrument_proof') : '' }}".trim();
    let isExistingFile    = {{ $finance && $finance->getFirstMediaUrl('instrument_proof') ? 'true' : 'false' }};

    function previewInstrumentProof(input) {
        const file = input.files[0];
        const $img = $('#instrumentImg');
        const $pdf = $('#instrumentPdf');
        const $clear = $('#instrumentClear');

        $img.hide(); $pdf.hide(); $clear.hide();

        if (!file) return;

        const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!validTypes.includes(file.type)) {
            alert('Only JPG, PNG, PDF files are allowed.');
            input.value = '';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2 MB.');
            input.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {
            if (file.type.startsWith('image/')) {
                $img.attr('src', e.target.result).show();
            } else {
                const blobUrl = URL.createObjectURL(new Blob([e.target.result], { type: 'application/pdf' }));
                $pdf.attr('src', blobUrl).show();
                $clear.data('revokeUrl', blobUrl);
            }
            $clear.show();
            isExistingFile = false; // New file selected → existing one is irrelevant
        };

        if (file.type === 'application/pdf') {
            reader.readAsArrayBuffer(file);
        } else {
            reader.readAsDataURL(file);
        }
    }

    function clearInstrumentProof() {
        $('#instrument_proof').val('');
        $('#instrumentImg').hide().attr('src', '');
        $('#instrumentPdf').hide().attr('src', '');
        $('#instrumentClear').hide().removeData('revokeUrl');

        if (isExistingFile) {
            // Tell server to delete old file on submit
            if (!$('input[name="delete_instrument_proof"]').length) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'delete_instrument_proof',
                    value: '1'
                }).appendTo('#financeForm');
            }
            isExistingFile = false;
        }
    }

    function applyLogic() {
        const mode     = $('#fin_mode').val();
        const caseVal  = $('#case_status').val();
        const prevFin  = $('#financier_select').val();

        $('.finance-field').hide();
        $('#case_lost_reason_wrapper').hide();
        $('#financier_wrapper, #financier_short_wrapper').show();
        $('#financier_select').prop('disabled', false).prop('required', true);
        $('#case_status').prop('disabled', false);
        $('#loan_status_box').prop('disabled', false);

        // ── Verification Status ──
        let vText = 'Please select Finance Mode';
        let vVal  = 1;
        let vColor = '#6c757d';

        if (mode) {
            if (mode === originalFinMode) {
                vText = 'Verified (Match)';
                vVal = 2;
                vColor = '#28a745';
            } else if (mode === 'Purchase Plan Cancelled') {
                vText = 'Plan Cancelled';
                vVal = 4;
                vColor = '#dc3545';
            } else {
                vText = 'Verified (Mismatch)';
                vVal = 3;
                vColor = '#dc3545';
            }
        }

        $('#verification_status_display').val(vText).css('color', vColor);
        $('#verification_status_hidden').val(vVal);

        let disableFinancier = false;

        if (mode === 'Cash' || mode === 'Customer Self') {
            disableFinancier = true;
            $('#case_status').val(3).prop('disabled', true);
            $('#case_lost_reason_wrapper').show();
            $('#case_lost_reason_display').val(
                mode === 'Cash' ? 'Cash Purchase' : 'Customer Self Finance'
            );
            $('#case_lost_reason_hidden').val(mode === 'Cash' ? '1' : '2');
        }

        // ── In-house specific ──
        if (mode === 'In-house') {
            $('#case_lost_option').hide();
            if (caseVal == 3) $('#case_status').val(1);

            if (caseVal == 2) {
                $('#loan_status_box').val('Complete').prop('disabled', true);
                showFinanceFields();
            }
        } else {
            $('#case_lost_option').show();
        }

        if (disableFinancier) {
            $('#financier_select').val('').trigger('change')
                .prop('disabled', true).prop('required', false);
        } else {
            if (prevFin && prevFin !== '') {
                $('#financier_select').val(prevFin).trigger('change');
            }
            $('#financier_select').prop('disabled', false).prop('required', true);
        }
    }

    function showFinanceFields() {
        $(
            '#instrument_type_wrapper, #instrument_ref_no_wrapper, ' +
            '#instrument_proof_wrapper, #loan_amount_wrapper, ' +
            '#margin_money_wrapper, #file_charge_wrapper, ' +
            '#payment_amount_wrapper'
        ).show();

        updateRefLabel();
        calculatePayment();
    }

    function updateRefLabel() {
        const type = $('#instrument_type').val();
        const $wrapper = $('#instrument_ref_no_wrapper');
        const $label   = $('#instrument_ref_label');

        if (type == '1' || type == '2') {
            $wrapper.show();
            $label.text(type == '1' ? 'Receipt No.' : 'Delivery Order No.');
        } else {
            $wrapper.hide();
        }
    }

    function calculatePayment() {
        const loan   = parseFloat($('#loan_amount').val())   || 0;
        const margin = parseFloat($('#margin_money').val())  || 0;
        const charge = parseFloat($('#file_charge').val())   || 0;

        const total = (loan + margin - charge).toFixed(2);
        $('#payment_amount').val(total);
    }

    function init() {
        // Select2
        $('.select2').select2();

        $('#financier_select').on('change', function() {
            const short = $(this).find('option:selected').data('short') || '';
            $('#financier_short_name').val(short);
        }).trigger('change');

        if (currentMediaUrl) {
            if (currentMediaUrl.toLowerCase().endsWith('.pdf')) {
                $('#instrumentPdf').attr('src', currentMediaUrl).show();
            } else {
                $('#instrumentImg').attr('src', currentMediaUrl).show();
            }
            $('#instrumentClear').show();
        }

        $('#financeForm').validate({
            ignore: ':hidden, :disabled',
            rules: {
                fin_mode:        { required: true },
                loan_status:     { required: true },
                case_status:     { required: true },
                remark:          { required: true }
            },
            messages: {
                fin_mode:     "Please select finance mode",
                loan_status:  "Please select loan status",
                case_status:  "Please select case status",
                remark:       "Remark is required"
            },
            errorElement: 'span',
            errorClass: 'text-danger small d-block mt-1',
            highlight: function(element) {
                $(element).addClass('is-invalid').removeClass('is-valid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid').addClass('is-valid');
            }
        });

        $('#fin_mode, #case_status, #loan_status_box, #instrument_type').on('change', applyLogic);
        $('.calc-field').on('input', calculatePayment);

        applyLogic();
    }

    $(document).ready(init);

})(jQuery);
</script>
@endpush