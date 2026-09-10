@extends(backpack_view('blank'))

@section('title', 'Edit Color - ' . $color->name)

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
        <div class="row">
            <div class="col-12">

                <div class="card">
                    <div class="card-header text-black">
                        <h2 class="mb-0">Edit Color Information</h2>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ backpack_url('color/' . $color->id) }}">
                            @csrf
                            @method('PUT')

                            <div class="row">

                                <div class="col-md-3 mb-3">
                                    <label>Segment *</label>
                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>

                                        <option value="">Select Segment</option>

                                        @foreach($segments as $code => $name)

                                            <option value="{{ $code }}" {{ old('segment_code', $color->segment_code) == $code ? 'selected' : '' }}>

                                                {{ $name }}

                                            </option>

                                        @endforeach

                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Sub Segment *</label>
                                    <select name="sub_segment_code" id="sub_segment_code" class="form-control form-select"
                                        required>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Model *</label>
                                    <select name="model_code" id="model_code" class="form-control form-select" required>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label>Variant *</label>
                                    <select name="variant_code" id="variant_code" class="form-control form-select" required>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Color Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control"
                                        value="{{ old('name', $color->name) }}" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Color Code <span class="text-danger">*</span></label>
                                    <input type="text" name="code" class="form-control text-uppercase"
                                        value="{{ old('code', $color->code) }}" style="text-transform: uppercase;"
                                        maxlength="5" title="Only alphabets allowed (no numbers or special characters)"
                                        required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Hex Code <span class="text-danger">*</span></label>
                                    <input type="text" name="hex_code" class="form-control"
                                        value="{{ old('hex_code', $color->hex_code) }}" placeholder="#FF0000"
                                        pattern="^#[0-9A-Fa-f]{6}$" title="Enter valid hex code like #FF0000" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Is Active?</label>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
        old('is_active', $color->is_active) ? 'checked' : '' }}>
                                    </div>
                                </div>

                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="la la-save"></i> Update Color
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

                const currentSubSegment =
                    "{{ $color->sub_segment_code }}";

                const currentModel =
                    "{{ $color->model_code }}";

                const currentVariant =
                    "{{ $color->variant_code }}";

                loadSubSegments(
                    $('#segment_code').val(),
                    currentSubSegment
                );

                function loadSubSegments(segmentCode, selected = null) {
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
                                    '<option value="' + item.code + '"' +
                                    (selected == item.code ? ' selected' : '') +
                                    '>' +
                                    item.name +
                                    '</option>';
                            });

                            $('#sub_segment_code').html(options);

                            if (selected) {
                                loadModels(
                                    selected,
                                    currentModel
                                );
                            }
                        }
                    );
                }

                function loadModels(subSegmentCode, selected = null) {
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
                                    '<option value="' + item.code + '"' +
                                    (selected == item.code ? ' selected' : '') +
                                    '>' +
                                    item.name +
                                    '</option>';
                            });

                            $('#model_code').html(options);

                            if (selected) {
                                loadVariants(
                                    selected,
                                    currentVariant
                                );
                            }
                        }
                    );
                }

                function loadVariants(modelCode, selected = null) {
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
                                    '<option value="' + item.code + '"' +
                                    (selected == item.code ? ' selected' : '') +
                                    '>' +
                                    item.oem_name +
                                    '</option>';
                            });

                            $('#variant_code').html(options);
                        }
                    );
                }

                $('#segment_code').change(function () {
                    loadSubSegments($(this).val());
                });

                $('#sub_segment_code').change(function () {
                    loadModels($(this).val());
                });

                $('#model_code').change(function () {
                    loadVariants($(this).val());
                });

            });

        </script>

    @endpush
@endsection
<script>
    document.querySelector('input[name="hex_code"]').addEventListener('input', function () {
        const val = this.value;
        const preview = document.getElementById('colorPreview');

        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            preview.style.backgroundColor = val;
        } else {
            preview.style.backgroundColor = 'transparent';
        }
    });
</script>