@extends(backpack_view('blank'))

@section('title', 'Add New Vehicle Model')

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
                        <h2 class="mb-0">Add New Vehicle Model</h2>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ backpack_url('vehicle-model') }}">
                            @csrf

                            <div class="row">

                                <div class="col-md-4 mb-3">
                                    <label>Segment <span class="text-danger">*</span></label>
                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>
                                        <option value="">Select Segment</option>
                                        @foreach($segments as $segment)
                                            <option value="{{ $segment->code }}" {{ old('segment_code') == $segment->code ? 'selected' : '' }}>
                                                {{ $segment->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>Sub Segment <span class="text-muted">(Optional)</span></label>
                                    <select name="sub_segment_id" id="sub_segment_id" class="form-control form-select">
                                        <option value="">Select Sub Segment</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label>OEM Code</label>
                                    <input type="text" name="oem_name" class="form-control text-uppercase"
                                        value="{{ old('oem_name') }}" style="text-transform: uppercase;">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Model Name <span class="text-danger">*</span></label>
                                    <input type="text" name="code" class="form-control text-uppercase"
                                        value="{{ old('code') }}" required style="text-transform: uppercase;">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Custom Name</label>
                                    <input type="text" name="name" class="form-control"
                                        value="{{ old('name') }}">
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Is Active?</label>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" 
                                               class="form-check-input" {{ old('is_active', true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="la la-save"></i> Create Vehicle Model
                                </button>
                                <a href="{{ backpack_url('vehicle-model') }}" class="btn btn-secondary btn-lg">Cancel</a>
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

    $('#segment_code').on('change', function () {
        let segmentCode = $(this).val();

        $('#sub_segment_id').html('<option value="">Loading...</option>');

        if (segmentCode) {
            $.ajax({
                url: "{{ url('admin/vehicle-model/sub-segments') }}/" + segmentCode,
                type: "GET",
                success: function (response) {
                    let options = '<option value="">Select Sub Segment</option>';
                    $.each(response, function (index, sub) {
                        options += `<option value="${sub.id}">${sub.name}</option>`;
                    });
                    $('#sub_segment_id').html(options);
                }
            });
        } else {
            $('#sub_segment_id').html('<option value="">Select Sub Segment</option>');
        }
    });

    if ($('#segment_code').val()) {
        $('#segment_code').trigger('change');
    }
});
</script>
@endpush