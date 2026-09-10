@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>{{ $title ?? 'Impact Summary' }}</h2>
        <p class="text-muted mb-0">Session #{{ $sessionId }}</p>
    </section>
@endsection

@section('content')
<div class="container-fluid">
    <div id="summary-loading" class="card"><div class="card-body">Loading…</div></div>
    <div id="summary-body" style="display:none;">
        <div class="row g-3 mb-3" id="kpi-row"></div>
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Stage writes</h5>
                <div id="stage-table"></div>
            </div>
        </div>
        <div class="card mb-3" id="error-card" style="display:none;">
            <div class="card-body">
                <h5 class="card-title text-danger">First errors (capped)</h5>
                <ul id="error-list" class="mb-0 small"></ul>
            </div>
        </div>
        <button class="btn btn-success" id="btn-calc">Calculate &amp; Publish</button>
        <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link">Back</a>
        <div id="calc-status" class="mt-3 small text-muted"></div>
    </div>
</div>

@push('after_scripts')
<script>
(async function () {
    const sessionId = {{ (int) $sessionId }};
    const res = await fetch("{{ route('pricing.workflow.impact-summary', $sessionId) }}", {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    document.getElementById('summary-loading').style.display = 'none';
    document.getElementById('summary-body').style.display = 'block';

    const kpi = [
        ['Fresh vehicles', data.fresh_created ?? data.session?.stats?.fresh_created ?? 0],
        ['Complete masters', data.complete_masters ?? 0],
        ['Active prices', data.active_price_rows ?? data.session?.stats?.prices_written ?? 0],
        ['Can calculate', data.can_calculate ? 'Yes' : 'No'],
    ];
    document.getElementById('kpi-row').innerHTML = kpi.map(([l,v]) =>
        `<div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="fs-3 fw-bold">${v}</div><div class="text-muted small">${l}</div></div></div>`
    ).join('');

    const addons = data.session?.stats?.addons || {};
    const rules = data.session?.stats?.rules || {};
    const rows = [];
    Object.entries(addons).forEach(([k, v]) => rows.push([k, v.written ?? 0, v.skipped ?? 0, v.error_count ?? (v.errors||[]).length]));
    Object.entries(rules).forEach(([k, v]) => rows.push([k, v.written ?? 0, v.skipped ?? 0, v.error_count ?? (v.errors||[]).length]));
    document.getElementById('stage-table').innerHTML = rows.length
        ? `<table class="table table-sm"><thead><tr><th>Sheet</th><th>Written</th><th>Skipped</th><th>Errors</th></tr></thead><tbody>${
            rows.map(r => `<tr><td>${r[0]}</td><td>${r[1]}</td><td>${r[2]}</td><td>${r[3]}</td></tr>`).join('')
        }</tbody></table>`
        : '<p class="text-muted mb-0">No addon / rules stats yet.</p>';

    const errs = [];
    Object.values(addons).forEach(v => (v.errors||[]).slice(0,3).forEach(e => errs.push(e)));
    Object.values(rules).forEach(v => (v.errors||[]).slice(0,3).forEach(e => errs.push(e)));
    if (errs.length) {
        document.getElementById('error-card').style.display = 'block';
        document.getElementById('error-list').innerHTML = errs.slice(0,8).map(e => `<li>${e}</li>`).join('');
    }

    document.getElementById('btn-calc').addEventListener('click', async function () {
        this.disabled = true;
        const r = await fetch("{{ route('pricing.workflow.calculate', $sessionId) }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        const j = await r.json();
        document.getElementById('calc-status').textContent = j.message || 'Queued';
        if (j.progress_url) poll(j.progress_url);
    });

    async function poll(url) {
        const box = document.getElementById('calc-status');
        const tick = async () => {
            const p = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const d = await p.json();
            box.textContent = (d.percent || 0) + '% — ' + (d.message || d.phase);
            if (!d.done) setTimeout(tick, 2000);
        };
        tick();
    }
})();
</script>
@endpush
@endsection
