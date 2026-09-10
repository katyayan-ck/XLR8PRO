@extends(backpack_view('blank'))

@section('title', 'Edit Vehicle Model - ' . $vehiclemodel->name)

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
                        <h2 class="mb-0">Edit Vehicle Model</h2>
                    </div>
                    <div class="card-body">

                        <form method="POST" action="{{ backpack_url('vehicle-model/' . $vehiclemodel->id) }}">
                            @csrf
                            @method('PUT')

                            <div class="row">

                                <!-- Segment -->
                                <div class="col-md-4 mb-3">
                                    <label>Segment <span class="text-danger">*</span></label>
                                    <select name="segment_code" id="segment_code" class="form-control form-select" required>
                                        <option value="">Select Segment</option>
                                        @foreach($segments as $segment)
                                            <option value="{{ $segment->code }}" 
                                                {{ old('segment_code', $vehiclemodel->segment_code) == $segment->code ? 'selected' : '' }}>
                                                {{ $segment->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Sub Segment -->
                                <div class="col-md-4 mb-3">
                                    <label>Sub Segment <span class="text-muted">(Optional)</span></label>
                                    <select name="sub_segment_id" id="sub_segment_id" class="form-control form-select">
                                        <option value="">Select Sub Segment</option>
                                    </select>
                                </div>

                                <!-- OEM Code -->
                                <div class="col-md-4 mb-3">
                                    <label>OEM Code</label>
                                    <input type="text" name="oem_name" class="form-control text-uppercase"
                                        value="{{ old('oem_name', $vehiclemodel->oem_name) }}" 
                                        style="text-transform: uppercase;">
                                </div>

                                <!-- Model Name -->
                                <div class="col-md-6 mb-3">
                                    <label>Model Name <span class="text-danger">*</span></label>
                                    <input type="text" name="code" class="form-control text-uppercase"
                                        value="{{ old('code', $vehiclemodel->code) }}" required 
                                        style="text-transform: uppercase;">
                                </div>

                                <!-- Custom Name -->
                                <div class="col-md-6 mb-3">
                                    <label>Custom Name</label>
                                    <input type="text" name="name" class="form-control"
                                        value="{{ old('name', $vehiclemodel->name) }}">
                                </div>

                                <!-- Is Active -->
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Is Active?</label>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" 
                                            class="form-check-input" {{ old('is_active', $vehiclemodel->is_active) ? 'checked' : '' }}>
                                    </div>
                                </div>

                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="la la-save"></i> Update Vehicle Model
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
        let currentSubId = "{{ old('sub_segment_id', $vehiclemodel->sub_segment_code ? 
            \App\Models\Vehicle\SubSegment::where('code', $vehiclemodel->sub_segment_code)->value('id') : '') }}";

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

                    // Pre-select saved sub segment
                    if (currentSubId) {
                        $('#sub_segment_id').val(currentSubId);
                    }
                }
            });
        } else {
            $('#sub_segment_id').html('<option value="">Select Sub Segment</option>');
        }
    });

    // Trigger on load
    if ($('#segment_code').val()) {
        $('#segment_code').trigger('change');
    }
});
</script>
@endpush
@push('after_scripts')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

$(document).ready(function () {

    const activeVariants =
        @json($activeVariants ?? []);

    $('input[type="checkbox"][name="is_active"]').on('change', function () {

        if ($(this).is(':checked')) {
            return;
        }

        if (activeVariants.length === 0) {
            return;
        }

        let displayVariants =
            activeVariants.slice(0, 5);

        let html =
            '<ul style="text-align:left;">';

        displayVariants.forEach(function(item) {

            html += '<li>' + item + '</li>';

        });

        if (activeVariants.length > 5) {

            html +=
                '<li><strong>+' +
                (activeVariants.length - 5) +
                ' more...</strong></li>';
        }

        html += '</ul>';

        Swal.fire({
            icon: 'warning',
            title: 'Cannot Deactivate Vehicle Model',
            html:
                '<p>Please deactivate the following active Variants first:</p>'
                + html,
            confirmButtonText: 'OK'
        });

        $(this).prop('checked', true);
    });

});

</script>

@endpush