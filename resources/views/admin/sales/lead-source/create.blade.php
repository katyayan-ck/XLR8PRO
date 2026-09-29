@extends(backpack_view('blank'))

@section('title', 'Add New Lead Source')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-body">
                    <h2 class="mb-0">Add New Lead Source</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('sales/lead-source') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">

                            <div class="col-md-3 mb-3">
                                <label>Code </label>
                                <input type="text" name="code" class="form-control" value="{{ old('code') }}"
                                    minlength="2" maxlength="20" required>
                                <small class="text-muted">Unique code (e.g. WEBSITE, REFERRAL)</small>
                                <div id="codeError" class="text-danger mt-1"></div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Source Name </label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>

                            <!-- <div class="col-md-3 mb-3">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" 
                                       value="{{ old('sort_order', 0) }}" min="0">
                                <small class="text-muted">Lower number = higher priority</small>
                            </div> -->

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', 1) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="4">{{ old('description') }}</textarea>
                            </div>

                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Lead Source
                            </button>
                            <a href="{{ backpack_url('sales/lead-source') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
    const codeInput = document.querySelector('input[name="code"]');
const codeError = document.getElementById('codeError');

codeInput.addEventListener('keyup', function () {

    let code = this.value.trim().toUpperCase();
    this.value = code;

    // Empty field
    if (code.length === 0) {
        codeError.innerHTML = '';
        codeInput.classList.remove('is-invalid');
        return;
    }

    // Less than 3 characters
    if (code.length < 3) {
        codeError.innerHTML = 'Code must be at least 3 characters';
        codeInput.classList.add('is-invalid');
        return;
    }

    // Hide length validation
    codeError.innerHTML = '';
    codeInput.classList.remove('is-invalid');

    // Check duplicate code
    fetch(`{{ route('sales.lead-source.check-code') }}?code=${encodeURIComponent(code)}`)
        .then(response => response.json())
        .then(data => {

            if (data.exists) {
                codeError.innerHTML = 'Code already exists.';
                codeInput.classList.add('is-invalid');
            } else {
                codeError.innerHTML = '';
                codeInput.classList.remove('is-invalid');
            }

        })
        .catch(() => {
            codeError.innerHTML = '';
        });

});
</script>
@endpush