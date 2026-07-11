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

            <div class="col-md-12">

                <div class="card">

                    <div class="card-header">

                        <h4 class="card-title mb-0">
                            {{ $title }}
                        </h4>

                    </div>

                    <form method="POST"
                        action="{{ isset($campaign) ? backpack_url('campaign/' . $campaign->id) : backpack_url('campaign') }}">

                        @csrf

                        @if (isset($campaign))
                            @method('PUT')
                        @endif

                        <div class="card-body">

                            <div class="row">

                                {{-- Activity Name --}}
                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Activity Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="name" class="form-control" required
                                        value="{{ old('name', $campaign->name ?? '') }}">

                                </div>

                                {{-- Segment --}}
                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Segment
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Segment
                                        </option>

                                        @foreach ($segments as $code => $name)
                                            <option value="{{ $code }}" @selected(old('segment_code', $campaign->segment_code ?? '') == $code)>

                                                {{ $name }}

                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                                {{-- Model --}}
                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Model
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select name="model_code" id="model_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Model
                                        </option>

                                        @foreach ($models as $code => $name)
                                            <option value="{{ $code }}" @selected(old('model_code', $campaign->model_code ?? '') == $code)>

                                                {{ $name }}

                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                                {{-- Activity Type --}}
                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Activity Type
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select name="activity_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Activity Type
                                        </option>

                                        @foreach ($activity_types as $item)
                                            <option value="{{ $item['code'] }}" @selected(old('activity_code', $campaign->activity_code ?? '') == $item['code'])>

                                                {{ $item['value'] }}

                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                                {{-- Start Date --}}
                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Start Date
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="date" name="start_date" class="form-control" required
                                        value="{{ old('start_date', isset($campaign) ? optional($campaign->start_date)->format('Y-m-d') : '') }}">

                                </div>

                                {{-- End Date --}}
                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        End Date
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="date" name="end_date" class="form-control" required
                                        value="{{ old('end_date', isset($campaign) ? optional($campaign->end_date)->format('Y-m-d') : '') }}">

                                </div>

                                {{-- Dealer Branch --}}
                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Dealer Branch
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select name="branch_code" id="branch_code" class="form-control form-select" required>

                                        <option value="">
                                            Select Dealer Branch
                                        </option>

                                        @foreach ($branches as $code => $name)
                                            <option value="{{ $code }}" @selected(old('branch_code', $campaign->branch_code ?? '') == $code)>

                                                {{ $name }}

                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                                {{-- Dealer Location --}}
                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Dealer Location
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select name="location_code" id="location_code" class="form-control form-select"
                                        required>

                                        <option value="">
                                            Select Dealer Location
                                        </option>

                                        @foreach ($locations as $code => $name)
                                            <option value="{{ $code }}" @selected(old('location_code', $campaign->location_code ?? '') == $code)>

                                                {{ $name }}

                                            </option>
                                        @endforeach

                                    </select>

                                </div>

                            </div>

                        </div>

                        <div class="card-footer">

                            <button type="submit" class="btn btn-success">

                                <i class="la la-save"></i>

                                {{ isset($campaign) ? 'Update Campaign' : 'Save Campaign' }}

                            </button>

                            <a href="{{ backpack_url('campaign') }}" class="btn btn-secondary">

                                <i class="la la-times"></i>

                                Cancel

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

@endsection
@push('after_scripts')
    <script>
        $(function() {

            /*
            |--------------------------------------------------------------------------
            | Segment -> Model
            |--------------------------------------------------------------------------
            */

            $('#segment_code').change(function() {

                let segment = $(this).val();

                $('#model_code')
                    .html('<option>Loading...</option>')
                    .prop('disabled', true);

                if (!segment) {

                    $('#model_code')
                        .html('<option value="">Select Model</option>')
                        .prop('disabled', true);

                    return;
                }

                $.get(

                    "{{ backpack_url('campaign/models') }}/" + segment,

                    function(response) {

                        let html = '<option value="">Select Model</option>';

                        $.each(response, function(code, name) {

                            html += `
                        <option value="${code}">
                            ${name}
                        </option>
                    `;

                        });

                        $('#model_code')
                            .html(html)
                            .prop('disabled', false);

                    }

                );

            });

            /*
            |--------------------------------------------------------------------------
            | Branch -> Location
            |--------------------------------------------------------------------------
            */

            $('#branch_code').change(function() {

                let branch = $(this).val();

                $('#location_code')
                    .html('<option>Loading...</option>')
                    .prop('disabled', true);

                if (!branch) {

                    $('#location_code')
                        .html('<option value="">Select Dealer Location</option>')
                        .prop('disabled', true);

                    return;
                }

                $.get(

                    "{{ backpack_url('campaign/locations') }}/" + branch,

                    function(response) {

                        let html = '<option value="">Select Dealer Location</option>';

                        $.each(response, function(code, name) {

                            html += `
                        <option value="${code}">
                            ${name}
                        </option>
                    `;

                        });

                        $('#location_code')
                            .html(html)
                            .prop('disabled', false);

                    }

                );

            });

            @if (isset($campaign))

                $('#segment_code').trigger('change');

                setTimeout(function() {

                    $('#model_code')
                        .val("{{ $campaign->model_code }}");

                }, 400);

                $('#branch_code').trigger('change');

                setTimeout(function() {

                    $('#location_code')
                        .val("{{ $campaign->location_code }}");

                }, 400);
            @endif

        });
    </script>
@endpush
