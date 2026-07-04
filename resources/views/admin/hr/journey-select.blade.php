@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Employee Journey</h2>
        <p class="ml-2 ml-md-4 mb-0">View the full post assignment timeline of any employee.</p>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <div class="card" style="max-width:500px">
        <div class="card-header"><strong>Select Employee</strong></div>
        <div class="card-body">
            <form method="GET" id="journeyForm">
                <div class="form-group">
                    <label>Employee</label>
                    <select name="emp_code" id="empSelect" class="form-control" required>
                        <option value="">-- Select --</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->code }}">
                                {{ $e->code }} — {{ $e->person?->display_name ?? $e->code }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="la la-history"></i> View Journey</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
document.getElementById('journeyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const code = document.getElementById('empSelect').value;
    if (code) window.location.href = '{{ backpack_url('hr/journey') }}/' + code;
});
</script>
@endpush