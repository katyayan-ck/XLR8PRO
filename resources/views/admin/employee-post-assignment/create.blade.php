@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Onboard Employee to Post</h2>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('emp-post-assignment') }}">
        @csrf
        <div class="card">
            <div class="card-header"><strong>Assignment Details</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Employee <span class="text-danger">*</span></label>
                        <select name="emp_code" class="form-control @error('emp_code') is-invalid @enderror" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $e)
                                <option value="{{ $e->code }}" {{ old('emp_code') == $e->code ? 'selected' : '' }}>
                                    {{ $e->code }} — {{ $e->person?->display_name ?? $e->code }}
                                </option>
                            @endforeach
                        </select>
                        @error('emp_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Only employees without a current primary post are shown.</small>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Post <span class="text-danger">*</span></label>
                        <select name="post_code" class="form-control @error('post_code') is-invalid @enderror" required>
                            <option value="">-- Select Vacant Post --</option>
                            @foreach($posts as $p)
                                <option value="{{ $p->post_code }}" {{ old('post_code') == $p->post_code ? 'selected' : '' }}>
                                    {{ $p->post_code }} — {{ $p->designation?->name ?? '' }}
                                    @if($p->branch) ({{ $p->branch->name }}) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('post_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
            <button type="submit" class="btn btn-success"><i class="la la-user-check"></i> Onboard</button>
            <a href="{{ backpack_url('emp-post-assignment') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection