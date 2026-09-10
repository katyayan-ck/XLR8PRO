@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>{{ $title ?? 'Insurance & RTO Rules' }}</h2>
        <p class="text-muted mb-0">
            Session #{{ $session->id }}
            · stage <code>{{ $session->current_stage }}</code>
            · WEF <code>{{ $session->wef_date?->format('Y-m-d') }}</code>
        </p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5>Current rules</h5>
                    <p>
                        RTO rows: <strong>{{ $presence['rto_count'] }}</strong>
                        &nbsp;|&nbsp;
                        Insurance base rows: <strong>{{ $presence['insurance_count'] }}</strong>
                    </p>
                    @if ($presence['any'])
                        <div class="alert alert-success">Rules already exist. Keep them or import a replacement workbook.
                        </div>
                        <form id="keep-form" class="d-inline">
                            @csrf
                            <button class="btn btn-primary" type="submit">Keep existing rules → Impact</button>
                        </form>
                    @else
                        <div class="alert alert-warning">No Insurance / RTO rules found. Import is required before
                            Calculate.</div>
                    @endif
                    <a href="{{ route('pricing.workflow.rules-export', $session->id) }}" class="btn btn-outline-secondary">
                        Download current rules
                    </a>
                    <hr>
                    <h5>Import new workbook</h5>
                    <form id="rules-form" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label>Insurance.xlsx and/or RTO-Rules.xlsx (one file, multiple sheets OK)</label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kinds[]" value="rto" id="k_rto"
                                checked>
                            <label class="form-check-label" for="k_rto">RTO</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kinds[]" value="insurance" id="k_ins"
                                checked>
                            <label class="form-check-label" for="k_ins">Insurance</label>
                        </div>
                        <div class="form-group mt-2">
                            <label>WEF</label>
                            <input type="date" name="wef_date" class="form-control"
                                value="{{ $session->wef_date?->format('Y-m-d') ?? date('Y-m-d') }}">
                        </div>
                        <button class="btn btn-outline-primary" id="btn-rules">Import rules</button>
                    </form>
                    <div id="rules-result" class="mt-3"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body small">
                    <p>Sheet titles containing <code>RTO</code> / <code>RTA</code> load into RTO rules.</p>
                    <p>Titles containing <code>INSUR</code>, <code>PLAN</code>, <code>ADDON</code>, <code>DEFAULT</code>
                        load Insurance tables.</p>
                    <p class="mb-0 text-muted">Most-specific RTO match wins. Insurance computes every company × plan ×
                        addon; snapshot stores the default combo.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        (function() {
            const keep = document.getElementById('keep-form');
            if (keep) {
                keep.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const res = await fetch("{{ route('pricing.workflow.rules-keep') }}", {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: new FormData(keep)
                    });
                    const data = await res.json();
                    if (!data.success) {
                        alert(data.message || 'Failed');
                        return;
                    }
                    window.location = data.impact_url;
                });
            }
            const form = document.getElementById('rules-form');
            const box = document.getElementById('rules-result');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                box.innerHTML = '<div class="alert alert-info">Importing rules…</div>';
                const res = await fetch("{{ route('pricing.workflow.rules') }}", {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(form)
                });
                const data = await res.json();
                if (!data.success) {
                    box.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed') +
                        '</div>';
                    return;
                }
                box.innerHTML = '<div class="alert alert-success">' + data.message + '</div>' +
                    '<pre class="small bg-light p-2">' + JSON.stringify(data.stats, null, 2) + '</pre>' +
                    '<a class="btn btn-primary" href="' + data.impact_url + '">Impact Summary</a>';
            });
        })();
    </script>
@endpush
