@extends(backpack_view('blank'))

@section('title', 'Add New Post Permission')

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
                    <h2 class="mb-0">Add New Post Permission</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('post-permission') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Post <span class="text-danger">*</span></label>
                                <select name="post_id" class="form-control form-select" required>
                                    <option value="">Select Post</option>
                                    @foreach($posts as $post)
                                        <option value="{{ $post->id }}" {{ old('post_id') == $post->id ? 'selected' : '' }}>
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
                                        <option value="{{ $perm->id }}" {{ old('permission_id') == $perm->id ? 'selected' : '' }}>
                                            {{ $perm->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Assign Permission
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
