@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h2>Pending Data for Booking #{{ $booking->sap_no ?? $booking->dms_no ?? $booking->id }}</h2>
</section>
@endsection

@section('content')
<div class="container-fluid">
    @include(backpack_view('inc.alerts'))

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif
    @if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

    <div class="card mt-4 shadow-sm">
        <div class="card-header bg-info text-black">
            <h4 class="mb-0 fw-bold">Receipt Log</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Receipt No.</th>
                            <th>Amount</th>
                            <th>Image</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($receiptLogs->isNotEmpty())
                        @foreach ($receiptLogs as $log)
                        @php $iurl = $log->getFirstMediaUrl('amount-proof') @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($log->date)->format('d-M-Y') }}</td>
                            <td>{{ $log->reciept ?? 'N/A' }}</td>
                            <td>{{ number_format($log->amount, 2) }}</td>
                            <td>
                                @if ($iurl)
                                <a href="{{ $iurl }}" target="_blank">
                                    <img src="{{ $iurl }}" class="img-fluid" width="80" alt="Receipt">
                                </a>
                                @else
                                N/A
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('receipt.edit', $log->id) }}" title="Edit Receipt"
                                    class="text-success">
                                    <i class="la la-edit fs-5"></i> Edit
                                </a>
                            </td>
                        </tr>
                        @endforeach
                        <tr class="font-weight-bold">
                            <td colspan="2" class="text-right">Total:</td>
                            <td>{{ number_format($data['total_amount'], 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        @else
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No receipts found.</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if (in_array($booking->col_type, [2, 3]) && $booking->booking_amount > ($data['total_amount'] ?? 0))
    <div class="card mt-4 shadow-sm" id="receipt-card">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0 fw-bold">Add New Receipt</h4>
        </div>
        <div class="card-body">
            <form id="receipt-form" method="POST" action="{{ route('booking.add-amount', $booking->id) }}"
                enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="bid" value="{{ $booking->id }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="fw-bold">Booking Amount</label>
                        <input type="text" class="form-control" value="{{ number_format($booking->booking_amount, 2) }}"
                            readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold">Pending Amount</label>
                        <input type="text" class="form-control"
                            value="{{ number_format($booking->booking_amount - ($data['total_amount'] ?? 0), 2) }}"
                            readonly>
                    </div>

                    <div class="col-md-3">
                        <label>Receipt No. <span class="required-mark">*</span></label>
                        <input type="text" name="reciept_no" id="reciept_no" class="form-control" required>
                        <div id="reciept_no_warning" class="text-danger small mt-1" style="display:none;">
                            Receipt No. already exists
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label>Receipt Date <span class="required-mark">*</span></label>
                        <input type="text" name="receipt_date" id="receipt_date" class="form-control flatpickr"
                            placeholder="dd-MMM-yyyy" required>
                        <input type="hidden" name="hidden_receipt_date" id="hidden_receipt_date">
                    </div>

                    <div class="col-md-3">
                        <label>Amount <span class="required-mark">*</span></label>
                        <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0.01"
                            required>
                    </div>

                    <div class="col-md-3">
                        <label>Upload Image/PDF <span class="required-mark">*</span></label>
                        <input type="file" name="amount_proof" id="amount_proof" class="form-control"
                            accept="image/jpeg,image/png,application/pdf" required>
                        <small class="form-text">JPG, PNG, PDF (max 2MB)</small>
                        <div id="imagePreviewContainer" class="mt-2 position-relative" style="display:none;">
                            <img id="frameLeft" src="" class="img-fluid" style="max-width:100px;">
                            <button type="button" id="removeImageButton" class="btn btn-danger btn-sm position-absolute"
                                style="top:-10px;right:-10px;">×</button>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-success">Add Receipt</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="card mt-4 shadow-sm" id="pending-data-card">
        <div class="card-header bg-dark text-black">
            <h4 class="mb-0 fw-bold">Pending Data</h4>
        </div>
        <div class="card-body">
            <form id="pending-form" method="POST" action="{{ route('booking.pending-update', $booking->id) }}"
                enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @if (request()->has('pending_flag'))
                <input type="hidden" name="pending_flag" value="1">
                @endif

                <div class="row g-3">
                    @if ($booking->b_mode == 'Online')
                    <div class="col-md-4">
                        <label>Online Booking Ref No. <span class="required-mark">*</span></label>
                        <input type="text" name="online_bk_ref_no" id="online_bk_ref_no" class="form-control"
                            value="{{ old('online_bk_ref_no', $booking->online_bk_ref_no) }}">
                    </div>
                    @endif

                    <div class="col-md-4">
                        <label>PAN No. <span class="required-mark">*</span></label>
                        <input type="text" name="pan_no" id="pan_no" class="form-control"
                            value="{{ old('pan_no', $booking->pan_no) }}">
                    </div>

                    <div class="col-md-4">
                        <label>Aadhar No. <span class="required-mark">*</span></label>
                        <input type="text" name="adhar_no" id="adhar_no" class="form-control"
                            value="{{ old('adhar_no', $booking->adhar_no) }}">
                    </div>

                    <div class="col-md-4">
                        <label>Sales Force Booking No. <span class="required-mark">*</span></label>
                        <input type="text" name="dms_no" id="dms_no" class="form-control"
                            value="{{ old('dms_no', $booking->dms_no) }}">
                    </div>

                    <div class="col-md-4">
                        <label>DMS OTF No. <span class="required-mark">*</span></label>
                        <input type="text" name="dms_otf" id="dms_otf" class="form-control"
                            value="{{ old('dms_otf', $booking->dms_otf) }}">
                    </div>

                    <div class="col-md-4">
                        <label>DMS OTF Date <span class="required-mark">*</span></label>
                        <input type="text" name="otf_date" id="otf_date" class="form-control flatpickr"
                            placeholder="dd-MMM-yyyy"
                            value="{{ old('otf_date', $booking->otf_date ? date('d-M-Y', strtotime($booking->otf_date)) : '') }}">
                        <input type="hidden" name="hidden_otf_date" id="hidden_otf_date"
                            value="{{ old('hidden_otf_date', $booking->otf_date) }}">
                    </div>

                    <div class="col-md-4">
                        <label>DMS SO No. <span class="required-mark">*</span></label>
                        <input type="text" name="dms_so" id="dms_so" class="form-control"
                            value="{{ old('dms_so', $booking->dms_so == 0 ? '' : $booking->dms_so) }}">
                        @if ($booking->order != 2)
                        <div class="form-check mt-2">
                            <input type="checkbox" name="not_required" id="not_required" class="form-check-input" {{
                                old('not_required', $booking->dms_so == 0) ? 'checked' : '' }}>
                            <label class="form-check-label" for="not_required">Not Required</label>
                        </div>
                        @endif
                    </div>

                    @if (request()->has('pending_flag'))
                    <div class="col-md-4">
                        <label>OEM Invoice No.</label>
                        <input type="text" name="invoice_number" id="invoice_number" class="form-control"
                            value="{{ old('invoice_number', $booking->inv_no) }}">
                    </div>
                    <div class="col-md-4">
                        <label>OEM Invoice Date</label>
                        <input type="text" name="invoice_date" id="invoice_date" class="form-control flatpickr"
                            placeholder="dd-MMM-yyyy"
                            value="{{ old('invoice_date', $booking->inv_date ? date('d-M-Y', strtotime($booking->inv_date)) : '') }}">
                        <input type="hidden" name="hidden_invoice_date" id="hidden_invoice_date"
                            value="{{ old('hidden_invoice_date', $booking->inv_date) }}">
                    </div>
                    <div class="col-md-4" id="dealerInvoiceNumberField">
                        <label>Dealer Invoice No.</label>
                        <input type="text" name="dealer_invoice_number" id="dealer_invoice_number" class="form-control">
                    </div>
                    <div class="col-md-4" id="dealerInvoiceDateField">
                        <label>Dealer Invoice Date</label>
                        <input type="text" name="dealer_invoice_date_display" id="dealer_invoice_date"
                            class="form-control flatpickr" placeholder="dd-MMM-yyyy">
                        <input type="hidden" name="dealer_invoice_date" id="hidden_dealer_invoice_date">
                    </div>
                    <div class="col-md-4">
                        <label>Chassis No. <span class="required-mark">*</span></label>
                        <input type="text" name="chassis" id="chassis" class="form-control"
                            value="{{ old('chassis', $booking->chassis_no) }}">
                    </div>
                    @endif

                    <div class="col-12 mt-4 text-center">
                        <button type="submit" id="submitBtn" class="btn btn-success btn-lg px-5">
                            {{ request()->has('pending_flag') ? 'Mark as Invoiced' : 'Update Pending Data' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="errorModalLabel">Form Errors</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="{{ asset('plugins/select2/dist/js/select2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/additional-methods.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    (function($) {
        'use strict';

        function initPendingForm() {
            initFlatpickr();
            initMasks();
            initValidation();
            setInitialState();
            bindEventListeners();
        }

        function initFlatpickr() {
            flatpickr('#receipt_date', {
                dateFormat: 'd-M-Y',
                maxDate: 'today',
                allowInput: false,
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates[0]) {
                        $('#hidden_receipt_date').val(flatpickr.formatDate(selectedDates[0], 'Y-m-d'));
                    }
                }
            });
            flatpickr('.flatpickr:not(#receipt_date)', {
                dateFormat: 'd-M-Y',
                maxDate: 'today',
                allowInput: false,
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates[0]) {
                        $(`#hidden_${instance.element.id}`).val(flatpickr.formatDate(selectedDates[0], 'Y-m-d'));
                    }
                }
            });
        }

        function initMasks() {
            $('#pan_no').mask('AAAAA0000A', {
                placeholder: 'ABCDE1234F'
            });
            $('#adhar_no').mask('0000-0000-0000', {
                placeholder: '1234-5678-9012'
            });
            $('#dms_no').mask('B-00000000', {
                placeholder: 'B-12345678'
            });
            $('#dms_otf').mask('OTF00A000000', {
                placeholder: 'OTF00A123456'
            });
            if ($('#invoice_number').length) {
                $('#invoice_number').mask('INV00A000000', {
                    placeholder: 'INV00A123456'
                });
            }
            if ($('#dealer_invoice_number').length) {
                $('#dealer_invoice_number').mask('INV00A000000', {
                    placeholder: 'INV00A123456'
                });
            }
            if ($('#dms_so').length) {
                $('#dms_so').mask('0000000000', {
                    placeholder: '0111763881'
                });
            }
            if ($('#chassis').length) {
                $('#chassis').mask('AAAAAAAA', {
                    placeholder: 'S1A12345'
                });
            }
            $('#pan_no, #adhar_no, #online_bk_ref_no, #dms_no, #dms_otf, #invoice_number, #dealer_invoice_number, #chassis, #dms_so').on('input', function() {
                $(this).val($(this).val().toUpperCase());
            });
        }

        function initValidation() {
            $.validator.addMethod('panFormat', function(value, element) {
                return this.optional(element) || /^[A-Z]{5}\d{4}[A-Z]$/.test(value);
            }, 'Please enter a valid PAN (e.g., ABCDE1234F)');

            $.validator.addMethod('udaiFormat', function(value, element) {
                return this.optional(element) || /^\d{4}-\d{4}-\d{4}$/.test(value);
            }, 'Please enter a valid Aadhar (e.g., 1234-5678-9012)');

            $.validator.addMethod('dmsFormat', function(value, element) {
                return this.optional(element) || /^B-\d{8}$/.test(value);
            }, 'Please enter a valid Sales Force Booking number (e.g., B-12345678)');

            $.validator.addMethod('otfFormat', function(value, element) {
                return this.optional(element) || /^OTF\d{2}[A-Z]\d{6}$/.test(value);
            }, 'Please enter a valid OTF number (e.g., OTF00A123456)');

            $.validator.addMethod('soFormat', function(value, element) {
                return this.optional(element) || /^\d{10}$/.test(value);
            }, 'Please enter a valid SO number (10 digits)');

            $.validator.addMethod('invoiceFormat', function(value, element) {
                return this.optional(element) || /^INV\d{2}[A-Z]\d{6}$/.test(value);
            }, 'Please enter a valid Invoice number (e.g., INV00A123456)');

            $.validator.addMethod('dealerInvoiceFormat', function(value, element) {
                return this.optional(element) || /^[A-Z]{3}\d{2}[A-Z]\d{6}$/.test(value);
            }, 'Please enter a valid Dealer Invoice number (e.g., ABC10D101010)');

            $.validator.addMethod('chassisFormat', function(value, element) {
                return this.optional(element) || /^S\d[A-Z]\d{5}$/.test(value);
            }, 'Please enter a valid Chassis number (e.g., S1A12345)');

            $('#pending-form').validate({
                rules: {
                    pan_no: {
                        required: true,
                        panFormat: true
                    },
                    adhar_no: {
                        required: true,
                        udaiFormat: true
                    },
                    online_bk_ref_no: {
                        required: function() {
                            return "{{ $booking->b_mode }}" === 'Online';
                        }
                    },
                    dms_no: {
                        required: true,
                        dmsFormat: true
                    },
                    dms_otf: {
                        required: true,
                        otfFormat: true
                    },
                    otf_date: {
                        required: true
                    },
                    dms_so: {
                        required: function() {
                            @if($booking -> order == 2)
                            return true;
                            @else
                            return !$('#not_required').is(':checked');
                            @endif
                        },
                        soFormat: true
                    },
                    invoice_number: {
                        invoiceFormat: true
                    },
                    invoice_date: {
                        required: function() {
                            return !!$('#invoice_number').val();
                        }
                    },
                    dealer_invoice_number: {
                        dealerInvoiceFormat: true
                    },
                    dealer_invoice_date: {
                        required: function() {
                            return !!$('#dealer_invoice_number').val();
                        }
                    },
                    chassis: {
                        required: true,
                        chassisFormat: true
                    }
                },
                messages: {
                    pan_no: {
                        required: 'PAN Number is required',
                        panFormat: 'Please enter a valid PAN (e.g., ABCDE1234F)'
                    },
                    adhar_no: {
                        required: 'Aadhar Number is required',
                        udaiFormat: 'Please enter a valid Aadhar (e.g., 1234-5678-9012)'
                    },
                    online_bk_ref_no: {
                        required: 'Online Booking Reference Number is required'
                    },
                    dms_no: {
                        required: 'Sales Force Booking Number is required',
                        dmsFormat: 'Please enter a valid Sales Force Booking number (e.g., B-12345678)'
                    },
                    dms_otf: {
                        required: 'DMS OTF Number is required',
                        otfFormat: 'Please enter a valid OTF number (e.g., OTF00A123456)'
                    },
                    otf_date: {
                        required: 'DMS OTF Date is required'
                    },
                    dms_so: {
                        required: 'DMS SO Number is required',
                        soFormat: 'Please enter a valid SO number (10 digits)'
                    },
                    invoice_number: {
                        invoiceFormat: 'Please enter a valid Invoice number (e.g., INV00A123456)'
                    },
                    invoice_date: {
                        required: 'Invoice Date is required when Invoice Number is provided'
                    },
                    dealer_invoice_number: {
                        dealerInvoiceFormat: 'Please enter a valid Dealer Invoice number (e.g., ABC10D101010)'
                    },
                    dealer_invoice_date: {
                        required: 'Dealer Invoice Date is required when Dealer Invoice Number is provided'
                    },
                    chassis: {
                        required: 'Chassis Number is required',
                        chassisFormat: 'Please enter a valid Chassis number (e.g., S1A12345)'
                    }
                },
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('text-danger');
                    error.insertAfter(element);
                },
                highlight: function(element) {
                    $(element).removeClass('is-valid').addClass('is-invalid');
                },
                unhighlight: function(element) {
                    $(element).removeClass('is-invalid').addClass('is-valid');
                },
                onfocusout: function(element) {
                    this.element(element);
                },
                submitHandler: function(form) {
                    // Check if invoice fields exist in the DOM
                    const invoiceFieldsExist = $('#invoice_number').length > 0 && $('#invoice_date').length > 0;
                    const dealerInvoiceFieldsExist = $('#dealer_invoice_number').length > 0 && $('#dealer_invoice_date').length > 0;

                    // Only validate invoice pairs if the fields are present
                    if (invoiceFieldsExist || dealerInvoiceFieldsExist) {
                        const normalFilled = invoiceFieldsExist && !!$('#invoice_number').val() && !!$('#invoice_date').val();
                        const dealerFilled = dealerInvoiceFieldsExist && !!$('#dealer_invoice_number').val() &&
                            !!$('#dealer_invoice_date').val();

                        if (!normalFilled && !dealerFilled) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: 'At least one complete pair (Invoice Number + Date or Dealer Invoice Number + Date) is required.',
                            });
                            return false;
                        }

                        if (normalFilled && (!$('#invoice_number').val() || !$('#invoice_date').val())) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: 'Invoice Number and Invoice Date must both be filled or both be empty.',
                            });
                            return false;
                        }

                        if (dealerFilled && (!$('#dealer_invoice_number').val() || !$('#dealer_invoice_date').val())) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                html: 'Dealer Invoice Number and Dealer Invoice Date must both be filled or both be empty.',
                            });
                            return false;
                        }
                    }

                    if ($('#pending-form').valid()) {
                        form.submit();
                    } else {
                        showErrorModal();
                        return false;
                    }
                }
            });
        }

        function setInitialState() {
            updateCardStates({
                {
                    $booking -> booking_amount - ($data['total_amount'] ?? 0)
                }
            });
            const notRequiredCheckbox = $('#not_required');
            const dmsSoInput = $('#dms_so');
            if (notRequiredCheckbox.is(':checked')) {
                dmsSoInput.hide();
                dmsSoInput.val('0');
            }
        }

        function bindEventListeners() {
            $('#not_required').on('change', function() {
                const dmsSoInput = $('#dms_so');
                if (this.checked) {
                    dmsSoInput.hide();
                    dmsSoInput.val('0');
                } else {
                    dmsSoInput.show();
                    dmsSoInput.val('');
                }
                $('#pending-form').validate().element('#dms_so');
            });

            $('#pending-form input, #pending-form select').on('input change', function() {
                if ($('#pending-form').valid()) {
                    $('#submitBtn').prop('disabled', false);
                } else {
                    $('#submitBtn').prop('disabled', true);
                }
            });

            function updateReceiptButtonState() {
                const isFormValid = $('#receipt-form').valid();
                const isReceiptNoValid = $('#reciept_no_warning').is(':hidden');
                $('#receipt-form button[type="submit"]').prop('disabled', !(isFormValid && isReceiptNoValid));
            }

            $('#receipt-form input, #receipt-form select').on('input change', function() {
                updateReceiptButtonState();
            });

            
           
            $('#reciept_no').on('change', function() {
                const rn = $(this).val().trim();
                if (rn) { // only when value hai tabhi AJAX
                    $.ajax({
                        url: "{{ url('/admin/check-receipt') }}/" + encodeURIComponent(rn), // ← yeh line change karo
                        method: 'GET',
                        success: function(data) {
                            if (data != 0) {
                                $('#reciept_no_warning').show();
                                $('#reciept_no').addClass('is-invalid');
                            } else {
                                $('#reciept_no_warning').hide();
                                $('#reciept_no').removeClass('is-invalid').addClass('is-valid');
                            }
                            updateReceiptButtonState();
                        },
                        error: function(xhr) {
                            console.error('Error checking receipt number:', xhr);
                            $('#reciept_no_warning').text('Error checking receipt number. Please try again.').show();
                            $('#reciept_no').addClass('is-invalid');
                            updateReceiptButtonState();
                        }
                    });
                } else {
                    $('#reciept_no_warning').hide();
                    $('#reciept_no').removeClass('is-invalid');
                    updateReceiptButtonState();
                }
            }).on('input', function() {
                $('#reciept_no_warning').hide();
                $('#reciept_no').removeClass('is-invalid');
                updateReceiptButtonState();
            });

            $('#amount_proof').on('change', function() {
                const file = this.files[0];
                const previewContainer = $('#imagePreviewContainer');
                const imgPreview = $('#frameLeft');
                const removeButton = $('#removeImageButton');

                imgPreview.attr('src', '').hide();
                previewContainer.hide();
                removeButton.hide();

                if (!file) {
                    $('#amount_proof').valid();
                    updateReceiptButtonState();
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Size Error',
                        text: 'File size must be under 2MB.',
                    });
                    this.value = '';
                    $('#amount_proof').valid();
                    updateReceiptButtonState();
                    return;
                }

                const fileType = file.type.toLowerCase();
                if (!['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'].includes(fileType)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Type Error',
                        text: 'Please select a JPG, JPEG, PNG, or PDF file.',
                    });
                    this.value = '';
                    $('#amount_proof').valid();
                    updateReceiptButtonState();
                    return;
                }

                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imgPreview.attr('src', e.target.result).show();
                        previewContainer.show();
                        removeButton.show();
                        $('#amount_proof').valid();
                        updateReceiptButtonState();
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.show();
                    removeButton.show();
                    $('#amount_proof').valid();
                    updateReceiptButtonState();
                }
            });

            $('#removeImageButton').on('click', function() {
                $('#amount_proof').val('');
                $('#imagePreviewContainer').css('display', 'none');
                $('#frameLeft').src = '';
                $(this).css('display', 'none');
                $('#amount_proof').valid();
                updateReceiptButtonState();
            });
        }

        function updateCardStates(pendingAmount) {
            const colType = {
                {
                    $booking -> col_type ?? 0
                }
            };
            const receiptTypes = [2, 3];

            // Receipt card logic
            if (receiptTypes.includes(colType) && pendingAmount > 0) {
                $('#receipt-card input, #receipt-card button').prop('disabled', false);
            } else {
                $('#receipt-card input, #receipt-card button').prop('disabled', true);
            }

            // Pending data card logic
            if (receiptTypes.includes(colType) && pendingAmount > 0) {
                $('#pending-data-card input:not(#not_required), #pending-data-card button').prop('disabled', true);
                $('#not_required').prop('disabled', true);
            } else {
                $('#pending-data-card input, #pending-data-card button').prop('disabled', false);
                $('#not_required').prop('disabled', false);

                if ("{{ $booking->b_mode }}" === 'Online') {
                    $('#online_bk_ref_no').prop('disabled', false);
                } else {
                    $('#online_bk_ref_no').prop('disabled', true);
                }
            }
        }

        function showErrorModal() {
            const errors = $('#pending-form').validate().errorList;
            let errorHtml = '<ul>';
            $.each(errors, function(_, error) {
                errorHtml += `<li>${error.message}</li>`;
            });
            errorHtml += '</ul>';
            Swal.fire({
                icon: 'error',
                title: 'Validation Errors',
                html: errorHtml,
            });
        }

        $(document).ready(function() {
            initPendingForm();

            const receiptCard = document.getElementById('receipt-card');
            const pendingDataCard = document.getElementById('pending-data-card');
            if (receiptCard && $('#receipt-card').is(':visible')) {
                receiptCard.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            } else if (pendingDataCard) {
                pendingDataCard.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    })(jQuery);
</script>
@endpush