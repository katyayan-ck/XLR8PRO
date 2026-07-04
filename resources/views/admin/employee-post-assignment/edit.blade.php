@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Edit Assignment</h2>
        <p class="ml-2 text-muted">{{ $assignment->emp_code }} → {{ $assignment->post_code }}</p>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('emp-post-assignment/'.$assignment->id) }}">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-header"><strong>Assignment Info</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3"><label>Employee</label><p class="form-control-plaintext"><code>{{ $assignment->emp_code }}</code></p></div>
                    <div class="col-md-3"><label>Post</label><p class="form-control-plaintext"><code>{{ $assignment->post_code }}</code></p></div>
                    <div class="col-md-3"><label>Designation</label><p class="form-control-plaintext">{{ $assignment->post?->designation?->name ?? '—' }}</p></div>
                    <div class="col-md-3"><label>From Date</label><p class="form-control-plaintext">{{ $assignment->from_date?->format('d-M-Y') }}</p></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>To Date <small class="text-muted">(leave blank if still active)</small></label>
                        <input type="date" name="to_date" value="{{ old('to_date', $assignment->to_date?->format('Y-m-d')) }}"
                               class="form-control @error('to_date') is-invalid @enderror">
                        @error('to_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Relieving Type</label>
                        <select name="relieving_type" class="form-control">
                            <option value="">-- N/A --</option>
                            @foreach(['transfer','relieving','separation','termination'] as $rt)
                                <option value="{{ $rt }}" {{ $assignment->relieving_type == $rt ? 'selected' : '' }}>
                                    {{ ucfirst($rt) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <button type="submit" class="btn btn-success"><i class="la la-save"></i> Update</button>
            <a href="{{ backpack_url('emp-post-assignment') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection