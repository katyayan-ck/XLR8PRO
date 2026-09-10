@extends(backpack_view('blank'))

@section('title', 'Delivery Data - Booking #' . $booking->id)

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

    .readonly-value.text-danger {
        font-weight: 600;
    }

    .preview-thumb {
        width: 32px;
        height: 32px;
        object-fit: cover;
        border-radius: 4px;
        margin-right: 8px;
    }

    .file-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background: #f8f9fa;
    }

    .icon-btn {
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 1rem;
    }

    .icon-btn.text-danger {
        color: #dc3545;
    }
</style>
@endpush

@section('content')

<div class="card card-body shadow-sm mb-4" style="border-radius:12px">
    <h2 class="mb-3">Booking & Vehicle Information (Read-only)</h2>

    <div class="row">

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Booking Date</label>
            <div class="readonly-value">
                {{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d-m-Y') : '—' }}
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
                {{ $data['branch'] ?? '—' }}
            </div>
        </div>

        <div class="col-md-3 form-group readonly-field">
            <label class="readonly-label">Location</label>
            <div class="readonly-value">
                {{ $data['location'] ?? '—' }}
            </div>
        </div>

        <div class="col-md-4 form-group readonly-field">
            <label class="readonly-label">Model</label>
            <div class="readonly-value">
                {{ $booking->model_code ?? '—' }}
            </div>
        </div>

        <div class="col-md-4 form-group readonly-field">
            <label class="readonly-label">Variant</label>
            <div class="readonly-value">
                {{ $booking->variant_code ?? '—' }}
            </div>
        </div>

        <div class="col-md-4 form-group readonly-field">
            <label class="readonly-label">Color</label>
            <div class="readonly-value">
                {{ $booking->color_code ?? '—' }}
            </div>
        </div>

    </div>
</div>


<form id="dms-form" method="POST" action="{{ route('dms.update', $booking->id) }}">
    @csrf
    @method('PUT')
    <div class="card card-body shadow-sm" style="border-radius:12px">
        <h2 class="mb-3">Pending DMS</h2>
        <div class="row">

            <div class="col-md-4 form-group">
                <label>
                    DMS Booking No.
                    <span class="required-mark">*</span>
                </label>
                <input type="text" name="dms_no" id="dms_no" class="form-control"
                    value="{{ old('dms_no', $booking->dms_no) }}" required>
            </div>

            <div class="col-md-4 form-group">
                <label>
                    DMS OTF No.
                    <span class="required-mark">*</span>
                </label>
                <input type="text" name="dms_otf" id="dms_otf" class="form-control"
                    value="{{ old('dms_otf', $booking->dms_otf) }}" required>
            </div>

            <div class="col-md-4 form-group">
                <label>
                    DMS OTF Date
                    <span class="required-mark">*</span>
                </label>
                <input type="text" name="otf_date" id="otf_date" class="form-control flatpickr-date"
                    value="{{ old('otf_date', $booking->otf_date ? \Carbon\Carbon::parse($booking->otf_date)->format('d-m-Y') : '') }}"
                    required>
                <input type="hidden" name="hidden_otf_date" id="hidden_otf_date"
                    value="{{ old('hidden_otf_date', $booking->otf_date ? $booking->otf_date : '') }}">
            </div>

            @if($data['is_bev_or_personal'])
            <div class="col-md-4 form-group">
                <label>
                    DMS SO No.
                    @if($data['so_required'])<span class="required-mark">*</span>@endif
                </label>
                <input type="text" name="dms_so" id="dms_so" class="form-control"
                    value="{{ old('dms_so', $booking->dms_so) }}" {{ $data['so_required'] ? 'required' : '' }}>
            </div>
            @endif

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="la la-save"></i> Save Order Details
                </button>
            </div>

        </div>
    </div>
</form>

@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    $(document).ready(function () {
    $("#otf_date").flatpickr({
        dateFormat: "d-m-Y",
        maxDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates[0]) {
                $('#hidden_otf_date').val(instance.formatDate(selectedDates[0], 'Y-m-d'));
            }
        }
    });

    $('#dms_no').mask('B-00000000', { placeholder: 'B-12345678' });
    $('#dms_otf').mask('OTF00A000000', { placeholder: 'OTF00A123456' });

    @if($data['is_bev_or_personal'])
        $('#dms_so').mask('0000000000', { placeholder: '0111763881' });
    @endif

    $('#dms_no, #dms_otf').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });

    $.validator.addMethod("dmsFormat", function(value, element) {
    return this.optional(element) || /^B-\d{8}$/.test(value);
}, "Format: B- followed by 8 digits");

    $.validator.addMethod("otfFormat", function(value, element) {
    return this.optional(element) || /^OTF\d{2}[A-Z]\d{6}$/.test(value);
}, "Format: OTF00A123456");

    @if($booking->order == 2)
    $.validator.addMethod("soFormat", function(value, element) {
    return this.optional(element) || /^\d{10}$/.test(value);
}, "10 digits required");
    @endif

    $('#dms-form').validate({
        rules: {
            dms_no: { required: true, dmsFormat: true },
            dms_otf: { required: true, otfFormat: true },
            otf_date: { required: true },
            @if($data['so_required'])
            dms_so: { required: true, soFormat: true }
            @endif
        },
        messages: {
            dms_no: {
                required: "DMS Booking No. is required",
                dmsFormat: "Invalid format (B-12345678)"
            },
            dms_otf: {
                required: "DMS OTF is required",
                otfFormat: "Invalid format (OTF00A123456)"
            },
            otf_date: "OTF Date is required",
            @if($data['so_required'])
            dms_so: {
                required: "DMS SO Number is mandatory for this booking",
                soFormat: "Must be exactly 10 digits"
            }
            @endif
        },
        errorElement: 'span',
        errorClass: 'text-danger small d-block mt-1',
        highlight: function(element) {
            $(element).removeClass('is-valid').addClass('is-invalid');
        },
        unhighlight: function(element) {
            $(element).removeClass('is-invalid').addClass('is-valid');
        }
    });
});
</script>
@endpush