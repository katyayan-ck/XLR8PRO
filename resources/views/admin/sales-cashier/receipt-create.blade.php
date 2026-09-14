@extends(backpack_view('blank'))

@section('title', 'Add Receipt')

@push('after_styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }

        .required-mark {
            color: red;
        }

        .flatpickr-input[readonly] {
            background-color: #fff;
            cursor: pointer;
        }

        .conditional-section {
            display: none;
        }

        .section-title {
            font-size: 16px;
            font-weight: 500;
            color: #1f4b78;
            margin-bottom: 15px;
        }

        .conditional-section {
            border-top: 1px solid #e5e7eb;
            margin-top: 10px;
            padding-top: 20px;
        }
    </style>
@endpush


@section('content')

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">

                <div class="card">

                    {{-- ====================== HEADER ====================== --}}
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            Add Receipt
                        </h4>
                    </div>


                    {{-- ====================== FORM ====================== --}}
                    <form method="POST" action="{{ backpack_url('receipt') }}">

                        @csrf

                        <div class="card-body">

                            <div class="row">

                                {{-- ===================================================== --}}
                                {{-- RECEIPT NO --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Receipt No. <span class="text-danger">*</span>
                                    </label>

                                    <input type="text"
                                           name="receipt_no"
                                           class="form-control"
                                           value="{{ old('receipt_no') }}"
                                           required>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- RECEIPT DATE --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Receipt Date <span class="text-danger">*</span>
                                    </label>

                                    <input type="text"
                                           id="receipt_date"
                                           name="receipt_date"
                                           class="form-control"
                                           placeholder="YYYY-MM-DD"
                                           value="{{ old('receipt_date') }}"
                                           required>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- CUSTOMER NAME --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Customer Name <span class="text-danger">*</span>
                                    </label>

                                    <input type="text"
                                           name="customer_name"
                                           class="form-control"
                                           value="{{ old('customer_name') }}"
                                           required>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- S/O --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        S/O
                                    </label>

                                    <input type="text"
                                           name="son_of"
                                           class="form-control"
                                           value="{{ old('son_of') }}">
                                </div>


                                {{-- ===================================================== --}}
                                {{-- ADDRESS --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        Address <span class="text-danger">*</span>
                                    </label>

                                    <input type="text"
                                           name="address"
                                           class="form-control"
                                           value="{{ old('address') }}"
                                           required>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- CONTACT NO --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        Contact No. <span class="text-danger">*</span>
                                    </label>

                                    <input type="text"
                                           name="contact_no"
                                           class="form-control"
                                           value="{{ old('contact_no') }}"
                                           required>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- ON A/C OF --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">
                                        On A/c Of <span class="text-danger">*</span>
                                    </label>

                                    <select name="on_account_of"
                                            id="on_account_of"
                                            class="form-control form-select"
                                            required>

                                        <option value="">
                                            Select On A/c Of
                                        </option>

                                        <option value="Vehicle Sales"
                                            @selected(old('on_account_of') == 'Vehicle Sales')>
                                            Vehicle Sales
                                        </option>

                                        <option value="Service"
                                            @selected(old('on_account_of') == 'Service')>
                                            Service
                                        </option>

                                        <option value="Accidental Repair"
                                            @selected(old('on_account_of') == 'Accidental Repair')>
                                            Accidental Repair
                                        </option>

                                        <option value="Insurance Renewal"
                                            @selected(old('on_account_of') == 'Insurance Renewal')>
                                            Insurance Renewal
                                        </option>

                                        <option value="RSA"
                                            @selected(old('on_account_of') == 'RSA')>
                                            RSA
                                        </option>

                                        <option value="Shield"
                                            @selected(old('on_account_of') == 'Shield')>
                                            Shield
                                        </option>

                                        <option value="Accessories"
                                            @selected(old('on_account_of') == 'Accessories')>
                                            Accessories
                                        </option>

                                    </select>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- MODE OF PAYMENT --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        Mode of Payment <span class="text-danger">*</span>
                                    </label>

                                    <select name="payment_mode"
                                            class="form-control form-select"
                                            required>

                                        <option value="">
                                            Select Mode of Payment
                                        </option>

                                        <option value="Cheque"
                                            @selected(old('payment_mode') == 'Cheque')>
                                            Cheque
                                        </option>

                                        <option value="Cash Deposited in Bank"
                                            @selected(old('payment_mode') == 'Cash Deposited in Bank')>
                                            Cash Deposited in Bank
                                        </option>

                                        <option value="Cash Deposited at Counter"
                                            @selected(old('payment_mode') == 'Cash Deposited at Counter')>
                                            Cash Deposited at Counter
                                        </option>

                                        <option value="RTGS"
                                            @selected(old('payment_mode') == 'RTGS')>
                                            RTGS
                                        </option>

                                        <option value="NEFT"
                                            @selected(old('payment_mode') == 'NEFT')>
                                            NEFT
                                        </option>

                                        <option value="Bank Transfer"
                                            @selected(old('payment_mode') == 'Bank Transfer')>
                                            Bank Transfer
                                        </option>

                                        <option value="Demand Draft"
                                            @selected(old('payment_mode') == 'Demand Draft')>
                                            Demand Draft
                                        </option>

                                    </select>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- AMOUNT --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        Amount <span class="text-danger">*</span>
                                    </label>

                                    <input type="number"
                                           name="amount"
                                           class="form-control"
                                           step="0.01"
                                           min="0"
                                           value="{{ old('amount') }}"
                                           required>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- TRANSACTION DATE --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        Transaction Date <span class="text-danger">*</span>
                                    </label>

                                    <input type="text"
                                           id="transaction_date"
                                           name="transaction_date"
                                           class="form-control"
                                           placeholder="YYYY-MM-DD"
                                           value="{{ old('transaction_date') }}"
                                           required>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- INSTRUMENT NO --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        Instrument No.
                                    </label>

                                    <input type="text"
                                           name="instrument_no"
                                           class="form-control"
                                           value="{{ old('instrument_no') }}">
                                </div>


                                {{-- ===================================================== --}}
                                {{-- TRANSACTION NO --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        Transaction No.
                                    </label>

                                    <input type="text"
                                           name="transaction_no"
                                           class="form-control"
                                           value="{{ old('transaction_no') }}">
                                </div>


                                {{-- ===================================================== --}}
                                {{-- BANK NAME --}}
                                {{-- ===================================================== --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        Bank Name
                                    </label>

                                    <input type="text"
                                           name="bank_name"
                                           class="form-control"
                                           value="{{ old('bank_name') }}">
                                </div>


                                {{-- ===================================================== --}}
                                {{-- VEHICLE SALES --}}
                                {{-- ===================================================== --}}
                                <div id="vehicle-sales-section"
                                     class="col-md-12 conditional-section">

                                    <div class="section-title">
                                        Vehicle Sales Details
                                    </div>

                                    <div class="row">

                                        {{-- Xceler8 Enq No --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Xceler8 Enq No.
                                                <span class="text-danger">*</span>
                                            </label>

                                            <input type="text"
                                                   name="xceler8_enq_no"
                                                   id="xceler8_enq_no"
                                                   class="form-control"
                                                   value="{{ old('xceler8_enq_no') }}">
                                        </div>


                                        {{-- Xceler8 Booking No --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Xceler8 Booking No.
                                            </label>

                                            <input type="text"
                                                   name="xceler8_booking_no"
                                                   class="form-control"
                                                   value="{{ old('xceler8_booking_no') }}">
                                        </div>


                                        {{-- VOTF No --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                VOTF No.
                                            </label>

                                            <input type="text"
                                                   name="votf_no"
                                                   class="form-control"
                                                   value="{{ old('votf_no') }}">
                                        </div>

                                    </div>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- SERVICE --}}
                                {{-- ===================================================== --}}
                                <div id="service-section"
                                     class="col-md-12 conditional-section">

                                    <div class="section-title">
                                        Service Details
                                    </div>

                                    <div class="row">

                                        {{-- Registration No --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Registration No.
                                            </label>

                                            <input type="text"
                                                   name="service_registration_no"
                                                   class="form-control"
                                                   value="{{ old('service_registration_no') }}">
                                        </div>


                                        {{-- Unregistered --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label d-block">
                                                &nbsp;
                                            </label>

                                            <div class="form-check d-flex align-items-center">

                                                <input type="checkbox"
                                                       class="form-check-input me-2"
                                                       id="service_unregistered"
                                                       name="service_unregistered"
                                                       value="1"
                                                       {{ old('service_unregistered') ? 'checked' : '' }}
                                                       style="width: 1.3rem; height: 1.3rem;">

                                                <label class="form-check-label"
                                                       for="service_unregistered">
                                                    Vehicle is Unregistered
                                                </label>

                                            </div>
                                        </div>


                                        {{-- Chassis No --}}
                                        <div class="col-md-4 mb-3"
                                             id="service-chassis-section"
                                             style="display:none;">

                                            <label class="form-label">
                                                Chassis No.
                                            </label>

                                            <input type="text"
                                                   name="service_chassis_no"
                                                   class="form-control"
                                                   value="{{ old('service_chassis_no') }}">
                                        </div>

                                    </div>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- ACCIDENTAL REPAIR --}}
                                {{-- ===================================================== --}}
                                <div id="accidental-repair-section"
                                     class="col-md-12 conditional-section">

                                    <div class="section-title">
                                        Accidental Repair Details
                                    </div>

                                    <div class="row">

                                        {{-- Registration No --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Registration No.
                                            </label>

                                            <input type="text"
                                                   name="accident_registration_no"
                                                   class="form-control"
                                                   value="{{ old('accident_registration_no') }}">
                                        </div>


                                        {{-- Unregistered --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label d-block">
                                                &nbsp;
                                            </label>

                                            <div class="form-check d-flex align-items-center">

                                                <input type="checkbox"
                                                       class="form-check-input me-2"
                                                       id="accident_unregistered"
                                                       name="accident_unregistered"
                                                       value="1"
                                                       {{ old('accident_unregistered') ? 'checked' : '' }}
                                                       style="width: 1.3rem; height: 1.3rem;">

                                                <label class="form-check-label"
                                                       for="accident_unregistered">
                                                    Vehicle is Unregistered
                                                </label>

                                            </div>
                                        </div>


                                        {{-- Chassis No --}}
                                        <div class="col-md-4 mb-3"
                                             id="accident-chassis-section"
                                             style="display:none;">

                                            <label class="form-label">
                                                Chassis No.
                                            </label>

                                            <input type="text"
                                                   name="accident_chassis_no"
                                                   class="form-control"
                                                   value="{{ old('accident_chassis_no') }}">
                                        </div>


                                        {{-- Invoice No --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Invoice No. <span class="text-danger">*</span>
                                            </label>

                                            <input type="text"
                                                   name="invoice_no"
                                                   class="form-control"
                                                   value="{{ old('invoice_no') }}">
                                        </div>

                                    </div>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- INSURANCE RENEWAL --}}
                                {{-- ===================================================== --}}
                                <div id="insurance-renewal-section"
                                     class="col-md-12 conditional-section">

                                    <div class="section-title">
                                        Insurance Renewal Details
                                    </div>

                                    <div class="row">

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Registration No.
                                                <span class="text-danger">*</span>
                                            </label>

                                            <input type="text"
                                                   name="insurance_registration_no"
                                                   class="form-control"
                                                   value="{{ old('insurance_registration_no') }}">
                                        </div>

                                    </div>
                                </div>


                                {{-- ===================================================== --}}
                                {{-- RSA / SHIELD / ACCESSORIES --}}
                                {{-- ===================================================== --}}
                                <div id="rsa-shield-accessories-section"
                                     class="col-md-12 conditional-section">

                                    <div class="section-title">
                                        Vehicle Details
                                    </div>

                                    <div class="row">

                                        {{-- Registration No --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">
                                                Registration No.
                                            </label>

                                            <input type="text"
                                                   name="vehicle_registration_no"
                                                   class="form-control"
                                                   value="{{ old('vehicle_registration_no') }}">
                                        </div>


                                        {{-- Unregistered --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label d-block">
                                                &nbsp;
                                            </label>

                                            <div class="form-check d-flex align-items-center">

                                                <input type="checkbox"
                                                       class="form-check-input me-2"
                                                       id="vehicle_unregistered"
                                                       name="vehicle_unregistered"
                                                       value="1"
                                                       {{ old('vehicle_unregistered') ? 'checked' : '' }}
                                                       style="width: 1.3rem; height: 1.3rem;">

                                                <label class="form-check-label"
                                                       for="vehicle_unregistered">
                                                    Vehicle is Unregistered
                                                </label>

                                            </div>
                                        </div>


                                        {{-- Chassis No --}}
                                        <div class="col-md-4 mb-3"
                                             id="vehicle-chassis-section"
                                             style="display:none;">

                                            <label class="form-label">
                                                Chassis No.
                                            </label>

                                            <input type="text"
                                                   name="vehicle_chassis_no"
                                                   class="form-control"
                                                   value="{{ old('vehicle_chassis_no') }}">
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>


                        {{-- ====================== FOOTER ====================== --}}
                        <div class="card-footer">

                            <button type="submit"
                                    class="btn btn-success">
                                Save Receipt
                            </button>

                            <a href="{{ backpack_url('receipt') }}"
                               class="btn btn-secondary">
                                Cancel
                            </a>

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

            /* ============================================================
             * DATE PICKERS
             * ============================================================ */

            flatpickr("#receipt_date", {
                dateFormat: "Y-m-d",
                allowInput: true
            });

            flatpickr("#transaction_date", {
                dateFormat: "Y-m-d",
                allowInput: true
            });


            /* ============================================================
             * ON A/C OF - CONDITIONAL SECTIONS
             * ============================================================ */

            function resetConditionalFields() {

                // Hide all sections
                $('.conditional-section').hide();

                // Remove required from conditional fields
                $('#xceler8_enq_no').prop('required', false);

                // Clear chassis required states
                $('input[name="service_chassis_no"]').prop('required', false);
                $('input[name="accident_chassis_no"]').prop('required', false);
                $('input[name="insurance_registration_no"]').prop('required', false);
                $('input[name="vehicle_registration_no"]').prop('required', false);

                // Invoice
                $('input[name="invoice_no"]').prop('required', false);
            }


            function handleOnAccountOf() {

                let accountOf = $('#on_account_of').val();

                resetConditionalFields();


                /* --------------------------------------------------------
                 * VEHICLE SALES
                 * -------------------------------------------------------- */

                if (accountOf === 'Vehicle Sales') {

                    $('#vehicle-sales-section').show();

                    // Xceler8 Enq No is mandatory
                    $('#xceler8_enq_no').prop('required', true);
                }


                /* --------------------------------------------------------
                 * SERVICE
                 * -------------------------------------------------------- */

                else if (accountOf === 'Service') {

                    $('#service-section').show();

                }


                /* --------------------------------------------------------
                 * ACCIDENTAL REPAIR
                 * -------------------------------------------------------- */

                else if (accountOf === 'Accidental Repair') {

                    $('#accidental-repair-section').show();

                    // Invoice No is mandatory
                    $('input[name="invoice_no"]').prop('required', true);

                }


                /* --------------------------------------------------------
                 * INSURANCE RENEWAL
                 * -------------------------------------------------------- */

                else if (accountOf === 'Insurance Renewal') {

                    $('#insurance-renewal-section').show();

                    $('input[name="insurance_registration_no"]')
                        .prop('required', true);

                }


                /* --------------------------------------------------------
                 * RSA / SHIELD / ACCESSORIES
                 * -------------------------------------------------------- */

                else if (
                    accountOf === 'RSA' ||
                    accountOf === 'Shield' ||
                    accountOf === 'Accessories'
                ) {

                    $('#rsa-shield-accessories-section').show();

                }
            }


            /* Run when On A/c Of changes */
            $('#on_account_of').on('change', function () {
                handleOnAccountOf();
            });


            /* Run on page load - important for validation errors */
            handleOnAccountOf();


            /* ============================================================
             * SERVICE - UNREGISTERED VEHICLE
             * ============================================================ */

            $('#service_unregistered').on('change', function () {

                if ($(this).is(':checked')) {

                    $('#service-chassis-section').show();

                } else {

                    $('#service-chassis-section').hide();
                    $('input[name="service_chassis_no"]').val('');

                }

            });


            /* ============================================================
             * ACCIDENTAL REPAIR - UNREGISTERED VEHICLE
             * ============================================================ */

            $('#accident_unregistered').on('change', function () {

                if ($(this).is(':checked')) {

                    $('#accident-chassis-section').show();

                } else {

                    $('#accident-chassis-section').hide();
                    $('input[name="accident_chassis_no"]').val('');

                }

            });


            /* ============================================================
             * RSA / SHIELD / ACCESSORIES - UNREGISTERED VEHICLE
             * ============================================================ */

            $('#vehicle_unregistered').on('change', function () {

                if ($(this).is(':checked')) {

                    $('#vehicle-chassis-section').show();

                } else {

                    $('#vehicle-chassis-section').hide();
                    $('input[name="vehicle_chassis_no"]').val('');

                }

            });


            /* ============================================================
             * RESTORE CONDITIONAL FIELDS AFTER VALIDATION ERROR
             * ============================================================ */

            if ($('#service_unregistered').is(':checked')) {
                $('#service-chassis-section').show();
            }

            if ($('#accident_unregistered').is(':checked')) {
                $('#accident-chassis-section').show();
            }

            if ($('#vehicle_unregistered').is(':checked')) {
                $('#vehicle-chassis-section').show();
            }

        });
    </script>

@endpush