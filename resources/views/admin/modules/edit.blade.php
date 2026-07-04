@extends(backpack_view('blank'))

@section('title', 'Edit Module - ' . $module->name)

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
                            Edit Module Information
                        </h2>
                    </div>

                    <div class="card-body">

                        <form method="POST" action="{{ backpack_url('modules/' . $module->id) }}">

                            @csrf
                            @method('PUT')

                            <div class="row">

                                <div class="col-md-4 mb-3">

                                    <label>
                                        Module Code
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="code" class="form-control text-uppercase"
                                        value="{{ old('code', $module->code) }}" maxlength="50" required>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label>
                                        Module Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="text" name="name" class="form-control"
                                        value="{{ old('name', $module->name) }}" required>

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">
                                        Is Active?
                                    </label>

                                    <div class="form-check form-switch">

                                        <input type="hidden" name="is_active" value="0">

                                        <input type="checkbox" id="moduleStatus" name="is_active" value="1"
                                            class="form-check-input" {{
        old('is_active', $module->is_active)
        ? 'checked'
        : ''
                                            }}>

                                    </div>

                                </div>

                                <div class="col-md-12 mb-3">

                                    <label>
                                        Description
                                    </label>

                                    <textarea name="description" class="form-control"
                                        rows="4">{{ old('description', $module->description) }}</textarea>

                                </div>

                            </div>

                            <div class="mt-4">

                                <button type="submit" class="btn btn-success btn-lg px-5">

                                    <i class="la la-save"></i>
                                    Update Module

                                </button>

                                <a href="{{ backpack_url('modules') }}" class="btn btn-secondary btn-lg">

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

        const activeProcesses =
            @json($activeProcesses ?? []);

        $('#moduleStatus').on('change', function () {

            if ($(this).is(':checked')) {
                return;
            }

            if (activeProcesses.length === 0) {
                return;
            }

            let displayProcesses =
                activeProcesses.slice(0, 5);

            let html =
                '<ul style="text-align:left;">';

            displayProcesses.forEach(function (item) {

                html +=
                    '<li>' + item + '</li>';

            });

            if (activeProcesses.length > 5) {

                html +=
                    '<li><strong>+' +
                    (activeProcesses.length - 5) +
                    ' more...</strong></li>';
            }

            html += '</ul>';

            Swal.fire({

                icon: 'warning',

                title: 'Cannot Deactivate Module',

                html: `
                    <p>
                        Please deactivate the following active
                        Processes first:
                    </p>
                    ${html}
                `,

                confirmButtonText: 'OK'

            });

            $(this).prop('checked', true);
        });

    </script>

@endpush