@extends(backpack_view('blank'))

@section('title', 'Edit Sub Segment - ' . $subsegment->name)

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
                    <h2 class="mb-0">Edit Sub Segment Information</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('sub-segment/' . $subsegment->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            <!-- Read Only Section -->
                            <div class="col-md-12 mb-4">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Sub Segment ID</label>
                                        <div class="readonly-value">{{ $subsegment->id }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Created At</label>
                                        <div class="readonly-value">
                                            {{ $subsegment->created_at ? $subsegment->created_at->format('d-m-Y H:i') :
                                            '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Editable Fields -->
                            <div class="col-md-4 mb-3">
                                <label>Segment <span class="text-danger">*</span></label>
                                <select name="segment_id" class="form-control form-select" required>
                                    <option value="">Select Segment</option>
                                    @foreach($segments as $segment)
                                    <option value="{{ $segment->id }}" {{ old('segment_id', $subsegment->segment_id) ==
                                        $segment->id ? 'selected' : '' }}>
                                        {{ $segment->name }}
                                        <small class="text-muted">({{ $segment->brand->name ?? '' }})</small>
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Sub Segment Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control text-uppercase"
                                    value="{{ old('code', $subsegment->code) }}" maxlength="5" required
                                    style="text-transform: uppercase;">
                                <small class="text-muted">e.g. MICRO, COMPT, PREMM, ENTRY</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Sub Segment Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $subsegment->name) }}" required>
                            </div>

                            <div class="col-md-11 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3">{{ old('description', $subsegment->description) }}</textarea>
                            </div>

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', $subsegment->is_active) ? 'checked' : '' }}>
                                </div>
                            </div>

                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Sub Segment
                            </button>
                            <a href="{{ backpack_url('sub-segment') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('after_scripts')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

$(document).ready(function () {

    const activeModels =
        @json($activeModels ?? []);

    $('input[type="checkbox"][name="is_active"]').on('change', function () {

        if ($(this).is(':checked')) {
            return;
        }

        if (activeModels.length === 0) {
            return;
        }

        let displayModels =
            activeModels.slice(0, 5);

        let html =
            '<ul style="text-align:left;">';

        displayModels.forEach(function(item) {

            html += '<li>' + item + '</li>';

        });

        if (activeModels.length > 5) {

            html +=
                '<li><strong>+' +
                (activeModels.length - 5) +
                ' more...</strong></li>';
        }

        html += '</ul>';

        Swal.fire({
            icon: 'warning',
            title: 'Cannot Deactivate Sub Segment',
            html:
                '<p>Please deactivate the following active Models first:</p>'
                + html,
            confirmButtonText: 'OK'
        });

        $(this).prop('checked', true);
    });

});

</script>

@endpush