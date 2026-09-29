@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Pricing process — step 5, add-ons & discounts (DEC-073 / DEC-077). Export Addon-N-Discounts.xlsx for the ticked
    sheets (all ticked), fill, re-upload with a WEF: each ticked sheet replaces its group (queued, recorded for Discard).
--}}
@section('content')
@php
    $progress = $session->progress ?? [];
    $running = ($progress['step'] ?? null) === 'addons' && ($progress['state'] ?? null) === 'running';
    $issues = collect($run['issues'] ?? []);
    $checked = old('groups', array_keys($groups));
    $labels = ['DEALER_CHARGES' => 'Dealer Charges', 'RSA' => 'RSA', 'SHIELD' => 'Shield', 'EXCHANGE' => 'Exchange', 'CORPORATE' => 'Corporate', 'LOYALTY' => 'Loyalty'];
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Process #{{ $session->id }} · Step 4 of 9</div>
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
        @foreach ($labels as $group => $label)
            <div class="col-6 col-md">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-body-secondary small">{{ $label }} — live rows</div>
                        <div class="h2 mb-0 {{ ($presence[$group] ?? 0) === 0 ? 'text-warning' : '' }}">{{ number_format($presence[$group] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <form method="GET" action="{{ route('pricing.workflow.addons-export', $session->id) }}" class="card">
                <div class="card-header"><h3 class="card-title">1 · Download</h3></div>
                <div class="card-body">
                    <p class="text-body-secondary small">Every segment, model, scheme and category appears; cells are blank where nothing is stored.</p>
                    <fieldset class="mb-3">
                        <legend class="form-label">Sheets</legend>
                        <div class="row g-2">
                            @foreach ($labels as $group => $label)
                                <div class="col-12 col-sm-6">
                                    <label class="form-check" for="ad-ex-{{ $group }}">
                                        <input class="form-check-input" type="checkbox" name="groups[]" value="{{ $group }}" id="ad-ex-{{ $group }}" checked>
                                        <span class="form-check-label">{{ $label }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    <button type="submit" class="btn btn-outline-primary"><i class="la la-download me-1"></i>Download Addon-N-Discounts</button>
                </div>
            </form>

            @if ($canManage)
                <form action="{{ route('pricing.workflow.addons') }}" method="POST" enctype="multipart/form-data" class="card mt-3">
                    @csrf
                    <div class="card-header"><h3 class="card-title">2 · Upload</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required" for="xl-ad-file">Addon-N-Discounts workbook (.xlsx)</label>
                            <x-ui.upload name="file" accept=".xlsx" id="xl-ad-file" :max-kb="20480" required />
                        </div>
                        <fieldset class="mb-3">
                            <legend class="form-label required">Replace these groups</legend>
                            <div class="row g-2">
                                @foreach ($labels as $group => $label)
                                    <div class="col-12 col-sm-6">
                                        <label class="form-check" for="ad-im-{{ $group }}">
                                            <input class="form-check-input" type="checkbox" name="groups[]" value="{{ $group }}" id="ad-im-{{ $group }}" @checked(in_array($group, $checked, true))>
                                            <span class="form-check-label">{{ $label }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-hint">A ticked sheet expires its group's live rows at the WEF and inserts the sheet's rows. Blank = no rule, 0 = a zero rule.</div>
                        </fieldset>
                        <div class="mb-3">
                            <label class="form-label required" for="xl-ad-wef">{{ __('pricing.fields.wef_date') }}</label>
                            <x-ui.date name="wef_date" id="xl-ad-wef" :value="old('wef_date', $run['wef'] ?? $session->wef_date)" required />
                        </div>
                        <button type="submit" class="btn btn-primary" @disabled($running)><i class="la la-upload me-1"></i>Import add-ons &amp; discounts</button>
                    </div>
                </form>
            @endif
        </div>

        <div class="col-12 col-lg-7">
            @include('admin.pricing.process._progress', ['session' => $session, 'step' => 'addons'])

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">3 · Result{{ $run ? ' — run '.$run['run'].' · WEF '.site_date($run['wef']) : '' }}</h3>
                    @if ($run)
                        <div class="card-actions text-body-secondary small">{{ site_datetime($run['at'] ?? null, '') }}</div>
                    @endif
                </div>
                @if (! $run)
                    <div class="card-body text-body-secondary">Nothing imported in this process yet — the live rows above are kept if you continue.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>Sheet</th><th class="text-end">Rows</th><th class="text-end">Written</th><th class="text-end">Blank</th><th class="text-end">Duplicates</th><th class="text-end">Rejected</th><th class="text-end">Expired</th></tr></thead>
                            <tbody>
                                @foreach ((array) $run['sheets'] as $group => $s)
                                    <tr>
                                        <td class="fw-medium text-nowrap">{{ $labels[$group] ?? $group }}</td>
                                        <td class="text-end">{{ number_format($s['rows'] ?? 0) }}</td>
                                        <td class="text-end text-green">{{ number_format($s['written'] ?? 0) }}</td>
                                        <td class="text-end text-body-secondary">{{ number_format($s['blank'] ?? 0) }}</td>
                                        <td class="text-end text-body-secondary">{{ number_format($s['duplicates'] ?? 0) }}</td>
                                        <td class="text-end text-danger">{{ number_format($s['rejected'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format($s['expired'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($issues->isNotEmpty())
                        <div class="table-responsive border-top" style="max-height: 20rem">
                            <table class="table table-sm card-table">
                                <thead class="sticky-top"><tr><th>Sheet</th><th>Row</th><th>Reason</th></tr></thead>
                                <tbody>
                                    @foreach ($issues->take(200) as $issue)
                                        <tr><td class="text-nowrap">{{ $issue['sheet'] }}</td><td>{{ $issue['row'] ?: '—' }}</td><td class="small">{{ $issue['reason'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                            <span class="text-body-secondary small me-auto">{{ ($run['issues_count'] ?? $issues->count()) > $issues->count() ? 'First '.$issues->count().' of '.number_format($run['issues_count']).' shown — download for all.' : '' }}</span>
                            <a href="{{ route('pricing.workflow.addons-issues', $session->id) }}" class="btn btn-sm btn-outline-secondary"><i class="la la-download me-1"></i>Download issues</a>
                        </div>
                    @endif
                @endif
            </div>

            @if ($canManage && $session->stage() === \App\Services\Vehicle\Pricing\Session\PricingStage::Addons)
                <div class="card mt-3">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        @php $nothing = ! $run && array_sum($presence) === 0; @endphp
                        <div class="me-auto small {{ $nothing ? 'text-warning' : 'text-body-secondary' }}">{{ $run ? 'Imported in this process.' : ($nothing ? 'Nothing is stored yet — import the workbook first.' : 'Continuing without an import keeps the live add-ons & discounts.') }}</div>
                        <form action="{{ route('pricing.workflow.addons-continue') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success" @disabled($running || $nothing)>Continue to insurance &amp; RTO<i class="la la-arrow-right ms-1"></i></button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
