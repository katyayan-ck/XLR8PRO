@extends(backpack_view('blank'))

@section('title', 'Edit Role')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-body">
                    <h2 class="mb-0">Edit Role</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('iam/role/' . $role->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Role ID</label>
                                        <div class="readonly-value">{{ $role->id }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Created At</label>
                                        <div class="readonly-value">{{ site_datetime($role->created_at, '—') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Role Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $role->name) }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Guard Name <span class="text-danger">*</span></label>
                                <select name="guard_name" class="form-control form-select" required>
                                    <option value="web" {{ old('guard_name', $role->guard_name) === 'web' ? 'selected' :
                                        '' }}>Web</option>
                                    <option value="api" {{ old('guard_name', $role->guard_name) === 'api' ? 'selected' :
                                        '' }}>API</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Assign Permissions</label>
                                <select name="permissions[]" class="form-control form-select" multiple size="5">
                                    @foreach($permissions as $permission)
                                    <option value="{{ $permission->id }}" {{ $role->
                                        permissions->contains($permission->id) || in_array($permission->id,
                                        old('permissions', [])) ? 'selected' : '' }}>
                                        {{ $permission->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Role
                            </button>
                            <a href="{{ backpack_url('iam/role') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection