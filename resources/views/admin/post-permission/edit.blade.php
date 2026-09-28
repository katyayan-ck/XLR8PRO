@extends(backpack_view('blank'))

@section('title', 'Edit Post Permission')

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
                    <h2 class="mb-0">Edit Post Permission</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('post-permission/' . $postPermission->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">ID</label>
                                        <div class="readonly-value">{{ $postPermission->id }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Created At</label>
                                        <div class="readonly-value">
                                            {{ $postPermission->created_at?->format('d-m-Y H:i') ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Post <span class="text-danger">*</span></label>
                                <select name="post_id" class="form-control form-select" required>
                                    <option value="">Select Post</option>
                                    @foreach($posts as $post)
                                    <option value="{{ $post->id }}" {{ old('post_id', $postPermission->post_id ?? '') ==
                                        $post->id ? 'selected' : '' }}>
                                        {{ $post->title ?? $post->name ?? $post->post_name ?? 'Post #'.$post->id }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Permission <span class="text-danger">*</span></label>
                                <select name="permission_id" class="form-control form-select" required>
                                    <option value="">Select Permission</option>
                                    @foreach($permissions as $perm)
                                    <option value="{{ $perm->id }}" {{ old('permission_id', $postPermission->
                                        permission_id ?? '') == $perm->id ? 'selected' : '' }}>
                                        {{ $perm->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Assignment
                            </button>
                            <a href="{{ backpack_url('post-permission') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection