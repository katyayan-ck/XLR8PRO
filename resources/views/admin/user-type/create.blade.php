@extends(backpack_view('blank'))

@section('title', 'Add New User Type')

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
                    <h2 class="mb-0">Add New User Type</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('user-type') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label>User Type Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" value="{{ old('code') }}"
                                    maxlength="5" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Display Name <span class="text-danger">*</span></label>
                                <input type="text" name="display_name" class="form-control"
                                    value="{{ old('display_name') }}" required>
                            </div>

                            <div class="col-md-5 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="4">{{ old('description') }}</textarea>
                            </div>

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active') ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create User Type
                            </button>
                            <a href="{{ backpack_url('user-type') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection