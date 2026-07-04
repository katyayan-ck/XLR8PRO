@extends(backpack_view('blank'))

@section('title', 'Add New Vertical')

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
                    <h2 class="mb-0">Add New Vertical</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('vertical') }}" enctype="multipart/form-data"> @csrf

                        <div class="row">



                            <div class="col-md-3 mb-3">
                                <label>Vertical Code (Min 3 Char)<span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" value="{{ old('code') }}"
                                    minlength="3" maxlength="10" required>


                                <div id="codeError" class="text-danger mt-1"></div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Vertical Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3">{{ old('description') }}</textarea>
                            </div>

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active') ? 'checked' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Vertical Image</label>

                                <input type="file" name="vertical_image" id="vertical_image" class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp">
                            </div>
                            <div class="col-md-3 mb-3">
                                <img id="imagePreview" src="" style="display:none;max-height:120px;"
                                    class="img-thumbnail">
                            </div>


                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Vertical
                            </button>
                            <a href="{{ backpack_url('vertical') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
    document.getElementById('vertical_image')
?.addEventListener('change', function(e){

    const file = e.target.files[0];

    if(!file) return;

    const reader = new FileReader();

    reader.onload = function(ev){

        const img =
            document.getElementById('imagePreview');

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


</script>

@endpush