@extends(backpack_view('blank'))

@section('title', 'Add New Keyword')

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
                    <h2 class="mb-0">Add New Keyword</h2>
                </div>

                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('keyword-master') }}">

                        @csrf

                        <div class="row">

                            {{-- Code --}}
                            <div class="col-md-3 mb-3">
                                <label>
                                    Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" name="code" class="form-control" value="{{ old('code') }}" required
                                    minlength="3" maxlength="50">

                                <div id="codeError" class="text-danger mt-1">
                                </div>
                            </div>

                            {{-- Keyword --}}
                            <div class="col-md-3 mb-3">
                                <label>
                                    Keyword
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" name="keyword" class="form-control" value="{{ old('keyword') }}"
                                    required maxlength="255">
                            </div>

                            {{-- Description --}}
                            <div class="col-md-3 mb-3">
                                <label>Description</label>

                                <input type="text" name="description" class="form-control"
                                    value="{{ old('description') }}">
                            </div>

                            {{-- Is Active --}}
                            <div class="col-md-1 mb-3">

                                <label class="form-label">
                                    Is Active?
                                </label>

                                <div class="form-check form-switch">

                                    <input type="hidden" name="is_active" value="0">

                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', true) ? 'checked' : '' }}>

                                </div>

                            </div>

                            {{-- Is Recursive --}}
                            <div class="col-md-2 mb-3">

                                <label class="form-label">
                                    Is Recursive?
                                </label>

                                <div class="form-check form-switch">

                                    <input type="hidden" name="is_recursive" value="0">

                                    <input type="checkbox" name="is_recursive" value="1" class="form-check-input" {{
                                        old('is_recursive') ? 'checked' : '' }}>

                                </div>

                            </div>

                            {{-- Details --}}
                            <div class="col-md-4 mb-3">
                                <label>Details</label>

                                <textarea name="details" rows="4" class="form-control">{{ old('details') }}</textarea>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label>
                                    Status
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="status" class="form-control form-select" required>

                                    <option value="1" {{ old('status',1)==1 ? 'selected' : '' }}>
                                        Active
                                    </option>

                                    <option value="0" {{ old('status')==='0' ? 'selected' : '' }}>
                                        Inactive
                                    </option>

                                </select>
                            </div>


                        </div>

                        <div class="mt-4">

                            <button type="submit" class="btn btn-success btn-lg px-5">

                                <i class="la la-save"></i>
                                Create Keyword

                            </button>

                            <a href="{{ backpack_url('keyword-master') }}" class="btn btn-secondary btn-lg">

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

    } else if (value.length > 50) {

        error.text('Code cannot exceed 50 characters');

    } else {

        error.text('');
    }

});

$('form').on('submit', function(e){

    let code = $('input[name="code"]').val().trim();

    if(code.length < 3 || code.length > 50){

        e.preventDefault();

        $('#codeError').text(
            'Code must be between 3 and 50 characters'
        );
    }

});
</script>
@endpush