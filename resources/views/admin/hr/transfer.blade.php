@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Transfer Employee</h2>
        <p class="ml-2 ml-md-4 mb-0">Relieve from current post and assign to a new one atomically.</p>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('hr/transfer') }}" id="transferForm">
        @csrf
        <div class="card">
            <div class="card-header"><strong>Transfer Details</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Employee <span class="text-danger">*</span></label>
                        <select name="emp_code" id="empSelect"
                                class="form-control @error('emp_code') is-invalid @enderror" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $e)
                                <option value="{{ $e->code }}" {{ old('emp_code') == $e->code ? 'selected' : '' }}>
                                    {{ $e->code }} — {{ $e->person?->display_name ?? $e->code }}
                                </option>
                            @endforeach
                        </select>
                        @error('emp_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>New Post <span class="text-danger">*</span></label>
                        <select name="new_post_code" id="postSelect"
                                class="form-control @error('new_post_code') is-invalid @enderror" required>
                            <option value="">-- Load vacant posts --</option>
                        </select>
                        @error('new_post_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Transfer Date <span class="text-danger">*</span></label>
                        <input type="date" name="transfer_date"
                               value="{{ old('transfer_date', date('Y-m-d')) }}"
                               class="form-control @error('transfer_date') is-invalid @enderror" required>
                        @error('transfer_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div id="postDetails" class="alert alert-info d-none"></div>
            </div>
        </div>
        <div class="mb-4">
            <button type="submit" class="btn btn-warning">
                <i class="la la-exchange-alt"></i> Execute Transfer
            </button>
            <a href="{{ backpack_url('emp-post-assignment') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('after_scripts')
<script>
document.getElementById('empSelect').addEventListener('change', function() {
    if (!this.value) return;
    fetch('{{ backpack_url('hr/transfer/posts') }}?emp_code=' + this.value)
        .then(r => r.json())
        .then(posts => {
            const sel = document.getElementById('postSelect');
            sel.innerHTML = '<option value="">-- Select Vacant Post --</option>';
            posts.forEach(p => {
                sel.innerHTML += `<option value="${p.post_code}">${p.post_code} — ${p.label} (${p.branch})</option>`;
            });
        });
});

document.getElementById('postSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!this.value) { document.getElementById('postDetails').classList.add('d-none'); return; }
    document.getElementById('postDetails').classList.remove('d-none');
    document.getElementById('postDetails').textContent = 'Selected: ' + opt.text;
});
</script>
@endpush