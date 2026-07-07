@extends(backpack_view('blank'))

@section('title', 'Edit Enquiry')


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
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">Edit Enquiry</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('enquiry/' . $enquiry->id) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <div class="row">

                            <!-- Basic Info -->
                            <div class="col-md-3 mb-3">
                                <label>Enquiry No <span class="required-mark">*</span></label>
                                <input type="text" name="enquiry_no" class="form-control"
                                    value="{{ old('enquiry_no', $enquiry->enquiry_no) }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Enquiry Date <span class="required-mark">*</span></label>
                                <input type="text" id="enquiry_date" name="enquiry_date" class="form-control"
                                    value="{{ old('enquiry_date', optional($enquiry->enquiry_date)->format('Y-m-d')) }}"
                                    autocomplete="off" required>
                            </div>

                            <div class="col-md-3 mb-3">

                                <label>

                                    Lead

                                </label>

                                <select name="lead_no" id="lead_no" class="form-control form-select">



                                    @foreach ($leads as $leadNo => $lead)
                                    <option value="{{ $leadNo }}" {{ old('lead_no', $enquiry->lead_no) == $leadNo ?
                                        'selected' : '' }}>

                                        {{ $lead }}

                                    </option>
                                    @endforeach

                                </select>

                            </div>


                            <!-- Person -->
                            <div class="col-md-4 mb-3">
                                <label>Customer (Person) <span class="required-mark">*</span></label>
                                <input name="person_code" id="person_code" class="form-control"
                                    value="{{ old('person_code', $enquiry->person_code) }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Source <span class="required-mark">*</span></label>

                                <select name="source_code" id="source_code" class="form-control form-select" required>

                                    <option value="">Select Source...</option>

                                    @foreach ($sources as $code => $name)
                                    <option value="{{ $code }}" {{ old('source_code', $enquiry->source_code) == $code ?
                                        'selected' : '' }}>
                                        {{ $name }}</option>
                                    @endforeach

                                </select>
                            </div>

                            <div class="col-md-5 mb-3">
                                <label>Referral Details</label>
                                <input type="text" name="referral_details" class="form-control"
                                    value="{{ old('referral_details', $enquiry->referral_details) }}">
                            </div>

                            <!-- Customer Info -->
                            <div class="col-md-3 mb-3">
                                <label>First Name <span class="required-mark">*</span></label>
                                <input type="text" name="first_name" class="form-control"
                                    value="{{ old('first_name', $enquiry->first_name) }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control"
                                    value="{{ old('last_name', $enquiry->last_name) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Mobile <span class="required-mark">*</span></label>
                                <input type="text" name="mobile" class="form-control"
                                    value="{{ old('mobile', $enquiry->mobile) }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $enquiry->email) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Occupation</label>

                                <input type="text" name="occupation" class="form-control"
                                    value="{{ old('occupation', $enquiry->occupation) }}">
                            </div>

                            <!-- Vehicle Details -->

                            <div class="col-md-3 mb-3">
                                <label>Segment <span class="required-mark">*</span></label>

                                <select name="segment_code" id="segment_code" class="form-control form-select" required>

                                    <option value="">Select Segment</option>

                                    @foreach ($segments as $code => $name)
                                    <option value="{{ $code }}" {{ old('segment_code', $enquiry->segment_code) == $code
                                        ? 'selected' : '' }}>

                                        {{ $name }}

                                    </option>
                                    @endforeach

                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Model <span class="required-mark">*</span></label>
                                <select name="model_code" id="model_code" class="form-control form-select" required>

                                    <option value="">Select Model...</option>

                                    @foreach ($models as $code => $name)
                                    <option value="{{ $code }}" {{ old('model_code', $enquiry->model_code) == $code ?
                                        'selected' : '' }}>

                                        {{ $name }}

                                    </option>
                                    @endforeach

                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Variant <span class="required-mark">*</span></label>
                                <select name="variant_code" id="variant_code" class="form-control form-select" required>

                                    <option value="">Select Variant...</option>

                                    @foreach ($variants as $code => $name)
                                    <option value="{{ $code }}" {{ old('variant_code', $enquiry->variant_code) == $code
                                        ? 'selected' : '' }}>

                                        {{ $name }}

                                    </option>
                                    @endforeach

                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Color <span class="required-mark">*</span></label>
                                <select name="color_code" id="color_code" class="form-control form-select" required>

                                    <option value="">Select Color...</option>

                                    @foreach ($colors as $code => $name)
                                    <option value="{{ $code }}" {{ old('color_code', $enquiry->color_code) == $code ?
                                        'selected' : '' }}>

                                        {{ $name }}

                                    </option>
                                    @endforeach

                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Place of Registration</label>
                                <input type="text" name="place_of_registration" class="form-control"
                                    value="{{ old('place_of_registration', $enquiry->place_of_registration) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Registration By</label>
                                <input type="text" name="registration_by" class="form-control"
                                    value="{{ old('registration_by', $enquiry->registration_by) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Insurance By</label>
                                <input type="text" name="insurance_by" class="form-control"
                                    value="{{ old('insurance_by', $enquiry->insurance_by) }}">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Expected Delivery Date</label>

                                <input type="text" id="expected_delivery_date" name="expected_delivery_date"
                                    class="form-control"
                                    value="{{ old('expected_delivery_date', optional($enquiry->expected_delivery_date)->format('Y-m-d')) }}"
                                    autocomplete="off">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>DMS Enquiry No</label>
                                <input type="text" name="dms_enquiry_no" class="form-control"
                                    value="{{ old('dms_enquiry_no', $enquiry->dms_enquiry_no) }}">
                            </div>

                            <!-- Sales Consultant -->
                            <div class="col-md-3 mb-3">
                                <label>Sales Consultant <span class="required-mark">*</span></label>

                                <select name="sales_consultant_id" id="sales_consultant_id"
                                    class="form-control form-select" required>

                                    <option value="">Please Select...</option>

                                    @foreach ($saleconsultants as $consultant)
                                    <option value="{{ $consultant['person_code'] }}" {{ old('sales_consultant_id',
                                        $enquiry->sales_consultant_id ?? '') == $consultant['person_code'] ? 'selected'
                                        : '' }}>

                                        {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}

                                    </option>
                                    @endforeach

                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Status <span class="required-mark">*</span></label>

                                <select name="status" class="form-control form-select" required>

                                    <option value="new" {{ old('status', $enquiry->status) == 'new' ? 'selected' : ''
                                        }}>
                                        New
                                    </option>

                                    <option value="in_followup" {{ old('status', $enquiry->status) == 'in_followup' ?
                                        'selected' : '' }}>
                                        In Followup
                                    </option>

                                    <option value="quotation_sent" {{ old('status', $enquiry->status) ==
                                        'quotation_sent' ? 'selected' : '' }}>
                                        Quotation Sent
                                    </option>

                                    <option value="quotation_approved" {{ old('status', $enquiry->status) ==
                                        'quotation_approved' ? 'selected' : '' }}>
                                        Quotation Approved
                                    </option>

                                    <option value="booking_done" {{ old('status', $enquiry->status) == 'booking_done' ?
                                        'selected' : '' }}>
                                        Booking Done
                                    </option>

                                    <option value="lost" {{ old('status', $enquiry->status) == 'lost' ? 'selected' : ''
                                        }}>
                                        Lost
                                    </option>

                                    <option value="cancelled" {{ old('status', $enquiry->status) == 'cancelled' ?
                                        'selected' : '' }}>
                                        Cancelled
                                    </option>

                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label>Lost Reason</label>

                                <textarea name="lost_reason" class="form-control"
                                    rows="2">{{ old('lost_reason', $enquiry->lost_reason) }}</textarea>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Priority <span class="required-mark">*</span></label>

                                <select name="priority" class="form-control form-select" required>

                                    <option value="high" {{ old('priority', $enquiry->priority) == 'high' ? 'selected' :
                                        '' }}>
                                        High
                                    </option>

                                    <option value="medium" {{ old('priority', $enquiry->priority) == 'medium' ?
                                        'selected' : '' }}>
                                        Medium
                                    </option>

                                    <option value="low" {{ old('priority', $enquiry->priority) == 'low' ? 'selected' :
                                        '' }}>
                                        Low
                                    </option>

                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label>Notes</label>

                                <textarea name="notes" class="form-control"
                                    rows="3">{{ old('notes', $enquiry->notes) }}</textarea>
                            </div>

                            <div class="col-12 mb-3">
                                <label>Conversion Notes</label>

                                <textarea name="conversion_notes" class="form-control"
                                    rows="3">{{ old('conversion_notes', $enquiry->conversion_notes) }}</textarea>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Has RSA?</label>

                                <div class="form-check form-switch mt-2">

                                    <input type="hidden" name="has_rsa" value="0">

                                    <input type="checkbox" name="has_rsa" value="1" class="form-check-input" {{
                                        old('has_rsa', $enquiry->has_rsa) ? 'checked' : '' }}>

                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Has Extended Warranty?</label>

                                <div class="form-check form-switch mt-2">

                                    <input type="hidden" name="has_extended_warranty" value="0">

                                    <input type="checkbox" name="has_extended_warranty" value="1"
                                        class="form-check-input" {{ old('has_extended_warranty',
                                        $enquiry->has_extended_warranty) ? 'checked' : '' }}>

                                </div>
                            </div>

                            <div class="mt-4">

                                <button type="submit" class="btn btn-success btn-lg px-5">

                                    <i class="la la-save"></i>

                                    Update Enquiry

                                </button>
                                <a href="{{ backpack_url('enquiry') }}" class="btn btn-secondary btn-lg">Cancel</a>

                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    $(document).ready(function() {
        flatpickr("#enquiry_date", {
            dateFormat: "Y-m-d",
            allowInput: false,
            maxDate: "today"
        });
        flatpickr("#expected_delivery_date", {
            dateFormat: "Y-m-d",
            allowInput: false,
            minDate: "today"
        });

            $('#lead_no').on('change', function() {

                let leadNo = $(this).val();

                if (leadNo == '') {

                    $('#source_code').val('');
                    $('#referral_details').val('');
                    $('#first_name').val('');
                    $('#last_name').val('');
                    $('#mobile').val('');
                    $('#email').val('');
                    $('#occupation').val('');
                    $('#model_code').val('');

                    $('#variant_code')
                        .html('<option value="">Select Variant</option>');

                    $('#color_code')
                        .html('<option value="">Select Color</option>');

                    return;

                }

                $.get(
                    "{{ backpack_url('enquiry/lead') }}/" +
                    encodeURIComponent(leadNo),

                    function(lead) {

                        $('#source_code').val(lead.source_code);

                        $('input[name="referral_details"]').val(lead.referral_details);

                        $('input[name="first_name"]').val(lead.first_name);

                        $('input[name="last_name"]').val(lead.last_name);

                        $('input[name="mobile"]').val(lead.mobile);

                        $('input[name="email"]').val(lead.email);

                        $('input[name="occupation"]').val(lead.occupation);

                        $('#segment_code')
                            .val(lead.segment_code)
                            .trigger('change');

                        setTimeout(function() {

                            $('#model_code')
                                .val(lead.model_code)
                                .trigger('change');

                            setTimeout(function() {

                                $('#variant_code')
                                    .val(lead.variant_code)
                                    .trigger('change');

                                setTimeout(function() {

                                    $('#color_code')
                                        .val(lead.color_code);

                                }, 300);

                            }, 300);

                        }, 300);

                    }

                );

            });

        });

        $(document).ready(function() {

            if (!$('#model_code').val()) {
                $('#variant_code').prop('disabled', true);
            }

            if (!$('#variant_code').val()) {
                $('#color_code').prop('disabled', true);
            }

        });

        // Load Sources on page load
        $(document).ready(function() {

            $('#model_code').prop('disabled', false);
            $('#variant_code').prop('disabled', !$('#model_code').val());
            $('#color_code').prop('disabled', !$('#variant_code').val());

            $.ajax({
                url: "{{ backpack_url('enquiry/sources') }}",
                type: "GET",
                dataType: "json",
                success: function(response) {
                    let options = '<option value="">Select Source...</option>';

                    $.each(response, function(code, name) {
                        options += `<option value="${code}">${name}</option>`;
                    });

                    $('#source_code').html(options);
                },
                error: function() {
                    $('#source_code').html('<option value="">Failed to load sources</option>');
                }
            });

        });


        $('#segment_code').on('change', function() {

            let segmentCode = $(this).val();

            $('#model_code')
                .prop('disabled', true)
                .html('<option value="">Loading...</option>');

            $('#variant_code')
                .prop('disabled', true)
                .html('<option value="">Select Variant</option>');

            $('#color_code')
                .prop('disabled', true)
                .html('<option value="">Select Color</option>');

            if (segmentCode == '') {

                $('#model_code')
                    .html('<option value="">Select Model</option>');

                return;
            }

            $.get(
                "{{ backpack_url('enquiry/models') }}/" +
                encodeURIComponent(segmentCode),

                function(response) {

                    let options = '<option value="">Select Model</option>';

                    $.each(response, function(code, name) {

                        options +=
                            `<option value="${code}">${name}</option>`;

                    });

                    $('#model_code')
                        .html(options)
                        .prop('disabled', false);

                }
            );

        });


        $('#model_code').on('change', function() {

            let modelCode = $(this).val();

            $('#variant_code')
                .prop('disabled', true)
                .html('<option value="">Loading...</option>');

            $('#color_code')
                .prop('disabled', true)
                .html('<option value="">Select Color</option>');

            if (modelCode == '') {

                $('#variant_code')
                    .html('<option value="">Select Variant</option>');

                return;

            }

            $.ajax({

                url: "{{ backpack_url('enquiry/variants') }}/" +
                    encodeURIComponent(modelCode),

                type: "GET",

                dataType: "json",

                success: function(response) {

                    let options =
                        '<option value="">Select Variant</option>';

                    $.each(response, function(code, name) {

                        options +=
                            '<option value="' +
                            code +
                            '">' +
                            name +
                            '</option>';

                    });

                    $('#variant_code')
                        .html(options)
                        .prop('disabled', false);

                },

                error: function() {

                    $('#variant_code')
                        .html('<option value="">No Variant Found</option>');

                }

            });

        });

        $('#variant_code').on('change', function() {

            let variantCode = $(this).val();

            $('#color_code')
                .prop('disabled', true)
                .html('<option value="">Loading...</option>');

            if (variantCode == '') {

                $('#color_code')
                    .html('<option value="">Select Color</option>');

                return;

            }

            $.ajax({

                url: "{{ backpack_url('enquiry/colors') }}/" +
                    encodeURIComponent(variantCode),

                type: "GET",

                dataType: "json",

                success: function(response) {

                    let options =
                        '<option value="">Select Color</option>';

                    $.each(response, function(code, name) {

                        options +=
                            '<option value="' +
                            code +
                            '">' +
                            name +
                            '</option>';

                    });

                    $('#color_code')
                        .html(options)
                        .prop('disabled', false);

                },

                error: function() {

                    $('#color_code')
                        .html('<option value="">No Color Found</option>');

                }

            });

        });
</script>
@endpush