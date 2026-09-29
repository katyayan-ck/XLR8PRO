@extends(backpack_view('blank'))

@section('title', 'View Enquiry Finance')

@push('after_styles')
    <style>
        .finance-field { display: none; }
        .freezed-input {
            background-color: var(--tblr-bg-surface-secondary) !important;
            color: var(--tblr-secondary) !important;
            pointer-events: none;
            cursor: not-allowed;
            opacity: 0.8;
            border-color: var(--tblr-border-color) !important;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            <div class="card bg-surface-secondary border-0 shadow-sm mb-4">
                <div class="card-header bg-surface">
                    <h2 class="mb-0">Enquiry & Customer Information (Read-only)</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-3">
                            <label class="form-label">Enquiry No.</label>
                            <input type="text" class="form-control freezed-input" value="XENQ-{{ $enquiry->id }}" readonly>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" class="form-control freezed-input" value="{{ $enquiry->name ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Model / Variant</label>
                            <input type="text" class="form-control freezed-input" value="{{ $enquiry->model_code ?? 'N/A' }} / {{ $enquiry->variant_code ?? 'N/A' }}" readonly>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Enquiry Stage</label>
                            <input type="text" class="form-control freezed-input" value="{{ $enquiry->dms_enquiry_stage ?? $enquiry->stage ?? 'N/A' }}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <form id="financeForm" method="POST" action="{{ route('sales.enquiry.finance.update', $enquiry->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card shadow-sm">
                    <div class="card-header bg-surface">
                        <h2 class="mb-0">Finance & Loan Details</h2>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Finance Mode -->
                            <div class="col-sm-3 mb-3">
                                <label class="form-label">Finance Mode</label>
                                <select name="fin_mode" id="fin_mode" class="form-control form-select freezed-input" required tabindex="-1">
                                    <option value="">-- Select --</option>
                                    @foreach ($finance_modes as $item)
                                        <option value="{{ $item['code'] }}"
                                            {{ old('fin_mode', $finance->fin_mode ?? $enquiry->fin_mode ?? '') == $item['code'] ? 'selected' : '' }}>
                                            {{ $item['value'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Loan Status -->
                            <div class="col-sm-3 mb-3">
                                <label class="form-label">Loan Status</label>
                                <select name="loan_status" id="loan_status_box" class="form-control form-select freezed-input" tabindex="-1">
                                    <option value="Pending" {{ old('loan_status', $finance->loan_status ?? $enquiry->loan_status ?? '') == 'Pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="Complete" {{ old('loan_status', $finance->loan_status ?? $enquiry->loan_status ?? '') == 'Complete' ? 'selected' : '' }}>Complete</option>
                                </select>
                            </div>

                            <!-- Financier -->
                            <div class="col-sm-3 mb-3" id="financier_wrapper">
                                <label class="form-label">Financier</label>
                                <select name="financier" id="financier_select" class="form-control form-select freezed-input" tabindex="-1">
                                    <option value="">-- Select Financier --</option>
                                    @foreach($financiers ?? [] as $fin)
                                        <option value="{{ $fin['id'] }}" {{ old('financier', $finance->financier ?? $enquiry->financier ?? '') == $fin['id'] ? 'selected' : '' }}>
                                            {{ $fin['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Case Status -->
                            <div class="col-sm-3 mb-3">
                                <label class="form-label">Case Status</label>
                                <select name="case_status" id="case_status" class="form-control form-select freezed-input" tabindex="-1">
                                    <option value="1" {{ old('case_status', $finance->case_status ?? 1) == 1 ? 'selected' : '' }}>In-Process</option>
                                    <option value="2" {{ old('case_status', $finance->case_status ?? 1) == 2 ? 'selected' : '' }}>In House Finance Done</option>
                                    <option value="3" id="case_lost_option" {{ old('case_status', $finance->case_status ?? 1) == 3 ? 'selected' : '' }}>Case Lost</option>
                                </select>
                            </div>

                            <!-- Case Lost Reason -->
                            <div class="col-sm-6 mb-3" id="case_lost_reason_wrapper" style="display:none;">
                                <label class="form-label">Case Lost Reason</label>
                                <input type="text" class="form-control freezed-input" id="case_lost_reason_display" readonly>
                                <input type="hidden" name="case_lost_reason" id="case_lost_reason_hidden" value="{{ old('case_lost_reason', $finance->case_lost_reason ?? '') }}">
                            </div>

                            <!-- Instrument Type -->
                            <div class="col-sm-3 mb-3 finance-field" id="instrument_type_wrapper">
                                <label class="form-label">Instrument Type</label>
                                <select name="instrument_type" id="instrument_type" class="form-control form-select freezed-input" tabindex="-1">
                                    <option value="">-- Select --</option>
                                    <option value="1" {{ old('instrument_type', $finance->instrument_type ?? '') == 1 ? 'selected' : '' }}>Financier Payment</option>
                                    <option value="2" {{ old('instrument_type', $finance->instrument_type ?? '') == 2 ? 'selected' : '' }}>Delivery Order</option>
                                    <option value="3" {{ old('instrument_type', $finance->instrument_type ?? '') == 3 ? 'selected' : '' }}>Sanction Letter</option>
                                </select>
                            </div>

                            <!-- Ref No. -->
                            <div class="col-sm-3 mb-3 finance-field" id="instrument_ref_no_wrapper">
                                <label class="form-label" id="instrument_ref_label">Reference No.</label>
                                <input type="text" class="form-control freezed-input" name="instrument_ref_no" id="instrument_ref_no" value="{{ old('instrument_ref_no', $finance->instrument_ref_no ?? '') }}" readonly>
                            </div>

                            <!-- Instrument Proof -->
                            <div class="col-sm-6 mb-3 finance-field" id="instrument_proof_wrapper">
                                <label class="form-label">Instrument Proof</label>
                                <input type="file" class="form-control freezed-input" name="instrument_proof" id="instrumentProofInput" accept="image/jpeg,image/png,application/pdf" disabled>
                                @if($finance && $finance->getFirstMediaUrl('instrument_proof'))
                                <div class="mt-2">
                                    <a href="{{ $finance->getFirstMediaUrl('instrument_proof') }}" target="_blank" class="btn btn-sm btn-info">View Current Proof</a>
                                </div>
                                @endif
                            </div>

                            <!-- Loan Fields -->
                            <div class="col-sm-3 mb-3 finance-field" id="loan_amount_wrapper">
                                <label class="form-label">Loan Amount</label>
                                <input type="number" name="loan_amount" id="loan_amount" class="form-control calc-field freezed-input" value="{{ old('loan_amount', $finance->loan_amount ?? '') }}" readonly>
                            </div>
                            <div class="col-sm-3 mb-3 finance-field" id="margin_money_wrapper">
                                <label class="form-label">Margin Money</label>
                                <input type="number" name="margin_money" id="margin_money" class="form-control calc-field freezed-input" value="{{ old('margin_money', $finance->margin ?? '') }}" readonly>
                            </div>
                            <div class="col-sm-3 mb-3 finance-field" id="file_charge_wrapper">
                                <label class="form-label">File Charge</label>
                                <input type="number" name="file_charge" id="file_charge" class="form-control calc-field freezed-input" value="{{ old('file_charge', $finance->file_charge ?? '') }}" readonly>
                            </div>
                            
                            <div class="col-md-12 mt-4">
                                <hr class="my-3">
                                <h4 class="fw-bold mb-3">Add Follow-up</h4>
                            </div>

                            <div class="col-md-12 mb-4">
                                <label class="form-label">Add New Remark</label>
                                <textarea name="remarks" class="form-control" rows="2" placeholder="Enter new follow-up remarks..." required></textarea>
                            </div>

                            @if(isset($fups) && $fups->count() > 0)
                            <div class="col-md-12 mb-2">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped text-center align-middle mb-0">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th style="width: 10%;">FUP #</th>
                                                <th style="width: 45%;">Remarks</th>
                                                <th style="width: 25%;">Created By</th>
                                                <th style="width: 20%;">Created At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($fups as $fup)
                                                @php
                                                    $creator = \App\Models\User::find($fup->created_by);
                                                    $code = $creator ? ($creator->employee_code ?? $creator->person_code) : null;
                                                    $creatorName = \App\Services\OrgService::getUserNameByCode($code);
                                                @endphp
                                                <tr>
                                                    <td class="fw-bold">{{ $fup->fup_count }}</td>
                                                    <td class="text-start">{{ $fup->remarks }}</td>
                                                    <td>{{ $creatorName }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($fup->created_at)->format('d-M-Y h:i A') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div class="card-footer bg-surface text-center pb-4 border-0 mt-2">
                        <button type="submit" class="btn btn-success btn-lg px-5 py-2 shadow-sm fw-bold">
                            <i class="la la-save"></i> Submit Follow-up
                        </button>
                        <a href="{{ url()->previous() }}" class="btn btn-secondary btn-lg ms-2">Back</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
    (function($) {
        'use strict';

        function applyLogic() {
            // Fetch dynamically resolved text for layout calculation
            const modeText = $('#fin_mode option:selected').text().trim().toUpperCase();
            const caseVal = $('#case_status').val();

            $('.finance-field').hide();
            $('#case_lost_reason_wrapper').hide();

            $('#financier_wrapper').show();

            let disableFinancier = false;

            if (modeText === 'CASH' || modeText === 'CUSTOMER SELF') {
                disableFinancier = true;
                $('#case_lost_reason_wrapper').show();
            
                if (modeText === 'CASH') {
                    $('#case_lost_reason_display').val('Cash Purchase');
                    $('#case_lost_reason_hidden').val('1');
                } else {
                    $('#case_lost_reason_display').val('Customer Self Finance');
                    $('#case_lost_reason_hidden').val('2');
                    showFinanceFields();
                }
            }
        
            if (modeText === 'IN-HOUSE' || modeText === 'IN HOUSE') {
                $('#case_lost_option').hide();
                if (caseVal == 2) {
                    showFinanceFields();
                }
            } else {
                $('#case_lost_option').show();
            }
        
            if (disableFinancier) {
                $('#financier_select').val('').trigger('change');
            }
            updateInstrumentFields();
        }

        function updateInstrumentFields() {
            const t = $('#instrument_type').val();
            if (t === '1' || t === '2') {
                $('#instrument_ref_no_wrapper').show();
                $('#instrument_ref_label').html((t === '1' ? 'Receipt No.' : 'Delivery Order No.'));
            } else {
                $('#instrument_ref_no_wrapper').hide();
            }
        }

        function showFinanceFields() {
            $('#instrument_type_wrapper, #instrument_ref_no_wrapper, #instrument_proof_wrapper, #loan_amount_wrapper, #margin_money_wrapper, #file_charge_wrapper').show();
            updateInstrumentFields();
        }

        // Run logic only once on load for the view layout
        applyLogic();
    })(jQuery);
</script>
@endpush