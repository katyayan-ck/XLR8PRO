@extends(backpack_view('blank'))

@section('title', 'Delivery Photos - Booking #' . ($booking->id ?? '—'))

@push('after_styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .required-mark {
        color: #dc3545;
        margin-left: 4px;
    }

    .form-group.readonly-field {
        margin-bottom: 1.25rem;
    }

    .readonly-label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.35rem;
        display: block;
    }

    .readonly-value {
        padding: 0.375rem 0.75rem;
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        min-height: 38px;
        display: flex;
        align-items: center;
    }

    .photo-upload-input {
        padding: 0.375rem 0.75rem;
    }

    .preview-container {
        margin-top: 0.75rem;
    }

    .small-hint {
        font-size: 0.8125rem;
        color: #6c757d;
    }

    .preview-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        line-height: 1.25;
    }

    .preview-chip .file-name {
        font-weight: 500;
        font-size: 0.875rem;
    }
</style>
@endpush

@section('title', 'Delivery Data')

@section('content')

<div class="container-fluid">

    @include(backpack_view('inc.alerts'))

    <div class="card card-body shadow-sm mb-4" style="border-radius: 12px">
        <h2 class="mb-3">Booking & Invoice Summary (Read-only)</h2>
        <div class="row g-3">

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">XB No.</label>
                <div class="readonly-value">
                    {{ $booking->id ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Booking Date</label>
                <div class="readonly-value">
                    {{ $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') : '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">DMS OTF No.</label>
                <div class="readonly-value">
                    {{ $booking->dms_otf ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Customer Name</label>
                <div class="readonly-value">
                    {{ $booking->name ?? '—' }}
                    @if($booking->care_of)
                    (C/o: {{ $booking->care_of }})
                    @endif
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Branch</label>
                <div class="readonly-value">
                    {{ $data['branch'] ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Location</label>
                <div class="readonly-value">
                    @if($booking->location_code)
                    {{ $booking->location?->name ?? '—' }}
                    @else
                    {{ $booking->location_other ?: '—' }}
                    @endif
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Model</label>
                <div class="readonly-value">
                    {{ $booking->model_code ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Variant</label>
                <div class="readonly-value">
                    {{ $booking->variant_code ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Color</label>
                <div class="readonly-value">
                    {{ $booking->color_code ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Chassis No.</label>
                <div class="readonly-value">
                    {{ $booking->chassis_no ?? $booking->chassis_no ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Invoice No.</label>
                <div class="readonly-value">
                    {{ $booking->inv_no ?? $booking->dms_invoice_number ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Invoice Date</label>
                <div class="readonly-value">
                    {{ $booking->inv_date ? \Carbon\Carbon::parse($booking->inv_date)->format('d M Y') : '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">Insurance Policy No.</label>
                <div class="readonly-value">
                    {{ $insurance?->pol_no ?? '—' }}
                </div>
            </div>

            <div class="col-md-2 form-group readonly-field">
                <label class="readonly-label">RTO Application No.</label>
                <div class="readonly-value">
                    {{ $rto?->app_no ?? '—' }}
                </div>
            </div>

        </div>
    </div>

    <div class="card card-body shadow-sm mb-4" style="border-radius: 12px">
        <h2 class="mb-3">Upload Delivery Photos</h2>

        <form method="POST" action="{{ route('booking.delivery-photos.update', $booking->id ?? '') }}"
            enctype="multipart/form-data" id="deliveryUploadForm">
            @csrf
            @method('PUT')

            <div class="row g-3 text-amber-300">

                @php
                $collections = [
                'delivery_ceremony_with_customer' => 'Delivery Ceremony (With Customer)',
                'bonnet' => 'Bonnet',
                'windshield_glass' => 'Windshield Glass',
                'vehicle_driver_side' => 'Vehicle (Driver Side)',
                'vehicle_co_driver_side' => 'Vehicle (Co Driver Side)',
                'vehicle_rear_side' => 'Vehicle (Rear Side)',
                'tire_front_driver_side' => 'Tyre (Front Driver Side)',
                'tire_front_co_driver_side' => 'Tyre (Front Co Driver Side)',
                'tire_rear_driver_side' => 'Tyre (Rear Driver Side)',
                'tire_rear_co_driver_side' => 'Tyre (Rear Co Driver Side)',
                'stepney' => 'Stepney',
                'foot_rest_driver_side' => 'Foot Rest (Driver Side)',
                'foot_rest_co_driver_side' => 'Foot Rest (Co Driver Side)',
                'tool_kit' => 'Tool Kit',
                'vehicle_chassis_no_photo' => 'Vehicle Chassis No.',
                'chassis_no_screenshot_invoice' => 'Chassis No. Screenshot (Invoice)',
                'chassis_no_screenshot_insurance' => 'Chassis No. Screenshot (Insurance)',
                ];
                @endphp

                @foreach($collections as $key => $label)
                <div class="col-md-3 col-sm-6">
                    <div class="form-group">
                        <label for="{{ $key }}">
                            {{ __($label) }}
                            <span class="required-mark">*</span>
                        </label>

                        @if(isset($existingPhotos[$key]) && $existingPhotos[$key])
                        <div class="mt-3" id="chip-{{ $key }}">
                            <span
                                class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                                style="cursor: pointer;"
                                onclick="openPreviewModal('{{ asset($existingPhotos[$key]) }}', '{{ basename($existingPhotos[$key]) }}')">
                                <i class="la la-paperclip"></i>
                                <span class="fw-medium small">{{ basename($existingPhotos[$key]) }}</span>
                            </span>
                            <button type="button" class="btn btn-sm btn-danger rounded-circle ms-2"
                                style="width:22px; height:22px; font-size:14px; padding:0; line-height:1;"
                                onclick="removeExistingPhoto('{{ $key }}')">
                                ×
                            </button>
                        </div>

                        @else
                        <!-- New upload -->
                        <input type="file" name="photos[{{ $key }}]" id="{{ $key }}"
                            class="form-control photo-upload-input mt-1" accept="image/jpeg,image/png,image/gif"
                            required data-preview-id="preview-{{ $key }}">
                        @endif

                        <small class="form-text text-muted">JPG / PNG only (max 5MB)</small>

                        <!-- Temporary preview for newly selected file -->
                        <div class="mt-3" id="preview-{{ $key }}" style="display:none;">
                            <span
                                class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                                style="cursor: pointer;" id="new-preview-span-{{ $key }}">
                                <i class="la la-paperclip"></i>
                                <span class="fw-medium small file-name"></span>
                            </span>

                        </div>
                    </div>
                </div>
                @endforeach

                <div class="col-12 mt-4">
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="chassis_verified" name="chassis_verified"
                            required>
                        <label class="form-check-label" for="chassis_verified">
                            I have personally verified the vehicle chassis number with invoice copy and insurance copy
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="remarks">
                            Remarks <span class="required-mark">*</span>
                        </label>
                        <textarea name="remarks" id="remarks" class="form-control" rows="3"
                            placeholder="Any additional observations..." required></textarea>
                    </div>
                </div>

                <div class="col-12 mt-4 text-center">
                    <button type="submit" class="btn btn-success btn-lg px-5 py-3">
                        <i class="ik ik-upload me-2"></i> Upload Delivery Photos
                    </button>
                </div>

            </div>
        </form>
    </div>

    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImagePreview" src="" alt="Preview"
                        style="max-width:100%; max-height:70vh; display:none;">
                    <div id="noPreviewText" class="py-5 text-muted" style="display:none;">No preview available</div>
                </div>
                <div class="modal-footer">
                    <a id="downloadLink" href="#" download class="btn btn-primary">
                        <i class="ik ik-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

    $('#previewModal').on('show.bs.modal', function () {
        $(this).appendTo('body');
    });

});
    function openPreviewModal(url, name) {

    document.getElementById('previewModalLabel').textContent = name;

    const previewFrame = document.getElementById('modalImagePreview');
    previewFrame.src = url;
    previewFrame.style.display = 'block';

    document.getElementById('noPreviewText').style.display = 'none';
    document.getElementById('downloadLink').href = url;
    document.getElementById('downloadLink').download = name;

    $('#previewModal').modal('show');
}

    function removeExistingPhoto(key) {
        document.getElementById('chip-' + key)?.remove();

        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = `delete_photos[${key}]`;
        input.value = '1';
        document.getElementById('deliveryUploadForm').appendChild(input);
    }

    document.querySelectorAll('.photo-upload-input').forEach(input => {
    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File Type',
                text: 'Only JPG and PNG images are allowed!',
                confirmButtonColor: '#dc3545'
            });
            this.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Maximum file size is 5MB!',
                confirmButtonColor: '#dc3545'
            });
            this.value = '';
            return;
        }

        const key = this.id;
        const previewDiv = document.getElementById('preview-' + key);
        const span = previewDiv.querySelector('.btn');
        const fileNameEl = previewDiv.querySelector('.file-name');

        fileNameEl.textContent = file.name;
        previewDiv.style.display = 'block';

        span.onclick = () => {
            const url = URL.createObjectURL(file);
            openPreviewModal(url, file.name);
        };

        this.required = false;
    });
});

    window.clearNewPreview = function(key) {
        const input = document.getElementById(key);
        if (input) {
            input.value = '';
            document.getElementById('preview-' + key).style.display = 'none';
            input.required = true;
        }
    };
</script>
@endpush