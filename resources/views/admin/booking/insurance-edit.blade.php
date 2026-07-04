@extends(backpack_view('blank'))

@section('title', 'Edit Insurance - Booking #' . $booking->id)

@push('after_styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .required-mark {
        color: #dc3545;
        margin-left: 4px;
    }

    .is-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 4px rgba(40, 167, 69, .4) !important;
    }

    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 4px rgba(220, 53, 69, .4) !important;
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

    .readonly-value.text-danger {
        font-weight: 600;
    }

    .modal-backdrop {
        z-index: 1040 !important;
    }

    .modal {
        z-index: 1060 !important;
    }

    .modal-dialog {
        z-index: 1070 !important;
    }
</style>
@endpush

@section('content')

<div class="container-fluid">

    @include(backpack_view('inc.alerts'))

    <div class="card card-body shadow-sm mb-4" style="border-radius:12px">
        <h2 class="mb-3">
            Invoice Details (Pending Insurance / RTO)</h2>
        <div class="row">

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

        </div>
    </div>

    <div class="card card-body shadow-sm mb-4" style="border-radius:12px">
        <h2 class="mb-3">Insurance Details</h2>

        <form method="POST" action="{{ route('booking.insurance.update', $booking->id) }}"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="booking_id" value="{{ $booking->id }}">

            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">
                        Insurance Source
                        <span class="required-mark">*</span>
                    </label>
                    <select name="insurance_category" class="form-control form-select" required>
                        <option value="">Select Source</option>
                        <option value="1" {{ old('insurance_category', $insurance?->source ?? '') == 1 ? 'selected' : ''
                            }}>
                            By Dealer (OEM Portal)
                        </option>
                        <option value="2" {{ old('insurance_category', $insurance?->source ?? '') == 2 ? 'selected' : ''
                            }}>
                            By Dealer (Agency)
                        </option>
                        <option value="3" {{ old('insurance_category', $insurance?->source ?? '') == 3 ? 'selected' : ''
                            }}>
                            By Owner (Self)
                        </option>
                    </select>
                    @error('insurance_category') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Insurance Company
                        <span class="required-mark">*</span>
                    </label>
                    <select name="insurance_company" id="insurance_company" class="form-control form-select" required>
                        <option value="">Select Company</option>
                        @foreach ($data['insurances'] ?? [] as $ins)
                        <option value="{{ $ins['id'] }}" data-short="{{ $ins['short_name'] ?? '' }}" {{
                            old('insurance_company', $insurance?->insurer ?? '') == $ins['id'] ? 'selected' : '' }}>
                            {{ $ins['name'] ?? 'N/A' }}
                        </option>
                        @endforeach
                    </select>
                    @error('insurance_company') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Short Name</label>
                    <input type="text" id="insurance_short_name" class="form-control" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Policy No.
                        <span class="required-mark">*</span>
                    </label>
                    <input type="text" name="policy_no" class="form-control text-uppercase"
                        value="{{ old('policy_no', $insurance?->pol_no ?? '') }}" maxlength="25" required>
                    @error('policy_no') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Policy Date
                        <span class="required-mark">*</span>
                    </label>
                    <input type="text" name="policy_date" id="policy_date" class="form-control flatpickr"
                        value="{{ old('policy_date', $insurance?->pol_date ? \Carbon\Carbon::parse($insurance->pol_date)->format('d-M-Y') : '') }}"
                        required>
                    <input type="hidden" name="hidden_policy_date" id="hidden_policy_date"
                        value="{{ old('hidden_policy_date', $insurance?->pol_date ?? '') }}">
                    @error('hidden_policy_date') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Policy Type
                        <span class="required-mark">*</span>
                    </label>
                    <select name="policy_type" class="form-control form-select" required>
                        <option value="">Select Type</option>
                        <option value="1" {{ old('policy_type', $insurance?->pol_type ?? '') == 1 ? 'selected' : ''
                            }}>Standard</option>
                        <option value="2" {{ old('policy_type', $insurance?->pol_type ?? '') == 2 ? 'selected' : ''
                            }}>Nil Dep</option>
                        <option value="3" {{ old('policy_type', $insurance?->pol_type ?? '') == 3 ? 'selected' : ''
                            }}>Base(Nil Dep + Consumables)</option>
                        <option value="4" {{ old('policy_type', $insurance?->pol_type ?? '') == 4 ? 'selected' : ''
                            }}>Higher(Nil Dep + Consumables + Add Ons)</option>
                    </select>
                    @error('policy_type') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>


                <div class="col-md-3">
                    <label class="form-label">
                        Policy Copy
                        <span class="required-mark">*</span>
                    </label>
                    <input type="file" name="policy_copy" id="policyCopyInput" class="form-control" accept=".pdf"
                        required>
                    @error('policy_copy') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror

                    <div id="policyCopyPreview" class="mt-3"></div>

                    @if ($insurance && $insurance->hasMedia('policy_copy'))
                    <div class="mt-2">
                        @php $media = $insurance->getFirstMedia('policy_copy'); @endphp
                        <a href="{{ $media->getUrl() }}" target="_blank" class="btn btn-sm btn-info">
                            <i class="la la-file-pdf"></i> View Current Policy
                        </a>
                    </div>
                    @endif
                </div>

                <div class="col-12 mt-4 text-center">
                    <button type="submit" class="btn btn-success btn-lg px-5">
                        <i class="la la-save"></i> Save Insurance
                    </button>
                    <a href="{{ route('booking.pending-insurance') }}" class="btn btn-secondary btn-lg px-5 ms-3">
                        Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>

</div>
<div class="modal fade" id="policyCopyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="policyModalFileName"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <iframe id="policyModalPreview" style="width:100%; height:500px;" frameborder="0"></iframe>
            </div>
            <div class="modal-footer">
                <a id="policyModalDownload" class="btn btn-success" download>Download</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

    flatpickr('#policy_date', {
        dateFormat: "d-M-Y",
        maxDate: "today",
        onChange: function(selectedDates) {
            const hidden = document.getElementById('hidden_policy_date');
            if (selectedDates[0] && hidden) {
                hidden.value = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
            }
        }
    });

    const companySelect = document.getElementById('insurance_company');
    const shortInput = document.getElementById('insurance_short_name');

    if (companySelect && shortInput) {
        companySelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            shortInput.value = opt.dataset.short || '';
        });
        companySelect.dispatchEvent(new Event('change'));
    }

    $('#policyCopyModal').on('show.bs.modal', function () {
        $(this).appendTo('body');
    });

});

function handlePolicyCopy(input) {
        const previewDiv = document.getElementById('policyCopyPreview');
        previewDiv.innerHTML = '';

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileURL = URL.createObjectURL(file);

            previewDiv.innerHTML = `
                <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                      style="cursor: pointer;"
                      onclick="openPolicyModal('${fileURL}', '${file.name.replace(/'/g, "\\'")}')">
                    <i class="la la-paperclip"></i>
                    <span class="fw-medium small">${file.name}</span>
                </span>
            `;
        }
    }

    function openPolicyModal(url, name) {
    document.getElementById('policyModalFileName').innerText = name;
    document.getElementById('policyModalDownload').href = url;
    document.getElementById('policyModalPreview').src = url;

    $('#policyCopyModal').modal('show');
}

    document.getElementById('policyCopyInput')?.addEventListener('change', function() {
        handlePolicyCopy(this);
    });
</script>
@endpush