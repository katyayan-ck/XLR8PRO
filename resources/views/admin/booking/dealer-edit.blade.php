@extends(backpack_view('blank'))

@section('title', 'Dealer Invoice - Booking #' . $booking->id)

@push('after_styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .required-mark {
        color: #dc3545;
        margin-left: 4px;
    }

    .form-group.readonly-field {
        margin-bottom: 1.25rem;
    }

    .readonly-label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.35rem;
        display: block;
    }

    .readonly-value {
        padding: 0.375rem 0.75rem;
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        min-height: 38px;
        display: flex;
        align-items: center;
    }
</style>
@endpush

@section('content')

<div class="card card-body shadow-sm mb-4" style="border-radius:12px">
    <h2 class="mb-3">Invoice Details</h2>
    <div class="row">
        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">XB No.</label>
            <div class="readonly-value">
                {{ $booking->id ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Booking Date</label>
            <div class="readonly-value">
                {{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d-m-Y') : '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">DMS OTF No.</label>
            <div class="readonly-value">
                {{ $booking->dms_otf ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Customer Name</label>
            <div class="readonly-value">
                {{ $booking->name ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Branch</label>
            <div class="readonly-value">
                {{ $booking->branch ? ($booking->branch->name ?? $booking->branch->abbr ?? '—') : '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Location</label>
            <div class="readonly-value">
                @if($booking->location_code)
                {{ $booking->location?->name ?? '—' }}
                @else
                {{ $booking->location_other ?: '—' }}
                @endif
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Model</label>
            <div class="readonly-value">
                {{ $booking->model_code ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Variant</label>
            <div class="readonly-value">
                {{ $booking->variant_code ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Color</label>
            <div class="readonly-value">
                {{ $booking->color_code ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Chassis No.</label>
            <div class="readonly-value">
                {{ $booking->chassis_no ?? $booking->chassis_no ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Dealer Invoice No.</label>
            <div class="readonly-value">
                {{ $booking->dealer_inv_no ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Dealer Invoice Date</label>
            <div class="readonly-value">
                {{ $booking->dealer_inv_date
                ? \Carbon\Carbon::parse($booking->dealer_inv_date)->format('d-m-Y')
                : '—' }}
            </div>
        </div>
    </div>
</div>

<form id="dealer-invoice-form" method="POST" action="{{ $saveAction }}">
    @csrf
    @method('PUT')

    <div class="card card-body shadow-sm" style="border-radius:12px">
        <h2 class="mb-3">Dealer Invoice Details - Booking </h2>

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">×</span>
            </button>
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">×</span>
            </button>
        </div>
        @endif

        <div class="row">
            <div class="col-md-3 form-group">
                <label>
                    DMS Invoice No.
                    <span class="required-mark">*</span>
                </label>
                <input type="text" name="dms_invoice_number" id="dms_invoice_number" class="form-control"
                    value="{{ old('dms_invoice_number') }}" placeholder="INV00A123456" required>
                @error('dms_invoice_number')
                <span class="text-danger small d-block mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-3 form-group">
                <label>
                    DMS Invoice Date
                    <span class="required-mark">*</span>
                </label>
                <input type="text" name="dms_invoice_date_display" id="dms_invoice_date"
                    class="form-control flatpickr-date" value="{{ old('dms_invoice_date_display') }}"
                    placeholder="dd-MMM-yyyy" required>
                <input type="hidden" name="dms_invoice_date" id="hidden_dms_invoice_date"
                    value="{{ old('dms_invoice_date') }}">
                @error('dms_invoice_date')
                <span class="text-danger small d-block mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div class="col-md-3 form-group readonly-field">
                <label class="readonly-label">Dealer Invoice Number</label>
                <div class="readonly-value">
                    {{ $booking->dealer_inv_no ?? 'N/A' }}
                </div>
            </div>

            <div class="col-md-3 form-group readonly-field">
                <label class="readonly-label">Dealer Invoice Date</label>
                <div class="readonly-value">
                    {{ $booking->dealer_inv_date ? \Carbon\Carbon::parse($booking->dealer_inv_date)->format('d-M-Y') :
                    'N/A' }}
                </div>
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                    Submit Dealer Invoice
                </button>
            </div>
        </div>
    </div>
</form>

@endsection

@push('after_scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    (function($) {
    'use strict';

    function initDealerInvoiceForm() {
        flatpickr("#dms_invoice_date", {
            dateFormat: "d-M-Y",
            maxDate: "today",
            allowInput: false,
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const d = selectedDates[0];
                    const formatted = d.getFullYear() + '-' +
                                     String(d.getMonth() + 1).padStart(2, '0') + '-' +
                                     String(d.getDate()).padStart(2, '0');
                    $("#hidden_dms_invoice_date").val(formatted);
                }
            }
        });

        $('#dms_invoice_number').mask('INV00A000000', {
            placeholder: "INV00A123456"
        });

        $('#dms_invoice_number').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });

        $.validator.addMethod("dmsInvoiceFormat", function(value, element) {
            return this.optional(element) || /^INV\d{2}[A-Z]\d{6}$/.test(value);
        }, "Please enter valid DMS Invoice number (e.g., INV00A123456)");

        
        $("#dealer-invoice-form").validate({
            rules: {
                dms_invoice_number: {
                    required: true,
                    dmsInvoiceFormat: true,
                    minlength: 12,
                    maxlength: 12
                },
                dms_invoice_date_display: {
                    required: true
                }
            },
            messages: {
                dms_invoice_number: {
                    required: "DMS Invoice Number is required",
                    dmsInvoiceFormat: "Please enter valid DMS Invoice number (e.g., INV00A123456)",
                    minlength: "Must be exactly 12 characters",
                    maxlength: "Must be exactly 12 characters"
                },
                dms_invoice_date_display: {
                    required: "DMS Invoice Date is required"
                }
            },
            errorElement: "span",
            errorClass: "text-danger small d-block mt-1",
            highlight: function(element) {
                $(element).addClass("is-invalid");
            },
            unhighlight: function(element) {
                $(element).removeClass("is-invalid");
            }
        });
    }

    $(document).ready(function() {
        initDealerInvoiceForm();
        document.getElementById("dealer-invoice-form")?.scrollIntoView({ behavior: "smooth", block: "start" });
    });

})(jQuery);
</script>
@endpush