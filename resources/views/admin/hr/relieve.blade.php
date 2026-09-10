@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Relieve Employee</h2>
        <p class="ml-2 ml-md-4 mb-0">Close all active post assignments and update employment status.</p>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('hr/relieve') }}">
        @csrf
        <div class="card">
            <div class="card-header"><strong>Relieving Details</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Employee <span class="text-danger">*</span></label>
                        <select name="emp_code" class="form-control @error('emp_code') is-invalid @enderror" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $e)
                                @php
                                    $currentPost = $e->postAssignments->first();
                                @endphp
                                <option value="{{ $e->code }}" {{ old('emp_code') == $e->code ? 'selected' : '' }}>
                                    {{ $e->code }} — {{ $e->person?->display_name ?? $e->code }}
                                    @if($currentPost) [{{ $currentPost->post?->designation?->name ?? $currentPost->post_code }}] @endif
                                </option>
                            @endforeach
                        </select>
                        @error('emp_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Separation Date <span class="text-danger">*</span></label>
                        <input type="date" name="separation_date"
                               value="{{ old('separation_date', date('Y-m-d')) }}"
                               class="form-control @error('separation_date') is-invalid @enderror" required>
                        @error('separation_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Relieving Type <span class="text-danger">*</span></label>
                        <select name="relieving_type" class="form-control @error('relieving_type') is-invalid @enderror" required>
                            <option value="">-- Select --</option>
                            <option value="relieving"   {{ old('relieving_type') == 'relieving'   ? 'selected' : '' }}>Relieving (Resignation)</option>
                            <option value="termination" {{ old('relieving_type') == 'termination' ? 'selected' : '' }}>Termination</option>
                            <option value="absconding"  {{ old('relieving_type') == 'absconding'  ? 'selected' : '' }}>Absconding</option>
                            <option value="resignation" {{ old('relieving_type') == 'resignation' ? 'selected' : '' }}>Resigned (Served Notice)</option>
                        </select>
                        @error('relieving_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="alert alert-warning">
                    <i class="la la-exclamation-triangle"></i>
                    This will <strong>close all active post assignments</strong> for the selected employee and mark them as <strong>separated</strong>.
                </div>
            </div>
        </div>
        <div class="mb-4">
            <button type="submit" class="btn btn-danger">
                <i class="la la-sign-out-alt"></i> Confirm Relieve
            </button>
            <a href="{{ backpack_url('emp-post-assignment') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection