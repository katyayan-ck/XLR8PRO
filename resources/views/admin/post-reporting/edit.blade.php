@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2>Edit Reporting Line</h2>
        <p class="ml-3 text-muted"><code>{{ $line->post_code }}</code> → <code>{{ $line->reports_to_post_code }}</code></p>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('post-reporting/'.$line->id) }}">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3"><label>Post</label><p class="form-control-plaintext"><code>{{ $line->post_code }}</code></p></div>
                    <div class="col-md-3"><label>Reports To</label><p class="form-control-plaintext"><code>{{ $line->reports_to_post_code }}</code></p></div>
                    <div class="col-md-3"><label>Topic</label><p class="form-control-plaintext">{{ $line->topic }}</p></div>
                    <div class="col-md-3"><label>From Date</label><p class="form-control-plaintext">{{ $line->from_date?->format('d-M-Y') }}</p></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>To Date <small class="text-muted">(close this reporting line)</small></label>
                        <input type="date" name="to_date" value="{{ old('to_date', $line->to_date?->format('Y-m-d')) }}"
                               class="form-control @error('to_date') is-invalid @enderror">
                        @error('to_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Priority</label>
                        <input type="number" name="priority" value="{{ old('priority', $line->priority) }}"
                               class="form-control" min="1" max="100">
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <button type="submit" class="btn btn-success"><i class="la la-save"></i> Update</button>
            <a href="{{ backpack_url('post-reporting') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection