@extends(backpack_view('blank'))

@section('title', $title ?? 'Location Form')

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
        background-color: var(--tblr-bg-surface-secondary);
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
                    <h2 class="mb-0">
                        {{ isset($location) ? 'Edit Location Information' : 'Add New Location' }}
                    </h2>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors->all() as $error): ?>
                                    <li>{{ $error }}</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ isset($location) ? backpack_url('org/location/' . $location->id) : backpack_url('org/location') }}" enctype="multipart/form-data">
                        @csrf
                        @if(isset($location))
                            @method('PUT')
                        @endif

                        <div class="row">

                            {{-- BRANCH --}}
                            <div class="col-md-3 mb-3">
                                <label>Branch <span class="text-danger">*</span></label>
                                <select name="branch_code" class="form-control form-select" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach($branches as $branchItem): ?>
                                        <option value="{{ $branchItem->code }}" {{ old('branch_code', $location->branch_code ?? '') == $branchItem->code ? 'selected' : '' }}>
                                            {{ $branchItem->name }} ({{ $branchItem->code }})
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            {{-- CODE --}}
                            <div class="col-md-2 mb-3">
                                <label>
                                    Code
                                    @if(!isset($location)) <span class="text-danger">*</span> @endif
                                </label>
                                
                                @if(isset($location))
                                    <!-- Visually disabled field for the user -->
                                    <input type="text" class="form-control" value="{{ $location->code }}" readonly disabled>
                                    <!-- Hidden field to safely pass validation -->
                                    <input type="hidden" name="code" value="{{ $location->code }}">
                                    <div class="form-text">Code cannot be changed after creation.</div>
                                @else
                                    <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code') }}" required minlength="3" maxlength="10">
                                    <div id="codeError" class="text-danger mt-1"></div>
                                @endif
                            </div>

                            {{-- NAME --}}
                            <div class="col-md-3 mb-3">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $location->name ?? '') }}" required>
                            </div>

                            {{-- DESCRIPTION --}}
                            <div class="col-md-4 mb-3">
                                <label>Description</label>
                                <input type="text" name="description" class="form-control" value="{{ old('description', $location->description ?? '') }}">
                            </div>

                            {{-- PHONE --}}
                            <div class="col-md-2 mb-3">
                                <label>Phone</label>
                                <input type="tel" name="phone" class="form-control" value="{{ old('phone', $location->phone ?? '') }}" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" title="Exactly 10 digits">
                            </div>

                            {{-- EMAIL --}}
                            <div class="col-md-3 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $location->email ?? '') }}">
                            </div>

                            {{-- ADDRESS --}}
                            <div class="col-md-7 mb-3">
                                <label>Address</label>
                                <textarea name="address" class="form-control" rows="1">{{ old('address', $location->address ?? '') }}</textarea>
                            </div>

                            {{-- CITY --}}
                            <div class="col-md-3 mb-3">
                                <label>City</label>
                                <input type="text" name="city" class="form-control" value="{{ old('city', $location->city ?? '') }}">
                            </div>

                            {{-- PINCODE --}}
                            <div class="col-md-3 mb-3">
                                <label>Pincode</label>
                                <input type="text" name="pincode" class="form-control" value="{{ old('pincode', $location->pincode ?? '') }}" maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
                            </div>

                            {{-- LATITUDE --}}
                            <div class="col-md-3 mb-3">
                                <label>Latitude</label>
                                <input name="latitude" class="form-control" value="{{ old('latitude', $location->latitude ?? '') }}" step="0.000001" min="-90" max="90" inputmode="decimal">
                            </div>

                            {{-- LONGITUDE --}}
                            <div class="col-md-3 mb-3">
                                <label>Longitude</label>
                                <input name="longitude" class="form-control" value="{{ old('longitude', $location->longitude ?? '') }}" step="0.000001" min="-180" max="180" inputmode="decimal">
                            </div>

                            <div class="col-12 mt-3"><hr></div>

                            {{-- STATUS CHECKBOXES --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $location->is_active ?? true) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Office Only?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_office_only" value="0">
                                    <input type="checkbox" name="is_office_only" value="1" class="form-check-input" {{ old('is_office_only', $location->is_office_only ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Sales Location?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_sales_location" value="0">
                                    <input type="checkbox" name="is_sales_location" value="1" class="form-check-input" {{ old('is_sales_location', $location->is_sales_location ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Workshop?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_workshop" value="0">
                                    <input type="checkbox" name="is_workshop" value="1" class="form-check-input" {{ old('is_workshop', $location->is_workshop ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Parts?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_parts_location" value="0">
                                    <input type="checkbox" name="is_parts_location" value="1" class="form-check-input" {{ old('is_parts_location', $location->is_parts_location ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Stock?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_stock_location" value="0">
                                    <input type="checkbox" name="is_stock_location" value="1" class="form-check-input" {{ old('is_stock_location', $location->is_stock_location ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is MWH?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_mwh" value="0">
                                    <input type="checkbox" name="is_mwh" value="1" class="form-check-input" {{ old('is_mwh', $location->is_mwh ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is LMMWS?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_lmmws" value="0">
                                    <input type="checkbox" name="is_lmmws" value="1" class="form-check-input" {{ old('is_lmmws', $location->is_lmmws ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                        </div>

                        @include('admin.org.partials.media-fields', ['imageCollection' => 'location_image', 'model' => $location ?? null])

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> 
                                {{ isset($location) ? 'Update Location' : 'Create Location' }}
                            </button>
                            <a href="{{ backpack_url('org/location') }}" class="btn btn-secondary btn-lg">
                                Cancel
                            </a>
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
    $('input[name="code"]').on('input', function () {
        let value = this.value.trim();
        let error = $('#codeError');

        if (value.length > 0 && value.length < 3) {
            error.text('Code must be at least 3 characters');
        } else if (value.length > 10) {
            error.text('Code cannot exceed 10 characters');
        } else {
            error.text('');
        }
    });

    $('form').on('submit', function(e){
        let codeInput = $('input[name="code"]');
        if (codeInput.length > 0 && !codeInput.prop('disabled')) {
            let code = codeInput.val().trim();
            if(code.length < 3 || code.length > 10){
                e.preventDefault();
                $('#codeError').text('Code must be between 3 and 10 characters');
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {

        const officeOnly = document.querySelector('input[type="checkbox"][name="is_office_only"]');
        
        const otherCheckboxes = [
            'is_sales_location', 'is_workshop', 'is_parts_location', 
            'is_stock_location', 'is_mwh', 'is_lmmws'
        ].map(name => document.querySelector(`input[type="checkbox"][name="${name}"]`));

        function toggleOfficeOnlyMode() {
            if (officeOnly.checked) {
                otherCheckboxes.forEach(cb => {
                    if (!cb) return;
                    cb.checked = false;
                    cb.disabled = true;
                });
            } else {
                otherCheckboxes.forEach(cb => {
                    if (!cb) return;
                    cb.disabled = false;
                });
            }
        }

        if(officeOnly) {
            officeOnly.addEventListener('change', toggleOfficeOnlyMode);
            toggleOfficeOnlyMode(); // Run on load for edit mode mapping
        }

        otherCheckboxes.forEach(cb => {
            if (!cb) return;
            cb.addEventListener('change', function () {
                if (this.checked && officeOnly) {
                    officeOnly.checked = false;
                    toggleOfficeOnlyMode();
                }
            });
        });

        ['phone', 'pincode'].forEach(name => {
            const field = document.querySelector(`[name="${name}"]`);
            if (field) {
                field.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '');
                });
            }
        });

        // Latitude & Longitude => Digits + Decimal
        ['latitude', 'longitude'].forEach(name => {
            const field = document.querySelector(`[name="${name}"]`);
            if (field) {
                field.addEventListener('input', function () {
                    this.value = this.value
                        .replace(/[^0-9.-]/g, '')   // remove text but allow minus sign
                        .replace(/(\..*)\./g, '$1'); // only one decimal point
                });

                // Block specific non-numeric keys
                field.addEventListener('keydown', function (e) {
                    if (['e', 'E', '+'].includes(e.key)) {
                        e.preventDefault();
                    }
                });
            }
        });
    });
</script>
@endpush