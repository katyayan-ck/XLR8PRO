@extends(backpack_view('blank'))

@section('title', 'Complete KYC - Booking #' . $booking->id)

@push('after_styles')
<link rel="stylesheet" href="{{ asset('plugins/select2/dist/css/select2.min.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .is-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 5px rgba(40, 167, 69, .5) !important;
    }

    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 5px rgba(220, 53, 69, .5) !important;
    }

    .required-mark {
        color: #dc3545;
        margin-left: 4px;
    }

    /* Read-only field styling similar to dms-edit */
    .readonly-field {
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
        font-weight: 500;
    }
</style>
@endpush

@section('content')

<div class="container-fluid">

    <div class="card card-body shadow-sm mb-4" style="border-radius: 12px">
        <h2 class="mb-3">
            Booking Details (Pending KYC / Payment)</h2>
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
                    {{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') : '—' }}
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
                    @if($booking->care_of)
                    (C/o: {{ $booking->care_of }})
                    @endif
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
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

            <div class="col-md-2 form-group readonly-field">
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

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Color</label>
                <div class="readonly-value">
                    {{ $booking->color_code ?? '—' }}
                </div>
            </div>

        </div>
    </div>

    <form id="kyc-form" method="POST" action="{{ route('kyc.update', $booking->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="pending_flag" value="1">

        <div class="card card-body shadow-sm" style="border-radius:12px">
            <h2 class="mb-3">
                Complete KYC</h2>

            @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert">×</button>
            </div>
            @endif

            @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-dismiss="alert">×</button>
            </div>
            @endif

            <div class="row">

                <div class="col-md-4 text-bold form-group">
                    <label>
                        PAN No.
                        <span class="required-mark">*</span>
                    </label>
                    <input type="text" name="pan_no" id="panno" class="form-control"
                        value="{{ old('pan_no', $booking->pan_no) }}" maxlength="10">
                </div>

                <div class="col-md-4 form-group">
                    <label>
                        Aadhaar No.
                        <span class="required-mark">*</span>
                    </label>
                    <input type="text" name="adhar_no" id="adharno" class="form-control"
                        value="{{ old('adhar_no', $booking->adhar_no) }}" maxlength="14">
                </div>

                <div class="col-md-4 form-group">
                    <label>GST No.</label>
                    <input type="text" name="gst_no" id="gstn" class="form-control"
                        value="{{ old('gst_no', $booking->gstn ?? '') }}" maxlength="15">
                    <div class="form-check mt-2">
                        <input type="checkbox" name="gst_not_required" id="notrequiredgst" class="form-check-input" {{
                            old('gst_not_required', ($booking->gstn === '0' || empty($booking->gstn)) ? 'checked' : '')
                        }}>
                        <label class="form-check-label">GST Not Required</label>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="la la-save"></i> Save KYC Details
                    </button>
                </div>

            </div>
        </div>
    </form>

</div>

@endsection

@push('after_scripts')
<script src="{{ asset('plugins/select2/dist/js/select2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/additional-methods.min.js"></script>

<script>
    $(document).ready(function() {

    function initMasks() {
        $('#panno').mask('AAAAA0000A', { placeholder: 'ABCDE1234F' });
        $('#adharno').mask('0000-0000-0000', { placeholder: '1234-5678-9012' });
        $('#gstn').mask('00AAAAA0000A0ZS', { placeholder: '12ABCDE1234F2ZK', clearIfNotMatch: false });
    }

    function initValidation() {
        $.validator.addMethod('panFormat', function(value, element) {
            return this.optional(element) || /^[A-Z]{5}[0-9]{4}[A-Z]$/.test(value);
        }, 'Please enter a valid PAN number e.g., ABCDE1234F');

        $.validator.addMethod('udaiFormat', function(value, element) {
            return this.optional(element) || /^[2-9]{1}[0-9]{3}[ -]?[0-9]{4}[ -]?[0-9]{4}$/.test(value);
        }, 'Please enter a valid Aadhaar No. e.g., 1234-5678-9012');

        $.validator.addMethod('gstnFormat', function(value, element) {
            return this.optional(element) || /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/.test(value);
        }, 'Please enter a valid GSTIN e.g., 08CDBPB0580N2ZK');

        $('#kyc-form').validate({
            rules: {
                pan_no: { required: true, panFormat: true },
                adhar_no: { required: true, udaiFormat: true },
                gst_no: {
                    gstnFormat: true,
                    required: function() { return !$('#notrequiredgst').is(':checked'); }
                }
            },
            messages: {
                pan_no: { required: 'PAN No. is required', panFormat: 'Please enter a valid PAN e.g., ABCDE1234F' },
                adhar_no: { required: 'Aadhaar No. is required', udaiFormat: 'Please enter a valid Aadhaar e.g., 1234-5678-9012' },
                gst_no: { required: 'GST No. is required when not marked as optional', gstnFormat: 'Please enter a valid GSTIN e.g., 08CDBPB0580N2ZK' }
            },
            errorElement: 'span',
            errorClass: 'text-danger small d-block mt-1',
            highlight: function(element) {
                $(element).removeClass('is-valid').addClass('is-invalid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid').addClass('is-valid');
            },
            submitHandler: function(form) {
                form.submit();
            }
        });
    }

    $('#notrequiredgst').on('change', function() {
        if (this.checked) {
            $('#gstn').val('0').prop('disabled', true).hide();
        } else {
            $('#gstn').val('').prop('disabled', false).show();
        }
        $('#kyc-form').validate().element('#gstn');
    }).trigger('change');

    
    $('#panno').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });

    // Initialize
    initMasks();
    initValidation();
});
</script>
@endpush