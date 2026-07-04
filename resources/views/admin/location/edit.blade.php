@extends(backpack_view('blank'))

@section('title', 'Edit Location - ' . $location->name)

@push('after_styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .readonly-value {
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 10px 15px;
        min-height: 42px;
        display: flex;
        align-items: center;
    }

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
                    <h2 class="mb-0">Edit Location Information</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('location/' . $location->id) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">

                            {{-- Branch --}}
                            <div class="col-md-3 mb-3">
                                <label>Branch <span class="text-danger">*</span></label>
                                <select name="branch_code" class="form-control form-select" required>
                                    @foreach(\App\Models\Admin\Branch::all() as $branchItem)
                                    <option value="{{ $branchItem->code }}" {{ old('branch_code', $location->
                                        branch_code) == $branchItem->code ? 'selected' : '' }}>
                                        {{ $branchItem->name }} ({{ $branchItem->code }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Code -->
                            <div class="col-md-3 mb-3">
                                <label>Code (Min 3 Char)<span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control"
                                    value="{{ old('code', $location->code) }}" required minlength="3" maxlength="10">
                                <div id="codeError" class="text-danger mt-1"></div>
                            </div>

                            <!-- Name -->
                            <div class="col-md-3 mb-3">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $location->name) }}" required>
                            </div>

                            {{-- Description --}}
                            <div class="col-md-3 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3">{{ old('description', $location->description) }}</textarea>
                            </div>

                            <!-- Phone -->
                            <div class="col-md-3 mb-3">
                                <label>Phone</label>
                                <input type="tel" name="phone" class="form-control"
                                    value="{{ old('phone', $location->phone) }}" maxlength="10" pattern="[0-9]{10}"
                                    inputmode="numeric" title="Phone number must be exactly 10 digits (0-9 only)">
                            </div>

                            <!-- Email -->
                            <div class="col-md-3 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $location->email) }}"
                                    title="Please enter a valid email address">
                            </div>

                            <!-- City -->
                            <div class="col-md-3 mb-3">
                                <label>City</label>
                                <input type="text" name="city" class="form-control"
                                    value="{{ old('city', $location->city) }}">
                            </div>



                            <!-- Pincode -->
                            <div class="col-md-3 mb-3">
                                <label>Pincode</label>
                                <input type="text" name="pincode" class="form-control"
                                    value="{{ old('pincode', $location->pincode) }}" maxlength="6" pattern="[0-9]{6}"
                                    inputmode="numeric" title="Pincode must be exactly 6 digits (0-9 only)">
                            </div>

                            <!-- Latitude -->
                            <div class="col-md-3 mb-3">
                                <label>Latitude</label>
                                <input name="latitude" class="form-control"
                                    value="{{ old('latitude', $location->latitude) }}" step="0.000001" min="-90"
                                    max="90" inputmode="decimal">
                            </div>

                            <!-- Longitude -->
                            <div class="col-md-3 mb-3">
                                <label>Longitude</label>
                                <input name="longitude" class="form-control"
                                    value="{{ old('longitude', $location->longitude) }}" step="0.000001" min="-180"
                                    max="180" inputmode="decimal">
                            </div>

                            <div class="col-md-4 mb-3">

                                <label>Location Image</label>

                                <input type="file" name="location_image" id="location_image" class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp">

                            </div>

                            <div class="col-md-2 mb-3">

                                <img id="imagePreview" src="{{ $location->getFirstMediaUrl('location_image') }}" style="max-height:120px;
                                {{ $location->getFirstMediaUrl('location_image') ? '' : 'display:none;' }}"
                                    class="img-thumbnail">

                                @if($location->getFirstMedia('location_image'))
                                <div id="currentFileBlock" class="mt-2 text-muted">
                                    Current File:
                                    <strong>
                                        {{ $location->getFirstMedia('location_image')->file_name }}
                                    </strong>
                                </div>
                                @endif

                                <div id="selectedFileName" class="mt-2 text-primary"></div>

                            </div>

                            <!-- 🔹 STATUS -->
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', $location->is_active) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- Is Sales Location --}}
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Sales Location?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_sales_location" value="0">
                                    <input type="checkbox" name="is_sales_location" value="1" class="form-check-input"
                                        {{ old('is_sales_location', $location->is_sales_location) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- Is Workshop --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Workshop?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_workshop" value="0">
                                    <input type="checkbox" name="is_workshop" value="1" class="form-check-input" {{
                                        old('is_workshop', $location->is_workshop) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- Is Parts Location --}}
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Parts Location?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_parts_location" value="0">
                                    <input type="checkbox" name="is_parts_location" value="1" class="form-check-input"
                                        {{ old('is_parts_location', $location->is_parts_location) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- Is Stock Location --}}
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Stock Location?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_stock_location" value="0">
                                    <input type="checkbox" name="is_stock_location" value="1" class="form-check-input"
                                        {{ old('is_stock_location', $location->is_stock_location) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- Is Office Only --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Office Only?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_office_only" value="0">
                                    <input type="checkbox" name="is_office_only" value="1" class="form-check-input" {{
                                        old('is_office_only', $location->is_office_only) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- Is MWH --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is MWH?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_mwh" value="0">
                                    <input type="checkbox" name="is_mwh" value="1" class="form-check-input" {{
                                        old('is_mwh', $location->is_mwh) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- Is LMMWS --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is LMMWS?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_lmmws" value="0">
                                    <input type="checkbox" name="is_lmmws" value="1" class="form-check-input" {{
                                        old('is_lmmws', $location->is_lmmws) ? 'checked' : '' }}>
                                </div>
                            </div>

                        </div>

                        <!-- 🔹 BUTTONS -->
                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Location
                            </button>
                            <a href="{{ backpack_url('location') }}" class="btn btn-secondary btn-lg">
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
    document.addEventListener('DOMContentLoaded', function () {

    const officeOnly = document.querySelector(
    'input[type="checkbox"][name="is_office_only"]'
);

const otherCheckboxes = [
    'is_sales_location',
    'is_workshop',
    'is_parts_location',
    'is_stock_location',
    'is_mwh',
    'is_lmmws'
].map(name =>
    document.querySelector(`input[type="checkbox"][name="${name}"]`)
);

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

officeOnly.addEventListener('change', toggleOfficeOnlyMode);

toggleOfficeOnlyMode();

otherCheckboxes.forEach(cb => {

    if (!cb) return;

    cb.addEventListener('change', function () {

        if (this.checked) {

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
                    .replace(/[^0-9.]/g, '')   // remove text
                    .replace(/(\..*)\./g, '$1'); // only one decimal point
            });

            // Block e,+,-
            field.addEventListener('keydown', function (e) {
                if (['e', 'E', '+', '-'].includes(e.key)) {
                    e.preventDefault();
                }
            });
        }
    });
    
});

document.getElementById('location_image')
.addEventListener('change', function(e){

    const file = e.target.files[0];

    if(!file) return;

    const currentFileBlock =
        document.getElementById('currentFileBlock');

    if(currentFileBlock){
        currentFileBlock.style.display = 'none';
    }

    document.getElementById('selectedFileName').innerText =
        'Selected: ' + file.name;

    const reader = new FileReader();

    reader.onload = function(ev){

        const img = document.getElementById('imagePreview');

        img.src = ev.target.result;
        img.style.display = 'block';
    };

    reader.readAsDataURL(file);
});
document.querySelector('input[name="code"]').addEventListener('input', function () {

    let value = this.value.trim();
    let error = document.getElementById('codeError');

    if (value.length > 0 && value.length < 3) {
        error.innerText = 'Code must be at least 3 characters';
    } else if (value.length > 10) {
        error.innerText = 'Code cannot exceed 10 characters';
    } else {
        error.innerText = '';
    }
});

document.querySelector('form').addEventListener('submit', function(e){

    let code = document.querySelector('input[name="code"]').value.trim();

    if(code.length < 3 || code.length > 10){

        e.preventDefault();

        document.getElementById('codeError').innerText =
            'Code must be between 3 and 10 characters';
    }
});


</script>
@endpush