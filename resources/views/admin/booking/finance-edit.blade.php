@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">

</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">




        <div class="card-body">

            <div class="card bg-light border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h2 class="mb-0">Booking & Customer Information (Read-only)</h2>
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
                            <input type="text" class="form-control" value="{{ $booking->fin_mode ?? 'N/A' }}" readonly>
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
                <input type="hidden" name="bid" value="{{ $booking->id }}">

                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="mb-0">Finance & Loan Details</h2>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <!-- Finance Mode -->
                            <div class="col-sm-3">
                                <label class="form-label">Finance Mode <span class="text-danger">*</span></label>
                                <select name="fin_mode" id="fin_mode" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <option value="In-house" {{ old('fin_mode', $finance->fin_mode ?? '') == 'In-house'
                                        ? 'selected' : '' }}>In-house</option>
                                    <option value="Cash" {{ old('fin_mode', $finance->fin_mode ?? '') == 'Cash' ?
                                        'selected' : '' }}>Cash</option>
                                    <option value="Customer Self" {{ old('fin_mode', $finance->fin_mode ?? '') ==
                                        'Customer Self' ? 'selected' : '' }}>Customer Self</option>
                                    <option value="Yet To Decide" {{ old('fin_mode', $finance->fin_mode ?? '') == 'Yet
                                        To Decide' ? 'selected' : '' }}>Yet To Decide</option>
                                    <option value="Purchase Plan Cancelled" {{ old('fin_mode', $finance->fin_mode ?? '')
                                        == 'Purchase Plan Cancelled' ? 'selected' : '' }}>Purchase Plan Cancelled
                                    </option>
                                </select>
                                @error('fin_mode') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <!-- Loan Status -->
                            <div class="col-sm-3">
                                <label class="form-label">Loan Status <span class="text-danger">*</span></label>
                                <select name="loan_status" id="loan_status_box" class="form-control form-select"
                                    required>
                                    <option value="Pending" {{ old('loan_status', $finance->loan_status ?? '') ==
                                        'Pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="Complete" {{ old('loan_status', $finance->loan_status ?? '') ==
                                        'Complete' ? 'selected' : '' }}>Complete</option>
                                </select>
                                @error('loan_status') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <!-- Financier -->
                            <div class="col-sm-3" id="financier_wrapper">
                                <label class="form-label">Financier <span class="text-danger">*</span></label>
                                <select name="financier" id="financier_select" class="form-select">
                                    <option value="">-- Select Financier --</option>
                                    @foreach($data['financiers'] ?? [] as $fin)
                                    <option value="{{ $fin['id'] }}" data-short="{{ $fin['short_name'] ?? '' }}" {{
                                        old('financier', $finance->financier ?? '') == $fin['id'] ? 'selected' : '' }}>
                                        {{ $fin['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('financier') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <!-- Short Name -->
                            <div class="col-sm-3" id="financier_short_wrapper">
                                <label class="form-label">Short Name</label>
                                <input type="text" class="form-control" id="financier_short_name" readonly
                                    value="{{ $finance && $finance->financier ? collect($data['financiers'] ?? [])->firstWhere('id', $finance->financier)['short_name'] ?? '' : '' }}">



                            </div>

                            <!-- Case Status -->
                            <div class="col-sm-3">
                                <label class="form-label">Case Status <span class="text-danger">*</span></label>
                                <select name="case_status" id="case_status" class="form-control form-select" required>
                                    <option value="1" {{ old('case_status', $finance->case_status ?? 1) == 1 ?
                                        'selected' : '' }}>In-Process</option>
                                    <option value="2" {{ old('case_status', $finance->case_status ?? 1) == 2 ?
                                        'selected' : '' }}>In House Finance Done</option>
                                    <option value="3" id="case_lost_option" {{ old('case_status', $finance->case_status
                                        ?? 1) == 3 ? 'selected' : '' }}>Case Lost</option>
                                </select>
                                @error('case_status') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <!-- Verification Status -->
                            <div class="col-sm-3">
                                <label class="form-label">Verification Status</label>
                                <input type="text" id="verification_status_display" class="form-control" readonly
                                    value="Please select Finance Mode">
                                <input type="hidden" name="verification_status" id="verification_status_hidden"
                                    value="1">
                                <input type="hidden" name="payout_eligible" id="payout_hidden" value="0">
                            </div>

                            <!-- Case Lost Reason -->
                            <div class="col-sm-6" id="case_lost_reason_wrapper" style="display:none;">
                                <label class="form-label">Case Lost Reason</label>
                                <input type="text" class="form-control" id="case_lost_reason_display" readonly>
                                <input type="hidden" name="case_lost_reason" id="case_lost_reason_hidden" value="">
                            </div>

                            <!-- Instrument Type -->
                            <div class="col-sm-3 finance-field" id="instrument_type_wrapper" style="display:none;">
                                <label class="form-label">Instrument Type <span class="text-danger">*</span></label>
                                <select name="instrument_type" id="instrument_type" class="form-control form-select">
                                    <option value="">-- Select --</option>
                                    <option value="1" {{ old('instrument_type', $finance->instrument_type ?? '') == 1 ?
                                        'selected' : '' }}>Financier Payment</option>
                                    <option value="2" {{ old('instrument_type', $finance->instrument_type ?? '') == 2 ?
                                        'selected' : '' }}>Delivery Order</option>
                                    <option value="3" {{ old('instrument_type', $finance->instrument_type ?? '') == 3 ?
                                        'selected' : '' }}>Sanction Letter</option>
                                    <option value="4" {{ old('instrument_type', $finance->instrument_type ?? '') == 4 ?
                                        'selected' : '' }}>Mail Communication</option>
                                    <option value="5" {{ old('instrument_type', $finance->instrument_type ?? '') == 5 ?
                                        'selected' : '' }}>Whatsapp Communication</option>
                                </select>
                            </div>

                            <!-- Ref No. -->
                            <div class="col-sm-3 finance-field" id="instrument_ref_no_wrapper" style="display:none;">
                                <label class="form-label" id="instrument_ref_label">Reference No. <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="instrument_ref_no" id="instrument_ref_no"
                                    value="{{ old('instrument_ref_no', $finance->instrument_ref_no ?? '') }}">
                            </div>

                            <!-- Instrument Proof -->
                            <!-- Instrument Proof -->
                            <div class="col-sm-6 finance-field" id="instrument_proof_wrapper" style="display:none;">
                                <label class="form-label">Instrument Proof <span class="text-danger">*</span></label>

                                <input type="file" class="form-control" name="instrument_proof"
                                    id="instrumentProofInput" accept="image/jpeg,image/png,application/pdf">

                                <!-- Chip Preview -->
                                <div id="instrumentProofPreview" class="mt-3"></div>

                                @if($finance && $finance->getFirstMediaUrl('instrument_proof'))
                                <div class="mt-2">
                                    <a href="{{ $finance->getFirstMediaUrl('instrument_proof') }}" target="_blank"
                                        class="btn btn-sm btn-info">
                                        <i class="la la-paperclip"></i> View Current Proof
                                    </a>
                                </div>
                                @endif

                                <small class="text-muted">Max 2 MB (JPG, PNG, PDF)</small>
                            </div>

                            <!-- Loan Fields -->
                            <div class="col-sm-3 finance-field" id="loan_amount_wrapper" style="display:none;">
                                <label class="form-label">Loan Amount <span class="text-danger">*</span></label>
                                <input type="number" name="loan_amount" id="loan_amount" class="form-control calc-field"
                                    value="{{ old('loan_amount', $finance->loan_amount ?? '') }}">
                            </div>

                            <div class="col-sm-3 finance-field" id="margin_money_wrapper" style="display:none;">
                                <label class="form-label">Margin Money (by Financier) </label>
                                <input type="number" name="margin_money" id="margin_money"
                                    class="form-control calc-field"
                                    value="{{ old('margin_money', $finance->margin ?? '') }}">
                            </div>

                            <div class="col-sm-3 finance-field" id="file_charge_wrapper" style="display:none;">
                                <label class="form-label">File Charge (deducted) <span
                                        class="text-danger">*</span></label>
                                <input type="number" name="file_charge" id="file_charge" class="form-control calc-field"
                                    value="{{ old('file_charge', $finance->file_charge ?? '') }}">
                            </div>

                            <div class="col-sm-3 finance-field" id="payment_amount_wrapper" style="display:none;">
                                <label class="form-label">Net Settlement Amount</label>
                                <input type="text" id="payment_amount" class="form-control" readonly>
                            </div>

                            <!-- Remarks -->
                            <div class="col-sm-12">
                                <label class="form-label">Remarks <span class="text-danger">*</span></label>
                                <textarea name="remark" class="form-control" rows="4"
                                    required>{{ old('remark', '') }}</textarea>
                                @error('remark') <span class="text-danger small">{{ $message }}</span> @enderror
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
<div class="row mt-4">
    <div class="col-12">

        @include('partials.entity-history', [
        'threads' => $bookingHistory ?? collect()
        ])

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
    .is-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 5px rgba(40, 167, 69, 0.5);
    }

    .text-danger {
        color: #dc3545;
    }

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
    }

    #instrumentClear {
        position: absolute;
        top: -10px;
        right: -10px;
        z-index: 10;
        width: 24px;
        height: 24px;
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

    .select2-container--default .select2-selection--single {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23495057'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 12px 12px;
        padding-right: 2.25rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        display: none;
    }

    .select2-container--default.select2-container--open .select2-selection--single {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23495057'%3E%3Cpath d='M7.646 4.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1-.708.708L8 6.207 2.354 11.354a.5.5 0 0 1-.708-.708l6-6z'/%3E%3C/svg%3E");
    }

    .modal-backdrop {
        z-index: 1040 !important;
    }

    .modal {
        z-index: 1060 !important;
    }

    .modal-dialog {
        z-index: 1070 !important;
    }
</style>
@endpush

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function handleInstrumentProof(input) {

    const previewDiv = document.getElementById('instrumentProofPreview');
    previewDiv.innerHTML = '';

    if (input.files && input.files[0]) {

        const file = input.files[0];

        if (file.size > 2 * 1024 * 1024) {

            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'File must be less than 2MB',
                confirmButtonColor: '#3085d6'
            });

            input.value = '';
            return;
        }

        const fileURL = URL.createObjectURL(file);

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


    function openInstrumentModal(url, name) {

        document.getElementById('instrumentModalFileName').innerText = name;
        document.getElementById('instrumentModalPreview').src = url;
        document.getElementById('instrumentModalDownload').href = url;

        $('#instrumentProofModal').modal('show');
    }

    document.getElementById('instrumentProofInput')
    ?.addEventListener('change', function () {
        handleInstrumentProof(this);
    });
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
                alert('Only JPG, PNG, PDF allowed');
                input.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('Max 2 MB');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = e => {
                if (file.type.startsWith('image/')) {
                    $img.attr('src', e.target.result).show();
                } else {
                    const url = URL.createObjectURL(new Blob([e.target.result], {type: 'application/pdf'}));
                    $pdf.attr('src', url).show();
                    $clear.data('revokeUrl', url);
                }
                $clear.show();
                isExistingFile = false;
                checkPayoutEligibility();
            };

            if (file.type === 'application/pdf') reader.readAsArrayBuffer(file);
            else reader.readAsDataURL(file);
        }

        function clearInstrumentProof() {
            $('#instrument_proof').val('');
            $('#instrumentImg').hide().attr('src', '');
            $('#instrumentPdf').hide().attr('src', '');
            $('#instrumentClear').hide().removeData('revokeUrl');

            if (isExistingFile) {
                if (!$('input[name="delete_instrument_proof"]').length) {
                    $('<input>').attr({type:'hidden', name:'delete_instrument_proof', value:'1'})
                        .appendTo('#financeForm');
                }
                isExistingFile = false;
            }
            checkPayoutEligibility();
        }

        function applyLogic() {

            const mode = $('#fin_mode').val();
            const caseVal = $('#case_status').val();
            const prevFin = $('#financier_select').val();

            $('.finance-field').hide();
            $('#case_lost_reason_wrapper').hide();

            $('#financier_wrapper, #financier_short_wrapper').show();
            $('#financier_select').prop('disabled', false).prop('required', true);

            $('#case_status').prop('disabled', false);
            $('#loan_status_box').prop('disabled', false).prop('required', true);

            let disableFinancier = false;

            if (mode === 'Cash' || mode === 'Customer Self') {
            
                disableFinancier = true;
            
                $('#case_status').val(3).prop('disabled', true);
                $('#loan_status_box').val('').prop('disabled', true).prop('required', false);
            
                $('#case_lost_reason_wrapper').show();
            
                if (mode === 'Cash') {
                
                    $('#case_lost_reason_display').val('Cash Purchase');
                    $('#case_lost_reason_hidden').val('1');
                
                } else {
                
                    $('#case_lost_reason_display').val('Customer Self Finance');
                    $('#case_lost_reason_hidden').val('2');
                
                    showFinanceFields();
                }
            
            }
        
            if (mode === 'In-house') {
            
                $('#case_lost_option').hide();
            
                if (caseVal == 3) {
                    $('#case_status').val(1);
                }
            
                if (caseVal == 2) {
                
                    $('#loan_status_box')
                        .val('Complete')
                        .prop('disabled', true)
                        .prop('required', false);
                
                    showFinanceFields();
                }
            
            } else {
            
                $('#case_lost_option').show();
            
            }
        
            if (disableFinancier) {
            
                $('#financier_select')
                    .val('')
                    .trigger('change')
                    .prop('disabled', true)
                    .prop('required', false);
            
            } else {
            
                if (prevFin && prevFin !== '') {
                    $('#financier_select').val(prevFin).trigger('change');
                }
            
                $('#financier_select')
                    .prop('disabled', false)
                    .prop('required', true);
            }
        
            updateInstrumentFields();
            updateVerificationStatus();
            checkPayoutEligibility();
        }

        function updateInstrumentFields() {

            const instrumentType = $('#instrument_type').val();

            if (instrumentType === '1' || instrumentType === '2') {

                $('#instrument_ref_no_wrapper').show();

                const labelText = instrumentType === '1'
                    ? 'Receipt No.'
                    : 'Delivery Order No.';

                $('#instrument_ref_label').text(labelText);

                $('#instrument_ref_no').prop('required', true);

            } else {

                $('#instrument_ref_no_wrapper').hide();

                $('#instrument_ref_no')
                    .prop('required', false)
                    .removeClass('is-invalid is-valid');
            }

        }

        function showFinanceFields() {

    $('#instrument_type_wrapper, #instrument_ref_no_wrapper, #instrument_proof_wrapper, ' +
      '#loan_amount_wrapper, #margin_money_wrapper, #file_charge_wrapper, #payment_amount_wrapper').show();

    const mode = $('#fin_mode').val();
    const caseStatus = $('#case_status').val();

    if (mode === 'In-house' && caseStatus == '2') {

        $('#instrument_type').prop('required', true);
        $('#instrument_proof').prop('required', true);
        $('#loan_amount').prop('required', true);
        $('#margin_money').prop('required', false);
        $('#file_charge').prop('required', true);
        $('#instrument_ref_no').prop('required', true);

    } else {

        $('#instrument_type').prop('required', false);
        $('#instrument_proof').prop('required', false);
        $('#loan_amount').prop('required', false);
        $('#margin_money').prop('required', false);
        $('#file_charge').prop('required', false);
        $('#instrument_ref_no').prop('required', false);

    }

    updateRefLabel();
    calculatePayment();
}

        function updateRefLabel() {
            const t = $('#instrument_type').val();
            const $w = $('#instrument_ref_no_wrapper');
            const $l = $('#instrument_ref_label');
            if (t == '1' || t == '2') {
                $w.show();
                $l.text(t == '1' ? 'Receipt No.' : 'Delivery Order No.');
            } else $w.hide();
        }

        function calculatePayment() {
            const l = parseFloat($('#loan_amount').val()) || 0;
            const m = parseFloat($('#margin_money').val()) || 0;
            const c = parseFloat($('#file_charge').val()) || 0;
            $('#payment_amount').val((l + m - c).toFixed(2));
        }

        function checkPayoutEligibility() {
            const eligible = (
                $('#fin_mode').val() === 'In-house' &&
                $('#case_status').val() == '2' &&
                $('#instrument_type').val() &&
                $.trim($('#instrument_ref_no').val()) &&
                ($('#instrumentProofInput').val() || currentMediaUrl) &&
                parseFloat($('#loan_amount').val()) > 0
            );
            $('#payout_hidden').val(eligible ? '1' : '0');
        }

        function updateVerificationStatus() {
            const mode = $('#fin_mode').val();
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
        }


        function init() {
            $('.select2').select2({
                placeholder: "-- Select --",

                width: '100%'
            });

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
                highlight:   function(el) { $(el).addClass('is-invalid').removeClass('is-valid'); },
                unhighlight: function(el) { $(el).removeClass('is-invalid').addClass('is-valid'); }
            });

            $('#financeForm').on('submit', function() {
                $(this).find(':disabled').prop('disabled', false);
            });

            $('#fin_mode, #case_status, #loan_status_box, #instrument_type').on('change', applyLogic);
            $('.calc-field').on('input', function() { calculatePayment(); checkPayoutEligibility(); });
            $('#instrument_ref_no').on('input', checkPayoutEligibility);

            applyLogic();
            updateVerificationStatus();
        }

        $(document).ready(init);

        $('#instrumentProofModal').on('show.bs.modal', function () {
    $(this).appendTo('body');
});

    })(jQuery);
</script>
@endpush