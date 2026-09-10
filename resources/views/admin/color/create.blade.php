@extends(backpack_view('blank'))

@section('title', 'Add New Color')

@push('after_styles')
    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <div class="card">
                    <div class="card-header text-black">
                        <h2 class="mb-0">Add New Color</h2>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ backpack_url('color') }}">
                            @csrf

                            <div class="row">

                                <div class="row">

                                    <div class="col-md-3 mb-3">

                                        <label>Segment <span class="text-danger">*</span></label>

                                        <select name="segment_code" id="segment_code" class="form-control form-select"
                                            required>

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

                                        <label>Sub Segment <span class="text-danger">*</span></label>

                                        <select name="sub_segment_code" id="sub_segment_code"
                                            class="form-control form-select" required>

                                            <option value="">
                                                Select Sub Segment
                                            </option>

                                        </select>

                                    </div>

                                    <div class="col-md-3 mb-3">

                                        <label>Model <span class="text-danger">*</span></label>

                                        <select name="model_code" id="model_code" class="form-control form-select" required>

                                            <option value="">
                                                Select Model
                                            </option>

                                        </select>

                                    </div>

                                    <div class="col-md-3 mb-3">

                                        <label>Variant <span class="text-danger">*</span></label>

                                        <select name="variant_code" id="variant_code" class="form-control form-select"
                                            required>

                                            <option value="">
                                                Select Variant
                                            </option>

                                        </select>

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Color Code <span class="text-danger">*</span></label>

                                        <input type="text" name="code" maxlength="5" class="form-control text-uppercase"
                                            value="{{ old('code') }}" required>

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Color Name <span class="text-danger">*</span></label>

                                        <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                            required>

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Hex Code</label>

                                        <input type="text" name="hex_code" class="form-control" placeholder="#FFFFFF"
                                            value="{{ old('hex_code') }}">

                                    </div>

                                <div class="col-md-2 mb-3">

                                    <label class="form-label">
                                        Is Active?
                                    </label>

                                    <div class="form-check form-switch">

                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value="0">

                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            class="form-check-input"
                                            {{ old('is_active', true) ? 'checked' : '' }}>

                                    </div>
                                    

                                </div>

                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="la la-save"></i> Create Color
                                </button>
                                <a href="{{ backpack_url('color') }}" class="btn btn-secondary btn-lg">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('after_scripts')

<script>

$(document).ready(function () {

    $('#segment_code').on('change', function () {

        let segmentCode = $(this).val();

        $('#sub_segment_code').html(
            '<option value="">Loading...</option>'
        );

        $('#model_code').html(
            '<option value="">Select Model</option>'
        );

        $('#variant_code').html(
            '<option value="">Select Variant</option>'
        );

        $.get(
            "{{ backpack_url('color/subsegments') }}",
            {
                segment_code: segmentCode
            },
            function (data) {

                let options =
                    '<option value="">Select Sub Segment</option>';

                $.each(data, function (i, item) {

                    options +=
                        '<option value="' +
                        item.code +
                        '">' +
                        item.name +
                        '</option>';

                });

                $('#sub_segment_code').html(options);
            }
        );
    });

    $('#sub_segment_code').on('change', function () {

        let subSegmentCode = $(this).val();

        $('#model_code').html(
            '<option value="">Loading...</option>'
        );

        $('#variant_code').html(
            '<option value="">Select Variant</option>'
        );

        $.get(
            "{{ backpack_url('color/models') }}",
            {
                sub_segment_code: subSegmentCode
            },
            function (data) {

                let options =
                    '<option value="">Select Model</option>';

                $.each(data, function (i, item) {

                    options +=
                        '<option value="' +
                        item.code +
                        '">' +
                        item.name +
                        '</option>';

                });

                $('#model_code').html(options);
            }
        );
    });

    $('#model_code').on('change', function () {

        let modelCode = $(this).val();

        $('#variant_code').html(
            '<option value="">Loading...</option>'
        );

        $.get(
            "{{ backpack_url('color/variants') }}",
            {
                model_code: modelCode
            },
            function (data) {

                let options =
                    '<option value="">Select Variant</option>';

                $.each(data, function (i, item) {

                    options +=
                        '<option value="' +
                        item.code +
                        '">' +
                        item.oem_name +
                        '</option>';

                });

                $('#variant_code').html(options);
            }
        );
    });

});

</script>

@endpush
@endsection