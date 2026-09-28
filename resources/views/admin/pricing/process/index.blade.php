@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Pricing process home (DEC-073, steps 0–2). Gate: one open process — Resume it or Discard it (discard only before
    anything is published). Shows the stepper, the running step's progress (polled) and the Detect report.
--}}
@section('content')
@php
    use App\Services\Vehicle\Pricing\Session\PricingStage;
    $detect = data_get($session?->stats, 'detect');
    $progress = $session?->progress ?? [];
    $resume = $session ? match ($stage) {
        PricingStage::VehicleInfo => route('pricing.workflow.vehicle-info-form'),
        PricingStage::Prices => route('pricing.workflow.prices-form'),
        PricingStage::Addons => route('pricing.workflow.addons-form'),
        PricingStage::Rules => route('pricing.workflow.rules-form'),
        PricingStage::Impact, PricingStage::HoldCheck => route('pricing.workflow.impact-summary-view', $session->id),
        PricingStage::Calculating, PricingStage::Summary => route('pricing.workflow.summary', $session->id),
        default => null,
    } : null;
    $stepIndex = $stage ? collect($steps)->search(fn ($s) => $s->order() >= $stage->order()) : false;
    $short = ['started' => 'Start & detect', 'vehicle_info' => 'Vehicle info', 'prices' => 'Prices', 'addons' => 'Add-ons', 'rules' => 'Insurance & RTO',
        'impact' => 'Impact', 'hold_check' => 'Hold check', 'calculating' => 'Calculate', 'summary' => 'Summary'];
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h2 class="mb-0 me-auto">{{ $title }}</h2>
        @if ($heldLists !== [])
            <span class="badge bg-warning-lt" title="Quotations and calculation are frozen for these lists">
                <i class="la la-pause-circle me-1"></i>On hold: {{ implode(', ', $heldLists) }}
            </span>
        @endif
    </div>

    @foreach (['success' => 'success', 'warning' => 'warning'] as $key => $tone)
        @if (session($key))
            <div class="alert alert-{{ $tone }}" role="alert">{{ session($key) }}</div>
        @endif
    @endforeach

    @if (! $session)
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="mb-2"><i class="la la-tags la-3x text-body-secondary" aria-hidden="true"></i></div>
                <h3 class="mb-1">No pricing process is open</h3>
                <p class="text-body-secondary mb-4">Upload the Pricing workbook, choose the price lists and the WEF date to start.</p>
                @if ($canManage)
                    <a href="{{ route('pricing.workflow.start-form') }}" class="btn btn-primary"><i class="la la-play me-1"></i>Start pricing process</a>
                @else
                    <span class="text-body-secondary small">You can view the process; starting one needs the manage permission.</span>
                @endif
            </div>
        </div>
    @else
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start gap-3 mb-3">
                    <div class="me-auto">
                        <div class="text-body-secondary small">Process #{{ $session->id }} · {{ $session->source_filename }}</div>
                        <h3 class="mb-1" id="pp-stage">{{ $stage->label() }}</h3>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-azure-lt">WEF {{ site_date($session->wef_date) }}</span>
                            @foreach ((array) $session->selected_sheets as $sheet)
                                <span class="badge bg-secondary-lt">{{ $sheet }}</span>
                            @endforeach
                            @if ($session->isPublished())
                                <span class="badge bg-green-lt">Published {{ site_datetime($session->published_at) }}</span>
                            @endif
                        </div>
                    </div>
                    @if ($canManage)
                        <div class="d-flex flex-wrap gap-2">
                            @if ($resume)
                                <a href="{{ $resume }}" class="btn btn-primary" id="pp-resume"><i class="la la-arrow-right me-1"></i>Resume</a>
                            @endif
                            @unless ($session->isPublished())
                                <form action="{{ route('pricing.workflow.discard') }}" method="POST"
                                      onsubmit="return confirm('Discard process #{{ $session->id }}? Every change it made is undone.');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger"><i class="la la-times me-1"></i>Discard</button>
                                </form>
                            @endunless
                        </div>
                    @endif
                </div>

                <ul class="steps steps-counter steps-blue my-2 d-none d-lg-flex">
                    @foreach ($steps as $i => $step)
                        <li class="step-item {{ $stepIndex === $i ? 'active' : '' }}">{{ $short[$step->value] }}</li>
                    @endforeach
                </ul>
                <div class="d-lg-none text-body-secondary small">
                    Step {{ $stepIndex === false ? '—' : $stepIndex + 1 }} of {{ count($steps) }}
                    <div class="progress progress-sm mt-1"><div class="progress-bar" style="width: {{ $stepIndex === false ? 0 : round(($stepIndex + 1) / count($steps) * 100) }}%" role="progressbar" aria-label="Process progress"></div></div>
                </div>
            </div>
        </div>

        <div class="card mb-3 {{ $stage === PricingStage::Detecting || ($progress['state'] ?? null) === 'failed' ? '' : 'd-none' }}" id="pp-progress"
             data-url="{{ route('pricing.workflow.status', $session->id) }}" data-poll="{{ $stage === PricingStage::Detecting ? 1 : 0 }}">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="spinner-border text-primary {{ ($progress['state'] ?? null) === 'failed' ? 'd-none' : '' }}" role="status" id="pp-spinner">
                        <span class="visually-hidden">Working…</span>
                    </div>
                    <div>
                        <div class="fw-medium" id="pp-message">{{ $progress['message'] ?? 'Queued — waiting for the queue worker…' }}</div>
                        <div class="text-danger small {{ empty($progress['error']) ? 'd-none' : '' }}" id="pp-error">{{ $progress['error'] ?? '' }}</div>
                        <div class="text-body-secondary small">Runs in the background (<code>php artisan queue:work --timeout=1800 --tries=1</code>). You can leave this page.</div>
                    </div>
                </div>
            </div>
        </div>

        @if ($detect)
            <div class="card">
                <div class="card-header"><h3 class="card-title">Detect — new vehicles from the price lists</h3></div>
                <div class="card-body border-bottom">
                    <div class="row g-3">
                        @foreach (['created' => ['New (incomplete)', 'text-primary'], 'known' => ['Already known', ''], 'csd_unknown' => ['CSD codes not in master', 'text-warning'], 'duplicates' => ['Duplicate rows', ''], 'errors' => ['Errors', 'text-danger']] as $key => [$label, $tone])
                            <div class="col-6 col-md">
                                <div class="text-body-secondary small">{{ $label }}</div>
                                <div class="h2 mb-0 {{ $tone }}">{{ number_format((int) data_get($detect, "totals.$key", 0)) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr><th>Sheet</th><th class="text-end">Codes</th><th class="text-end">Known</th><th class="text-end">New</th><th class="text-end">CSD unknown</th><th class="text-end">Duplicates</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            @foreach ((array) ($detect['sheets'] ?? []) as $sheet => $r)
                                <tr>
                                    <td class="fw-medium text-nowrap">{{ $sheet }}</td>
                                    <td class="text-end">{{ number_format((int) ($r['rows'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($r['known'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($r['created'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($r['csd_unknown'] ?? 0)) }}</td>
                                    <td class="text-end">{{ number_format((int) ($r['duplicates'] ?? 0)) }}</td>
                                    <td class="small">
                                        @if (! empty($r['error']))
                                            <span class="text-danger">{{ $r['error'] }}</span>
                                        @endif
                                        @foreach (array_slice((array) ($r['errors'] ?? []), 0, 5) as $err)
                                            <div class="text-danger">{{ $err }}</div>
                                        @endforeach
                                        @if (count((array) ($r['errors'] ?? [])) > 5)
                                            <div class="text-body-secondary">+{{ count($r['errors']) - 5 }} more</div>
                                        @endif
                                        @if (! empty($r['csd_unknown_codes']))
                                            <details><summary class="text-body-secondary">CSD codes skipped</summary><div class="text-break">{{ implode(', ', $r['csd_unknown_codes']) }}</div></details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-body-secondary small">
                    New vehicles are created <strong>Incomplete</strong> (not sellable). Next: download Vehicle Info, fill the masters and re-import.
                </div>
            </div>
        @endif
    @endif

    @if ($recent->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Recent processes</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>#</th><th>Workbook</th><th>WEF</th><th>Result</th><th>Closed</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td class="text-break">{{ $r->source_filename ?? '—' }}</td>
                                <td>{{ site_date($r->wef_date, '—') }}</td>
                                <td><span class="badge {{ $r->status === 'completed' ? 'bg-green-lt' : 'bg-secondary-lt' }}">{{ $r->stage()->label() }}</span></td>
                                <td>{{ site_datetime($r->completed_at ?? $r->cancelled_at, '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@push('after_scripts')
<script>
(function () {
    const card = document.getElementById('pp-progress');
    if (!card || card.dataset.poll !== '1') {
        return;
    }
    const msg = document.getElementById('pp-message');
    const err = document.getElementById('pp-error');
    const spinner = document.getElementById('pp-spinner');
    const timer = setInterval(async function () {
        try {
            const res = await fetch(card.dataset.url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            const p = data.progress || {};
            if (p.message) {
                msg.textContent = p.message;
            }
            if (p.state === 'failed') {
                clearInterval(timer);
                spinner.classList.add('d-none');
                err.textContent = p.error || 'Detect failed.';
                err.classList.remove('d-none');
                return;
            }
            if (data.stage !== 'detecting') {
                clearInterval(timer);
                window.location.reload();
            }
        } catch (e) {
            // transient network error — keep polling
        }
    }, 2000);
})();
</script>
@endpush
