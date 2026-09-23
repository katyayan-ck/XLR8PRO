@extends(backpack_view('blank'))

@section('title', 'Edit Department - ' . $department->name)

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
                    <h2 class="mb-0">Edit Department Information</h2>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ backpack_url('org/department/' . $department->id) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            <div class="col-md-3 mb-3">
                                <label>Department Code</label>
                                <input type="text" class="form-control" value="{{ $department->code }}" readonly disabled>
                                <div class="form-text">Code cannot be changed after creation — every relation in the app points at it.</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label>Department Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $department->name) }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3">{{ old('description', $department->description) }}</textarea>
                            </div>



                            <div class="col-md-3 mb-3">
                                <label class="form-label">Is Active?</label>

                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">

                                    <input type="checkbox" id="departmentStatus" name="is_active" value="1"
                                        class="form-check-input" {{ old('is_active', $department->is_active) ? 'checked'
                                    : '' }}>
                                </div>


                            </div>
                        </div>

                        @include('admin.org.partials.media-fields', ['imageCollection' => 'department_image', 'model' => $department])

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Department
                            </button>
                            <a href="{{ backpack_url('org/department') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
@endpush