@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Pricing process — step 6, insurance & RTO (DEC-073 / DEC-078). Two standalone workbooks: a kind with no rules must
    be imported; otherwise keep it, or download → edit → re-import (queued, recorded for Discard).
--}}
@section('content')
@php
    $progress = $session->progress ?? [];
    $running = ($progress['step'] ?? null) === 'rules' && ($progress['state'] ?? null) === 'running';
    $kinds = [
        'insurance' => ['Insurance', 'Insurance Co. (companies per model), Insu Premium (OD / TP heads, IDV slots, add-on rates) and Permit Map.'],
        'rto' => ['RTO', 'One RTO sheet: permit, wheels, registration type, body, GVW, seater, fuel, CC, assessable value, tax basis / slab, surcharge and fees.'],
    ];
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Process #{{ $session->id }} · Step 5 of 9</div>
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

    @include('admin.pricing.process._progress', ['session' => $session, 'step' => 'rules'])

    <div class="row g-3">
        @foreach ($kinds as $kind => [$label, $hint])
            @php
                $run = $runs[$kind] ?? null;
                $stored = $presence[$kind];
                $issues = collect($run['issues'] ?? []);
                $sheets = $kind === 'rto' ? ($run ? ['RTO' => array_diff_key($run, array_flip(['run', 'at', 'wef', 'issues', 'issues_count']))] : []) : ($run['sheets'] ?? []);
            @endphp
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">{{ $label }}</h3>
                        <div class="card-actions">
                            @if ($run)
                                <span class="badge bg-green-lt">Imported · run {{ $run['run'] }}</span>
                            @elseif ($stored)
                                <span class="badge bg-azure-lt">Stored — kept</span>
                            @else
                                <span class="badge bg-red-lt">None stored — import required</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-body-secondary small">{{ $hint }}</p>
                        @if ($stored)
                            <a href="{{ route('pricing.workflow.rules-export', ['kind' => $kind, 'sessionId' => $session->id]) }}" class="btn btn-outline-primary mb-3"><i class="la la-download me-1"></i>Download current {{ $label }} rules</a>
                        @endif

                        @if ($canManage)
                            <form action="{{ route('pricing.workflow.rules') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="kind" value="{{ $kind }}">
                                <div class="mb-3">
                                    <label class="form-label {{ $stored ? '' : 'required' }}" for="xl-{{ $kind }}-file">{{ $label }} workbook (.xlsx)</label>
                                    <x-ui.upload name="file" accept=".xlsx" :id="'xl-'.$kind.'-file'" :max-kb="20480" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required" for="xl-{{ $kind }}-wef">{{ __('pricing.fields.wef_date') }}</label>
                                    <x-ui.date name="wef_date" :id="'xl-'.$kind.'-wef'" :value="old('wef_date', $run['wef'] ?? $session->wef_date)" required />
                                </div>
                                <button type="submit" class="btn btn-primary" @disabled($running)><i class="la la-upload me-1"></i>Import {{ $label }} rules</button>
                            </form>
                        @endif
                    </div>
                    @if ($run)
                        <div class="table-responsive border-top">
                            <table class="table table-sm table-vcenter card-table">
                                <thead><tr><th>Sheet</th><th class="text-end">Rows</th><th class="text-end">Written</th><th class="text-end">Blank</th><th class="text-end">Rejected</th><th class="text-end">Replaced</th></tr></thead>
                                <tbody>
                                    @foreach ($sheets as $sheet => $s)
                                        <tr>
                                            <td class="text-nowrap">{{ \App\Services\Vehicle\Pricing\Import\InsuranceWorkbookService::SHEETS[$sheet] ?? $sheet }}</td>
                                            <td class="text-end">{{ number_format($s['rows'] ?? 0) }}</td>
                                            <td class="text-end text-green">{{ number_format($s['written'] ?? 0) }}</td>
                                            <td class="text-end text-body-secondary">{{ number_format($s['blank'] ?? 0) }}</td>
                                            <td class="text-end text-danger">{{ number_format($s['rejected'] ?? 0) }}</td>
                                            <td class="text-end">{{ number_format($s['expired'] ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($issues->isNotEmpty())
                            <div class="card-body border-top small">
                                @foreach ($issues->take(8) as $issue)
                                    <div class="{{ str_contains($issue['reason'], 'runs backwards') ? 'text-warning' : 'text-danger' }}">{{ $issue['sheet'] }}{{ $issue['row'] ? ' · row '.$issue['row'] : '' }} — {{ $issue['reason'] }}</div>
                                @endforeach
                                <a href="{{ route('pricing.workflow.rules-issues', ['kind' => $kind, 'sessionId' => $session->id]) }}" class="btn btn-sm btn-outline-secondary mt-2"><i class="la la-download me-1"></i>All {{ number_format($run['issues_count'] ?? $issues->count()) }} issue(s)</a>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($canManage && $session->stage() === \App\Services\Vehicle\Pricing\Session\PricingStage::Rules)
        @php $ready = $presence['insurance'] && $presence['rto']; @endphp
        <div class="card mt-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="me-auto small {{ $ready ? 'text-body-secondary' : 'text-warning' }}">
                    {{ $ready ? 'Both rule sets are in place — stored rules are kept unless you import new ones.' : 'Import the missing rule set before continuing.' }}
                </div>
                <form action="{{ route('pricing.workflow.rules-continue') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success" @disabled($running || ! $ready)>Continue to the impact summary<i class="la la-arrow-right ms-1"></i></button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
