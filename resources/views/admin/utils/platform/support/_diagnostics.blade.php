{{--
    Support-request diagnostics on the ticket page (DEC-094, W16e; owner 03-10): included only for the support team
    (SupportRequestService::canViewDiagnostics — support admins, owner, assignees, snoopers; never the requester).
    Everything here was masked before it was stored. Needs $supportRequest and $diagnostics ({files, screenshot} or null).
--}}
@php
    $f = $diagnostics['files'] ?? [];
    $page = ($f['page'] ?? []) + ['route' => $supportRequest->route];
    $tabs = ['actions' => 'Actions', 'network' => 'Network', 'errors' => 'Errors', 'server' => 'Server', 'user' => 'User'];
@endphp
<div class="card mb-3" data-xl-diagnostics>
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="la la-stethoscope me-1" aria-hidden="true"></i>{{ __('utils.support.diagnostics') }}</h3>
        @if ($diagnostics)
            <a href="{{ route('utils.support.download', ['id' => $supportRequest->id]) }}" class="btn btn-sm btn-outline-secondary"
                title="{{ __('utils.support.download_zip') }}" aria-label="{{ __('utils.support.download_zip') }}"><i class="la la-file-archive" aria-hidden="true"></i></a>
        @endif
    </div>
    @if (! $diagnostics)
        <div class="card-body small text-body-secondary">{{ $supportRequest->purged_at ? __('utils.support.bundle_gone') : __('utils.support.no_diagnostics') }}</div>
    @else
        <div class="card-body small">
            <div class="text-body-secondary mb-2">{{ __('utils.support.team_only') }}</div>
            @if ($diagnostics['screenshot'])
                <a href="{{ $diagnostics['screenshot'] }}" target="_blank" rel="noopener"><img src="{{ $diagnostics['screenshot'] }}" alt="Screenshot sent with the request" class="img-fluid border rounded mb-2"></a>
            @endif
            <dl class="row mb-2">
                @foreach (['route' => 'Screen', 'url' => 'URL', 'viewport' => 'Window', 'browser' => 'Browser', 'time' => 'Time'] as $key => $label)
                    <dt class="col-4">{{ $label }}</dt>
                    <dd class="col-8 text-break">{{ is_scalar($page[$key] ?? null) ? $page[$key] : '—' }}</dd>
                @endforeach
            </dl>
            <ul class="nav nav-tabs nav-tabs-alt small" role="tablist">
                @foreach ($tabs as $key => $label)
                    @php
                        $data = $f[$key] ?? [];
                        $count = $key === 'server' ? count($data['requests'] ?? []) : ($key === 'user' ? null : count((array) $data));
                    @endphp
                    <li class="nav-item" role="presentation">
                        <a class="nav-link px-2 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" href="#diag-{{ $key }}" role="tab">
                            {{ $label }}@if ($count !== null) <span class="badge bg-secondary-lt">{{ $count }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content pt-2">
                @foreach ($tabs as $key => $label)
                    <div class="tab-pane {{ $loop->first ? 'active show' : '' }}" id="diag-{{ $key }}" role="tabpanel">
                        @php $rows = $key === 'server' ? ($f['server']['requests'] ?? []) : ($f[$key] ?? []); @endphp
                        @if ($key === 'user')
                            <pre class="xl-pre mb-0">{{ json_encode($f['user'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @elseif (empty($rows))
                            <div class="text-body-secondary">Nothing recorded.</div>
                        @else
                            <div class="list-group list-group-flush xl-scroll-y">
                                @foreach (array_reverse($rows) as $r)
                                    <div @class(['list-group-item px-0 py-1', 'text-danger' => (int) ($r['status'] ?? 0) >= 400])>
                                        @foreach ((array) $r as $k => $v)
                                            <span class="text-body-secondary">{{ $k }}:</span>
                                            <span class="text-break">{{ is_scalar($v) || $v === null ? ($v ?? '—') : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</span>@if (! $loop->last) · @endif
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if ($key === 'server' && ! empty($f['server']['log']))
                            <div class="fw-bold mt-2">Log lines of error references</div>
                            <pre class="xl-pre mb-0">{{ json_encode($f['server']['log'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
