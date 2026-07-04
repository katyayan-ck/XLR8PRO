@extends(backpack_view('blank'))

@section('title', 'Add New Process')

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
                        Add New Process
                    </h2>
                </div>

                <div class="card-body">

                    <form
                        method="POST"
                        action="{{ backpack_url('process') }}">

                        @csrf

                        <div class="row">

                            <div class="col-md-4 mb-3">

                                <label>
                                    Module
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="module_code"
                                    class="form-control form-select"
                                    required>

                                    <option value="">
                                        Select Module
                                    </option>

                                    @foreach($modules as $module)

                                        <option
                                            value="{{ $module->code }}"
                                            {{ old('module_code') == $module->code ? 'selected' : '' }}>

                                            {{ $module->name }}

                                        </option>

                                    @endforeach

                                </select>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Process Code
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="code"
                                    class="form-control text-uppercase"
                                    value="{{ old('code') }}"
                                    maxlength="50"
                                    required>

                            </div>

                            <div class="col-md-4 mb-3">

                                <label>
                                    Process Name
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    value="{{ old('name') }}"
                                    required>

                            </div>

                            <div class="col-md-12 mb-3">

                                <label>
                                    Description
                                </label>

                                <textarea
                                    name="description"
                                    class="form-control"
                                    rows="4">{{ old('description') }}</textarea>

                            </div>

                            <div class="col-md-2 mb-3">

                                <label class="form-label">
                                    Is Active?
                                </label>

                                <div class="form-check form-switch">

                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0">

                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        class="form-check-input"
                                        {{
                                            old('is_active', true)
                                                ? 'checked'
                                                : ''
                                        }}>

                                </div>

                            </div>

                        </div>

                        <div class="mt-4">

                            <button
                                type="submit"
                                class="btn btn-success btn-lg px-5">

                                <i class="la la-save"></i>
                                Create Process

                            </button>

                            <a
                                href="{{ backpack_url('process') }}"
                                class="btn btn-secondary btn-lg">

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