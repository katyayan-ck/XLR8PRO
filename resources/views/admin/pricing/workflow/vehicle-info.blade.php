@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2 class="mb-0">{{ $title ?? 'Complete Vehicle Info' }}</h2>
        <p class="text-muted mb-0">
            Session #{{ $session->id }} · stage
            <span class="badge badge-info">{{ $session->current_stage }}</span>
        </p>
    </section>
@endsection

@section('content')
<style>
    .vi-log {
        background: #0f172a;
        color: #e2e8f0;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12px;
        max-height: 320px;
        overflow-y: auto;
        border-radius: 8px;
        padding: 12px 14px;
        user-select: text;
        white-space: pre-wrap;
    }
    .vi-log .line { margin: 0 0 2px; }
    .vi-stat {
        border-radius: 10px;
        padding: 14px 16px;
        background: #fff;
        border: 1px solid #e5e7eb;
        text-align: center;
    }
    .vi-stat .n { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
    .vi-stat .l { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
    .progress-vi { height: 10px; border-radius: 999px; background: #e5e7eb; overflow: hidden; }
    .progress-vi > div {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #2563eb, #06b6d4);
        transition: width .25s ease;
    }
</style>

<div class="row">
    <div class="col-lg-5 mb-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">1. Download template</h5>
                <p class="text-muted small">
                    Export includes all masters + FRESH incomplete rows. Fill required fields,
                    set <strong>Master Complete = Y</strong> and <strong>Inactive = N</strong> to activate.
                </p>
                <a href="{{ route('pricing.workflow.vehicle-info-export', $session->id) }}"
                   class="btn btn-outline-secondary btn-sm">
                    Download current Vehicle Info export
                </a>
                <hr>
                <h5 class="card-title">2. Upload completed workbook</h5>
                <form id="vi-form" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <input type="file" name="file" id="vi-file" class="form-control" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">
                            Active requires complete master data (Segment, Fuel, Wheels…).
                            Set Inactive = Y only to leave a vehicle incomplete.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-vi">
                        Import Vehicle Info
                    </button>
                    <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link">Back</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Import progress</h5>
                    <span id="vi-phase" class="badge badge-secondary">Idle</span>
                </div>
                <p id="vi-message" class="text-muted small mb-2">Waiting for upload…</p>
                <div class="progress-vi mb-3"><div id="vi-bar"></div></div>

                <div class="row mb-3">
                    <div class="col-3"><div class="vi-stat"><div class="n" id="st-processed">0</div><div class="l">Processed</div></div></div>
                    <div class="col-3"><div class="vi-stat"><div class="n text-success" id="st-completed">0</div><div class="l">Completed</div></div></div>
                    <div class="col-3"><div class="vi-stat"><div class="n text-warning" id="st-inactive">0</div><div class="l">Inactive</div></div></div>
                    <div class="col-3"><div class="vi-stat"><div class="n text-danger" id="st-rejected">0</div><div class="l">Rejected</div></div></div>
                </div>

                <div class="vi-log" id="vi-log"></div>

                <div id="vi-result" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
(function () {
    const sessionId = {{ (int) $session->id }};
    const progressUrl = @json(route('pricing.workflow.vehicle-info-progress', $session->id));
    const importUrl = @json(route('pricing.workflow.vehicle-info-import'));
    const pricesUrl = @json(route('pricing.workflow.prices-form'));
    const homeUrl = @json(route('pricing.workflow.index'));

    const logEl = document.getElementById('vi-log');
    const barEl = document.getElementById('vi-bar');
    const phaseEl = document.getElementById('vi-phase');
    const msgEl = document.getElementById('vi-message');
    let pollTimer = null;
    let seenLogs = 0;

    function appendLogs(logs) {
        if (!Array.isArray(logs)) return;
        for (let i = seenLogs; i < logs.length; i++) {
            const div = document.createElement('div');
            div.className = 'line';
            div.textContent = logs[i];
            logEl.appendChild(div);
        }
        seenLogs = logs.length;
        logEl.scrollTop = logEl.scrollHeight;
    }

    function renderProgress(data) {
        if (!data) return;
        const pct = data.percent || 0;
        barEl.style.width = pct + '%';
        phaseEl.textContent = (data.phase || '…').toString();
        msgEl.textContent = data.message || '';
        document.getElementById('st-processed').textContent = data.processed || 0;
        document.getElementById('st-completed').textContent = data.completed || 0;
        document.getElementById('st-inactive').textContent = data.inactive || 0;
        document.getElementById('st-rejected').textContent = data.rejected || 0;
        appendLogs(data.logs || []);
    }

    async function poll() {
        try {
            const res = await fetch(progressUrl, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            renderProgress(data);
            if (data.done) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        } catch (e) { /* ignore transient */ }
    }

    document.getElementById('vi-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-vi');
        btn.disabled = true;
        btn.textContent = 'Importing…';
        seenLogs = 0;
        logEl.innerHTML = '';
        document.getElementById('vi-result').style.display = 'none';
        renderProgress({ phase: 'starting', message: 'Upload started…', percent: 1, logs: ['[' + new Date().toLocaleTimeString() + '] Upload started'] });

        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(poll, 800);

        const fd = new FormData(this);
        try {
            const res = await fetch(importUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: fd
            });
            const data = await res.json();
            await poll(); // final snapshot

            const box = document.getElementById('vi-result');
            box.style.display = 'block';
            if (!data.success) {
                box.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed') + '</div>';
                btn.disabled = false;
                btn.textContent = 'Import Vehicle Info';
                return;
            }

            let extra = '';
            if (data.stats && data.stats.rejected && data.stats.rejected.length) {
                const more = (data.stats.rejected_total || data.stats.rejected.length) > data.stats.rejected.length
                    ? ' <em>(showing first ' + data.stats.rejected.length + ' of ' + data.stats.rejected_total + ')</em>'
                    : '';
                extra = '<details class="mt-2"><summary>Rejected rows' + more + '</summary><ul class="small mb-0">' +
                    data.stats.rejected.map(r => '<li>' + r + '</li>').join('') + '</ul></details>';
            }

            box.innerHTML =
                '<div class="alert alert-success mb-2">' + data.message + '</div>' + extra +
                '<div class="mt-2">' +
                '<a class="btn btn-primary" href="' + pricesUrl + '">Continue to Prices</a> ' +
                '<a class="btn btn-link" href="' + homeUrl + '">Workflow home</a>' +
                '</div>';

            btn.textContent = 'Import again';
            btn.disabled = false;
        } catch (err) {
            document.getElementById('vi-result').style.display = 'block';
            document.getElementById('vi-result').innerHTML =
                '<div class="alert alert-danger">' + (err.message || 'Network error') + '</div>';
            btn.disabled = false;
            btn.textContent = 'Import Vehicle Info';
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }
    });
})();
</script>
@endpush
