@extends(backpack_view('blank'))

@section('title', $title ?? 'Permission Form')

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
    .readonly-field {
        background-color: var(--tblr-bg-surface-secondary);
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
                        {{ isset($permission) ? 'Edit Permission' : 'Add New Permission' }}
                    </h2>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ isset($permission) ? backpack_url('iam/permission/' . $permission->id) : backpack_url('iam/permission') }}">
                        @csrf
                        @if(isset($permission))
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>
                                    Module
                                    <span class="text-danger">*</span>
                                </label>
                                <select name="module_code" id="module_code" class="form-control form-select" required>
                                    <option value="">Select Module</option>
                                    @foreach($modules as $module)
                                        <option value="{{ $module->code }}" {{ old('module_code', $permission->module_code ?? '') == $module->code ? 'selected' : '' }}>
                                            {{ $module->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>
                                    Process
                                    <span class="text-danger">*</span>
                                </label>
                                <select name="process_code" id="process_code" class="form-control form-select" required>
                                    <option value="">Select Process</option>
                                    @foreach($processes as $process)
                                        <option value="{{ $process->code }}" {{ old('process_code', $permission->process_code ?? '') == $process->code ? 'selected' : '' }}>
                                            {{ $process->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>
                                    Permission Suffix
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="permission_suffix" class="form-control text-uppercase"
                                    value="{{ $suffix ?? '' }}" placeholder="CUSTOMER" required>
                            </div>

                            <div class="col-md-8 mb-3">
                                <label>
                                    Permission Name
                                </label>
                                <input type="text" id="permission_preview" class="form-control readonly-field" readonly
                                    value="{{ $permission->name ?? '' }}">
                                <input type="hidden" name="name" id="permission_name" value="{{ $permission->name ?? '' }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>
                                    Guard Name
                                </label>
                                @if(isset($permission))
                                    <select name="guard_name" class="form-control form-select" required>
                                        <option value="web" {{ $permission->guard_name == 'web' ? 'selected' : '' }}>Web</option>
                                        <option value="api" {{ $permission->guard_name == 'api' ? 'selected' : '' }}>API</option>
                                    </select>
                                @else
                                    <input type="text" name="guard_name" class="form-control readonly-field" value="web" readonly>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i>
                                {{ isset($permission) ? 'Update Permission' : 'Create Permission' }}
                            </button>
                            <a href="{{ backpack_url('iam/permission') }}" class="btn btn-secondary btn-lg">
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
    $('#module_code').on('change', function () {
        let moduleCode = $(this).val();

        $('#process_code').html('<option value="">Loading...</option>');

        $.get("{{ backpack_url('iam/permission/processes') }}/" + moduleCode, function(response) {
            let options = '<option value="">Select Process</option>';
            response.forEach(function(item) {
                options += '<option value="' + item.code + '">' + item.name + '</option>';
            });

            $('#process_code').html(options);
            generatePermissionName();
        });
    });

    function generatePermissionName() {
        const moduleCode = $('#module_code').val();
        const processCode = $('#process_code').val();
        const suffix = $('#permission_suffix').val().trim().toUpperCase();

        let permissionName = '';

        if (moduleCode) { permissionName += moduleCode; }
        if (processCode) { permissionName += '_' + processCode; }
        if (suffix) { permissionName += '_' + suffix; }

        $('#permission_preview').val(permissionName);
        $('#permission_name').val(permissionName);
    }

    $('#process_code').on('change', generatePermissionName);
    $('#permission_suffix').on('keyup', generatePermissionName);

    $(document).ready(function () {
        generatePermissionName();
    });
</script>
@endpush