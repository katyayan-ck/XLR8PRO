@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Pricing process — step 3, Vehicle Info (DEC-073). Download every vehicle (incomplete rows highlighted, Missing Fields
    column), fill the masters, re-import (queued, recorded for Discard), read this round's summary, repeat, continue.
--}}
@section('content')
@php
    $progress = $session->progress ?? [];
    $running = ($progress['step'] ?? null) === 'vehicle_info' && ($progress['state'] ?? null) === 'running';
    $failed = ($progress['step'] ?? null) === 'vehicle_info' && ($progress['state'] ?? null) === 'failed';
    $issues = collect($round['issues'] ?? []);
    $rejectedTotal = (int) (($round['rejected'] ?? 0) + ($round['unknown'] ?? 0));
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Process #{{ $session->id }} · Step 2 of 9</div>
            <h2 class="mb-0">{{ $title }}</h2>
        </div>
        <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link px-0"><i class="la la-arrow-left me-1"></i>Process</a>
    </div>

    @foreach (['success' => 'success', 'warning' => 'warning'] as $key => $tone)
        @if (session($key))
            <div class="alert alert-{{ $tone }}" role="alert">{{ session($key) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([['Active', 'ACTIVE', 'text-green'], ['Incomplete', 'INCOMPLETE', 'text-warning'], ['Inactive', 'INACTIVE', ''], ['Discontinued', 'DISCONTINUED', 'text-body-secondary']] as [$label, $key, $tone])
            <div class="col-6 col-md-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-body-secondary small">{{ $label }} vehicles</div>
                        <div class="h2 mb-0 {{ $tone }}">{{ number_format($counts[$key] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">1 · Download</h3></div>
                <div class="card-body">
                    <p class="text-body-secondary small mb-3">
                        Every vehicle in the master, sorted Segment → OEM Model. Incomplete rows are highlighted and list
                        their <strong>Missing Fields</strong>. Fuel, Permit, Body Make and Body Type must be existing key values.
                        @if ($detect)
                            Detect added {{ number_format($detect['created'] ?? 0) }} new vehicle(s) in this process.
                        @endif
                    </p>
                    <a href="{{ route('pricing.workflow.vehicle-info-export', $session->id) }}" class="btn btn-outline-primary">
                        <i class="la la-download me-1"></i>Download Vehicle Info
                    </a>
                </div>
            </div>

            @if ($canManage && $canImport)
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">2 · Upload the filled sheet</h3></div>
                    <div class="card-body">
                        <form action="{{ route('pricing.workflow.vehicle-info-import') }}" method="POST" enctype="multipart/form-data" id="vi-form">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label required" for="xl-vi-file">{{ __('pricing.fields.vehicle_info_file') }} (.xlsx)</label>
                                <x-ui.upload name="file" accept=".xlsx" id="xl-vi-file" :max-kb="20480" required />
                                <div class="form-hint">Blank cells keep the stored value. Only a complete vehicle can be Active; unknown codes are rejected.</div>
                            </div>
                            <button type="submit" class="btn btn-primary" @disabled($running)><i class="la la-upload me-1"></i>Import Vehicle Info</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-7">
            <div class="card {{ $running || $failed ? '' : 'd-none' }}" id="vi-progress" data-url="{{ route('pricing.workflow.status', $session->id) }}" data-poll="{{ $running ? 1 : 0 }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="spinner-border text-primary {{ $failed ? 'd-none' : '' }}" role="status"><span class="visually-hidden">Working…</span></div>
                    <div>
                        <div class="fw-medium" id="vi-message">{{ $progress['message'] ?? '' }}</div>
                        <div class="text-danger small {{ $failed ? '' : 'd-none' }}" id="vi-error">{{ $progress['error'] ?? '' }}</div>
                    </div>
                </div>
            </div>

            <div class="card {{ $running || $failed ? 'mt-3' : '' }}">
                <div class="card-header">
                    <h3 class="card-title">3 · Result{{ $round ? ' — round '.$round['round'] : '' }}</h3>
                    @if ($round)
                        <div class="card-actions text-body-secondary small">{{ site_datetime($round['at'] ?? null, '') }}</div>
                    @endif
                </div>
                @if (! $round)
                    <div class="card-body text-body-secondary">No Vehicle Info imported in this process yet.</div>
                @else
                    <div class="card-body border-bottom">
                        <div class="row g-3">
                            @foreach ([['Rows', $round['rows'] ?? 0, ''], ['Complete', $round['completed'] ?? 0, 'text-green'], ['Newly completed', $round['newly_completed'] ?? 0, 'text-primary'], ['Still incomplete', $round['incomplete'] ?? 0, 'text-warning'], ['Rejected', $rejectedTotal, 'text-danger']] as [$label, $value, $tone])
                                <div class="col-6 col-md">
                                    <div class="text-body-secondary small">{{ $label }}</div>
                                    <div class="h2 mb-0 {{ $tone }}">{{ number_format((int) $value) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($issues->isNotEmpty())
                        <div class="table-responsive" style="max-height: 24rem">
                            <table class="table table-vcenter card-table table-sm">
                                <thead class="sticky-top"><tr><th>Row</th><th>Model Code</th><th>Result</th><th>Reason</th></tr></thead>
                                <tbody>
                                    @foreach ($issues->sortBy(fn ($i) => $i['result'] === 'rejected' ? 0 : 1)->take(200) as $issue)
                                        <tr>
                                            <td>{{ $issue['row'] }}</td>
                                            <td class="text-nowrap">{{ $issue['code'] }}</td>
                                            <td><span class="badge {{ $issue['result'] === 'rejected' ? 'bg-red-lt' : 'bg-yellow-lt' }}">{{ ucfirst($issue['result']) }}</span></td>
                                            <td class="small">{{ $issue['reason'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                            <span class="text-body-secondary small me-auto">{{ $issues->count() > 200 ? 'First 200 of '.number_format($issues->count()).' shown.' : '' }}</span>
                            <a href="{{ route('pricing.workflow.vehicle-info-issues', $session->id) }}" class="btn btn-sm btn-outline-secondary"><i class="la la-download me-1"></i>Download issues</a>
                        </div>
                    @endif
                @endif
            </div>

            @if ($canManage && $session->stage() === \App\Services\Vehicle\Pricing\Session\PricingStage::VehicleInfo)
                <div class="card mt-3">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <div class="me-auto small text-body-secondary">
                            @if (($counts['INCOMPLETE'] ?? 0) > 0)
                                {{ number_format($counts['INCOMPLETE']) }} incomplete vehicle(s) will be skipped by the price import and calculation.
                            @else
                                Every vehicle is complete.
                            @endif
                        </div>
                        <form action="{{ route('pricing.workflow.vehicle-info-continue') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success" @disabled($running)>Continue to prices<i class="la la-arrow-right ms-1"></i></button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
(function () {
    const card = document.getElementById('vi-progress');
    if (!card || card.dataset.poll !== '1') {
        return;
    }
    const msg = document.getElementById('vi-message');
    const timer = setInterval(async function () {
        try {
            const res = await fetch(card.dataset.url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) {
                return;
            }
            const p = (await res.json()).progress || {};
            if (p.message) {
                msg.textContent = p.message;
            }
            if (p.state !== 'running') {
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
