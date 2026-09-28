@extends(backpack_view('blank'))

@section('title', isset($isEdit) ? 'Edit Journal Voucher' : 'Add Journal Voucher')

@push('after_styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); }
        .form-control:focus, .form-select:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25); }
        .section-title { font-size: 16px; font-weight: 500; color: #1f4b78; margin-bottom: 15px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px;}
        .conditional-section { display: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ isset($isEdit) ? 'Edit Journal Voucher' : 'Add Journal Voucher' }}</h4>
                    </div>

                    <form method="POST" action="{{ isset($isEdit) ? backpack_url('accounts/journal-voucher/'.$voucher->id) : backpack_url('accounts/journal-voucher') }}">
                        @csrf
                        @if(isset($isEdit)) @method('PUT') @endif

                        <div class="card-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                                </div>
                            @endif

                            <div class="row">
                                @if(isset($isEdit))
                                <div class="col-md-3 mb-3">
                                    <label class="form-label text-muted">Voucher No.</label>
                                    <input type="text" class="form-control fw-bold" style="background-color: var(--tblr-bg-surface-secondary);" value="{{ $voucher->type_number }}" readonly>
                                </div>
                                @endif

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Voucher Date <span class="text-danger">*</span></label>
                                    <input type="text" id="voucher_date" name="voucher_date" class="form-control" value="{{ old('voucher_date', isset($isEdit) ? $voucher->date : date('Y-m-d')) }}" required>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Created For Location <span class="text-danger">*</span></label>
                                    <select name="location" id="location" class="form-control form-select" required>
                                        <option value="BKN" @selected(old('location', $voucher->location ?? $userLocation) == 'BKN')>Bikaner</option>
                                        <option value="CHU" @selected(old('location', $voucher->location ?? $userLocation) == 'CHU')>Churu</option>
                                        <option value="SUJ" @selected(old('location', $voucher->location ?? $userLocation) == 'SUJ')>Sujangarh</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">On A/c Of <span class="text-danger">*</span></label>
                                    <select name="on_account_of" id="on_account_of" class="form-control form-select" required>
                                        <option value="">Select On A/c Of</option>
                                        @foreach($onAccountOfOptions as $id => $value)
                                            <option value="{{ $id }}" @selected(old('on_account_of', $voucher->account_of ?? '') == $id)>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12"><div class="section-title">Transaction Details</div></div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Xceler8 Enq No. <span class="text-danger" id="req_enq_asterisk" style="display:none;">*</span></label>
                                    <input type="text" name="xceler8_enq_no" id="xceler8_enq_no" class="form-control" value="{{ old('xceler8_enq_no', isset($voucher->enq_id) ? 'XENQ-'.$voucher->enq_id : 'XENQ-') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Xceler8 Booking No.</label>
                                    @php 
                                        $bVal = old('xceler8_booking_no', $voucher->bid ?? '');
                                        $bValWithPrefix = $bVal && !str_starts_with(strtoupper($bVal), 'XB-') ? 'XB-'.$bVal : ($bVal ?: 'XB-');
                                    @endphp
                                    <input type="text" name="xceler8_booking_no" id="xceler8_booking_no" class="form-control" value="{{ $bValWithPrefix }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">VOTF No.</label>
                                    <input type="text" name="votf_no" id="votf_no" class="form-control" placeholder="e.g. 27/BKN..." value="{{ old('votf_no', $voucher->otf_no ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary w-100 shadow-sm" id="fetch_enquiry_btn">
                                        <i class="la la-search me-1"></i> Fetch Details
                                    </button>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12"><div class="section-title">JV Category Details</div></div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">JV Category <span class="text-danger">*</span></label>
                                    <select name="jv_cat" id="jv_cat" class="form-control form-select" required>
                                        <option value="">Select Category</option>
                                        <option value="1" @selected(old('jv_cat', $voucher->jv_cat ?? '') == '1')>Used Car Purchase</option>
                                        <option value="2" @selected(old('jv_cat', $voucher->jv_cat ?? '') == '2')>Internal Transfer</option>
                                    </select>
                                </div>

                                <div class="col-md-9 row m-0 p-0 conditional-section" id="sec_used_car">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Used Car Model <span class="text-danger">*</span></label>
                                        <input type="text" name="used_model" id="used_model" class="form-control" value="{{ old('used_model', $voucher->used_model ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Used Car Reg No. <span class="text-danger">*</span></label>
                                        <input type="text" name="used_rgn_no" id="used_rgn_no" class="form-control" value="{{ old('used_rgn_no', $voucher->used_rgn_no ?? '') }}">
                                    </div>
                                </div>

                                <div class="col-md-9 row m-0 p-0 conditional-section" id="sec_internal_transfer">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">From Department <span class="text-danger">*</span></label>
                                        <input type="text" name="from_dept" id="from_dept" class="form-control" value="{{ old('from_dept', $voucher->from_dept ?? '') }}">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">To Department <span class="text-danger">*</span></label>
                                        <input type="text" name="to_dept" id="to_dept" class="form-control" value="{{ old('to_dept', $voucher->to_dept ?? '') }}">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Existing Receipt No. <span class="text-danger">*</span></label>
                                        <input type="text" name="exist_receipt_no" id="exist_receipt_no" class="form-control" value="{{ old('exist_receipt_no', $voucher->exist_receipt_no ?? '') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12"><div class="section-title">Party & Customer Information</div></div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Debit To (Party Name) <span class="text-danger">*</span></label>
                                    <input type="text" name="party_name" class="form-control" placeholder="Where to receive from..." value="{{ old('party_name', $voucher->party_name ?? '') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Credit To (Customer Name) <span class="text-danger">*</span></label>
                                    <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Where to deposit..." value="{{ old('customer_name', $voucher->name ?? $voucher->customer_name ?? '') }}" required>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Care Of Type</label>
                                    <select name="care_of_type" id="care_of_type" class="form-control form-select">
                                        <option value="">Select Care Of Type</option>
                                        <option value="1" @selected(old('care_of_type', $voucher->care_of_type ?? '') == '1')>Son of</option>
                                        <option value="2" @selected(old('care_of_type', $voucher->care_of_type ?? '') == '2')>Daughter of</option>
                                        <option value="3" @selected(old('care_of_type', $voucher->care_of_type ?? '') == '3')>Married to</option>
                                        <option value="4" @selected(old('care_of_type', $voucher->care_of_type ?? '') == '4')>Guardian Name</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Care Of Name</label>
                                    <input type="text" name="care_of" id="care_of" class="form-control" value="{{ old('care_of', $voucher->care_of ?? '') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Contact No.</label>
                                    <input type="text" name="mobile" id="mobile" class="form-control" value="{{ old('mobile', $voucher->mobile ?? '') }}" maxlength="10">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Alt Contact No.</label>
                                    <input type="text" name="alternate_mobile" id="alternate_mobile" class="form-control" value="{{ old('alternate_mobile', $voucher->alternate_mobile ?? '') }}" maxlength="10">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" id="address" class="form-control" value="{{ old('address', $voucher->address ?? '') }}">
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Registration No. (Manual)</label>
                                    <input type="text" name="vehicle_registration_no" id="vehicle_registration_no" class="form-control" value="{{ old('vehicle_registration_no', $voucher->vh_rgn_no ?? '') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Chassis No. (Manual)</label>
                                    <input type="text" name="vehicle_chassis_no" id="vehicle_chassis_no" class="form-control" value="{{ old('vehicle_chassis_no', $voucher->chassis_no ?? '') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Invoice No. (Manual)</label>
                                    <input type="text" name="invoice_no" id="invoice_no" class="form-control" value="{{ old('invoice_no', $voucher->inv_no ?? '') }}">
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12"><div class="section-title">Payment & Remarks</div></div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Mode of Payment</label>
                                    <input type="text" class="form-control" value="Journal Voucher" readonly style="background-color: var(--tblr-bg-surface-secondary);">
                                    <input type="hidden" name="payment_mode" value="{{ $jvModeId }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Amount (In Figures) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="1" value="{{ old('amount', $voucher->amount ?? '') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Amount (In Words)</label>
                                    <input type="text" id="amount_in_words" class="form-control" readonly style="background-color: var(--tblr-bg-surface-secondary);">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Remarks <span class="text-danger">*</span></label>
                                    <textarea name="remarks" class="form-control" rows="2" maxlength="150" required>{{ old('remarks', $voucher->remarks ?? '') }}</textarea>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer bg-white text-center pb-4 border-0 mt-2">
                            <button type="submit" class="btn btn-success btn-lg px-5 py-2 shadow-sm fw-bold">
                                <i class="la la-save"></i> {{ isset($isEdit) ? 'Update Journal Voucher' : 'Generate Voucher' }}
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
            flatpickr("#voucher_date", { dateFormat: "Y-m-d", allowInput: true });

            $('#xceler8_enq_no').on('input', function() {
                if (!$(this).val().toUpperCase().startsWith('XENQ-')) {
                    $(this).val('XENQ-');
                }
            });

            $('#xceler8_booking_no').on('input', function() {
                if (!$(this).val().toUpperCase().startsWith('XB-')) {
                    $(this).val('XB-');
                }
            });

            // Smart Formatter for VOTF No. (e.g., 27/BKN0001/0001)
            $('#votf_no').on('input', function(e) {
                let isDeleting = e.originalEvent && e.originalEvent.inputType === 'deleteContentBackward';
                let val = $(this).val().toUpperCase();
                
                let raw = val.replace(/[^A-Z0-9/]/g, '');
                let fy = '';
                let rest = '';
                
                if (raw.indexOf('/') > -1) {
                    fy = raw.substring(0, raw.indexOf('/')).replace(/[^0-9]/g, '').substring(0, 2);
                    rest = raw.substring(raw.indexOf('/') + 1);
                } else {
                    fy = raw.substring(0, 2).replace(/[^0-9]/g, '');
                    rest = raw.substring(2);
                }
                
                let formatted = fy;
                if (fy.length === 2) {
                    if (!isDeleting || rest.length > 0) {
                        formatted += '/';
                    }
                }
                if (rest.length > 0) {
                    formatted += rest;
                }
                $(this).val(formatted);
            });

            function handleJVCat() {
                let cat = $('#jv_cat').val();
                $('.conditional-section').hide();
                $('#used_model, #used_rgn_no, #from_dept, #to_dept, #exist_receipt_no').prop('required', false);

                if (cat === '1') {
                    $('#sec_used_car').show();
                    $('#used_model, #used_rgn_no').prop('required', true);
                } else if (cat === '2') {
                    $('#sec_internal_transfer').show();
                    $('#from_dept, #to_dept, #exist_receipt_no').prop('required', true);
                }
            }
            $('#jv_cat').on('change', handleJVCat);
            handleJVCat();

            function fetchEnquiryData() {
                let enqNo = $('#xceler8_enq_no').val();
                let bookingNo = $('#xceler8_booking_no').val();
                let votfNo = $('#votf_no').val();

                if((!enqNo || enqNo === 'XENQ-') && (!bookingNo || bookingNo === 'XB-') && !votfNo) return;

                $('#fetch_enquiry_btn').text('...').prop('disabled', true);
                
                $.ajax({
                    url: "{{ route('accounts.journal-voucher.fetch-enquiry') }}",
                    type: "GET",
                    data: { 
                        enq_no: enqNo,
                        booking_no: bookingNo,
                        votf_no: votfNo
                    },
                    success: function(res) {
                        if(res.success) {
                            $('#customer_name').val(res.customer_name);
                            $('#care_of_type').val(res.care_of_type);
                            $('#care_of').val(res.care_of);
                            $('#address').val(res.address);
                            $('#mobile').val(res.mobile);
                            $('#alternate_mobile').val(res.alternate_mobile);
                            
                            if(res.enq_id && $('#xceler8_enq_no').val() === 'XENQ-') {
                                $('#xceler8_enq_no').val('XENQ-' + res.enq_id);
                            }
                            if(res.booking_no && $('#xceler8_booking_no').val() === 'XB-') {
                                let bClean = res.booking_no.toString().replace(/XB-/i, '');
                                $('#xceler8_booking_no').val('XB-' + bClean);
                            }
                            if(res.votf_no && !$('#votf_no').val()) {
                                $('#votf_no').val(res.votf_no);
                            }
                        }
                    },
                    complete: function() {
                        $('#fetch_enquiry_btn').html('<i class="la la-search me-1"></i> Fetch Details').prop('disabled', false);
                    }
                });
            }

            $('#xceler8_enq_no, #xceler8_booking_no, #votf_no').on('blur', fetchEnquiryData);
            $('#fetch_enquiry_btn').on('click', fetchEnquiryData);

            const a = ['', 'One ', 'Two ', 'Three ', 'Four ', 'Five ', 'Six ', 'Seven ', 'Eight ', 'Nine ', 'Ten ', 'Eleven ', 'Twelve ', 'Thirteen ', 'Fourteen ', 'Fifteen ', 'Sixteen ', 'Seventeen ', 'Eighteen ', 'Nineteen '];
            const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            function inWords(num) {
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
                $('#amount_in_words').val(val > 0 ? 'Rupees ' + inWords(val) : '');
            });
            if ($('#amount').val() > 0) $('#amount').trigger('input');
        });
    </script>
@endpush