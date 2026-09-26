@extends(backpack_view('blank'))

@section('title', $title ?? 'Branch Form')

@push('after_styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                        {{ isset($branch) ? 'Edit Branch Information' : 'Add New Branch' }}
                    </h2>
                </div>

                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors->all() as$error): ?>
                                    <li>{{ $error }}</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ isset($branch) ? backpack_url('org/branch/' . $branch->code) : backpack_url('org/branch') }}" enctype="multipart/form-data">
                        @csrf
                        @if(isset($branch))
                            @method('PUT')
                        @endif

                        <div class="row">

                            {{-- CODE --}}
                            <div class="col-md-2 mb-3">
                                <label>
                                    Code
                                    @if(!isset($branch)) <span class="text-danger">*</span> @endif
                                </label>
                                
                                @if(isset($branch))
                                    <!-- Visually disabled field for the user -->
                                    <input type="text" class="form-control" value="{{ $branch->code }}" readonly disabled>
                                    
                                    <!-- Hidden field to safely pass validation -->
                                    <input type="hidden" name="code" value="{{ $branch->code }}">
                                    
                                    <div class="form-text">Code cannot be changed after creation.</div>
                                @else
                                    <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code') }}" maxlength="10" minlength="3" required>
                                    <div id="codeError" class="text-danger mt-1"></div>
                                @endif
                            </div>

                            {{-- NAME --}}
                            <div class="col-md-3 mb-3">
                                <label>
                                    Branch Name
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $branch->name ?? '') }}" required>
                            </div>

                            {{-- DESCRIPTION --}}
                            <div class="col-md-3 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control">{{ old('description', $branch->description ?? '') }}</textarea>
                            </div>

                            {{-- PHONE --}}
                            <div class="col-md-3 mb-3">
                                <label>
                                    Phone
                                    <small class="text-muted">(10 digits)</small>
                                </label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $branch->phone ?? '') }}" maxlength="10">
                            </div>

                            {{-- EMAIL --}}
                            <div class="col-md-3 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $branch->email ?? '') }}">
                            </div>

                            {{-- ADDRESS --}}
                            <div class="col-md-6 mb-3">
                                <label>Address</label>
                                <textarea name="address" class="form-control" rows="3">{{ old('address', $branch->address ?? '') }}</textarea>
                            </div>

                            {{-- CITY --}}
                            <div class="col-md-3 mb-3">
                                <label>City</label>
                                <input type="text" name="city" class="form-control" value="{{ old('city', $branch->city ?? '') }}">
                            </div>

                            {{-- PINCODE --}}
                            <div class="col-md-3 mb-3">
                                <label>
                                    Pincode
                                    <small class="text-muted">(6 digits)</small>
                                </label>
                                <input type="text" name="pincode" class="form-control" value="{{ old('pincode', $branch->pincode ?? '') }}" maxlength="6" pattern="[0-9]{6}">
                            </div>

                            {{-- LATITUDE --}}
                            <div class="col-md-3 mb-3">
                                <label>Latitude</label>
                                <small class="text-muted">(Range: -90 to 90)</small>
                                <input type="text" name="latitude" class="form-control" value="{{ old('latitude', $branch->latitude ?? '') }}">
                            </div>

                            {{-- LONGITUDE --}}
                            <div class="col-md-3 mb-3">
                                <label>Longitude</label>
                                <small class="text-muted">(Range: -180 to 180)</small>
                                <input type="text" name="longitude" class="form-control" value="{{ old('longitude', $branch->longitude ?? '') }}">
                            </div>

                            {{-- HEAD OFFICE --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">
                                    Is Head Office?
                                </label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_head_office" value="0">
                                    <input type="checkbox" id="is_head_office" name="is_head_office" value="1" class="form-check-input" 
                                        {{ old('is_head_office', $branch->is_head_office ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- ACTIVE --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">
                                    Is Active?
                                </label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" 
                                        {{ old('is_active', $branch->is_active ?? true) ? 'checked' : '' }}>
                                </div>
                            </div>

                        </div>

                        @include('admin.org.partials.media-fields', ['imageCollection' => 'branch_image', 'model' => $branch ?? null])

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i>
                                {{ isset($branch) ? 'Update Branch' : 'Create Branch' }}
                            </button>
                            <a href="{{ backpack_url('org/branch') }}" class="btn btn-secondary btn-lg">
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    $('input[name="phone"]').on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });

    $('input[name="pincode"]').on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
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

    const existingHeadOffice = @json($headOffice?->name ?? null);
    const currentBranch = @json($branch->name ?? null);

    $('#is_head_office').on('change', function () {
        if (!this.checked) return;

        if (existingHeadOffice && existingHeadOffice !== currentBranch) {
            Swal.fire({
                title: 'Switch Head Office?',
                text: `"${existingHeadOffice}" is already marked as Head Office. Do you want to switch?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Switch',
                cancelButtonText: 'No'
            }).then((result) => {
                if (!result.isConfirmed) {
                    $('#is_head_office').prop('checked', false);
                }
            });
        }
    });
</script>
@endpush