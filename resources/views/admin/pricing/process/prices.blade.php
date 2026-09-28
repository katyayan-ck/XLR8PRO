@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Pricing process — step 4, price import (DEC-073 / DEC-076). The workbook from Start or an updated one, the lists and
    the WEF; queued and recorded for Discard; per-list summary (new / updated / unchanged / skipped) and row issues.
--}}
@section('content')
@php
    $progress = $session->progress ?? [];
    $running = ($progress['step'] ?? null) === 'prices' && ($progress['state'] ?? null) === 'running';
    $issues = collect($run['issues'] ?? []);
    $checked = old('lists', $selected);
    $source = old('source', 'session');
@endphp
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Process #{{ $session->id }} · Step 3 of 9</div>
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

    <div class="row g-3">
        @if ($canManage)
            <div class="col-12 col-lg-5">
                <form action="{{ route('pricing.workflow.prices') }}" method="POST" enctype="multipart/form-data" class="card">
                    @csrf
                    <div class="card-header"><h3 class="card-title">Import prices</h3></div>
                    <div class="card-body">
                        <fieldset class="mb-3">
                            <legend class="form-label required">{{ __('pricing.fields.source') }}</legend>
                            <label class="form-check" for="pr-src-session">
                                <input class="form-check-input" type="radio" name="source" value="session" id="pr-src-session" @checked($source === 'session')>
                                <span class="form-check-label">The one uploaded at Start <span class="text-body-secondary">({{ $session->source_filename }})</span></span>
                            </label>
                            <label class="form-check" for="pr-src-upload">
                                <input class="form-check-input" type="radio" name="source" value="upload" id="pr-src-upload" @checked($source === 'upload')>
                                <span class="form-check-label">An updated Pricing workbook</span>
                            </label>
                            <div class="mt-2 {{ $source === 'upload' ? '' : 'd-none' }}" id="pr-upload">
                                <label class="form-label" for="xl-pr-file">{{ __('pricing.fields.file') }} (.xlsx)</label>
                                <x-ui.upload name="file" accept=".xlsx" id="xl-pr-file" :max-kb="20480" />
                            </div>
                        </fieldset>

                        <fieldset class="mb-3">
                            <legend class="form-label required">{{ __('pricing.fields.lists') }}</legend>
                            <div class="row g-2">
                                @foreach ($lists as $list)
                                    <div class="col-12 col-sm-6">
                                        <label class="form-check" for="pr-list-{{ $list }}">
                                            <input class="form-check-input" type="checkbox" name="lists[]" value="{{ $list }}" id="pr-list-{{ $list }}" @checked(in_array($list, $checked, true))>
                                            <span class="form-check-label">{{ __("pricing.lists.$list") }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="mb-3">
                            <label class="form-label required" for="xl-pr-wef">{{ __('pricing.fields.wef_date') }}</label>
                            <x-ui.date name="wef_date" id="xl-pr-wef" :value="old('wef_date', $run['wef'] ?? $session->wef_date)" required />
                            <div class="form-hint">Same WEF as a live price updates it; a new WEF with a changed price closes the old one. Incomplete vehicles are skipped.</div>
                        </div>
                        <button type="submit" class="btn btn-primary" @disabled($running)><i class="la la-file-import me-1"></i>Import prices</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="col-12 {{ $canManage ? 'col-lg-7' : '' }}">
            @include('admin.pricing.process._progress', ['session' => $session, 'step' => 'prices'])

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Result{{ $run ? ' — run '.$run['run'].' · WEF '.site_date($run['wef']) : '' }}</h3>
                    @if ($run)
                        <div class="card-actions text-body-secondary small">{{ site_datetime($run['at'] ?? null, '') }}</div>
                    @endif
                </div>
                @if (! $run)
                    <div class="card-body text-body-secondary">No prices imported in this process yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr><th>List</th><th class="text-end">Codes</th><th class="text-end">New</th><th class="text-end">Updated</th><th class="text-end">Unchanged</th><th class="text-end">Incomplete</th><th class="text-end">Other skipped</th></tr>
                            </thead>
                            <tbody>
                                @foreach ((array) $run['sheets'] as $sheet => $s)
                                    <tr>
                                        <td class="fw-medium text-nowrap">{{ $sheet }}</td>
                                        <td class="text-end">{{ number_format($s['rows'] ?? 0) }}</td>
                                        <td class="text-end text-green">{{ number_format($s['inserted'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format($s['updated'] ?? 0) }}</td>
                                        <td class="text-end text-body-secondary">{{ number_format($s['unchanged'] ?? 0) }}</td>
                                        <td class="text-end text-warning">{{ number_format($s['skipped_incomplete'] ?? 0) }}</td>
                                        <td class="text-end text-danger">{{ number_format(($s['skipped_unknown'] ?? 0) + ($s['no_price'] ?? 0) + ($s['conflicts'] ?? 0) + ($s['rejected'] ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($issues->isNotEmpty())
                        <div class="card-body border-top pb-0"><h4 class="mb-2">Issues</h4></div>
                        <div class="table-responsive" style="max-height: 20rem">
                            <table class="table table-sm card-table">
                                <thead class="sticky-top"><tr><th>List</th><th>Model Code</th><th>Reason</th></tr></thead>
                                <tbody>
                                    @foreach ($issues->take(200) as $issue)
                                        <tr><td class="text-nowrap">{{ $issue['sheet'] }}</td><td class="text-nowrap">{{ $issue['code'] }}</td><td class="small">{{ $issue['reason'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                            <span class="text-body-secondary small me-auto">{{ ($run['issues_count'] ?? $issues->count()) > $issues->count() ? 'First '.$issues->count().' of '.number_format($run['issues_count']).' shown — download for all.' : '' }}</span>
                            <a href="{{ route('pricing.workflow.prices-issues', $session->id) }}" class="btn btn-sm btn-outline-secondary"><i class="la la-download me-1"></i>Download issues</a>
                        </div>
                    @endif
                @endif
            </div>

            @if ($canManage && $run && $session->stage() === \App\Services\Vehicle\Pricing\Session\PricingStage::Prices)
                <div class="card mt-3">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <div class="me-auto small text-body-secondary">No on-road prices are calculated yet — that happens at Calculate &amp; Publish.</div>
                        <form action="{{ route('pricing.workflow.prices-continue') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success" @disabled($running)>Continue to add-ons<i class="la la-arrow-right ms-1"></i></button>
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
    document.querySelectorAll('input[name="source"]').forEach(function (el) {
        el.addEventListener('change', function () {
            document.getElementById('pr-upload').classList.toggle('d-none', el.value !== 'upload' || !el.checked);
        });
    });
})();
</script>
@endpush
