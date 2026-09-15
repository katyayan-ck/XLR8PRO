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
                        <h4 class="card-title mb-0">
                            {{ isset($isEdit) ? 'Edit Receipt' : 'Add Receipt' }}
                        </h4>
                    </div>

                    <form method="POST" action="{{ isset($isEdit) ? backpack_url('receipt/'.$receipt->id) : backpack_url('receipt') }}">
                        @csrf
                        @if(isset($isEdit))
                            @method('PUT')
                        @endif

                        <div class="card-body">
                            <div class="row">

                                {{-- RECEIPT NO --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Receipt No. <span class="text-danger">*</span></label>
                                    <input type="text" name="receipt_no" class="form-control" value="{{ old('receipt_no', $receipt->type_number ?? '') }}" required>
                                </div>

                                {{-- RECEIPT DATE --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                                    <input type="text" id="receipt_date" name="receipt_date" class="form-control" placeholder="YYYY-MM-DD" value="{{ old('receipt_date', $receipt->date ?? '') }}" required>
                                </div>

                                {{-- CUSTOMER NAME --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name', $receipt->customer_name ?? '') }}">
                                </div>

                                {{-- CARE OF --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Care Of</label>
                                    <input type="text" name="care_of" class="form-control" value="{{ old('care_of', $receipt->care_of ?? '') }}">
                                </div>

                                {{-- ADDRESS --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" class="form-control" value="{{ old('address', $receipt->address ?? '') }}">
                                </div>

                                {{-- CONTACT NO --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Contact No.</label>
                                    <input type="text" name="contact_no" class="form-control" value="{{ old('contact_no', $receipt->contact_no ?? '') }}">
                                </div>

                                {{-- ON A/C OF --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">On A/c Of <span class="text-danger">*</span></label>
                                    <select name="on_account_of" id="on_account_of" class="form-control form-select" required>
                                        <option value="">Select On A/c Of</option>
                                        @foreach($onAccountOfOptions as $id => $value)
                                            <option value="{{ $id }}" data-text="{{ $value }}" @selected(old('on_account_of', $receipt->ACC_OF ?? '') == $id)>
                                                {{ $value }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- MODE OF PAYMENT --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Mode of Payment <span class="text-danger">*</span></label>
                                    <select name="payment_mode" class="form-control form-select" required>
                                        <option value="">Select Mode of Payment</option>
                                        @foreach($paymentModeOptions as $id => $value)
                                            <option value="{{ $id }}" @selected(old('payment_mode', $receipt->MOP ?? '') == $id)>
                                                {{ $value }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- AMOUNT --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0" value="{{ old('amount', $receipt->amount ?? '') }}" required>
                                </div>

                                {{-- TRANSACTION DATE --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Transaction Date</label>
                                    <input type="text" id="transaction_date" name="transaction_date" class="form-control" placeholder="YYYY-MM-DD" value="{{ old('transaction_date', $receipt->trans_date ?? '') }}">
                                </div>

                                {{-- INSTRUMENT NO --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Instrument No.</label>
                                    <input type="text" name="instrument_no" class="form-control" value="{{ old('instrument_no', $receipt->instrument_no ?? '') }}">
                                </div>

                                {{-- TRANSACTION NO --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Transaction No.</label>
                                    <input type="text" name="transaction_no" class="form-control" value="{{ old('transaction_no', $receipt->trans_no ?? '') }}">
                                </div>

                                {{-- BANK NAME --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $receipt->bank ?? '') }}">
                                </div>

                                {{-- VEHICLE SALES SECTION --}}
                                <div id="vehicle-sales-section" class="col-md-12 conditional-section">
                                    <div class="section-title">Vehicle Sales Details</div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Xceler8 Enq No. <span class="text-danger">*</span></label>
                                            <input type="text" name="xceler8_enq_no" id="xceler8_enq_no" class="form-control" value="{{ old('xceler8_enq_no', $receipt->enq_id ?? '') }}">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Xceler8 Booking No.</label>
                                            <input type="text" name="xceler8_booking_no" class="form-control" value="{{ old('xceler8_booking_no', $receipt->bid ?? '') }}">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">VOTF No.</label>
                                            <input type="text" name="votf_no" class="form-control" value="{{ old('votf_no', $receipt->otf_no ?? '') }}">
                                        </div>
                                    </div>
                                </div>

                                {{-- SERVICE & OTHER CONDITIONAL SECTIONS --}}
                                <div id="general-vehicle-section" class="col-md-12 conditional-section">
                                    <div class="section-title">Vehicle Details</div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Registration No.</label>
                                            <input type="text" name="vehicle_registration_no" class="form-control" value="{{ old('vehicle_registration_no', $receipt->vh_rgn_no ?? '') }}">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <div class="form-check d-flex align-items-center">
                                                <input type="checkbox" class="form-check-input me-2" id="vehicle_unregistered" name="vehicle_unregistered" value="1" {{ old('vehicle_unregistered') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="vehicle_unregistered">Vehicle is Unregistered</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3" id="vehicle-chassis-section" style="display:none;">
                                            <label class="form-label">Chassis No.</label>
                                            <input type="text" name="vehicle_chassis_no" class="form-control" value="{{ old('vehicle_chassis_no', $receipt->chassis_no ?? '') }}">
                                        </div>
                                        <div class="col-md-4 mb-3" id="invoice-no-section" style="display:none;">
                                            <label class="form-label">Invoice No. <span class="text-danger">*</span></label>
                                            <input type="text" name="invoice_no" class="form-control" value="{{ old('invoice_no') }}">
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-success">Save Receipt</button>
                            <a href="{{ backpack_url('receipt') }}" class="btn btn-secondary">Cancel</a>
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
            flatpickr("#transaction_date", { dateFormat: "Y-m-d", allowInput: true });

            function handleOnAccountOf() {
                let selectedText = $('#on_account_of option:selected').data('text') || $('#on_account_of option:selected').text();
                selectedText = selectedText.trim();

                $('.conditional-section').hide();
                $('#invoice-no-section').hide();
                $('#xceler8_enq_no').prop('required', false);

                if (selectedText === 'Vehicle Sales') {
                    $('#vehicle-sales-section').show();
                    $('#xceler8_enq_no').prop('required', true);
                } else if (['Service', 'Accidental Repair', 'Insurance Renewal', 'RSA', 'Shield', 'Accessories'].includes(selectedText)) {
                    $('#general-vehicle-section').show();
                    if (selectedText === 'Accidental Repair') {
                        $('#invoice-no-section').show();
                    }
                }
            }

            $('#on_account_of').on('change', handleOnAccountOf);
            handleOnAccountOf();

            $('#vehicle_unregistered').on('change', function () {
                if ($(this).is(':checked')) {
                    $('#vehicle-chassis-section').show();
                } else {
                    $('#vehicle-chassis-section').hide();
                    $('input[name="vehicle_chassis_no"]').val('');
                }
            });

            if ($('#vehicle_unregistered').is(':checked')) {
                $('#vehicle-chassis-section').show();
            }
        });
    </script>
@endpush