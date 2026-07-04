@extends(backpack_view('blank'))

@section('title', 'Edit Lead Source - ' . $leadSource->name)

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
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">Edit Lead Source Information</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('lead-source/' . $leadSource->id) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            <div class="col-md-3 mb-3">
                                <label>Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control"
                                    value="{{ old('code', $leadSource->code) }}" 
                                    minlength="2" maxlength="20" required>
                                <small class="text-muted">Unique code (e.g. WEBSITE, REFERRAL)</small>
                                <div id="codeError" class="text-danger mt-1"></div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Source Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $leadSource->name) }}" required>
                            </div>

                            <!-- <div class="col-md-3 mb-3">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" class="form-control"
                                    value="{{ old('sort_order', $leadSource->sort_order) }}" min="0">
                                <small class="text-muted">Lower number = higher priority</small>
                            </div> -->

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                        {{ old('is_active', $leadSource->is_active) ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control" 
                                    rows="4">{{ old('description', $leadSource->description) }}</textarea>
                            </div>

                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Lead Source
                            </button>
                            <a href="{{ backpack_url('lead-source') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
    // Real-time code validation
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
</script>
@endpush