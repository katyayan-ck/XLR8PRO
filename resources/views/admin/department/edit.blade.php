@extends(backpack_view('blank'))

@section('title', 'Edit Department - ' . $department->name)

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
        background-color: #f8f9fa;
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
                    <h2 class="mb-0">Edit Department Information</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('department/' . $department->id) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            <div class="col-md-3 mb-3">
                                <label>Department Code (Min 3 Char) <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control"
                                    value="{{ old('code', $department->code) }}" maxlength="10" minlength="3" required>
                                <div id="codeError" class="text-danger mt-1"></div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Department Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $department->name) }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3">{{ old('description', $department->description) }}</textarea>
                            </div>

                            <div class="col-md-4 mb-3">

                                <label>Department Image</label>

                                <input type="file" name="department_image" id="department_image" class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp">

                            </div>

                            <div class="col-md-4 mb-3">

                                <img id="imagePreview" src="{{ $department->getFirstMediaUrl('department_image') }}"
                                    style="max-height:120px;
         {{ $department->getFirstMediaUrl('department_image') ? '' : 'display:none;' }}" class="img-thumbnail">

                                @if($department->getFirstMedia('department_image'))
                                <div id="currentFileBlock" class="mt-2 text-muted">
                                    Current File:
                                    <strong>
                                        {{ $department->getFirstMedia('department_image')->file_name }}
                                    </strong>
                                </div>
                                @endif

                                <div id="selectedFileName" class="mt-2 text-primary"></div>

                            </div>






                            <div class="col-md-3 mb-3">
                                <label class="form-label">Is Active?</label>

                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">

                                    <input type="checkbox" id="departmentStatus" name="is_active" value="1"
                                        class="form-check-input" {{ old('is_active', $department->is_active) ? 'checked'
                                    : '' }}>
                                </div>


                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Department
                            </button>
                            <a href="{{ backpack_url('department') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
    document.getElementById('department_image')
?.addEventListener('change', function(e){

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

    if (code.length > 0 && code.length < 3) {
        error.text('Department Code must be at least 3 characters.');
    } else {
        error.text('');
    }
});

$('form').on('submit', function (e) {

    const code = $('input[name="code"]').val().trim();

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

@if(session('division_blocked'))
<script>
    window.addEventListener('load', function () {

    let divisions = @json(session('division_blocked'));

    let divisionList = divisions.map(function(item) {
        return '<li>' + item + '</li>';
    }).join('');

    Swal.fire({
        icon: 'warning',
        title: 'Cannot Deactivate Department',
        html: `
            <div style="text-align:left;">
                <p><strong>The following divisions are still active:</strong></p>

                <ul style="margin-top:10px;">
                    ${divisionList}
                </ul>

                <p style="margin-top:10px;">
                    Please deactivate these divisions first.
                </p>
            </div>
        `,
        confirmButtonText: 'OK',
        confirmButtonColor: '#3085d6'
    });

});
</script>
@endif

<script>
    const activeDivisions = @json($activeDivisions ?? []);

    $('#departmentStatus').on('change', function () {

        if ($(this).is(':checked')) {
            return;
        }

        if (activeDivisions.length === 0) {
            return;
        }

        let displayDivisions = activeDivisions.slice(0, 5);

        let html = '<ul style="text-align:left;">';

        displayDivisions.forEach(function(item) {
            html += '<li>' + item + '</li>';
        });

        if (activeDivisions.length > 5) {
            html += '<li><strong>+' +
                (activeDivisions.length - 5) +
                ' more...</strong></li>';
        }

        html += '</ul>';

        Swal.fire({
            icon: 'warning',
            title: 'Cannot Deactivate Department',
            html: `
                <p>
                    Please deactivate the following active divisions first:
                </p>
                ${html}
            `,
            confirmButtonText: 'OK'
        });

        $(this).prop('checked', true);
    });

</script>



@endpush