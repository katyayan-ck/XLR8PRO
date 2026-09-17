@extends(backpack_view('blank'))

@section('title', isset($isEdit) ? 'Edit Receipt' : 'Add Receipt')

@push('after_styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); }
        .form-control:focus, .form-select:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25); }
        .conditional-section { display: none; border-top: 1px solid #e5e7eb; margin-top: 10px; padding-top: 20px; }
        .section-title { font-size: 16px; font-weight: 500; color: #1f4b78; margin-bottom: 15px; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ isset($isEdit) ? 'Edit Receipt' : 'Add Receipt' }}</h4>
                    </div>

                    <form method="POST" action="{{ isset($isEdit) ? backpack_url('accounts/receipt/'.$receipt->id) : backpack_url('accounts/receipt-list') }}">
                        @csrf
                        @if(isset($isEdit))
                            @method('PUT')
                        @endif

                        <div class="card-body">
                            {{-- ERROR DISPLAY BLOCK --}}
                            @if ($errors->any())
                                <div class="alert alert-danger rounded-3 shadow-sm pb-0 mb-4">
                                    <ul class="mb-3">
                                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="row">

                                @if(isset($isEdit))
                                <div class="col-md-3 mb-3">
                                    <label class="form-label text-muted">Receipt No.</label>
                                    <input type="text" class="form-control fw-bold" style="background-color: #e9ecef;" value="{{ $receipt->type_number }}" readonly>
                                </div>
                                @endif

                                {{-- RECEIPT DATE --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                                    <input type="text" id="receipt_date" name="receipt_date" class="form-control" value="{{ old('receipt_date', isset($isEdit) ? $receipt->date : date('Y-m-d')) }}" required>
                                </div>

                                {{-- RECEIPT ISSUED FOR LOCATION --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Receipt Issued For Location <span class="text-danger">*</span></label>
                                    <select name="location" id="location" class="form-control form-select" required>
                                        <option value="BKN" @selected(old('location', $receipt->location ?? $userLocation) == 'BKN')>Bikaner</option>
                                        <option value="CHU" @selected(old('location', $receipt->location ?? $userLocation) == 'CHU')>Churu</option>
                                        <option value="SUJ" @selected(old('location', $receipt->location ?? $userLocation) == 'SUJ')>Sujangarh</option>
                                    </select>
                                </div>

                                {{-- ON A/C OF --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">On A/c Of <span class="text-danger">*</span></label>
                                    <select name="on_account_of" id="on_account_of" class="form-control form-select" required>
                                        <option value="">Select On A/c Of</option>
                                        @foreach($onAccountOfOptions as $id => $value)
                                            <option value="{{ $id }}" data-text="{{ strtoupper($value) }}" @selected(old('on_account_of', $receipt->account_of ?? '') == $id)>
                                                {{ $value }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- MODE OF PAYMENT --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Mode of Payment <span class="text-danger">*</span></label>
                                    <select name="payment_mode" id="payment_mode" class="form-control form-select" required>
                                        <option value="">Select Mode of Payment</option>
                                        @foreach($paymentModeOptions as $id => $value)
                                            <option value="{{ $id }}" data-text="{{ strtoupper($value) }}" @selected(old('payment_mode', $receipt->mode ?? '') == $id)>
                                                {{ $value }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- DYNAMIC IDENTIFICATION SECTIONS --}}
                            <div class="row">
                                {{-- Xceler8 Identifiers --}}
                                <div id="vehicle-sales-section" class="col-md-12 conditional-section">
                                    <div class="section-title">Transaction Details</div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Xceler8 Enq No. <span class="text-danger" id="req_enq_asterisk">*</span></label>
                                            <div class="input-group">
                                                <input type="text" name="xceler8_enq_no" id="xceler8_enq_no" class="form-control" placeholder="e.g. XENQ-1234" value="{{ old('xceler8_enq_no', isset($receipt->enq_id) ? 'XENQ-'.$receipt->enq_id : '') }}">
                                                <button type="button" class="btn btn-primary" id="fetch_enquiry_btn">Fetch</button>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Xceler8 Booking No.</label>
                                            <input type="text" name="xceler8_booking_no" id="xceler8_booking_no" class="form-control" value="{{ old('xceler8_booking_no', $receipt->bid ?? '') }}">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">VOTF No.</label>
                                            <input type="text" name="votf_no" id="votf_no" class="form-control" value="{{ old('votf_no', $receipt->otf_no ?? '') }}">
                                        </div>
                                    </div>
                                </div>

                                {{-- Vehicle Identifiers --}}
                                <div id="general-vehicle-section" class="col-md-12 conditional-section">
                                    <div class="section-title">Vehicle & Service Details</div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Registration No. <span class="text-danger req-reg-asterisk">*</span></label>
                                            <input type="text" name="vehicle_registration_no" id="vehicle_registration_no" class="form-control" value="{{ old('vehicle_registration_no', $receipt->vh_rgn_no ?? '') }}">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <div class="form-check d-flex align-items-center">
                                                <input type="checkbox" class="form-check-input me-2" id="vehicle_unregistered" name="vehicle_unregistered" value="1" {{ (old('vehicle_unregistered') || (isset($isEdit) && empty($receipt->vh_rgn_no) && !empty($receipt->chassis_no))) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="vehicle_unregistered">Vehicle is Unregistered</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3" id="vehicle-chassis-section" style="display:none;">
                                            <label class="form-label">Chassis No. <span class="text-danger req-chassis-asterisk">*</span></label>
                                            <input type="text" name="vehicle_chassis_no" id="vehicle_chassis_no" class="form-control" value="{{ old('vehicle_chassis_no', $receipt->chassis_no ?? '') }}">
                                        </div>
                                        <div class="col-md-4 mb-3" id="invoice-no-section" style="display:none;">
                                            <label class="form-label">Invoice No. <span class="text-danger">*</span></label>
                                            <input type="text" name="invoice_no" id="invoice_no" class="form-control" value="{{ old('invoice_no', $receipt->inv_no ?? '') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            {{-- CUSTOMER DETAILS --}}
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                    <input type="text" name="customer_name" id="customer_name" class="form-control" value="{{ old('customer_name', $receipt->customer_name ?? '') }}" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Care Of Type</label>
                                    <select name="care_of_type" id="care_of_type" class="form-control form-select">
                                        <option value="">Select Care Of Type</option>
                                        <option value="1" @selected(old('care_of_type', $receipt->care_of_type ?? '') == '1')>Son of</option>
                                        <option value="2" @selected(old('care_of_type', $receipt->care_of_type ?? '') == '2')>Daughter of</option>
                                        <option value="3" @selected(old('care_of_type', $receipt->care_of_type ?? '') == '3')>Married to</option>
                                        <option value="4" @selected(old('care_of_type', $receipt->care_of_type ?? '') == '4')>Guardian Name</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Care Of Name</label>
                                    <input type="text" name="care_of" id="care_of" class="form-control" value="{{ old('care_of', $receipt->care_of ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Mobile No. <span class="text-danger">*</span></label>
                                    <input type="text" name="mobile" id="mobile" class="form-control" value="{{ old('mobile', $receipt->mobile ?? '') }}" maxlength="10" required>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" id="address" class="form-control" value="{{ old('address', $receipt->address ?? '') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Alt Mobile No.</label>
                                    <input type="text" name="alternate_mobile" id="alternate_mobile" class="form-control" value="{{ old('alternate_mobile', $receipt->alternate_mobile ?? '') }}" maxlength="10">
                                </div>
                            </div>

                            <hr>

                            {{-- PAYMENT DETAILS --}}
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Amount (In Figures) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="1" value="{{ old('amount', $receipt->amount ?? '') }}" required>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Amount (In Words)</label>
                                    <input type="text" id="amount_in_words" class="form-control" readonly style="background-color:#e9ecef;">
                                </div>

                                {{-- PAYMENT INSTRUMENT CONDITIONAL --}}
                                <div class="col-md-4 mb-3 req-instrument" style="display:none;">
                                    <label class="form-label" id="instrument_label">Instrument No. <span class="text-danger">*</span></label>
                                    <input type="text" name="instrument_no" id="instrument_no" class="form-control" value="{{ old('instrument_no', $receipt->instrument_no ?? '') }}">
                                </div>
                                <div class="col-md-4 mb-3 req-instrument" style="display:none;">
                                    <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                                    <input type="text" id="transaction_date" name="transaction_date" class="form-control" placeholder="YYYY-MM-DD" value="{{ old('transaction_date', $receipt->trans_date ?? '') }}">
                                </div>
                                <div class="col-md-4 mb-3 req-instrument" style="display:none;">
                                    <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                                    <input type="text" name="bank_name" id="bank_name" class="form-control" value="{{ old('bank_name', $receipt->bank ?? '') }}">
                                </div>
                                <div class="col-md-4 mb-3 req-instrument" style="display:none;">
                                    <label class="form-label">Transaction ID / UTR</label>
                                    <input type="text" name="transaction_no" id="transaction_no" class="form-control" value="{{ old('transaction_no', $receipt->trans_no ?? '') }}">
                                </div>
                            </div>

                        </div>

                        <div class="card-footer bg-white text-center pb-4 border-0 mt-2">
                            <button type="submit" class="btn btn-success btn-lg px-5 py-2 shadow-sm fw-bold">
                                <i class="la la-save"></i> {{ isset($isEdit) ? 'Update Receipt' : 'Generate Receipt' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        $(function () {
            flatpickr("#receipt_date", { dateFormat: "Y-m-d", allowInput: true });
            flatpickr("#transaction_date", { dateFormat: "Y-m-d", allowInput: true, maxDate: "today" });

            function handleOnAccountOf() {
                let selectedText = $('#on_account_of option:selected').data('text') || '';

                $('.conditional-section').hide();
                $('#invoice-no-section').hide();
                
                // Reset required attrs
                $('#xceler8_enq_no').prop('required', false);
                $('#invoice_no').prop('required', false); 
                $('.req-reg-asterisk, .req-chassis-asterisk').hide();

                if (['NEW VEHICLE SALES', 'USED VEHICLE SALES'].includes(selectedText)) {
                    $('#vehicle-sales-section').show();
                    $('#xceler8_enq_no').prop('required', true);
                    $('#req_enq_asterisk').show();
                } 
                else if (['SERVICE', 'ACCIDENTAL REPAIR', 'INSURANCE RENEWAL', 'RSA', 'SHIELD', 'ACCESSORIES'].includes(selectedText)) {
                    $('#general-vehicle-section').show();
                    $('#vehicle-sales-section').show(); 
                    $('#req_enq_asterisk').hide();
                    
                    if (selectedText === 'ACCIDENTAL REPAIR') {
                        $('#invoice-no-section').show();
                        $('#invoice_no').prop('required', true);
                    }

                    if (selectedText === 'INSURANCE RENEWAL') {
                        $('.req-reg-asterisk').show();
                        $('#vehicle_unregistered').prop('disabled', true).prop('checked', false).trigger('change');
                    } else {
                        $('#vehicle_unregistered').prop('disabled', false);
                        if($('#vehicle_unregistered').is(':checked')) {
                            $('.req-chassis-asterisk').show();
                        } else {
                            $('.req-reg-asterisk').show();
                        }
                    }
                }
            }

            $('#on_account_of').on('change', handleOnAccountOf);
            
            $('#vehicle_unregistered').on('change', function () {
                if ($(this).is(':checked')) {
                    $('#vehicle-chassis-section').show();
                    $('.req-reg-asterisk').hide();
                    if($('#general-vehicle-section').is(':visible')) $('.req-chassis-asterisk').show();
                    $('#vehicle_registration_no').val('');
                } else {
                    $('#vehicle-chassis-section').hide();
                    $('.req-chassis-asterisk').hide();
                    if($('#general-vehicle-section').is(':visible')) $('.req-reg-asterisk').show();
                    $('#vehicle_chassis_no').val('');
                }
            });

            function handlePaymentMode() {
                let pMode = $('#payment_mode option:selected').data('text') || '';
                if (['CHEQUE', 'RTGS', 'NEFT', 'BANK TRANSFER', 'DEMAND DRAFT'].includes(pMode)) {
                    $('.req-instrument').show();
                    $('#instrument_no, #transaction_date, #bank_name').prop('required', true);
                    
                    if(pMode === 'CHEQUE') $('#instrument_label').html('Cheque No. <span class="text-danger">*</span>');
                    else if(pMode === 'DEMAND DRAFT') $('#instrument_label').html('DD No. <span class="text-danger">*</span>');
                    else $('#instrument_label').html('Reference / UTR No. <span class="text-danger">*</span>');

                } else {
                    $('.req-instrument').hide();
                    $('#instrument_no, #transaction_date, #bank_name').prop('required', false);
                }
            }
            $('#payment_mode').on('change', handlePaymentMode);

            // Trigger init states
            handleOnAccountOf();
            handlePaymentMode();
            if ($('#vehicle_unregistered').is(':checked')) {
                $('#vehicle-chassis-section').show();
            }

            // AJAX Fetch Enquiry Data
            // AJAX Fetch Enquiry Data
            function fetchEnquiryData() {
                let enqNo = $('#xceler8_enq_no').val();
                if(!enqNo) return;

                $('#fetch_enquiry_btn').text('Loading...').prop('disabled', true);
                
                $.ajax({
                    url: "{{ route('accounts.receipt.fetch-enquiry') }}",
                    type: "GET",
                    data: { enq_no: enqNo },
                    success: function(res) {
                        if(res.success) {
                            // OVERWRITE the fields directly without checking if they are empty
                            $('#customer_name').val(res.customer_name);
                            $('#care_of_type').val(res.care_of_type);
                            $('#care_of').val(res.care_of);
                            $('#address').val(res.address);
                            $('#mobile').val(res.mobile);
                            $('#alternate_mobile').val(res.alternate_mobile);
                            
                            $('#xceler8_booking_no').val(res.booking_no);
                            $('#votf_no').val(res.votf_no);
                            $('#vehicle_registration_no').val(res.vehicle_registration_no);
                        } else {
                            console.log("Enquiry not found or missing details.");
                            alert("Enquiry not found."); // Optional: give the user visual feedback
                        }
                    },
                    complete: function() {
                        $('#fetch_enquiry_btn').text('Fetch').prop('disabled', false);
                    }
                });
            }

            $('#xceler8_enq_no').on('blur', fetchEnquiryData);
            $('#fetch_enquiry_btn').on('click', fetchEnquiryData);

            // Number to Words Converter
            const a = ['', 'One ', 'Two ', 'Three ', 'Four ', 'Five ', 'Six ', 'Seven ', 'Eight ', 'Nine ', 'Ten ', 'Eleven ', 'Twelve ', 'Thirteen ', 'Fourteen ', 'Fifteen ', 'Sixteen ', 'Seventeen ', 'Eighteen ', 'Nineteen '];
            const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

            function inWords (num) {
                if ((num = num.toString()).length > 9) return 'overflow';
                let n = ('000000000' + num).substr(-9).match(/^(\d{2})(\d{2})(\d{2})(\d{1})(\d{2})$/);
                if (!n) return; let str = '';
                str += (n[1] != 0) ? (a[Number(n[1])] || b[n[1][0]] + ' ' + a[n[1][1]]) + 'Crore ' : '';
                str += (n[2] != 0) ? (a[Number(n[2])] || b[n[2][0]] + ' ' + a[n[2][1]]) + 'Lakh ' : '';
                str += (n[3] != 0) ? (a[Number(n[3])] || b[n[3][0]] + ' ' + a[n[3][1]]) + 'Thousand ' : '';
                str += (n[4] != 0) ? (a[Number(n[4])] || b[n[4][0]] + ' ' + a[n[4][1]]) + 'Hundred ' : '';
                str += (n[5] != 0) ? ((str != '') ? 'and ' : '') + (a[Number(n[5])] || b[n[5][0]] + ' ' + a[n[5][1]]) + 'Only' : 'Only';
                return str;
            }

            $('#amount').on('input', function() {
                let val = Math.floor($(this).val());
                if(val > 0) {
                    $('#amount_in_words').val('Rupees ' + inWords(val));
                } else {
                    $('#amount_in_words').val('');
                }
            });

            // Trigger amount in words on page load for Edit mode
            if ($('#amount').val() > 0) {
                $('#amount').trigger('input');
            }
        });
    </script>
@endpush