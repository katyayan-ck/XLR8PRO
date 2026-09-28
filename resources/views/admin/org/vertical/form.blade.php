@extends(backpack_view('blank'))

@section('title', $title ?? 'Vertical Form')

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
                        {{ isset($vertical) ? 'Edit Vertical Information' : 'Add New Vertical' }}
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

                    <form method="POST" action="{{ isset($vertical) ? backpack_url('org/vertical/' . $vertical->id) : backpack_url('org/vertical') }}" enctype="multipart/form-data">
                        @csrf
                        @if(isset($vertical))
                            @method('PUT')
                        @endif

                        <div class="row">
                            {{-- CODE --}}
                            <div class="col-md-3 mb-3">
                                <label>
                                    Vertical Code
                                    @if(!isset($vertical)) <span class="text-danger">*</span> @endif
                                </label>
                                
                                @if(isset($vertical))
                                    <!-- Visually disabled field for the user -->
                                    <input type="text" class="form-control" value="{{ $vertical->code }}" readonly disabled>
                                    <!-- Hidden field to safely pass validation -->
                                    <input type="hidden" name="code" value="{{ $vertical->code }}">
                                    <div class="form-text">Code cannot be changed after creation.</div>
                                @else
                                    <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code') }}" maxlength="10" minlength="3" required>
                                    <div id="codeError" class="text-danger mt-1"></div>
                                @endif
                            </div>

                            {{-- NAME --}}
                            <div class="col-md-3 mb-3">
                                <label>Vertical Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $vertical->name ?? '') }}" required>
                            </div>

                            {{-- DESCRIPTION --}}
                            <div class="col-md-5 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="1">{{ old('description', $vertical->description ?? '') }}</textarea>
                            </div>

                            {{-- ACTIVE --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $vertical->is_active ?? true) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        @include('admin.org.partials.media-fields', ['imageCollection' => 'vertical_image', 'model' => $vertical ?? null])

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> 
                                {{ isset($vertical) ? 'Update Vertical' : 'Create Vertical' }}
                            </button>
                            <a href="{{ backpack_url('org/vertical') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
        this.value = this.value
            .replace(/[^A-Za-z0-9]/g, '')
            .toUpperCase()
            .slice(0, 10);

        let code = this.value.trim();
        let error = $('#codeError');

        if (code.length > 0 && code.length < 3) {
            error.text('Vertical Code must be at least 3 characters.');
        } else {
            error.text('');
        }
    });

    $('form').on('submit', function (e) {
        let codeInput = $('input[name="code"]');
        
        if (codeInput.length > 0 && !codeInput.prop('disabled')) {
            const code = codeInput.val().trim();

            if (code.length < 3) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Vertical Code must be at least 3 characters.'
                });
                return false;
            }
        }
    });
</script>
@endpush