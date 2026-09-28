@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>{{ $title ?? 'Import Price Lists' }}</h2>
        <p class="text-muted mb-0">
            Session #{{ $session->id }}
            · stage <code>{{ $session->current_stage }}</code>
            · WEF <code>{{ $session->wef_date?->format('Y-m-d') }}</code>
        </p>
    </section>
@endsection

@section('content')
    <style>
        .progress-prices {
            height: 10px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .progress-prices>div {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            transition: width .35s ease;
        }

        #prices-log {
            background: #0f172a;
            color: #e2e8f0;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            max-height: 280px;
            overflow-y: auto;
            border-radius: 8px;
            padding: 12px;
            user-select: text;
            -webkit-user-select: text;
        }

        #prices-log div {
            margin-bottom: 4px;
        }

        .stat-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            text-align: center;
        }

        .stat-tile .n {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .stat-tile .l {
            font-size: .75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
    </style>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <form id="prices-form" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Pricing workbook</label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                            <div class="form-text">Same Pricing.xlsx used for detect (PV / CV / BEV / LMM / LMM TZU / CSD).
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sheets</label>
                            @foreach ($sheetOptions as $code => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sheet_types[]"
                                        value="{{ $code }}" id="p_{{ $code }}"
                                        @if (in_array($code, $session->selected_sheets ?? array_keys($sheetOptions))) checked @endif>
                                    <label class="form-check-label" for="p_{{ $code }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="mb-3">
                            <label class="form-label">WEF date</label>
                            <input type="date" name="wef_date" class="form-control"
                                value="{{ $session->wef_date?->format('Y-m-d') ?? date('Y-m-d') }}">
                        </div>
                        <button type="submit" class="btn btn-primary" id="btn-prices">Import prices</button>
                        <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link">Back</a>
                    </form>
                    <div class="alert alert-info mt-3 mb-0 small">
                        <strong>Queue worker required.</strong>
                        Run in a terminal:<br>
                        <code>php artisan queue:work --timeout=1800 --tries=1</code>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Import progress</h5>
                    <span class="badge bg-secondary" id="prices-phase">idle</span>
                </div>
                <div class="card-body">
                    <p class="mb-2" id="prices-message">Waiting for upload…</p>
                    <div class="progress-prices mb-3">
                        <div id="prices-bar"></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="stat-tile">
                                <div class="n" id="st-processed">0</div>
                                <div class="l">Processed</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-tile">
                                <div class="n text-success" id="st-written">0</div>
                                <div class="l">Written</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-tile">
                                <div class="n text-warning" id="st-skipped">0</div>
                                <div class="l">Skipped</div>
                            </div>
                        </div>
                    </div>

                    <div id="prices-log"></div>

                    <div id="prices-result" class="mt-3" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        (function() {
            const form = document.getElementById('prices-form');
            const btn = document.getElementById('btn-prices');
            const phaseEl = document.getElementById('prices-phase');
            const msgEl = document.getElementById('prices-message');
            const bar = document.getElementById('prices-bar');
            const logEl = document.getElementById('prices-log');
            const result = document.getElementById('prices-result');
            let pollTimer = null;
            let lastLogCount = 0;

            function appendLogs(logs) {
                if (!Array.isArray(logs) || logs.length <= lastLogCount) return;
                const fresh = logs.slice(lastLogCount);
                lastLogCount = logs.length;
                fresh.forEach(line => {
                    const d = document.createElement('div');
                    d.textContent = line;
                    logEl.appendChild(d);
                });
                logEl.scrollTop = logEl.scrollHeight;
            }

            function applyProgress(data) {
                if (!data) return;
                phaseEl.textContent = data.phase || '…';
                msgEl.textContent = data.message || '';
                bar.style.width = (data.percent || 0) + '%';
                document.getElementById('st-processed').textContent = data.processed ?? 0;
                if (data.price_stats) {
                    document.getElementById('st-written').textContent = data.price_stats.written ?? 0;
                    document.getElementById('st-skipped').textContent = data.price_stats.skipped ?? 0;
                }
                if (data.logs) appendLogs(data.logs);

                if (data.done) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    btn.disabled = false;
                    btn.textContent = 'Import prices';
                    result.style.display = 'block';
                    if (data.failed) {
                        result.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed') + '</div>';
                        return;
                    }
                    if (data.next_step === 'vehicle-info') {
                        result.innerHTML = '<div class="alert alert-warning">' + (data.message ||
                            'New vehicles found') + '</div>' +
                            (data.export_filename ? '<p>Export: <code>' + data.export_filename + '</code></p>' : '') +
                            '<a class="btn btn-primary" href="{{ route('pricing.workflow.vehicle-info-form') }}">Complete Vehicle Info</a>';
                        return;
                    }
                    const written = data.price_stats?.written ?? '—';
                    const changed = data.price_stats?.changed ?? '—';
                    result.innerHTML = '<div class="alert alert-success">Pricing imported. Written: ' +
                        written + ', changed: ' + changed + '.</div>' +
                        '<a class="btn btn-primary" href="{{ route('pricing.workflow.addons-form') }}">Continue to Addons</a> ' +
                        '<a class="btn btn-link" href="{{ route('pricing.workflow.index') }}">Workflow home</a>';
                }
            }

            async function poll(url) {
                try {
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    applyProgress(data);
                } catch (e) {
                    console.warn('progress poll failed', e);
                }
            }

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                btn.disabled = true;
                btn.textContent = 'Queuing…';
                lastLogCount = 0;
                logEl.innerHTML = '';
                result.style.display = 'none';
                phaseEl.textContent = 'queuing';
                msgEl.textContent = 'Uploading workbook…';
                bar.style.width = '2%';

                const fd = new FormData(form);
                try {
                    const res = await fetch("{{ route('pricing.workflow.prices') }}", {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: fd
                    });
                    const data = await res.json();
                    if (!data.success) {
                        result.style.display = 'block';
                        result.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed') +
                            '</div>';
                        btn.disabled = false;
                        btn.textContent = 'Import prices';
                        return;
                    }

                    msgEl.textContent = data.message || 'Queued…';
                    phaseEl.textContent = 'queued';
                    btn.textContent = 'Running…';

                    const progressUrl = data.progress_url ||
                        "{{ route('pricing.workflow.progress', $session->id) }}";
                    if (pollTimer) clearInterval(pollTimer);
                    pollTimer = setInterval(() => poll(progressUrl), 1500);
                    poll(progressUrl);
                } catch (err) {
                    alert(err.message);
                    btn.disabled = false;
                    btn.textContent = 'Import prices';
                }
            });
        })();
    </script>
@endpush
