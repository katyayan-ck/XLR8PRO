@extends(backpack_view('blank'))

@section('title', 'Pending RTO - Booking #' . $booking->id)

@push('after_styles')
<style>
    .proof-chip {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 6px;
        color: #fff;
        font-size: 13px;
    }

    .proof-chip i {
        margin-right: 6px;
    }

    .file-name {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btn-action {
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
    }

    .btn-download {
        color: #fff;
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

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

    .readonly-value.text-danger {
        font-weight: 600;
    }
</style>
@endpush

@section('content')

<div class="container-fluid">

    @include(backpack_view('inc.alerts'))

    <div class="card card-body shadow-sm mb-4" style="border-radius: 12px">
        <h2 class="mb-3">
            Invoice Details (RTO)</h2>
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

    <div class="card card-body shadow-sm mb-4" style="border-radius: 12px">
        <h2 class="mb-3">RTO / Registration Details</h2>

        <form id="rtoForm" method="POST" action="{{ route('booking.rto.update', $booking->id) }}"
            enctype="multipart/form-data">
            @csrf

            <div class="row g-3">



                <div class="col-md-3">
                    <label class="form-label">Trade Used</label>
                    <select name="trade_used" class="form-control form-select">
                        <option value="">Select Trade Used</option>
                        <option value="1" {{ old('trade_used', $rto->trade_used ?? '') == '1' ? 'selected' : '' }}>BKN
                            AD User 1 (RJ0730024TC)</option>
                        <option value="2" {{ old('trade_used', $rto->trade_used ?? '') == '2' ? 'selected' : '' }}>BKN
                            AD User 2 (RJ0730024TC)</option>
                        <option value="3" {{ old('trade_used', $rto->trade_used ?? '') == '3' ? 'selected' : '' }}>BKN
                            AD User 3 (RJ0730024TC)</option>
                        <option value="4" {{ old('trade_used', $rto->trade_used ?? '') == '4' ? 'selected' : '' }}>SUJ
                            AD (RJ44C0012TC)</option>
                        <option value="5" {{ old('trade_used', $rto->trade_used ?? '') == '5' ? 'selected' : '' }}>BKN
                            LMM L5 (RJ07C0056TC)</option>
                        <option value="6" {{ old('trade_used', $rto->trade_used ?? '') == '6' ? 'selected' : '' }}>BKN
                            LMM L3 (RJ07TC0322)</option>
                    </select>
                    @error('trade_used') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Sale Type</label>
                    <select name="sale_type" id="sale_type" class="form-control form-select">
                        <option value="">Select Sale Type</option>
                        <option value="1" {{ old('sale_type', $rto->sale_type ?? '') == '1' ? 'selected' : '' }}>Within
                            State</option>
                        <option value="2" {{ old('sale_type', $rto->sale_type ?? '') == '2' ? 'selected' : '' }}>Outside
                            State</option>
                    </select>
                    @error('sale_type') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Permit</label>
                    <select name="permit" id="permit" class="form-control form-select">
                        <option value="">Select Permit</option>
                        <option value="1" {{ old('permit', $rto->permit ?? '') == '1' ? 'selected' : '' }}>Private - U/C
                            (4 Wheeler)</option>
                        <option value="2" {{ old('permit', $rto->permit ?? '') == '2' ? 'selected' : '' }}>Private - BH
                            (4 Wheeler)</option>
                        <option value="3" {{ old('permit', $rto->permit ?? '') == '3' ? 'selected' : '' }}>Private - EV
                            (4 Wheeler)</option>
                        <option value="4" {{ old('permit', $rto->permit ?? '') == '4' ? 'selected' : '' }}>Goods - G (4
                            Wheeler)</option>
                        <option value="5" {{ old('permit', $rto->permit ?? '') == '5' ? 'selected' : '' }}>Goods - G 3
                            Ton+ (4 Wheeler)</option>
                        <option value="6" {{ old('permit', $rto->permit ?? '') == '6' ? 'selected' : '' }}>Goods - G (3
                            Wheeler)</option>
                        <option value="7" {{ old('permit', $rto->permit ?? '') == '7' ? 'selected' : '' }}>Goods - G EV
                            (3 Wheeler)</option>
                        <option value="8" {{ old('permit', $rto->permit ?? '') == '8' ? 'selected' : '' }}>Taxi - T (4
                            Wheeler)</option>
                        <option value="9" {{ old('permit', $rto->permit ?? '') == '9' ? 'selected' : '' }}>Passenger - P
                            (3 Wheeler)</option>
                        <option value="10" {{ old('permit', $rto->permit ?? '') == '10' ? 'selected' : '' }}>Passenger -
                            P EV (3 Wheeler)</option>
                        <option value="11" {{ old('permit', $rto->permit ?? '') == '11' ? 'selected' : '' }}>Ambulance
                            (Misc.)</option>
                    </select>
                    @error('permit') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Body Type</label>
                    <select name="body_type" id="body_type" class="form-control form-select">
                        <option value="">Select Body Type</option>
                        <option value="1" {{ old('body_type', $rto->body_type ?? '') == '1' ? 'selected' : ''
                            }}>Complete</option>
                        <option value="2" {{ old('body_type', $rto->body_type ?? '') == '2' ? 'selected' : '' }}>CBC
                        </option>
                    </select>
                    @error('body_type') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Registration Type</label>
                    <select name="registration_type" class="form-control form-select">

                        <option value="">Select Registration Type</option>

                        <option value="0" {{ old('registration_type', $rto->rgn_type ?? '') == '0' ? 'selected' : '' }}>
                            Exempted (Reg & Hypo Fee Only)
                        </option>

                        <option value="1" {{ old('registration_type', $rto->rgn_type ?? '') == '1' ? 'selected' : '' }}>
                            TRC Only
                        </option>

                        <option value="2" {{ old('registration_type', $rto->rgn_type ?? '') == '2' ? 'selected' : '' }}>
                            Tax Only
                        </option>

                        <option value="3" {{ old('registration_type', $rto->rgn_type ?? '') == '3' ? 'selected' : '' }}>
                            TRC + Tax
                        </option>

                    </select>
                    @error('registration_type') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Registration No. Type</label>
                    <select name="reg_no_type" id="reg_no_type" class="form-control form-select">
                        <option value="">Select Registration No. Type</option>
                        <option value="1" {{ old('reg_no_type', $rto->rgn_no_type ?? '') == '1' ? 'selected' : ''
                            }}>Regular</option>
                        <option value="2" {{ old('reg_no_type', $rto->rgn_no_type ?? '') == '2' ? 'selected' : '' }}>BH
                        </option>
                        <option value="3" {{ old('reg_no_type', $rto->rgn_no_type ?? '') == '3' ? 'selected' : ''
                            }}>Special</option>
                    </select>
                    @error('reg_no_type') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="application_no_group">
                    <label class="form-label">RTO Application No.</label>
                    <input type="text" name="application_no" id="application_no" class="form-control text-uppercase"
                        value="{{ old('application_no', $rto->app_no ?? '') }}">
                    @error('application_no') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="trc_number_group">
                    <label class="form-label">TRC Number</label>
                    <input type="text" name="trc_number" id="trc_number" class="form-control text-uppercase"
                        value="{{ old('trc_number', $rto->trc_no ?? '') }}">
                    @error('trc_number') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="bank_ref_no_group">
                    <label class="form-label">TRC Payment Ref. No.</label>
                    <input type="text" name="bank_ref_no" id="bank_ref_no" class="form-control text-uppercase"
                        value="{{ old('bank_ref_no', $rto->trc_payment_no ?? '') }}">
                    @error('bank_ref_no') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>


                <div class="col-md-3" id="trc_copy_group">
                    <label class="form-label">TRC Copy</label>

                    @php
                    $trcMedia = $rto?->getFirstMedia('trc_copy');
                    @endphp

                    <!-- Existing saved file (shown only if one is already uploaded) -->
                    <div id="trcCopyExisting" class="mb-2" style="{{ $trcMedia ? '' : 'display:none;' }}">
                        @if($trcMedia)
                        <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                            style="cursor:pointer;"
                            onclick="openPolicyModal('{{ $trcMedia->getUrl() }}', '{{ addslashes($trcMedia->file_name) }}')">
                            <i class="la la-paperclip"></i>
                            <span class="fw-medium small text-truncate" style="max-width:140px;">
                                {{ Str::limit($trcMedia->file_name, 20) }}
                            </span>
                        </span>
                        <button type="button" class="btn btn-outline-danger btn-sm ms-1" title="Remove file"
                            onclick="removeExistingFile('trc_copy')">
                            <i class="la la-trash"></i>
                        </button>
                        @endif
                    </div>

                    <input type="file" name="trc_copy" id="trcCopyInput" class="form-control" accept=".pdf">
                    @error('trc_copy') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror

                    <!-- Flag sent to server: '1' means delete the existing file (only acted on if no new file is uploaded) -->
                    <input type="hidden" name="remove_trc_copy" id="removeTrcCopyFlag" value="0">

                    <!-- Chip Preview Area for a newly selected (not yet saved) file -->
                    <div id="trcCopyPreview" class="mt-2"></div>

                    <small class="text-muted d-block mt-1" id="trcCopyHint"
                        style="{{ $trcMedia ? '' : 'display:none;' }}">
                        Uploading a new file will replace the existing one.
                    </small>

                </div>

                <div class="col-md-3" id="tax_payment_ref_no_group">
                    <label class="form-label">Tax Payment Ref. No.</label>
                    <input type="text" name="tax_payment_ref_no" id="tax_payment_ref_no"
                        class="form-control text-uppercase"
                        value="{{ old('tax_payment_ref_no', $rto->tax_payment_bank_ref_no ?? '') }}">
                    @error('tax_payment_ref_no') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>


                <div class="col-md-3" id="tax_receipt_copy_group">
                    <label class="form-label">Tax Receipt Copy</label>

                    @php
                    $taxMedia = $rto?->getFirstMedia('tax_receipt_copy');
                    @endphp

                    <!-- Existing saved file (shown only if one is already uploaded) -->
                    <div id="taxCopyExisting" class="mb-2" style="{{ $taxMedia ? '' : 'display:none;' }}">
                        @if($taxMedia)
                        <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                            style="cursor:pointer;"
                            onclick="openPolicyModal('{{ $taxMedia->getUrl() }}', '{{ addslashes($taxMedia->file_name) }}')">
                            <i class="la la-paperclip"></i>
                            <span class="fw-medium small text-truncate" style="max-width:140px;">
                                {{ Str::limit($taxMedia->file_name, 20) }}
                            </span>
                        </span>
                        <button type="button" class="btn btn-outline-danger btn-sm ms-1" title="Remove file"
                            onclick="removeExistingFile('tax_receipt_copy')">
                            <i class="la la-trash"></i>
                        </button>
                        @endif
                    </div>

                    <input type="file" name="tax_receipt_copy" id="taxCopyInput" class="form-control" accept=".pdf">
                    @error('tax_receipt_copy') <span class="text-danger small d-block mt-1">{{ $message }}</span>
                    @enderror

                    <!-- Flag sent to server: '1' means delete the existing file (only acted on if no new file is uploaded) -->
                    <input type="hidden" name="remove_tax_receipt_copy" id="removeTaxCopyFlag" value="0">

                    <div id="taxCopyPreview" class="mt-2"></div>

                    <small class="text-muted d-block mt-1" id="taxCopyHint"
                        style="{{ $taxMedia ? '' : 'display:none;' }}">
                        Uploading a new file will replace the existing one.
                    </small>

                </div>

                <div class="col-md-4" id="vehicle_reg_no_group">
                    <label class="form-label">Vehicle Registration No.</label>
                    <input type="text" name="vehicle_reg_no" id="vehicle_reg_no" class="form-control text-uppercase"
                        value="{{ old('vehicle_reg_no', $rto->vh_rgn_no ?? '') }}">
                    @error('vehicle_reg_no') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="col-12 mt-4 text-center">
                    <button type="submit" class="btn btn-success btn-lg px-5">
                        <i class="la la-save"></i> Save RTO Details
                    </button>
                    <a href="{{ route('booking.pending-rto') }}" class="btn btn-secondary btn-lg px-5 ms-3">
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {


    function handlePolicyFile(input, previewContainerId) {
        const previewDiv = document.getElementById(previewContainerId);
        if (!previewDiv) return;

        previewDiv.innerHTML = '';

        if (input.files && input.files[0]) {
            const file = input.files[0];

            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'File size must be less than 2MB',
                    confirmButtonColor: '#3085d6'
                });
                input.value = '';
                return;
            }

            const fileURL = URL.createObjectURL(file);

            previewDiv.innerHTML = `
                <span class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                      style="cursor: pointer;"
                      onclick="openPolicyModal('${fileURL}', '${file.name.replace(/'/g, "\\'")}')">
                    <i class="la la-paperclip"></i>
                    <span class="fw-medium small text-truncate" style="max-width: 180px;">
                        ${file.name}
                    </span>
                </span>
            `;
        }
    }

    window.openPolicyModal = function(url, name) {
        const modalTitle    = document.getElementById('policyModalFileName');
        const modalDownload = document.getElementById('policyModalDownload');
        const modalPreview  = document.getElementById('policyModalPreview');

        if (modalTitle)    modalTitle.innerText = name;
        if (modalDownload) modalDownload.href = url;
        if (modalPreview)  modalPreview.src = url;

        const modalElement = document.getElementById('policyCopyModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    };

    

    const trcInput = document.getElementById('trcCopyInput');
    const taxInput = document.getElementById('taxCopyInput');

    if (trcInput) {
        trcInput.addEventListener('change', function() {
            handlePolicyFile(this, 'trcCopyPreview');
            
            const flag = document.getElementById('removeTrcCopyFlag');
            const existing = document.getElementById('trcCopyExisting');
            if (flag) flag.value = '0';
            if (existing) existing.style.display = 'none';
        });
    }

    if (taxInput) {
        taxInput.addEventListener('change', function() {
            handlePolicyFile(this, 'taxCopyPreview');
            const flag = document.getElementById('removeTaxCopyFlag');
            const existing = document.getElementById('taxCopyExisting');
            if (flag) flag.value = '0';
            if (existing) existing.style.display = 'none';
        });
    }

    // Remove an already-saved TRC / Tax Receipt copy (sets a hidden flag the
    // controller checks; actual delete only happens if no replacement file is uploaded)
    window.removeExistingFile = function(type) {
        Swal.fire({
            title: 'Remove this file?',
            text: 'This will delete the saved copy when you submit the form. You can upload a new one instead.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove',
            confirmButtonColor: '#dc3545'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            if (type === 'trc_copy') {
                document.getElementById('trcCopyExisting').style.display = 'none';
                document.getElementById('removeTrcCopyFlag').value = '1';
            } else if (type === 'tax_receipt_copy') {
                document.getElementById('taxCopyExisting').style.display = 'none';
                document.getElementById('removeTaxCopyFlag').value = '1';
            }
        });
    };

    
    const policyModal = document.getElementById('policyCopyModal');
    if (policyModal) {
        $('#policyCopyModal').on('show.bs.modal', function () {
            $(this).appendTo('body');
        });
    }

    

    function applyStrictMask(selector, maskPattern, placeholderText) {
        $(selector).mask(maskPattern, {
            placeholder: placeholderText
        }).on('input paste keyup', function() {
            let $this = $(this);
            let val = $this.val().toUpperCase().replace(/[^A-Z0-9]/g, '');
            if ($this.attr('id') === 'vehicle_reg_no') {
                val = val.substring(0, 10);
            }
            $this.val(val);
            $this.unmask().mask(maskPattern, { placeholder: '' });
        }).on('blur', function() {
            if ($(this).val() === '') {
                $(this).attr('placeholder', placeholderText);
            }
        });
    }

    applyStrictMask('#trc_number', 'AAAAAAAAAAAAAAA', 'TRC123456789');
    applyStrictMask('#application_no', 'AAAAAAAAAAAAAAA', 'APP123456789');
    applyStrictMask('#bank_ref_no', 'AAAAAAAAAAAAAAAAAAAA', 'BANKREF123456789');
    applyStrictMask('#tax_payment_ref_no', 'AAAAAAAAAAAAAAAAAAAA', 'TAXREF123456789');

    $('#vehicle_reg_no').on('input paste keyup', function() {
        let $this = $(this);
        let val = $this.val().toUpperCase().replace(/\s+/g, '');
        $this.val(val);
    });

    function validateField(fieldId, regex, errorMsg) {
        const field = $('#' + fieldId);
        let error = field.next('.text-danger');
        if (error.length) error.remove();
        const val = field.val().trim();
        if (val && !regex.test(val)) {
            field.addClass('is-invalid');
            $('<span class="text-danger small d-block mt-1">' + errorMsg + '</span>').insertAfter(field);
        } else {
            field.removeClass('is-invalid');
        }
    }

    $('#trc_number').on('input change blur', function() { validateField('trc_number', /^[A-Z0-9]{10,15}$/, 'TRC Number: 10-15 alphanumeric only'); });
    $('#application_no').on('input change blur', function() { validateField('application_no', /^[A-Z0-9]{10,15}$/, 'Application No.: 10-15 alphanumeric only'); });
    $('#bank_ref_no, #tax_payment_ref_no').on('input change blur', function() { validateField(this.id, /^[A-Z0-9]{10,20}$/, 'Ref No.: 10-20 alphanumeric only'); });

    // Dynamic fields show/hide logic (tumhara purana code)
    const rulesData = @json($data['rto_rules'] ?? []);

    function updateFields() {
        const normalize = str => (str || '').trim().replace(/\s+/g, ' ').toUpperCase();
        const saleText   = normalize($('#sale_type option:selected').text());
        const permitText = normalize($('#permit option:selected').text());
        const bodyText   = normalize($('#body_type option:selected').text());
        const regText    = normalize($('#reg_no_type option:selected').text());

        const matchingRule = rulesData.find(rule => {
            const ruleSale   = normalize(rule.sale_type);
            const rulePermit = normalize(rule.permit);
            const ruleBody   = normalize(rule.body_type);
            const ruleReg    = normalize(rule.reg_no_type);
            return (ruleSale === saleText) &&
                   (rulePermit === permitText) &&
                   (ruleBody === bodyText) &&
                   (ruleReg === regText);
        });

        const groups = {
            'application_no_group':   'app_no',
            'trc_number_group':       'trc_number',
            'bank_ref_no_group':      'trc_pay',
            'trc_copy_group':         'trc_copy',
            'tax_payment_ref_no_group': 'tax_pay',
            'tax_receipt_copy_group': 'tax_copy',
            'vehicle_reg_no_group':   'veh_reg'
        };

        Object.keys(groups).forEach(id => $('#' + id).hide());

        if (matchingRule) {
            Object.keys(groups).forEach(id => {
                const key = groups[id];
                if (String(matchingRule[key] || '').trim().toUpperCase() === 'YES') {
                    $('#' + id).show();
                }
            });
        }
    }

    $('#sale_type, #permit, #body_type, #reg_no_type').on('change', updateFields);
    updateFields(); // Initial call
});
</script>

@endpush