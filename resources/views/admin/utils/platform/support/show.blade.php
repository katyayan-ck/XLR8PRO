@extends(backpack_view('blank'))

@section('title', $title)

{{-- Support request diagnostics on screen (DEC-094, W16e). Everything here was masked before it was stored. --}}
@section('content')
@php
    $f = $bundle['files'];
    $page = ($f['page'] ?? []) + ['route' => $row->route];   // the route is also saved on the request
    $tabs = ['actions' => 'Recent actions', 'network' => 'Network calls', 'errors' => 'Script errors', 'server' => 'Server requests', 'user' => 'User & access'];
@endphp
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="page-pretitle"><a href="{{ route('utils.support.index') }}">{{ __('utils.support.my_requests') }}</a></div>
                <h2 class="page-title">{{ $title }}</h2>
                <div class="text-body-secondary small">
                    {{ $row->requester?->display_name ?? $row->requester?->username }} · {{ site_datetime($row->created_at) }}
                    · {{ __('utils.support.categories.'.$row->category) }}
                </div>
            </div>
            <div class="d-flex gap-2">
                @if ($row->ticket)
                    <a href="{{ route('utils.tickets.show', ['id' => $row->ticket_id]) }}" class="btn btn-outline-primary">{{ $row->ticket->number }}</a>
                @endif
                <a href="{{ route('utils.support.download', ['id' => $row->id]) }}" class="btn btn-outline-secondary">
                    <i class="la la-file-archive me-1" aria-hidden="true"></i>{{ __('utils.support.download_zip') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">Screenshot</h3></div>
                <div class="card-body">
                    @if ($bundle['screenshot'])
                        <a href="{{ $bundle['screenshot'] }}" target="_blank" rel="noopener"><img src="{{ $bundle['screenshot'] }}" alt="Screenshot sent with the request" class="img-fluid border rounded"></a>
                    @else
                        <div class="xl-empty"><i class="la la-image" aria-hidden="true"></i>No screenshot was sent.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title">Page</h3></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        @foreach (['url' => 'URL', 'route' => 'Screen (route)', 'title' => 'Title', 'viewport' => 'Window', 'screen' => 'Screen size', 'browser' => 'Browser', 'theme' => 'Theme', 'time' => 'Time'] as $key => $label)
                            <dt class="col-4">{{ $label }}</dt>
                            <dd class="col-8 text-break">{{ is_scalar($page[$key] ?? null) ? $page[$key] : '—' }}</dd>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                @foreach ($tabs as $key => $label)
                    @php
                        $data = $f[$key] ?? [];
                        $count = $key === 'server' ? count($data['requests'] ?? []) : ($key === 'user' ? null : count((array) $data));
                    @endphp
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" href="#diag-{{ $key }}" role="tab">
                            {{ $label }} @if ($count !== null)<span class="badge bg-secondary-lt ms-1">{{ $count }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body tab-content">
            @foreach ($tabs as $key => $label)
                <div class="tab-pane {{ $loop->first ? 'active show' : '' }}" id="diag-{{ $key }}" role="tabpanel">
                    @php $rows = $key === 'server' ? ($f['server']['requests'] ?? []) : ($f[$key] ?? []); @endphp
                    @if ($key === 'user')
                        <pre class="xl-pre small mb-0">{{ json_encode($f['user'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    @elseif (empty($rows))
                        <div class="text-body-secondary">Nothing recorded.</div>
                    @else
                        @php $cols = collect($rows)->flatMap(fn ($r) => array_keys((array) $r))->unique()->values()->all(); @endphp
                        <div class="table-responsive">
                            <table class="table table-sm table-striped small mb-0">
                                <thead><tr>@foreach ($cols as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                                <tbody>
                                    @foreach ($rows as $r)
                                        <tr @class(['table-danger' => (int) ($r['status'] ?? 0) >= 400])>
                                            @foreach ($cols as $c)
                                                @php $v = $r[$c] ?? null; @endphp
                                                <td class="text-break">{{ is_scalar($v) || $v === null ? ($v === null ? '—' : $v) : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if ($key === 'server' && ! empty($f['server']['log']))
                        <h4 class="mt-3">Log lines of error references</h4>
                        <pre class="xl-pre small mb-0">{{ json_encode($f['server']['log'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
