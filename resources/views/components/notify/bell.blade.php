@props(['kind' => 'N', 'icon' => 'la-bell', 'color' => 'warning', 'label' => 'Notifications', 'limit' => 6])
@php
    $notify = app(\App\Services\Platform\Notify\NotifyService::class);
    $userId = backpack_user()?->id;
    $bucket = ['N' => 'notifications', 'A' => 'alerts', 'M' => 'messages'][$kind];
    $unread = $userId ? $notify->counts($userId)[$bucket]['unread'] : 0;
    $rows = $userId ? $notify->list($userId, $kind, 'UNREAD', $limit)->items() : [];
@endphp
<div class="dropdown notify-bell" data-kind="{{ $kind }}">
    <a class="nav-link position-relative p-1" data-bs-toggle="dropdown" href="#" role="button" title="{{ $label }}">
        <i class="la {{ $icon }} fs-3"></i>
        <span class="notify-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-{{ $color }} text-white {{ $unread ? '' : 'd-none' }}">{{ $unread > 99 ? '99+' : $unread }}</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0 rounded-3" style="width: 340px;">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-{{ $color }} text-white">
            <strong><i class="la {{ $icon }} me-1"></i>{{ $label }} (<span class="notify-count">{{ $unread }}</span> unread)</strong>
            @if ($unread)
                <button type="button" class="btn btn-sm btn-light px-2 py-0 notify-mark-all">Mark all read</button>
            @endif
        </div>
        <div class="py-1" style="max-height: 280px; overflow-y: auto;">
            @forelse ($rows as $row)
                <div class="dropdown-item d-flex align-items-start gap-2 px-3 py-2 notify-row">
                    <a href="{{ route('utils.inbox.open', [$kind, $row->id]) }}" class="flex-grow-1 text-reset text-decoration-none">
                        <div class="fw-medium text-wrap">{{ $row->title }}</div>
                        @if ($row->description)
                            <div class="small text-wrap text-secondary">{{ \Illuminate\Support\Str::limit($row->description, 90) }}</div>
                        @endif
                        <small class="text-muted">{{ $row->created_at?->diffForHumans() }}</small>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary px-2 py-0 notify-mark" data-url="{{ route('utils.inbox.mark', [$kind, $row->id]) }}">Read</button>
                </div>
            @empty
                <div class="text-center text-muted py-4 small">Nothing unread</div>
            @endforelse
        </div>
        <div class="px-3 py-2 border-top">
            <a href="{{ route('utils.inbox.index', ['kind' => $kind]) }}" class="btn btn-sm btn-light w-100">See all</a>
        </div>
    </div>
</div>

@once
    @push('after_scripts')
        <script>
            document.addEventListener('click', function (e) {
                const one = e.target.closest('.notify-mark');
                const all = e.target.closest('.notify-mark-all');
                if (!one && !all) { return; }
                e.preventDefault();
                e.stopPropagation();
                const bell = e.target.closest('.notify-bell');
                const url = one ? one.dataset.url : @json(route('utils.inbox.mark-all'));
                const body = one ? {state: 'READ'} : {kind: bell.dataset.kind};
                fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                    body: JSON.stringify(body),
                }).then(r => r.json()).then(function (res) {
                    const bucket = {N: 'notifications', A: 'alerts', M: 'messages'}[bell.dataset.kind];
                    const unread = res.counts ? res.counts[bucket].unread : 0;
                    if (one) { one.closest('.notify-row').remove(); } else { bell.querySelectorAll('.notify-row').forEach(r => r.remove()); }
                    bell.querySelector('.notify-count').textContent = unread;
                    const badge = bell.querySelector('.notify-badge');
                    badge.textContent = unread > 99 ? '99+' : unread;
                    badge.classList.toggle('d-none', unread === 0);
                });
            });
        </script>
    @endpush
@endonce
