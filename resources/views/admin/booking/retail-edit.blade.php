@extends(backpack_view('blank'))
<!-- or 'backpack::blank' / 'backpack::layouts.app' depending on your Backpack version -->

@section('header')
<section class="container-fluid">

</section>
@endsection

@section('content')
<div class="row">







    <div class="card bg-light border-0 shadow-sm mb-4">
        <div class="card-header">
            <h2 class="mb-0">Invoice Details</h2>
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
                        value="{{ $booking->model_code ?? 'N/A' }} / {{ $booking->variant_code ?? 'N/A' }}" readonly>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Finance Mode (Primary)</label>
                    <input type="text" class="form-control" value="{{ $booking->fin_mode ?? 'N/A' }}" readonly>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Financier Name (Primary)</label>
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

        <input type="hidden" name="retail" value="1">
        <input type="hidden" name="payout" value="1">
        <input type="hidden" name="bid" value="{{ $booking->id }}">

        @if(request()->has('from') && request()->get('from') === 'payout')
        <input type="hidden" name="from" value="payout">
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="mb-0">Finance Details (Final)</h2>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-sm-3">
                        <label class="form-label">Finance Mode <span class="text-danger">*</span></label>
                        <select name="fin_mode" id="fin_mode" class="form-control form-select" required>
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
                        @error('fin_mode') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-sm-3">
                        <label class="form-label">Loan Status <span class="text-danger">*</span></label>
                        <select name="loan_status" id="loan_status_box" class="form-control form-select" required>
                            <option value="Pending" {{ old('loan_status', $finance->loan_status ?? '') ==
                                'Pending' ? 'selected' : '' }}>Pending</option>
                            <option value="Complete" {{ old('loan_status', $finance->loan_status ?? '') ==
                                'Complete' ? 'selected' : '' }}>Complete</option>
                        </select>
                        @error('loan_status') <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-sm-3" id="financier_wrapper">
                        <label class="form-label">Financier Name (Final) <span class="text-danger">*</span></label>
                        <select name="financier" id="financier_select" class="form-control form-select">
                            <option value="">-- Select Financier --</option>
                            @foreach($data['financiers'] ?? [] as $fin)
                            <option value="{{ $fin['id'] }}" data-short="{{ $fin['short_name'] ?? '' }}" {{
                                old('financier', $finance->financier ?? '') == $fin['id'] ? 'selected' : ''
                                }}>
                                {{ $fin['name'] }}
                            </option>
                            @endforeach
                        </select>
                        @error('financier') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-sm-3" id="financier_short_wrapper">
                        <label class="form-label">Financier Short Name</label>
                        <input type="text" class="form-control" id="financier_short_name" readonly
                            value="{{ optional(collect($data['financiers'] ?? [])->firstWhere('id', optional($finance)->financier))['short_name'] ?? '' }}">
                    </div>

                    <div class="col-sm-3">
                        <label class="form-label">Case Status <span class="text-danger">*</span></label>
                        <select name="case_status" id="case_status" class="form-control form-select" required>
                            <option value="1" {{ old('case_status', $finance->case_status ?? 1) == 1 ?
                                'selected' : '' }}>In-Process</option>
                            <option value="2" {{ old('case_status', $finance->case_status ?? 1) == 2 ?
                                'selected' : '' }}>In House Finance Done</option>
                            <option value="3" id="case_lost_option" {{ old('case_status', $finance->
                                case_status ?? 1) == 3 ? 'selected' : '' }}>Case Lost</option>
                        </select>
                        @error('case_status') <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-sm-3">
                        <label class="form-label">Verification Status</label>
                        <input type="text" id="verification_status_display" class="form-control" readonly
                            value="Please select Finance Mode">
                        <input type="hidden" name="verification_status" id="verification_status_hidden" value="1">
                    </div>

                    <div class="col-sm-6" id="case_lost_reason_wrapper" style="display:none;">
                        <label class="form-label">Case Lost Reason</label>
                        <input type="text" class="form-control" id="case_lost_reason_display" readonly>
                        <input type="hidden" name="case_lost_reason" id="case_lost_reason_hidden" value="">
                    </div>

                    <div class="col-sm-3 finance-field" id="instrument_type_wrapper" style="display:none;">
                        <label class="form-label">Instrument Type <span class="text-danger">*</span></label>
                        <select name="instrument_type" id="instrument_type" class="form-control form-select">
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

                    <div class="col-sm-3 finance-field" id="instrument_ref_no_wrapper" style="display:none;">
                        <label class="form-label" id="instrument_ref_label">Reference No.
                            <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="instrument_ref_no" id="instrument_ref_no"
                            value="{{ old('instrument_ref_no', $finance->instrument_ref_no ?? '') }}">
                    </div>

                    <div class="col-sm-3 finance-field" id="instrument_proof_wrapper" style="display:none;">
                        <label class="form-label">
                            Instrument Proof <span class="text-danger">*</span>
                        </label>

                        <input type="file" class="form-control" name="instrument_proof" id="instrumentProofInput"
                            accept="image/jpeg,image/png,application/pdf">

                        <div id="instrumentProofPreview" class="mt-3"></div>


                        @if($finance && $finance->getFirstMediaUrl('instrument_proof'))
                        @php
                        $existingUrl = $finance->getFirstMediaUrl('instrument_proof');
                        $fileName = basename($existingUrl);
                        @endphp

                        <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                            style="cursor:pointer"
                            onclick="openInstrumentModal('{{ $existingUrl }}','{{ $fileName }}')">

                            <i class="la la-paperclip"></i>
                            <span class="fw-medium small">{{ $fileName }}</span>

                        </span>

                        @endif

                    </div>

                    <div class="col-sm-2 finance-field" id="loan_amount_wrapper" style="display:none;">
                        <label class="form-label">Loan Amount <span class="text-danger">*</span></label>
                        <input type="number" name="loan_amount" id="loan_amount" class="form-control calc-field"
                            value="{{ old('loan_amount', $finance->loan_amount ?? '') }}">
                    </div>

                    <div class="col-sm-2 finance-field" id="margin_money_wrapper" style="display:none;">
                        <label class="form-label">Margin Money (by Financier) <span class="text-danger">*</span></label>
                        <input type="number" name="margin_money" id="margin_money" class="form-control calc-field"
                            value="{{ old('margin_money', $finance->margin ?? '') }}">
                    </div>

                    <div class="col-sm-2 finance-field" id="file_charge_wrapper" style="display:none;">
                        <label class="form-label">File Charge (Deducted) <span class="text-danger">*</span></label>
                        <input type="number" name="file_charge" id="file_charge" class="form-control calc-field"
                            value="{{ old('file_charge', $finance->file_charge ?? '') }}">
                    </div>

                    <div class="col-sm-3 finance-field" id="payment_amount_wrapper" style="display:none;">
                        <label class="form-label">Payment Amount</label>
                        <input type="text" id="payment_amount" class="form-control" readonly>
                    </div>

                    <div class="col-sm-12">
                        <label class="form-label">Remarks <span class="text-danger">*</span></label>
                        <textarea name="remark" class="form-control" rows="4"
                            required>{{ old('remark', $finance->remark ?? '') }}</textarea>
                        @error('remark') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                </div>
            </div>

            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="la la-save"></i> Save Finance Info
                </button>
                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                    <i class="la la-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </form>


    <div class="row mt-4">
        <div class="col-12">

            @include('partials.entity-history', [
            'threads' => $bookingHistory ?? collect()
            ])

        </div>
    </div>


