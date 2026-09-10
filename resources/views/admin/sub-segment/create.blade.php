@extends(backpack_view('blank'))

@section('title', 'Add New Sub Segment')

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
                        <h2 class="mb-0">Add New Sub Segment</h2>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ backpack_url('sub-segment') }}">
                            @csrf

                            <div class="row">
                                {{-- <div class="col-md-4 mb-3">
                                    <label>Brand <span class="text-danger">*</span></label>
                                    <select name="brand_code" id="brand_code" class="form-control form-select" required>
                                        <option value="">Select Brand</option>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand->code }}" {{ old('brand_code') == $brand->id ? 'selected' : ''
                                                                        }}>
                                                {{ $brand->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div> --}}
                                <div class="col-md-4 mb-3">
                                    <label>Segment <span class="text-danger">*</span></label>
                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>
                                        <option value="">Select Segment</option>
                                        @foreach($segments as $segment)
                                            <option value="{{ $segment->code }}" {{ old('segment_code') == $segment->code ? 'selected' : '' }}>
                                                {{ $segment->name }}
                                                {{-- <small class="text-muted">({{ $segment->brand->name ?? 'N/A' }})</small> --}}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Sub Segment Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Sub Segment Code <span class="text-danger">*</span></label>
                                    <input type="text" name="code" class="form-control text-uppercase"
                                        value="{{ old('code') }}" maxlength="5" required style="text-transform: uppercase;">
                                    <small class="text-muted">e.g. MICRO, COMPT, PREMM, ENTRY</small>
                                </div>



                                {{-- <div class="col-md-11 mb-3">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control"
                                        rows="3">{{ old('description') }}</textarea>
                                </div> --}}

                                <div class="col-md-1 mb-3">
                                    <label class="form-label">Is Active?</label>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
        old('is_active', true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="la la-save"></i> Create Sub Segment
                                </button>
                                <a href="{{ backpack_url('sub-segment') }}" class="btn btn-secondary btn-lg">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Move JS to the proper Backpack stack
    @push('after_scripts')
        <script>
            $(document).ready(function () {
                $('#brand_code').on('change', function () {
                    let brandCode = $(this).val();

                    $('#segment_code').html('<option value="">Loading...</option>');

                    if (brandCode) {
                        $.ajax({
                            url: "{{ url('admin/sub-segment/segments') }}/" + brandCode,
                            type: "GET",
                            success: function (response) {
                                let options = '<option value="">Select Segment</option>';

                                $.each(response, function (index, segment) {
                                    options += `<option value="${segment.code}">${segment.name}</option>`;
                                });

                                $('#segment_code').html(options);
                            },
                            error: function () {
                                $('#segment_code').html('<option value="">Error loading segments</option>');
                            }
                        });
                    } else {
                        $('#segment_code').html('<option value="">Select Segment</option>');
                    }
                });

                // Optional: Trigger change if brand is pre-selected (edit mode)
                // $('#brand_code').trigger('change');
            });
        </script>
    @endpush --}}
@endsection