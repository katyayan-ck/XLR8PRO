@extends(backpack_view('blank'))

@section('title', 'Add New Segment')

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
                    <h2 class="mb-0">Add New Segment</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('segment') }}">
                        @csrf

                        <div class="row">

                            {{-- <div class="col-md-4 mb-3">
                                <label>Brand <span class="text-danger">*</span></label>
                                <select name="brand_code" class="form-control form-select" required>
                                    <option value="">Select Brand</option>
                                    @foreach($brands as $brand)
                                    <option value="{{ $brand->code }}" {{ old('brand_code')==$brand->id ? 'selected' : ''
                                        }}>
                                        {{ $brand->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div> --}}

                            <div class="col-md-4 mb-3">
                                <label>Segment Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control text-uppercase"
                                    value="{{ old('code') }}" maxlength="5" required style="text-transform: uppercase;">
                                <small class="text-muted">e.g. HATCH, SUVXX, SEDAN, MPVXX</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Segment Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>

                            {{-- <div class="col-md-11 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control"
                                    rows="3">{{ old('description') }}</textarea>
                            </div> --}}

                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{
                                        old('is_active', true) ? 'checked' : '' }}>
                                </div>
                            </div>

                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Segment
                            </button>
                            <a href="{{ backpack_url('segment') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection