@extends(backpack_view('blank'))

@section('title', 'Edit Key Value')

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
                    <h2 class="mb-0">
                        Edit Key Value
                    </h2>
                </div>

                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('keyvalue/' . $keyValue->id) }}">

                        @csrf
                        @method('PUT')

                        <div class="row">

                            {{-- Keyword Master --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Keyword Master
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="keyword_code" class="form-control form-select" required>

                                    <option value="">
                                        Select Keyword
                                    </option>

                                    @foreach($keywordMasters as $keyword)

                                    <option value="{{ $keyword->code }}" {{ old('keyword_code', $keyValue->keyword_code)
                                        == $keyword->code ? 'selected' : '' }}>

                                        {{ $keyword->keyword }}

                                    </option>

                                    @endforeach

                                </select>

                            </div>

                            {{-- Code --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text" name="code" class="form-control"
                                    value="{{ old('code', $keyValue->code) }}" required maxlength="150">
                                <div id="codeError" class="text-danger mt-1"></div>

                            </div>

                            {{-- Key --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Key
                                </label>

                                <input type="text" name="key" class="form-control"
                                    value="{{ old('key', $keyValue->key) }}" maxlength="255">

                            </div>

                            {{-- Status --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Status
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="status" class="form-control form-select" required>

                                    <option value="1" {{ old('status', $keyValue->status) == 1 ? 'selected' : '' }}>
                                        Active
                                    </option>

                                    <option value="0" {{ old('status', $keyValue->status) == 0 ? 'selected' : '' }}>
                                        Inactive
                                    </option>

                                </select>

                            </div>

                            {{-- Value --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Value
                                    <span class="text-danger">*</span>
                                </label>

                                <textarea name="value" rows="4" class="form-control"
                                    required>{{ old('value', $keyValue->value) }}</textarea>

                            </div>

                            {{-- Details --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Details
                                </label>

                                <textarea name="details" rows="4"
                                    class="form-control">{{ old('details', $keyValue->details) }}</textarea>

                            </div>

                            {{-- Parent Id --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Parent Id
                                </label>

                                <input type="number" name="parent_id" class="form-control"
                                    value="{{ old('parent_id', $keyValue->parent_id) }}">

                            </div>

                            {{-- Level --}}
                            <div class="col-md-3 mb-3">

                                <label>
                                    Level
                                </label>

                                <input type="number" name="level" class="form-control"
                                    value="{{ old('level', $keyValue->level) }}">

                            </div>

                            {{-- Path --}}
                            <div class="col-md-4 mb-3">

                                <label>
                                    Path
                                </label>

                                <input type="text" name="path" class="form-control"
                                    value="{{ old('path', $keyValue->path) }}">

                            </div>

                            {{-- Is Active --}}
                            <div class="col-md-2 mb-3">

                                <label class="form-label">
                                    Is Active?
                                </label>

                                <div class="form-check form-switch">

                                    <input type="hidden" name="is_active" value="0">

                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', $keyValue->is_active) ? 'checked' : '' }}>

                                </div>

                            </div>

                            {{-- Extra Data --}}
                            <div class="col-md-6 mb-3">

                                <label>
                                    Extra Data
                                </label>

                                <textarea name="extra_data" rows="4"
                                    class="form-control">{{ old('extra_data', is_array($keyValue->extra_data) ? json_encode($keyValue->extra_data, JSON_PRETTY_PRINT) : $keyValue->extra_data) }}</textarea>

                            </div>

                        </div>

                        <div class="mt-4">

                            <button type="submit" class="btn btn-success btn-lg px-5">

                                <i class="la la-save"></i>
                                Update Key Value

                            </button>

                            <a href="{{ backpack_url('keyvalue') }}" class="btn btn-secondary btn-lg">

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

    let error =
        $('#codeError');

    if (
        value.length > 0 &&
        value.length < 3
    ) {

        error.text(
            'Code must be at least 3 characters'
        );

        $(this).addClass('is-invalid');

    }
    else if (
        value.length > 150
    ) {

        error.text(
            'Code cannot exceed 150 characters'
        );

        $(this).addClass('is-invalid');

    }
    else {

        error.text('');

        $(this).removeClass('is-invalid');
    }

});

$('form').on('submit', function (e) {

    let code =
        $('input[name="code"]')
        .val()
        .trim();

    if (
        code.length < 3 ||
        code.length > 150
    ) {

        e.preventDefault();

        $('#codeError').text(
            'Code must be between 3 and 150 characters'
        );

        $('input[name="code"]')
            .addClass('is-invalid');
    }

});

</script>

@endpush