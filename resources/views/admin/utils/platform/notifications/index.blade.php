@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $tabs = ['N' => ['Notifications', 'notifications', 'la-bell'], 'A' => ['Alerts', 'alerts', 'la-exclamation-triangle'], 'M' => ['Messages', 'messages', 'la-envelope']];
@endphp
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <ul class="nav nav-tabs card-header-tabs">
                @foreach ($tabs as $code => [$label, $bucket, $icon])
                    <li class="nav-item">
                        <a class="nav-link {{ $kind === $code ? 'active' : '' }}" href="{{ route('utils.inbox.index', ['kind' => $code, 'state' => $state]) }}">
                            <i class="la {{ $icon }} me-1"></i>{{ $label }}
                            @if ($counts[$bucket]['unread'])
                                <span class="badge bg-red text-white ms-1">{{ $counts[$bucket]['unread'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="d-flex gap-2">
                <div class="btn-group btn-group-sm">
                    @foreach (['ALL' => 'Inbox', 'UNREAD' => 'Unread', 'READ' => 'Read', 'ARCHIVE' => 'Archive'] as $code => $label)
                        <a href="{{ route('utils.inbox.index', ['kind' => $kind, 'state' => $code]) }}" class="btn {{ $state === $code ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('utils.inbox.mark-all') }}">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <button class="btn btn-sm btn-outline-secondary">Mark all read</button>
                </form>
            </div>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($rows as $row)
                <div class="list-group-item d-flex align-items-start gap-3 {{ $row->is_read ? '' : 'bg-primary-lt' }}">
                    <div class="flex-grow-1">
                        <a href="{{ route('utils.inbox.open', [$kind, $row->id]) }}" class="fw-medium text-reset">{{ $row->title }}</a>
                        @if ($row->description)
                            <div class="text-secondary small text-wrap">{{ $row->description }}</div>
                        @endif
                        <div class="small text-muted">
                            {{ $row->created_at?->diffForHumans() }}
                            @if ($row->reference_type)
                                · {{ config('platform.entities.'.$row->reference_type.'.label', $row->reference_type) }} #{{ $row->reference_id }}
                            @endif
                            @if ($kind === 'A' && $row->severity)
                                · <span class="badge bg-{{ $row->severity === 'critical' ? 'red' : 'orange' }}-lt">{{ $row->severity }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        @foreach ($row->archived_at ? ['READ' => 'Restore'] : ($row->is_read ? ['UNREAD' => 'Unread', 'ARCHIVE' => 'Archive'] : ['READ' => 'Read', 'ARCHIVE' => 'Archive']) as $target => $label)
                            <form method="POST" action="{{ route('utils.inbox.mark', [$kind, $row->id]) }}">
                                @csrf
                                <input type="hidden" name="state" value="{{ $target }}">
                                <button class="btn btn-sm btn-outline-secondary">{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-5">Nothing here.</div>
            @endforelse
        </div>
        @if ($rows->hasPages())
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
