@extends(backpack_view('blank'))

@section('title', 'Edit Graph Node')

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
                    <h2 class="mb-0">Edit Graph Node</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('graph-node/' . $node->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Node ID</label>
                                        <div class="readonly-value">{{ $node->id }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Created At</label>
                                        <div class="readonly-value">
                                            {{ $node->created_at?->format('d-m-Y H:i') ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>User <span class="text-danger">*</span></label>
                                <select name="user_id" class="form-control form-select" required>
                                    <option value="">Select User</option>
                                    @foreach(\App\Models\User::orderBy('name')->get() as $user)
                                    <option value="{{ $user->id }}" {{ old('user_id', $node->user_id) == $user->id ?
                                        'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-control form-select" required>
                                    <option value="">Select Role</option>
                                    <option value="person" {{ old('role', $node->role) == 'person' ? 'selected' : ''
                                        }}>Person</option>
                                    <option value="role" {{ old('role', $node->role) == 'role' ? 'selected' : '' }}>Role
                                    </option>
                                    <option value="department" {{ old('role', $node->role) == 'department' ? 'selected'
                                        : '' }}>Department</option>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label>Attributes (JSON)</label>
                                <textarea name="attributes" class="form-control"
                                    rows="8">{{ old('attributes', is_string($node->attributes) ? $node->attributes : json_encode($node->attributes, JSON_PRETTY_PRINT)) }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Node
                            </button>
                            <a href="{{ backpack_url('graph-node') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection