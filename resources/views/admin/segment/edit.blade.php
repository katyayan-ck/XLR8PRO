@extends(backpack_view('blank'))

@section('title', 'Edit Segment - ' . $segment->name)

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
                    <h2 class="mb-0">Edit Segment Information</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('segment/' . $segment->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            <!-- Read Only Section -->
                            <div class="col-md-12 mb-4">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Segment ID</label>
                                        <div class="readonly-value">{{ $segment->id }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Created At</label>
                                        <div class="readonly-value">
                                            {{ $segment->created_at ? $segment->created_at->format('d-m-Y H:i') : '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- <!-- Editable Fields -->
                            <div class="col-md-4 mb-3">
                                <label>Brand <span class="text-danger">*</span></label>
                                <select name="brand_code" class="form-control form-select" required>
                                    <option value="">Select Brand</option>
                                    @foreach($brands as $brand)
                                    <option value="{{ $brand->code }}" {{ old('brand_code', $segment->brand_code) ==
                                        $brand->code ? 'selected' : '' }}>
                                        {{ $brand->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div> --}}

                            <div class="col-md-4 mb-3">
                                <label>Segment Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control text-uppercase"
                                    value="{{ old('code', $segment->code) }}" maxlength="5" required
                                    style="text-transform: uppercase;">
                                <small class="text-muted">e.g. HATCH, SUVXX, SEDAN, MPVXX</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Segment Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $segment->name) }}" required>
                            </div>

                            {{-- <div class="col-md-11 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3">{{ old('description', $segment->description) }}</textarea>
                            </div> --}}

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', $segment->is_active) ? 'checked' : '' }}>
                                </div>
                            </div>

                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Segment
                            </button>
                            <a href="{{ backpack_url('segment') }}" class="btn btn-secondary btn-lg">Cancel</a>
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

    const activeSubSegments =
        @json($activeSubSegments ?? []);

    $('input[type="checkbox"][name="is_active"]').on('change', function () {

        if ($(this).is(':checked')) {
            return;
        }

        if (activeSubSegments.length === 0) {
            return;
        }

        let displaySubSegments =
            activeSubSegments.slice(0, 5);

        let html =
            '<ul style="text-align:left;">';

        displaySubSegments.forEach(function(item) {

            html += '<li>' + item + '</li>';

        });

        if (activeSubSegments.length > 5) {

            html +=
                '<li><strong>+' +
                (activeSubSegments.length - 5) +
                ' more...</strong></li>';
        }

        html += '</ul>';

        Swal.fire({
            icon: 'warning',
            title: 'Cannot Deactivate Segment',
            html:
                '<p>Please deactivate the following active Sub Segments first:</p>'
                + html,
            confirmButtonText: 'OK'
        });

        $(this).prop('checked', true);
    });

});

</script>

@endpush