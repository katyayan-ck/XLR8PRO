@extends(backpack_view('blank'))

@section('title', 'Add New Variant')

@push('after_styles')
<style>
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,.08);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 .2rem rgba(0,123,255,.25);
    }

    .section-title {
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
</style>
@endpush

@section('content')

    <div class="container-fluid">

        <div class="row">

            <div class="col-12">

                <div class="card">

                    <div class="card-header">
                        <h2 class="mb-0">Add New Variant</h2>
                    </div>

                    <div class="card-body">

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST"
                              action="{{ backpack_url('variant') }}">

                            @csrf

                            <div class="row">

                                <div class="col-12">
                                    <h5 class="section-title">
                                        Vehicle Hierarchy
                                    </h5>
                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Segment *
                                    </label>

                                    <select
                                        name="segment_code"
                                        id="segment_code"
                                        class="form-control form-select"
                                        required>

                                        <option value="">
                                            Select Segment
                                        </option>

                                        @foreach($segments as $code => $name)

                                            <option
                                                value="{{ $code }}"
                                                {{ old('segment_code') == $code ? 'selected' : '' }}>

                                                {{ $name }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Sub Segment *
                                    </label>

                                    <select
                                        name="sub_segment_code"
                                        id="sub_segment_code"
                                        class="form-control form-select"
                                        required>

                                        <option value="">
                                            Select Sub Segment
                                        </option>

                                    </select>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Vehicle Model *
                                    </label>

                                    <select
                                        name="model_code"
                                        id="model_code"
                                        class="form-control form-select"
                                        required>

                                        <option value="">
                                            Select Model
                                        </option>

                                    </select>

                                </div>

                                <div class="col-12 mt-2">
                                    <h5 class="section-title">
                                        Variant Information
                                    </h5>
                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Variant Code *
                                    </label>

                                    <input
                                        type="text"
                                        name="code"
                                        value="{{ old('code') }}"
                                        class="form-control"
                                        required>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Variant Name *
                                    </label>

                                    <input
                                        type="text"
                                        name="oem_name"
                                        value="{{ old('name') }}"
                                        class="form-control"
                                        required>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Custom Name
                                    </label>

                                    <input
                                        type="text"
                                        name="custom_name"
                                        value="{{ old('custom_name') }}"
                                        class="form-control">

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>Display Name</label>

                                    <input type="text" name="display_name" value="{{ old('display_name') }}" class="form-control">

                                </div>

                                <div class="col-md-4 mb-3">

    <label>Taxi Price</label>

    <input
        type="text"
        name="taxi_price"
        value="{{ old('taxi_price') }}"
        class="form-control">

</div>


                                                            <div class="col-12 mt-2">
                                    <h5 class="section-title">
                                        Vehicle Attributes
                                    </h5>
                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>Permit</label>

                                    <select
                                        name="permit_id"
                                        class="form-control form-select">

                                        <option value="">
                                            Select Permit
                                        </option>

                                        @foreach($permits as $item)

                                            <option
                                                value="{{ $item->id }}"
                                                {{ old('permit_id') == $item->id ? 'selected' : '' }}>

                                                {{ $item->value }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>Fuel Type</label>

                                    <select
                                        name="fuel_type_id"
                                        class="form-control form-select">

                                        <option value="">
                                            Select Fuel Type
                                        </option>

                                        @foreach($fuelTypes as $item)

                                            <option
                                                value="{{ $item->id }}"
                                                {{ old('fuel_type_id') == $item->id ? 'selected' : '' }}>

                                                {{ $item->value }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>Body Type</label>

                                    <select
                                        name="body_type_id"
                                        class="form-control form-select">

                                        <option value="">
                                            Select Body Type
                                        </option>

                                        @foreach($bodyTypes as $item)

                                            <option
                                                value="{{ $item->id }}"
                                                {{ old('body_type_id') == $item->id ? 'selected' : '' }}>

                                                {{ $item->value }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>Body Make</label>

                                    <select
                                        name="body_make_id"
                                        class="form-control form-select">

                                        <option value="">
                                            Select Body Make
                                        </option>

                                        @foreach($bodyMakes as $item)

                                            <option
                                                value="{{ $item->id }}"
                                                {{ old('body_make_id') == $item->id ? 'selected' : '' }}>

                                                {{ $item->value }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>Status</label>

                                    <select
                                        name="status_id"
                                        class="form-control form-select">

                                        <option value="">
                                            Select Status
                                        </option>

                                        @foreach($statuses as $item)

                                            <option
                                                value="{{ $item->id }}"
                                                {{ old('status_id') == $item->id ? 'selected' : '' }}>

                                                {{ $item->value }}

                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                                <div class="col-12 mt-2">
                                    <h5 class="section-title">
                                        Technical Details
                                    </h5>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Seating Capacity</label>
                                    <input type="number"
                                           name="seating_capacity"
                                           value="{{ old('seating_capacity') }}"
                                           class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Wheels</label>
                                    <input type="number"
                                           name="wheels"
                                           value="{{ old('wheels') }}"
                                           class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>GVW</label>
                                    <input type="number"
                                           name="gvw"
                                           value="{{ old('gvw') }}"
                                           class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>CC Capacity</label>
                                    <input type="text"
                                           name="cc_capacity"
                                           value="{{ old('cc_capacity') }}"
                                           class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Transmission</label>
                                    <input type="text"
                                           name="transmission"
                                           value="{{ old('transmission') }}"
                                           class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Drivetrain</label>
                                    <input type="text"
                                           name="drivetrain"
                                           value="{{ old('drivetrain') }}"
                                           class="form-control">
                                </div>

                                                            <div class="col-12 mt-2">
                                    <h5 class="section-title">
                                        Status Settings
                                    </h5>
                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>CSD Index</label>

                                    <input
                                        type="text"
                                        name="csd_index"
                                        value="{{ old('csd_index') }}"
                                        class="form-control">

                                </div>

                                <div class="col-md-2 mb-3">

                                    <label class="form-label">
                                        CSD Available?
                                    </label>

                                    <div class="form-check form-switch">

                                        <input
                                            type="hidden"
                                            name="is_csd"
                                            value="0">

                                        <input
                                            type="checkbox"
                                            name="is_csd"
                                            value="1"
                                            class="form-check-input"
                                            {{ old('is_csd') ? 'checked' : '' }}>
                                    </div>

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

                                <button
                                    type="submit"
                                    class="btn btn-success btn-lg px-5">

                                    <i class="la la-save"></i>
                                    Create Variant

                                </button>

                                <a href="{{ backpack_url('variant') }}"
                                   class="btn btn-secondary btn-lg">

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

    $('#segment_code').on('change', function () {

    let segmentCode = $(this).val();

    $('#sub_segment_code').html(
        '<option value="">Loading...</option>'
    );

    $('#model_code').html(
        '<option value="">Select Model</option>'
    );

    if (segmentCode) {

        $.ajax({

            url: "{{ backpack_url('variant/subsegments') }}",

            type: "GET",

            data: {
                segment_code: segmentCode
            },

            success: function (response) {

                let options =
                    '<option value="">Select Sub Segment</option>';

                $.each(response, function (index, sub) {

                    options +=
                        `<option value="${sub.code}">
                            ${sub.name}
                        </option>`;

                });

                $('#sub_segment_code').html(options);

            }

        });

    } else {

        $('#sub_segment_code').html(
            '<option value="">Select Sub Segment</option>'
        );

    }

});

   $('#sub_segment_code').on('change', function () {

    let subSegmentCode = $(this).val();

    $('#model_code').html(
        '<option value="">Loading...</option>'
    );

    $.ajax({

        url: "{{ backpack_url('variant/models') }}",

        type: "GET",

        data: {
            segment_code: $('#segment_code').val(),
            sub_segment_code: subSegmentCode
        },

        success: function (response) {

            let options =
                '<option value="">Select Model</option>';

            $.each(response, function (index, model) {

                options +=
                    `<option value="${model.code}">
                        ${model.name}
                    </option>`;

            });

            $('#model_code').html(options);

        }

    });

});

    </script>

@endpush