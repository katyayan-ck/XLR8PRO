@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Pricing process — step 7, impact summary (always shown before any calculation), and step 8, hold check
    (DEC-073 / DEC-079). Figures are live: the session change log plus the current masters.
--}}
@section('content')
@php
    use App\Services\Vehicle\Pricing\Session\PricingStage;
    $stage = $session->stage();
    $calc = $summary['calculate'];
    $groupLabels = ['DEALER_CHARGES' => 'Dealer charges', 'RSA' => 'RSA', 'SHIELD' => 'Shield', 'EXCHANGE' => 'Exchange', 'CORPORATE' => 'Corporate'];
    $listLabels = ['PV' => 'PV', 'CV' => 'CV', 'BEV' => 'BEV', 'LMM' => 'LMM', 'LMM_TZU' => 'LMM TZU', 'CSD' => 'CSD (channel)', 'TAXI' => 'Taxi (Passenger)', 'UNLISTED' => 'No list recorded'];
    $n = fn ($v) => number_format((int) $v);
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Process #{{ $session->id }} · Step {{ $stage === PricingStage::Impact ? '6' : '7' }} of 9</div>
            <h2 class="mb-0">{{ $stage === PricingStage::Impact ? $title : 'Hold Check' }}</h2>
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
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
            <div class="text-body-secondary small">Vehicles to calculate</div><div class="h1 mb-0 text-primary">{{ $n($calc['vehicles']) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
            <div class="text-body-secondary small">≈ Price snapshots</div><div class="h1 mb-0">{{ $n($calc['snapshots']) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
            <div class="text-body-secondary small">Skipped — incomplete</div><div class="h1 mb-0 text-warning">{{ $n($summary['skipped']['incomplete']) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card card-sm"><div class="card-body">
            <div class="text-body-secondary small">On hold</div><div class="h1 mb-0 {{ $calc['held'] ? 'text-warning' : 'text-body-secondary' }}">{{ $calc['held'] ? implode(', ', $calc['held']) : 'None' }}</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Will calculate, per list</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>List</th><th class="text-end">Active vehicles with a live price</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($calc['lists'] as $list => $row)
                                <tr>
                                    <td class="fw-medium">{{ $listLabels[$list] ?? $list }}</td>
                                    <td class="text-end">{{ $n($row['vehicles']) }}</td>
                                    <td>
                                        @if ($row['held'])
                                            <span class="badge bg-yellow-lt">On hold — skipped</span>
                                        @elseif ($row['vehicles'] === 0)
                                            <span class="badge bg-secondary-lt">Nothing to calculate</span>
                                        @else
                                            <span class="badge bg-green-lt">Will calculate</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-body-secondary d-flex flex-wrap gap-3 align-items-center">
                    <span>Also skipped: {{ $n($summary['skipped']['inactive']) }} inactive / discontinued · {{ $n($summary['skipped']['no_price']) }} Active without a price.</span>
                    @if ($summary['skipped']['incomplete'] > 0)
                        <a href="{{ route('pricing.workflow.impact-incomplete', $session->id) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="la la-download me-1"></i>Incomplete vehicles</a>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Vehicles</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6"><div class="text-body-secondary small">New in this process</div><div class="h2 mb-0">{{ $n($summary['vehicles']['new']) }}</div></div>
                        <div class="col-6"><div class="text-body-secondary small">Made Active in this process</div><div class="h2 mb-0 text-green">{{ $n($summary['vehicles']['activated']) }}</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Price changes</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Channel</th><th class="text-end">New</th><th class="text-end">Up</th><th class="text-end">Down</th><th class="text-end">Other change</th></tr></thead>
                        <tbody>
                            @foreach (['normal' => 'Normal', 'csd' => 'CSD'] as $channel => $label)
                                @php $p = $summary['prices'][$channel]; @endphp
                                <tr>
                                    <td class="fw-medium">{{ $label }}</td>
                                    <td class="text-end">{{ $n($p['new']) }}</td>
                                    <td class="text-end text-red">{{ $n($p['up']) }}</td>
                                    <td class="text-end text-green">{{ $n($p['down']) }}</td>
                                    <td class="text-end">{{ $n($p['other']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-body-secondary">{{ $n($summary['prices']['unchanged']) }} price(s) re-imported unchanged. "Other change" = schemes or discounts changed, ex-showroom the same.</div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Add-ons, discounts &amp; rules</h3></div>
                <div class="list-group list-group-flush">
                    @foreach ($summary['addons'] as $group => $a)
                        <div class="list-group-item d-flex justify-content-between">
                            <span>{{ $groupLabels[$group] ?? $group }}</span>
                            <span class="text-body-secondary small">{{ isset($a['kept']) ? 'Kept ('.$n($a['kept']).' live rows)' : 'Replaced: '.$n($a['written']).' written, '.$n($a['replaced']).' expired' }}</span>
                        </div>
                    @endforeach
                    @foreach (['insurance' => 'Insurance rules', 'rto' => 'RTO rules'] as $kind => $label)
                        @php $r = $summary['rules'][$kind]; @endphp
                        <div class="list-group-item d-flex justify-content-between">
                            <span>{{ $label }}</span>
                            <span class="text-body-secondary small">{{ isset($r['kept']) ? 'Kept' : 'Imported (WEF '.site_date($r['wef'] ?? null).')' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if ($canManage && $stage === PricingStage::Impact)
        <div class="card mt-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="me-auto small text-body-secondary">Review the figures above. Nothing is calculated until you confirm at the hold check.</div>
                <form action="{{ route('pricing.workflow.impact-continue') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success">Reviewed — continue to hold check<i class="la la-arrow-right ms-1"></i></button>
                </form>
            </div>
        </div>
    @endif

    @if ($canManage && $stage === PricingStage::HoldCheck)
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Hold check</h3></div>
            <form action="{{ route('pricing.workflow.hold-check') }}" method="POST" class="card-body">
                @csrf
                <p class="text-body-secondary small">A held list is not calculated; quotations and bookings for its vehicles stay frozen until it is reopened.</p>
                <div class="row g-2 mb-3">
                    @foreach ($holdLists as $code => $label)
                        <div class="col-6 col-md-3">
                            <label class="form-check" for="hc-{{ $code }}">
                                <input class="form-check-input" type="checkbox" name="hold_lists[]" value="{{ $code }}" id="hc-{{ $code }}">
                                <span class="form-check-label">{{ $label }} @if (in_array($code, $calc['held'], true))<span class="badge bg-yellow-lt ms-1">held</span>@endif</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="action" value="hold" class="btn btn-outline-warning"><i class="la la-pause me-1"></i>Hold selected</button>
                    <button type="submit" name="action" value="reopen" class="btn btn-outline-secondary"><i class="la la-play me-1"></i>Reopen selected</button>
                </div>
            </form>
            <div class="card-footer d-flex flex-wrap align-items-center gap-3">
                <div class="me-auto small text-body-secondary">{{ $n($calc['vehicles']) }} vehicle(s) will be calculated and published.</div>
                @if (Route::has('pricing.workflow.calculate-start'))
                    <form action="{{ route('pricing.workflow.calculate-start') }}" method="POST" onsubmit="return confirm('Calculate and publish on-road prices now? After publishing this process can no longer be discarded.');">
                        @csrf
                        <button type="submit" class="btn btn-primary" @disabled($calc['vehicles'] === 0)><i class="la la-calculator me-1"></i>Calculate &amp; publish</button>
                    </form>
                @else
                    <span class="badge bg-secondary-lt">Calculate &amp; publish arrives in the next phase</span>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
