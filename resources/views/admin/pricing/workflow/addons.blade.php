@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>{{ $title ?? 'Addons & Discounts' }}</h2>
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
                    <h5>1. Download current values</h5>
                    <p class="small text-muted">
                        Review workbook lists every stored rule (including blank groups if none exist yet).
                        Blank / ANY = all descendants. A more specific later row replaces the previous match.
                    </p>
                    <a href="{{ route('pricing.workflow.addons-export', $session->id) }}"
                        class="btn btn-outline-secondary btn-sm">
                        Download Addon-N-Discounts
                    </a>
                    <hr>
                    <h5>2. Re-upload after review</h5>
                    <form id="addons-form" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label>Workbook</label>
                            <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                        </div>
                        <div class="form-group">
                            <label>Sheets to apply (default all)</label>
                            @foreach ($sheetOptions as $code => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sheet_types[]"
                                        value="{{ $code }}" id="a_{{ $code }}" checked>
                                    <label class="form-check-label" for="a_{{ $code }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-group">
                            <label>WEF</label>
                            <input type="date" name="wef_date" class="form-control"
                                value="{{ $session->wef_date?->format('Y-m-d') ?? date('Y-m-d') }}">
                        </div>
                        <button type="submit" class="btn btn-primary" id="btn-addons">Import addons</button>
                        <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link">Back</a>
                    </form>
                    <div id="addons-result" class="mt-3"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body small">
                    <h6>Sheet rules</h6>
                    <ul class="mb-0">
                        <li><strong>Dealer Charges</strong> — Segment + Permit + Model. Heads: Incidental, FastTag, TRC, RTO
                            Tape, COD.</li>
                        <li><strong>RSA</strong> — Segment + Model. Std+1 … Std+5. First paid year is default.</li>
                        <li><strong>Shield</strong> — Pack + Transmission + Fuel. Scheme 1 is default.</li>
                        <li><strong>Exchange</strong> — Model + Variant. Scheme type + OEM/Dealer share.</li>
                        <li><strong>Corporate</strong> — Model + Variant. Category + OEM/Dealer share.</li>
                    </ul>
                    <p class="mt-3 mb-0 text-muted">
                        Segment typos (PERSONAL → PV) go through SynonymService.
                        After import go to Impact Summary → Calculate &amp; Publish.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        (function() {
            const form = document.getElementById('addons-form');
            const btn = document.getElementById('btn-addons');
            const box = document.getElementById('addons-result');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!form.querySelectorAll('input[name="sheet_types[]"]:checked').length) {
                    alert('Select at least one sheet.');
                    return;
                }
                btn.disabled = true;
                box.innerHTML = '<div class="alert alert-info">Importing…</div>';
                try {
                    const res = await fetch("{{ route('pricing.workflow.addons') }}", {
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
                        btn.disabled = false;
                        return;
                    }
                    box.innerHTML = '<div class="alert alert-success">' + data.message + '</div>' +
                        '<pre class="small bg-light p-2">' + JSON.stringify(data.stats, null, 2) +
                        '</pre>' +
                        '<a class="btn btn-primary" href="' + (data.rules_url ||
                            '{{ route('pricing.workflow.rules-form') }}') + '">Insurance & RTO</a>';
                    btn.disabled = false;
                } catch (err) {
                    box.innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
                    btn.disabled = false;
                }
            });
        })();
    </script>
@endpush
