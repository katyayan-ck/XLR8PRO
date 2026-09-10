@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>{{ $title ?? 'Start Pricing Process' }}</h2>
    </section>
@endsection

@section('content')
<style>
.pricing-progress-card { position: sticky; top: 1rem; }
.pricing-step { display: flex; align-items: center; gap: .75rem; padding: .5rem 0; opacity: .45; }
.pricing-step.active { opacity: 1; font-weight: 600; color: #1F4E79; }
.pricing-step.done { opacity: 1; color: #1e7e34; }
.pricing-step .dot {
    width: 28px; height: 28px; border-radius: 50%;
    border: 2px solid #ccc; display: flex; align-items: center; justify-content: center;
    font-size: 12px; flex-shrink: 0; background: #fff;
}
.pricing-step.active .dot {
    border-color: #1F4E79; background: #1F4E79; color: #fff;
    animation: pulse 1.2s ease-in-out infinite;
}
.pricing-step.done .dot { border-color: #1e7e34; background: #1e7e34; color: #fff; animation: none; }
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(31,78,121,.45); }
    50% { box-shadow: 0 0 0 8px rgba(31,78,121,0); }
}
.pricing-spinner {
    width: 42px; height: 42px; border: 4px solid #e9ecef; border-top-color: #1F4E79;
    border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 1rem;
}
@keyframes spin { to { transform: rotate(360deg); } }
#progress-log {
    max-height: 260px; overflow-y: auto; font-size: 12px;
    background: #0f172a; color: #e2e8f0; border-radius: 6px; padding: .75rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    user-select: text; -webkit-user-select: text; cursor: text;
}
#progress-log div { margin-bottom: 4px; user-select: text; -webkit-user-select: text; }
.progress { height: 8px; margin: .75rem 0 1rem; }
</style>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form id="start-form" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label>Pricing workbook (xlsx)</label>
                        <input type="file" name="file" id="file-input" class="form-control" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">Detection uses Price List sheets only. Processing runs in a background job (no PHP timeout).</small>
                    </div>

                    <div class="form-group">
                        <label>Price list sheets to process</label>
                        @foreach($sheetOptions as $code => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sheet_types[]" value="{{ $code }}" id="s_{{ $code }}"
                                    @if(in_array($code, ['PRICE_LIST_PV','PRICE_LIST_CV'])) checked @endif>
                                <label class="form-check-label" for="s_{{ $code }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="form-group">
                        <label>WEF date</label>
                        <input type="date" name="wef_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="run_prices_now" value="1" id="run_prices" checked>
                        <label class="form-check-label" for="run_prices">Also import prices for already-known vehicles in this file</label>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btn-start">Start detect</button>
                    <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link">Cancel</a>
                </form>

                <div id="result" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card pricing-progress-card">
            <div class="card-header"><strong>Process progress</strong></div>
            <div class="card-body">
                <div id="idle-hint" class="text-muted small">
                    Progress updates live after you click <strong>Start detect</strong>.
                    Ensure <code>php artisan queue:work</code> is running.
                </div>

                <div id="progress-active" style="display:none;">
                    <div class="pricing-spinner" id="main-spinner"></div>
                    <p class="text-center mb-1" id="progress-headline">Starting…</p>
                    <p class="text-center text-muted small mb-0" id="progress-detail"></p>
                    <div class="progress">
                        <div class="progress-bar" id="progress-bar" role="progressbar" style="width:0%"></div>
                    </div>

                    <div class="pricing-step" data-step="upload"><div class="dot">1</div><div>Upload workbook</div></div>
                    <div class="pricing-step" data-step="session"><div class="dot">2</div><div>Create session &amp; queue job</div></div>
                    <div class="pricing-step" data-step="detect"><div class="dot">3</div><div>Detect new vehicles</div></div>
                    <div class="pricing-step" data-step="prices"><div class="dot">4</div><div>Import prices (chunked)</div></div>
                    <div class="pricing-step" data-step="finish"><div class="dot">5</div><div>Finish &amp; next steps</div></div>

                    <hr>
                    <div id="progress-log"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
(function () {
    const form = document.getElementById('start-form');
    const btn = document.getElementById('btn-start');
    const result = document.getElementById('result');
    const idle = document.getElementById('idle-hint');
    const active = document.getElementById('progress-active');
    const headline = document.getElementById('progress-headline');
    const detail = document.getElementById('progress-detail');
    const bar = document.getElementById('progress-bar');
    const logEl = document.getElementById('progress-log');
    const spinner = document.getElementById('main-spinner');
    let pollTimer = null;
    let lastLogCount = 0;

    function setStep(name) {
        const order = ['upload', 'session', 'detect', 'prices', 'finish'];
        const idx = order.indexOf(name);
        document.querySelectorAll('.pricing-step').forEach(function (el) {
            const si = order.indexOf(el.getAttribute('data-step'));
            el.classList.remove('active', 'done');
            if (si < idx) el.classList.add('done');
            if (si === idx) el.classList.add('active');
        });
    }

    function appendLogs(logs) {
        if (!Array.isArray(logs)) return;
        if (logs.length <= lastLogCount) return;
        for (let i = lastLogCount; i < logs.length; i++) {
            const line = document.createElement('div');
            line.textContent = logs[i];
            logEl.appendChild(line);
        }
        lastLogCount = logs.length;
        logEl.scrollTop = logEl.scrollHeight;
    }

    function applyProgress(data) {
        const pct = data.percent || 0;
        bar.style.width = pct + '%';
        headline.textContent = data.message || data.phase || 'Working…';
        if (data.processed != null && data.total) {
            detail.textContent = (data.sheet ? data.sheet + ' — ' : '') +
                data.processed + ' / ' + data.total +
                (data.last_code ? ' (last: ' + data.last_code + ')' : '');
        } else {
            detail.textContent = data.phase || '';
        }
        appendLogs(data.logs);

        if (data.phase === 'queued' || data.phase === 'session') setStep('session');
        else if (data.phase === 'detect' || data.phase === 'detect_done') setStep('detect');
        else if (data.phase === 'prices') setStep('prices');
        else if (data.phase === 'done' || data.phase === 'failed') setStep('finish');

        if (data.done) {
            clearInterval(pollTimer);
            spinner.style.display = 'none';
            if (data.failed) {
                headline.textContent = 'Failed';
                result.style.display = 'block';
                result.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed') + '</div>';
                btn.disabled = false;
                return;
            }
            headline.textContent = 'Completed';
            let html = '<div class="alert alert-success">' + (data.message || 'Done') + '</div>';
            if (data.export_url) {
                html += '<a class="btn btn-secondary" href="' + data.export_url + '">Download Vehicle Info</a> ';
                html += '<a class="btn btn-primary" href="' + data.vehicle_info_form + '">Vehicle Info import</a>';
            } else {
                html += '<a class="btn btn-primary" href="{{ route('pricing.workflow.index') }}">Workflow home</a>';
            }
            result.style.display = 'block';
            result.innerHTML = html;
        }
    }

    function startPolling(url) {
        pollTimer = setInterval(async function () {
            try {
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                applyProgress(data);
            } catch (e) {
                appendLogs(['Poll error: ' + e.message]);
            }
        }, 1500);
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!form.querySelectorAll('input[name="sheet_types[]"]:checked').length) {
            alert('Select at least one Price List sheet.');
            return;
        }
        btn.disabled = true;
        result.style.display = 'none';
        idle.style.display = 'none';
        active.style.display = 'block';
        spinner.style.display = 'block';
        logEl.innerHTML = '';
        lastLogCount = 0;
        bar.style.width = '2%';
        headline.textContent = 'Uploading workbook…';
        setStep('upload');

        const fd = new FormData(form);
        if (!fd.get('run_prices_now')) fd.set('run_prices_now', '0');

        try {
            const res = await fetch("{{ route('pricing.workflow.start') }}", {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value
                },
                body: fd
            });
            const data = await res.json();
            if (!data.success) {
                spinner.style.display = 'none';
                headline.textContent = 'Failed';
                result.style.display = 'block';
                result.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed') + '</div>';
                btn.disabled = false;
                return;
            }
            setStep('session');
            headline.textContent = 'Job queued — waiting for worker…';
            appendLogs(['Session #' + data.session_id + ' — ' + data.message]);
            startPolling(data.progress_url);
        } catch (err) {
            spinner.style.display = 'none';
            headline.textContent = 'Error';
            result.style.display = 'block';
            result.innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
            btn.disabled = false;
        }
    });
})();
</script>
@endpush
@endsection
