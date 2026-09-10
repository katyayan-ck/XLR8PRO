@extends(backpack_view('blank'))

@section('title', 'Edit Variant - ' . $variant->oem_name)

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

    .readonly-value {
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 10px 15px;
        min-height: 42px;
        display: flex;
        align-items: center;
    }
</style>
@endpush

@section('content')

                <div class="container-fluid">

                    <div class="card">

                        <div class="card-header">
                            <h2>Edit Variant</h2>
                        </div>

                        <div class="card-body">

                            <form method="POST" action="{{ backpack_url('variant/' . $variant->id) }}">
                                @if ($errors->any())

                                    <div class="alert alert-danger">

                                        <ul class="mb-0">

                                            @foreach ($errors->all() as $error)

                                                <li>{{ $error }}</li>

                                            @endforeach

                                        </ul>

                                    </div>

                                @endif

                                @csrf
                                @method('PUT')

                                <div class="row">

                                    <div class="col-12">
                                        <h5>Vehicle Hierarchy</h5>
                                        <hr>
                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Segment *</label>

                                        <select name="segment_code" id="segment_code" class="form-control form-select" required>

                                            <option value="">
                                                Select Segment
                                            </option>

                                            @foreach($segments as $code => $name)

                                                <option value="{{ $code }}" {{ old('segment_code', $variant->segment_code) == $code ? 'selected' : '' }}>

                                                    {{ $name }}

                                                </option>

                                            @endforeach

                                        </select>

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Sub Segment *</label>

                                        <select name="sub_segment_code" id="sub_segment_code" class="form-control form-select" required>

                                            <option value="">
                                                Select Sub Segment
                                            </option>

                                            @foreach($subSegments as $sub)

                                                <option
                                                    value="{{ $sub->code }}"
                                                    {{ old('sub_segment_code', $variant->sub_segment_code) == $sub->code ? 'selected' : '' }}>
                                                    {{ $sub->name }}
                                                </option>

                                            @endforeach

                                        </select>

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Vehicle Model *</label>

                                        <select name="model_code" id="model_code" class="form-control form-select" required>

                                            <option value="">
                                                Select Model
                                            </option>

                                            @foreach($models as $model)

                                                <option value="{{ $model->code }}" {{ old('model_code', $variant->model_code) == $model->code ? 'selected' : '' }}>

                                                    {{ $model->name }}

                                                </option>

                                            @endforeach

                                        </select>

                                        </div>

                                        <div class="col-12 mt-3">
                                            <h5>Variant Information</h5>
                                            <hr>
                                    </div>
                                                        <div class="col-md-4 mb-3">

                                        <label>Variant Code *</label>

                                        <input
                                            type="text"
                                            name="code"
                                            class="form-control"
                                            value="{{ old('code', $variant->code) }}"
                                            required>

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>OEM Name *</label>

                                        <input
                                            type="text"
                                            name="oem_name"
                                            class="form-control"
                                            value="{{ old('oem_name', $variant->oem_name) }}"
                                            required>

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Custom Name</label>

                                        <input
                                            type="text"
                                            name="custom_name"
                                            class="form-control"
                                            value="{{ old('custom_name', $variant->custom_name) }}">

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Display Name</label>

                                        <input
                                            type="text"
                                            name="display_name"
                                            class="form-control"
                                            value="{{ old('display_name', $variant->display_name) }}">

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Taxi Price</label>

                                        <input
                                            type="text"
                                            name="taxi_price"
                                            class="form-control"
                                            value="{{ old('taxi_price', $variant->taxi_price) }}">

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
                                                    {{ old('permit_id', $variant->permit_id) == $item->id ? 'selected' : '' }}>

                                                    {{ $item->value }}

                                                </option>

                                            @endforeach

                                        </select>

                                    </div>

                                    <div class="col-md-4 mb-3">

                                        <label>Fuel Type</label>

                                        <select name="fuel_type_id" class="form-control form-select">

                                            <option value="">
                                                Select Fuel Type
                                            </option>

                                            @foreach($fuelTypes as $item)

                                                <option value="{{ $item->id }}" {{ old('fuel_type_id', $variant->fuel_type_id) == $item->id ? 'selected' : '' }}>

                                                    {{ $item->value }}

                                                </option>

                                            @endforeach

                                        </select>

                                    </div>

                                    <div class="col-md-4 mb-3">

                    <label>Body Type</label>

                    <select name="body_type_id"
                            class="form-control form-select">

                        <option value="">Select Body Type</option>

                        @foreach($bodyTypes as $item)

                            <option value="{{ $item->id }}"
                                {{ old('body_type_id', $variant->body_type_id) == $item->id ? 'selected' : '' }}>

                                {{ $item->value }}

                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="col-md-4 mb-3">

                    <label>Body Make</label>

                    <select name="body_make_id"
                            class="form-control form-select">

                        <option value="">Select Body Make</option>

                        @foreach($bodyMakes as $item)

                            <option value="{{ $item->id }}"
                                {{ old('body_make_id', $variant->body_make_id) == $item->id ? 'selected' : '' }}>

                                {{ $item->value }}

                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="col-md-4 mb-3">

                    <label>Status</label>

                    <select name="status_id" class="form-control form-select">

                        <option value="">Select Status</option>

                        @foreach($statuses as $item)

                            <option value="{{ $item->id }}" {{ old('status_id', $variant->status_id) == $item->id ? 'selected' : '' }}>

                                {{ $item->value }}

                            </option>

                        @endforeach

                    </select>

                </div>
            <div class="col-md-4 mb-3">
                <label>Seating Capacity</label>
                <input type="number" name="seating_capacity" class="form-control"
                    value="{{ old('seating_capacity', $variant->seating_capacity) }}">
            </div>

            <div class="col-md-4 mb-3">
                <label>Wheels</label>
                <input type="number" name="wheels" class="form-control" value="{{ old('wheels', $variant->wheels) }}">
            </div>

            <div class="col-md-4 mb-3">
                <label>GVW</label>
                <input type="number" name="gvw" class="form-control" value="{{ old('gvw', $variant->gvw) }}">
            </div>
        <div class="col-md-4 mb-3">
            <label>CC Capacity</label>
            <input type="text" name="cc_capacity" class="form-control" value="{{ old('cc_capacity', $variant->cc_capacity) }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>Transmission</label>
            <input type="text" name="transmission" class="form-control"
                value="{{ old('transmission', $variant->transmission) }}">
        </div>

        <div class="col-md-4 mb-3">
            <label>Drivetrain</label>
            <input type="text" name="drivetrain" class="form-control" value="{{ old('drivetrain', $variant->drivetrain) }}">
        </div><div class="col-md-4 mb-3">

            <label>CSD Index</label>

            <input type="text" name="csd_index" class="form-control" value="{{ old('csd_index', $variant->csd_index) }}">

        </div>

        <div class="col-md-2 mb-3">

            <label>CSD</label>

            <input type="hidden" name="is_csd" value="0">

            <input type="checkbox" name="is_csd" value="1" {{ old('is_csd', $variant->is_csd) ? 'checked' : '' }}>

        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label">Is Active?</label>
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $variant->is_active) ? 'checked' : '' }}>
            </div>
        </div>

        </div>
    <button type="submit" class="btn btn-success">

        Update Variant

    </button>

    <a href="{{ backpack_url('variant') }}" class="btn btn-secondary">

        Cancel

    </a>

    </form>

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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>

        $(document).ready(function () {

            const activeColors =
                @json($activeColors ?? []);

            $('input[type="checkbox"][name="is_active"]').on('change', function () {

                if ($(this).is(':checked')) {
                    return;
                }

                if (activeColors.length === 0) {
                    return;
                }

                let displayColors =
                    activeColors.slice(0, 5);

                let html =
                    '<ul style="text-align:left;">';

                displayColors.forEach(function (item) {

                    html += '<li>' + item + '</li>';

                });

                if (activeColors.length > 5) {

                    html +=
                        '<li><strong>+' +
                        (activeColors.length - 5) +
                        ' more...</strong></li>';
                }

                html += '</ul>';

                Swal.fire({
                    icon: 'warning',
                    title: 'Cannot Deactivate Variant',
                    html:
                        '<p>Please deactivate the following active Colors first:</p>'
                        + html,
                    confirmButtonText: 'OK'
                });

                $(this).prop('checked', true);
            });

        });

    </script>

@endpush