</div>

<div class="modal fade" id="instrumentProofModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="instrumentModalFileName"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center">
                <iframe id="instrumentModalPreview" style="width:100%; height:500px;" frameborder="0"></iframe>
            </div>

            <div class="modal-footer">

                <a id="instrumentModalDownload" class="btn btn-success" download>
                    Download
                </a>

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>

            </div>

        </div>
    </div>
</div>
@endsection

@push('after_styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    .finance-field {
        display: none;
    }

    .readonly-field,
    input[readonly],
    select[readonly] {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }

    #instrument_preview_container {
        min-height: 160px;
        position: relative;
        display: inline-block;
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

    /* Base height & padding match Bootstrap input/select */
    .select2-container--default .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px) !important;
        padding: 0.375rem 2.25rem 0.375rem 0.75rem !important;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        background-color: #fff;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        display: none !important;
    }

    .select2-container--default .select2-selection--single {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 16px;
    }

    .select2-container--default.select2-container--open .select2-selection--single {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 11 6-6 6 6'/%3e%3c/svg%3e") !important;
    }

    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, .25);
    }

    @media (max-width: 576px) {
        .select2-container--default .select2-selection--single {
            padding-right: 2rem !important;
            background-position: right 0.5rem center;
        }
    }
</style>
@endpush

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    (function($) {
        'use strict';

        const originalFinMode = "{{ $booking->fin_mode ?? '' }}".trim();
        let currentMediaUrl   = "{{ $finance && $finance->getFirstMediaUrl('instrument_proof') ? $finance->getFirstMediaUrl('instrument_proof') : '' }}".trim();
        let isExistingFile    = {{ $finance && $finance->getFirstMediaUrl('instrument_proof') ? 'true' : 'false' }};

        function apply() {
            const mode = $('#fin_mode').val();
            const caseStatus = $('#case_status').val();
            const previouslySelected = $('#financier_select').val();

            $('#instrument_ref_no_wrapper, #instrument_proof_wrapper, #loan_amount_wrapper, #margin_money_wrapper, #file_charge_wrapper, #payment_amount_wrapper').hide();
            $('#case_lost_reason_wrapper').hide();
            $('#financier_wrapper, #financier_short_wrapper').show();
            $('#financier_select').prop('disabled', false).prop('required', true);
            $('#case_status').prop('disabled', false);
            $('#loan_status_box').prop('disabled', false).prop('required', true);

            let vText = 'Please select Finance Mode';
            let vVal = 1;
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

            let shouldDisableFinancier = false;

            if (mode === 'Cash' || mode === 'Customer Self') {
                shouldDisableFinancier = true;
                $('#case_status').val('3').prop('disabled', true);
                $('#loan_status_box').val('').prop('disabled', true);
                $('#case_lost_reason_wrapper').show();

                if (mode === 'Cash') {
                    $('#case_lost_reason_display').val('Cash Purchase');
                    $('#case_lost_reason_hidden').val('1');
                } else {
                    $('#case_lost_reason_display').val('Customer Self Finance');
                    $('#case_lost_reason_hidden').val('2');
                    showFinanceFields();
                }

                $('#instrument_type').val('');
                updateRefLabel();
                checkPayoutEligibility();
                $('#instrument_ref_no').val('');
                $('#loan_amount').val('');
                $('#margin_money').val('');
                $('#file_charge').val('');
                $('#payment_amount').val('');

                $('#instrument_proof, #instrument_ref_no, #instrument_type, #loan_amount, #margin_money, #file_charge')
                    .prop('required', false);
            }

            if (mode === 'In-house') {
                $('#case_lost_option').hide();
                if (caseStatus == 3) $('#case_status').val(1);

                if (caseStatus == 2) {
                    $('#loan_status_box').val('Complete').prop('disabled', true).prop('required', false);
                    showFinanceFields();
                } else {
                    $('#loan_status_box').prop('disabled', false);
                }
            } else {
                $('#case_lost_option').show();
            }

            if (shouldDisableFinancier) {
                $('#financier_select').val('').trigger('change')
                    .prop('disabled', true).prop('required', false);
            } else {
                if (previouslySelected && previouslySelected !== '') {
                    $('#financier_select').val(previouslySelected).trigger('change');
                }
                $('#financier_select').prop('disabled', false).prop('required', true);
            }

            verifyStatus();
            checkPayoutEligibility();
        }

        function showFinanceFields() {
            $('#instrument_type_wrapper, #instrument_ref_no_wrapper, #instrument_proof_wrapper, ' +
              '#loan_amount_wrapper, #margin_money_wrapper, #file_charge_wrapper, #payment_amount_wrapper').show();

            const mode = $('#fin_mode').val();
            const caseStatus = $('#case_status').val();

            if (mode === 'In-house' && caseStatus == '2') {
                $('#instrument_type, #instrument_proof, #loan_amount, #margin_money, #file_charge').prop('required', true);
                $('#instrument_ref_no').prop('required', true);
            } else {
                $('#instrument_type, #instrument_proof, #instrument_ref_no, #loan_amount, #margin_money, #file_charge')
                    .prop('required', false);
            }

            updateRefLabel();
            calculatePayment();
            checkPayoutEligibility();
        }

        function updateRefLabel() {
            const t = $('#instrument_type').val();
            const $w = $('#instrument_ref_no_wrapper');
            const $l = $('#instrument_ref_label');

            if (t == '1' || t == '2') {
                $w.show();
                $l.text(t == '1' ? 'Receipt No.' : 'Delivery Order No.');
                $('#instrument_ref_no').prop('required', true);
            } else {
                $w.hide();
                $('#instrument_ref_no').prop('required', false);
            }
        }

        function calculatePayment() {
            const l = parseFloat($('#loan_amount').val()) || 0;
            const m = parseFloat($('#margin_money').val()) || 0;
            const c = parseFloat($('#file_charge').val()) || 0;
            $('#payment_amount').val((l + m - c).toFixed(2));
        }

        function verifyStatus() {
            const sel = $('#fin_mode').val();
            if (!sel) {
                $('#verification_status_display').val('Please select Finance Mode').css('color', '#dc3545');
                $('#verification_status_hidden').val(1);
            } else if (sel === originalFinMode) {
                $('#verification_status_display').val('Verified (Match)').css('color', '#28a745');
                $('#verification_status_hidden').val(2);
            } else {
                $('#verification_status_display').val('Verified (Mismatch)').css('color', '#dc3545');
                $('#verification_status_hidden').val(3);
            }
        }

        function checkPayoutEligibility() {
            const mode = $('#fin_mode').val();
            const caseStatus = $('#case_status').val();
            const instType = $('#instrument_type').val();
            const instRef = $('#instrument_ref_no').val()?.trim();
            const instProof = $('#instrumentProofInput').val() || currentMediaUrl;
            const loan = $('#loan_amount').val();
            const margin = $('#margin_money').val();
            const fileCharge = $('#file_charge').val();

            const eligible = mode === 'In-house' && caseStatus == 2 && instType && instRef && instProof &&
                             loan && parseFloat(loan) > 0 && parseFloat(margin || 0) >= 0 && parseFloat(fileCharge || 0) >= 0;

            $('#payout_hidden').val(eligible ? 1 : 0);
        }
        

        function handleInstrumentProof(input) {

    const previewDiv = document.getElementById('instrumentProofPreview');

    if (input.files && input.files[0]) {

        const file = input.files[0];
        const fileURL = URL.createObjectURL(file);

        // remove old chip
        previewDiv.innerHTML = '';

        previewDiv.innerHTML = `
            <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                style="cursor:pointer"
                onclick="openInstrumentModal('${fileURL}', '${file.name.replace(/'/g,"\\'")}')">

                <i class="la la-paperclip"></i>
                <span class="fw-medium small">${file.name}</span>

            </span>
        `;
    }
}

window.openInstrumentModal = function(url, name) {

    document.getElementById('instrumentModalFileName').innerText = name;
    document.getElementById('instrumentModalPreview').src = url;
    document.getElementById('instrumentModalDownload').href = url;

    $('#instrumentProofModal').modal('show');
}

document.getElementById('instrumentProofInput')
?.addEventListener('change', function () {
    handleInstrumentProof(this);
});



        function init() {
            $('.select2').select2({
                placeholder: "-- Select --",

                width: '100%'
            });

            $('#financier_select').on('change', function() {
                const short = $(this).find('option:selected').data('short') || '';
                $('#financier_short_name').val(short);
            }).trigger('change');

            
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
                highlight:   function(el) { $(el).addClass('is-invalid').removeClass('is-valid'); },
                unhighlight: function(el) { $(el).removeClass('is-invalid').addClass('is-valid'); }
            });

            $('#financeForm').on('submit', function(e) {
                const mode = $('#fin_mode').val();
                const caseStatus = $('#case_status').val();

                if (mode === 'In-house' && caseStatus === '1') {
                    e.preventDefault();

                    if (!$('#in_house_pending_warning').length) {
                        $('#fin_mode').closest('.row').after(`
                            <div id="in_house_pending_warning" class="alert alert-danger mt-4 mb-0 p-3 rounded">
                                <strong>Action Required</strong><br>
                                This booking is marked as <strong>In-house Pending</strong>.<br>
                                To save, please either:<br>
                                • Set <strong>Case Status = Complete</strong> and fill all finance details, OR<br>
                                • Change Finance Mode to <strong>Cash</strong> or <strong>Customer Self</strong>
                            </div>
                        `);
                    }

                    $('html, body').animate({
                        scrollTop: $('#in_house_pending_warning').offset().top - 100
                    }, 500);

                    return false;
                }

                $('#in_house_pending_warning').remove();

                if (mode === 'Yet To Decide') {
                    e.preventDefault();
                    alert('Cannot save with "Yet to Decide". Please select a final finance mode.');
                    return false;
                }

                $('#instrument_proof, #instrument_ref_no, #instrument_type').prop('required', false);
                $(this).find('input:disabled, select:disabled').prop('disabled', false);
            });

            // Events
            $('#fin_mode, #case_status, #loan_status_box').on('change', apply);
            $('#instrument_type').on('change', updateRefLabel);
            $('.calc-field').on('input keyup change', function() {
                calculatePayment();
                checkPayoutEligibility();
            });
            $('#instrument_ref_no').on('input', checkPayoutEligibility);

            // Initial calls
            apply();
            verifyStatus();
            checkPayoutEligibility();
            calculatePayment();

            if ($('#financier_select').val()) {
                $('#financier_select').trigger('change');
            }
        }

        $('#instrumentProofModal').on('show.bs.modal', function () {
    $(this).appendTo('body');
});

        $(document).ready(init);

    })(jQuery);
</script>
@endpush