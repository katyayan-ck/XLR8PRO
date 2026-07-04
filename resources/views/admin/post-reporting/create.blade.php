@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Add Reporting Line</h2>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('post-reporting') }}">
        @csrf
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Post (Subordinate) <span class="text-danger">*</span></label>
                        <select name="post_code" class="form-control @error('post_code') is-invalid @enderror" required>
                            <option value="">-- Select Post --</option>
                            @foreach($posts as $p)
                                <option value="{{ $p->post_code }}" {{ old('post_code') == $p->post_code ? 'selected' : '' }}>
                                    {{ $p->post_code }} — {{ $p->designation?->name ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('post_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Reports To (Manager Post) <span class="text-danger">*</span></label>
                        <select name="reports_to_post_code" class="form-control @error('reports_to_post_code') is-invalid @enderror" required>
                            <option value="">-- Select Post --</option>
                            @foreach($posts as $p)
                                <option value="{{ $p->post_code }}" {{ old('reports_to_post_code') == $p->post_code ? 'selected' : '' }}>
                                    {{ $p->post_code }} — {{ $p->designation?->name ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('reports_to_post_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Topic <span class="text-danger">*</span></label>
                        <input type="text" name="topic" value="{{ old('topic') }}"
                               class="form-control @error('topic') is-invalid @enderror"
                               placeholder="e.g. Sales, Service, Admin" required>
                        @error('topic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Param Type <small class="text-muted">(optional)</small></label>
                        <input type="text" name="param_type" value="{{ old('param_type') }}"
                               class="form-control" placeholder="e.g. segment, branch">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Param Value <small class="text-muted">(blank = wildcard)</small></label>
                        <input type="text" name="param_value" value="{{ old('param_value') }}"
                               class="form-control" placeholder="e.g. NV, UV">
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Priority</label>
                        <input type="number" name="priority" value="{{ old('priority', 10) }}" class="form-control" min="1" max="100">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>From Date <span class="text-danger">*</span></label>
                        <input type="date" name="from_date" value="{{ old('from_date', date('Y-m-d')) }}"
                               class="form-control @error('from_date') is-invalid @enderror" required>
                        @error('from_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <button type="submit" class="btn btn-success"><i class="la la-save"></i> Save</button>
            <a href="{{ backpack_url('post-reporting') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection