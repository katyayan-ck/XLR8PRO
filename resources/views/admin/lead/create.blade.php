@extends(backpack_view('blank'))

@section('title', 'Add New Lead')

@push('after_styles')

    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .08);
        }

        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .25);
        }

        .readonly-field {
            background: #f8f9fa;
        }
    </style>

@endpush

@section('content')

    <div class="container-fluid">

        <div class="row">

            <div class="col-12">

                <div class="card">

                    <div class="card-header text-black">

                        <h2 class="mb-0">

                            Add New Lead

                        </h2>

                    </div>

                    <div class="card-body">

                        <form method="POST" action="{{ backpack_url('lead') }}">

                            @csrf

                            <div class="row">

                                <div class="col-md-3 mb-3">

                                    <label>

                                        First Name

                                          

                                    </label>

                                    <input type="text" name="first_name" class="form-control"
                                        value="{{ old('first_name') }}" required>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Last Name

                                    </label>

                                    <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Email

                                    </label>

                                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Contact No.

                                          

                                    </label>

                                    <input type="text" name="mobile" maxlength="10" class="form-control" required>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Occupation

                                    </label>

                                    <input type="text" name="occupation" class="form-control"
                                        value="{{ old('occupation') }}">

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Segment
                                    </label>

                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Segment
                                        </option>

                                        @foreach($segments as $code => $name)

                                            <option value="{{ $code }}" {{ old('segment_code') == $code ? 'selected' : '' }}>

                                                {{ $name }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Model

                                          

                                    </label>

                                    <select name="model_code" id="model_code" class="form-control form-select" required>

                                        <option value="">Select Model</option>

                                        @foreach($models as $code => $name)

                                            <option value="{{ $code }}">

                                                {{ $name }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Variant

                                          

                                    </label>

                                    <select name="variant_code" id="variant_code" class="form-control form-select" required>

                                        <option value="">Select Variant</option>

                                    </select>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Color

                                          

                                    </label>
                                    <select name="color_code" id="color_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Color
                                        </option>

                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Source


                                    </label>

                                    <select name="source_code" id="source_code" class="form-control form-select" required>

                                        <option value="">

                                            Select Source

                                        </option>

                                        @foreach($sources as $code => $name)

                                            <option value="{{ $code }}" {{ old('source_code') == $code ? 'selected' : '' }}>

                                                {{ $name }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>




                                <div class="col-md-3 mb-3">

                                    <label>

                                        Referred By

                                    </label>

                                    <input type="text" name="referral_details" class="form-control"
                                        value="{{ old('referral_details') }}">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Expected Delivery Date

                                    </label>

                                    <input type="date" name="expected_delivery_date" class="form-control">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label>

                                        Priority

                                    </label>

                                    <select name="priority" class="form-control form-select">

                                        <option value="medium">

                                            Medium

                                        </option>

                                        <option value="high">

                                            High

                                        </option>

                                        <option value="low">

                                            Low

                                        </option>

                                    </select>

                                </div>



                                <div class="col-md-12 mb-3">

                                    <label>

                                        Notes

                                    </label>

                                    <textarea name="notes" rows="4" class="form-control"></textarea>

                                </div>

                                </>

                                <div class="mt-4">

                                    <button type="submit" class="btn btn-success btn-lg px-5">

                                        <i class="la la-save"></i>

                                        Create Lead

                                    </button>

                                    <a href="{{ backpack_url('lead') }}" class="btn btn-secondary btn-lg">

                                        Cancel

                                    </a>

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

        $(document).ready(function () {

            $('#model_code').prop('disabled', true);
            $('#variant_code').prop('disabled', true);
            $('#color_code').prop('disabled', true);

        });

        $('#segment_code').on('change', function () {

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

                url: "{{ backpack_url('lead/models') }}/" + encodeURIComponent(segmentCode),

                type: "GET",

                dataType: "json",

                success: function (response) {

                    let options = '<option value="">Select Model</option>';

                    $.each(response, function (code, name) {

                        options += '<option value="' + code + '">' + name + '</option>';

                    });

                    $('#model_code')
                        .html(options)
                        .prop('disabled', false);

                },

                error: function () {

                    $('#model_code')
                        .html('<option value="">No Model Found</option>');

                }

            });

        });

        $('#model_code').on('change', function () {

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

                url:
                    "{{ backpack_url('lead/variants') }}/" +
                    encodeURIComponent(modelCode),

                type: "GET",

                dataType: "json",

                success: function (response) {

                    let options =
                        '<option value="">Select Variant</option>';

                    $.each(response, function (code, name) {

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

                error: function () {

                    $('#variant_code')
                        .html('<option value="">No Variant Found</option>');

                }

            });

        });

        $('#variant_code').on('change', function () {

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

                url:
                    "{{ backpack_url('lead/colors') }}/" +
                    encodeURIComponent(variantCode),

                type: "GET",

                dataType: "json",

                success: function (response) {

                    let options =
                        '<option value="">Select Color</option>';

                    $.each(response, function (code, name) {

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

                error: function () {

                    $('#color_code')
                        .html('<option value="">No Color Found</option>');

                }

            });

        });

    </script>

@endpush