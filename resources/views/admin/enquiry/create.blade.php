@extends(backpack_view('blank'))

@section('title', 'Add New Enquiry')


@push('after_styles')
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
                        <h2 class="mb-0">Add Hot Enquiry</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ backpack_url('enquiry') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="row">

                                <!-- Basic Info -->
                                <div class="col-md-3 mb-3">
                                    <label>Enquiry No <span class="required-mark">*</span></label>
                                    <input type="text" name="enquiry_no" class="form-control" required>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Enquiry Date <span class="required-mark">*</span></label>
                                    <input type="date" name="enquiry_date" class="form-control" required>
                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Lead

                                    </label>

                                    <select name="lead_no" id="lead_no" class="form-control form-select">

                                        <option value="">
                                            Create Without Lead
                                        </option>

                                        @foreach ($leads as $leadNo => $lead)
                                            <option value="{{ $leadNo }}">

                                                {{ $lead }}

                                            </option>
                                        @endforeach

                                    </select>

                                </div>


                                <!-- Person -->
                                <div class="col-md-4 mb-3">
                                    <label>Customer (Person) <span class="required-mark">*</span></label>
                                    <input name="person_code" id="person_code" class="form-control" required>
                                </div>

                                <!-- Source -->
                                <div class="col-md-3 mb-3">
                                    <label>Source <span class="required-mark">*</span></label>

                                    <select name="source_code" id="source_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Source
                                        </option>

                                        @foreach ($sources as $code => $name)
                                            <option value="{{ $code }}"
                                                {{ old('source_code') == $code ? 'selected' : '' }}>

                                                {{ $name }}

                                            </option>
                                        @endforeach

                                    </select>
                                </div>

                                <div class="col-md-5 mb-3">
                                    <label>Referral Details</label>
                                    <input type="text" name="referral_details" class="form-control">
                                </div>

                                <!-- Customer Info -->
                                <div class="col-md-3 mb-3">
                                    <label>First Name <span class="required-mark">*</span></label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" class="form-control">
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Mobile <span class="required-mark">*</span></label>
                                    <input type="text" name="mobile" class="form-control" required>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Occupation</label>
                                    <input type="text" name="occupation" class="form-control"
                                        value="{{ old('occupation') }}">
                                </div>

                                <!-- Vehicle Details -->

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Segment *
                                    </label>

                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Segment
                                        </option>

                                        @foreach ($segments as $code => $name)
                                            <option value="{{ $code }}"
                                                {{ old('segment_code') == $code ? 'selected' : '' }}>

                                                {{ $name }}

                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Model <span class="required-mark">*</span></label>
                                    <select name="model_code" id="model_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Model
                                        </option>

                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Variant <span class="required-mark">*</span></label>
                                    <select name="variant_code" id="variant_code" class="form-control form-select" required>
                                        <option value="">Select Variant...</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Color <span class="required-mark">*</span></label>
                                    <select name="color_code" id="color_code" class="form-control form-select" required>
                                        <option value="">Select Color...</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Place of Registration</label>
                                    <input type="text" name="place_of_registration" class="form-control"
                                        value="{{ old('place_of_registration') }}">
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Registration By</label>
                                    <input type="text" name="registration_by" class="form-control"
                                        value="{{ old('registration_by') }}">
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Insurance By</label>
                                    <input type="text" name="insurance_by" class="form-control"
                                        value="{{ old('insurance_by') }}">
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Expected Delivery Date</label>
                                    <input type="date" name="expected_delivery_date" class="form-control"
                                        value="{{ old('expected_delivery_date') }}">
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>DMS Enquiry No</label>
                                    <input type="text" name="dms_enquiry_no" class="form-control"
                                        value="{{ old('dms_enquiry_no') }}">
                                </div>

                                <!-- Sales Consultant -->
                                <div class="col-md-4 mb-3">
                                    <div class="form-group">
                                        <label for="saleconsultant">
                                            Sales Consultant <span class="required-mark">*</span>
                                        </label>

                                        <select name="sales_consultant_id" id="sales_consultant_id"
                                            class="form-control form-select" required>
                                            <option value="">Please Select...</option>

                                            @foreach ($saleconsultants as $consultant)
                                                <option value="{{ $consultant['person_code'] }}">
                                                    {{ $consultant['display_name'] }} - {{ $consultant['employee_code'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Status <span class="required-mark">*</span></label>
                                    <select name="status" class="form-control form-select" required>
                                        <option value="new" {{ old('status', 'new') == 'new' ? 'selected' : '' }}>New
                                        </option>
                                        <option value="in_followup"
                                            {{ old('status') == 'in_followup' ? 'selected' : '' }}>In
                                            Followup</option>
                                        <option value="quotation_sent"
                                            {{ old('status') == 'quotation_sent' ? 'selected' : '' }}>Quotation Sent</
                                                option>
                                        <option value="quotation_approved"
                                            {{ old('status') == 'quotation_approved' ? 'selected' : '' }}>Quotation
                                            Approved</option>
                                        <option value="booking_done"
                                            {{ old('status') == 'booking_done' ? 'selected' : '' }}>
                                            Booking Done</option>
                                        <option value="lost" {{ old('status') == 'lost' ? 'selected' : '' }}>Lost
                                        </option>
                                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>
                                            Cancelled</option>
                                    </select>
                                </div>

                                <div class="col-12 mb-3">
                                    <label>Lost Reason</label>
                                    <textarea name="lost_reason" class="form-control" rows="2">{{ old('lost_reason') }}</textarea>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Priority <span class="required-mark">*</span></label>
                                    <select name="priority" class="form-control form-select" required>
                                        <option value="high"
                                            {{ old('priority', 'medium') == 'high' ? 'selected' : '' }}>High
                                        </option>
                                        <option value="medium"
                                            {{ old('priority', 'medium') == 'medium' ? 'selected' : '' }}>
                                            Medium</option>
                                        <option value="low" {{ old('priority', 'medium') == 'low' ? 'selected' : '' }}>
                                            Low
                                        </option>
                                    </select>
                                </div>

                                <!-- More fields can be added below -->

                                <div class="col-12 mb-3">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"></textarea>
                                </div>

                                <div class="col-12 mb-3">
                                    <label>Conversion Notes</label>
                                    <textarea name="conversion_notes" class="form-control" rows="3">{{ old('conversion_notes') }}</textarea>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Has RSA?</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="hidden" name="has_rsa" value="0">
                                        <input type="checkbox" name="has_rsa" value="1" class="form-check-input"
                                            {{ old('has_rsa', 0) ? 'checked' : '' }}>
                                    </div>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Has Extended Warranty?</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="hidden" name="has_extended_warranty" value="0">
                                        <input type="checkbox" name="has_extended_warranty" value="1"
                                            class="form-check-input"
                                            {{ old('has_extended_warranty', 0) ? 'checked' : '' }}>
                                    </div>
                                </div>

                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="la la-save"></i> Create Enquiry
                                </button>
                                <a href="{{ backpack_url('hot-enquiry-list') }}"
                                    class="btn btn-secondary btn-lg">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        $(document).ready(function() {

            $('#model_code').prop('disabled', true);
            $('#variant_code').prop('disabled', true);
            $('#color_code').prop('disabled', true);

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

            $.ajax({

                url: "{{ backpack_url('enquiry/models') }}/" +
                    encodeURIComponent(segmentCode),

                type: "GET",

                dataType: "json",

                success: function(response) {

                    let options =
                        '<option value="">Select Model</option>';

                    $.each(response, function(code, name) {

                        options +=
                            '<option value="' +
                            code +
                            '">' +
                            name +
                            '</option>';

                    });

                    $('#model_code')
                        .html(options)
                        .prop('disabled', false);

                }

            });

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

                }

            });

        });
    </script>
@endpush
