@extends(backpack_view('blank'))

@section('title', 'Add New Division')

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
                    <h2 class="mb-0">Add New Division</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('division') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Department <span class="text-danger">*</span></label>
                                <select name="dept_code" class="form-control form-select" required>
                                    <option value="">-- Select Department --</option>

                                    @foreach($departments as $department)
                                    <option value="{{ $department->code }}" {{ old('dept_code')==$department->code ?
                                        'selected' : '' }}>
                                        {{ $department->name }} ({{ $department->code }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Division Code (Min 3 Char)<span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" value="{{ old('code') }}"
                                    maxlength="10" minlength="3" required>
                                <div id="codeError" class="text-danger mt-1"></div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Division Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3">{{ old('description') }}</textarea>
                            </div>

                            <div class="col-md-4 mb-3">

                                <label>Division Image</label>

                                <input type="file" name="division_image" id="division_image" class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp">

                            </div>

                            <div class="col-md-4 mb-3">

                                <img id="imagePreview" src="" style="display:none;max-height:120px;"
                                    class="img-thumbnail">

                            </div>



                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', true) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Division
                            </button>
                            <a href="{{ backpack_url('division') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
    document.getElementById('division_image')
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

$('input[name="code"]').on('input', function () {

    this.value = this.value
        .replace(/[^A-Za-z0-9]/g, '')
        .toUpperCase()
        .slice(0, 10);

    let code = this.value.trim();
    let error = $('#codeError');

    if(code.length > 0 && code.length < 3){
        error.text('Division Code must be at least 3 characters.');
    }else{
        error.text('');
    }
});

$('form').on('submit', function (e) {

    const code =
        $('input[name="code"]').val().trim();

    if (code.length < 3) {

        e.preventDefault();

        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Code must be at least 3 characters.'
        });

        return false;
    }
});

</script>

@endpush