@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Pricing process — step 9 progress and step 10 summary (DEC-073 / DEC-080): the queued Calculate & Publish run,
    counts per list, failures with reasons (download, retry), and Complete (reopen chosen held lists, release the gate).
--}}
@section('content')
@php
    use App\Models\Vehicle\Pricing\CalcResult;
    use App\Services\Vehicle\Pricing\Session\PricingStage;
    $stage = $session->stage();
    $running = ($session->progress['state'] ?? null) === 'running';
    $n = fn ($v) => number_format((int) $v);
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Process #{{ $session->id }} · Step {{ $stage === PricingStage::Calculating ? '8' : '9' }} of 9</div>
            <h2 class="mb-0">{{ $stage === PricingStage::Calculating ? 'Calculate & Publish' : $title }}</h2>
        </div>
        <a href="{{ route('pricing.workflow.index') }}" class="btn btn-link px-0"><i class="la la-arrow-left me-1"></i>Process</a>
    </div>

    @foreach (['success' => 'success', 'warning' => 'warning'] as $key => $tone)
        @if (session($key))
            <div class="alert alert-{{ $tone }}" role="alert">{{ session($key) }}</div>
        @endif
    @endforeach

    @include('admin.pricing.process._progress', ['session' => $session, 'step' => 'calculate'])

    <div class="row g-3 mb-3">
        @foreach ([['Published vehicles', $counts['published'], 'text-green'], ['Snapshots', $counts['snapshots'], ''], ['Failed', $counts['failed'], 'text-danger'], ['Skipped', $counts['skipped'], 'text-body-secondary']] as [$label, $value, $tone])
            <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
                <div class="text-body-secondary small">{{ $label }}</div><div class="h1 mb-0 {{ $tone }}">{{ $n($value) }}</div>
            </div></div></div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Per list</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>List</th><th class="text-end">Published</th><th class="text-end">Snapshots</th><th class="text-end">Failed</th><th class="text-end">Skipped</th></tr></thead>
                        <tbody>
                            @forelse ($byList as $list => $rows)
                                @php $by = $rows->keyBy('status'); @endphp
                                <tr>
                                    <td class="fw-medium">{{ $list }}</td>
                                    <td class="text-end text-green">{{ $n($by[CalcResult::PUBLISHED]->c ?? 0) }}</td>
                                    <td class="text-end">{{ $n($by[CalcResult::PUBLISHED]->s ?? 0) }}</td>
                                    <td class="text-end text-danger">{{ $n($by[CalcResult::FAILED]->c ?? 0) }}</td>
                                    <td class="text-end text-body-secondary">{{ $n($by[CalcResult::SKIPPED]->c ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-body-secondary">No results yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Failures</h3>
                    <div class="card-actions">
                        @if ($counts['failed'] + $counts['skipped'] > 0)
                            <a href="{{ route('pricing.workflow.summary-results', $session->id) }}" class="btn btn-sm btn-outline-secondary"><i class="la la-download me-1"></i>Failed &amp; skipped</a>
                        @endif
                    </div>
                </div>
                @if ($failures->isEmpty())
                    <div class="card-body text-body-secondary">{{ $running ? 'Calculating…' : 'No failures.' }}</div>
                @else
                    <div class="table-responsive" style="max-height: 22rem">
                        <table class="table table-sm table-vcenter card-table">
                            <thead class="sticky-top"><tr><th>Model Code</th><th>List</th><th>Reason</th></tr></thead>
                            <tbody>
                                @foreach ($failures as $f)
                                    <tr><td class="text-nowrap">{{ $f->model_code }}</td><td>{{ $f->price_list ?? '—' }}</td><td class="small">{{ $f->message }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer small text-body-secondary">
                        {{ $failedTotal > 200 ? 'First 200 of '.$n($failedTotal).' shown. ' : '' }}Fix the rule or master data named in the reason (Vehicle Info, Insurance or RTO rules), then retry.
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($canManage && $stage === PricingStage::Summary)
        <div class="row g-3 mt-0">
            @if ($counts['failed'] > 0)
                <div class="col-12 col-lg-5">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-wrap align-items-center gap-3">
                            <div class="me-auto small text-body-secondary">{{ $n($counts['failed']) }} vehicle(s) failed. Retry them after fixing the rules.</div>
                            <form action="{{ route('pricing.workflow.retry-failed') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary" @disabled($running)><i class="la la-redo me-1"></i>Retry failed</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
            <div class="col-12 {{ $counts['failed'] > 0 ? 'col-lg-7' : '' }}">
                <form action="{{ route('pricing.workflow.complete') }}" method="POST" class="card h-100"
                      onsubmit="return confirm('Complete process #{{ $session->id }}? The published prices stay; the process closes and a new one can start.');">
                    @csrf
                    <div class="card-body">
                        <h3 class="card-title mb-2">Complete the process</h3>
                        @if ($heldLists !== [])
                            <p class="small text-body-secondary mb-2">Reopen held lists now (quotations and bookings for them unfreeze):</p>
                            <div class="d-flex flex-wrap gap-3 mb-3">
                                @foreach ($heldLists as $list)
                                    <label class="form-check" for="rl-{{ $list }}">
                                        <input class="form-check-input" type="checkbox" name="reopen_lists[]" value="{{ $list }}" id="rl-{{ $list }}" checked>
                                        <span class="form-check-label">{{ $holdLabels[$list] ?? $list }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="small text-body-secondary">No list is on hold.</p>
                        @endif
                        <button type="submit" class="btn btn-success" @disabled($running)><i class="la la-check me-1"></i>Mark complete</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
