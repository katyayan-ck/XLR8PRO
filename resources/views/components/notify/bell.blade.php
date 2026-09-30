@props(['limit' => 8])
{{--
    Notification centre (FRS NOT-06/07): one bell, combined unread badge (red when an alert is unread),
    tabs for Notifications / Alerts / Messages, mark-read per item and per tab, deep links.
--}}
@php
    $notify = app(\App\Services\Platform\Notify\NotifyService::class);
    $userId = backpack_user()?->id;
    // W7: the bell renders more than once per page (desktop + mobile bars) — counts and lists are read once per request
    $bell = request()->attributes->get('xl.bell.'.$limit);
    if ($bell === null) {
        $bell = ['counts' => $userId ? $notify->counts($userId) : ['notifications' => ['unread' => 0], 'alerts' => ['unread' => 0], 'messages' => ['unread' => 0]], 'rows' => []];
        foreach (['N', 'A', 'M'] as $kindKey) {
            $bell['rows'][$kindKey] = $userId ? $notify->list($userId, $kindKey, 'UNREAD', $limit)->items() : [];
        }
        request()->attributes->set('xl.bell.'.$limit, $bell);
    }
    $counts = $bell['counts'];
    $tabs = [
        'N' => ['Notifications', 'notifications', 'la-bell'],
        'A' => ['Alerts', 'alerts', 'la-exclamation-triangle'],
        'M' => ['Messages', 'messages', 'la-comment'],
    ];
    $total = $counts['notifications']['unread'] + $counts['alerts']['unread'] + $counts['messages']['unread'];
    $firstTab = $counts['alerts']['unread'] ? 'A' : 'N';
@endphp
<div class="dropdown xl-bell" data-first="{{ $firstTab }}">
    <a class="nav-link" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-label="Notifications ({{ $total }} unread)">
        <i class="la la-bell fs-2"></i>
        <span class="xl-bell-badge {{ $counts['alerts']['unread'] ? 'is-alert' : '' }} {{ $total ? '' : 'd-none' }}">{{ $total > 99 ? '99+' : $total }}</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end xl-panel">
        <div class="xl-panel-head">
            <span class="xl-panel-title">Inbox</span>
            <button type="button" class="btn btn-link btn-sm p-0 xl-mark-tab">Mark all read</button>
        </div>
        <div class="xl-panel-tabs" role="tablist">
            @foreach ($tabs as $kind => [$label, $bucket, $icon])
                <button type="button" class="xl-panel-tab {{ $kind === $firstTab ? 'active' : '' }}" data-kind="{{ $kind }}" role="tab" aria-selected="{{ $kind === $firstTab ? 'true' : 'false' }}">
                    {{ $label }} <span class="xl-count" data-count="{{ $kind }}">{{ $counts[$bucket]['unread'] }}</span>
                </button>
            @endforeach
        </div>
        @foreach ($tabs as $kind => [$label, $bucket, $icon])
            @php $rows = $bell['rows'][$kind] ?? []; @endphp
            <div class="xl-panel-list" data-list="{{ $kind }}" @if ($kind !== $firstTab) hidden @endif>
                @forelse ($rows as $row)
                    <div class="xl-panel-item" data-row>
                        <span class="xl-dot"></span>
                        <span class="xl-panel-icon is-{{ $kind }}"><i class="la {{ $icon }}"></i></span>
                        <a href="{{ route('utils.inbox.open', [$kind, $row->id]) }}" class="xl-panel-body text-reset text-decoration-none">
                            <strong>{{ $row->title }}</strong>
                            @if ($row->description) <p>{{ $row->description }}</p> @endif
                            <time datetime="{{ $row->created_at?->toIso8601String() }}" title="{{ site_datetime($row->created_at) }}">{{ $row->created_at?->diffForHumans() }}</time>
                        </a>
                        <button type="button" class="xl-panel-read" data-url="{{ route('utils.inbox.mark', [$kind, $row->id]) }}" aria-label="Mark as read" title="Mark as read"><i class="la la-check"></i></button>
                    </div>
                @empty
                    <div class="xl-panel-empty"><i class="la {{ $icon }}"></i>You're all caught up</div>
                @endforelse
            </div>
        @endforeach
        <a href="{{ route('utils.inbox.index') }}" class="xl-panel-foot border-top">Open inbox</a>
    </div>
</div>

@once
    @push('after_scripts')
        <script>
            (function () {
                const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
                const buckets = {N: 'notifications', A: 'alerts', M: 'messages'};
                function paint(bell, counts) {
                    let total = 0;
                    Object.entries(buckets).forEach(([kind, bucket]) => {
                        const n = counts[bucket].unread; total += n;
                        const el = bell.querySelector(`[data-count="${kind}"]`); if (el) el.textContent = n;
                    });
                    const badge = bell.querySelector('.xl-bell-badge');
                    badge.textContent = total > 99 ? '99+' : total;
                    badge.classList.toggle('d-none', total === 0);
                    badge.classList.toggle('is-alert', counts.alerts.unread > 0);
                }
                function post(url, body) {
                    return fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf()}, body: JSON.stringify(body)}).then(r => r.json());
                }
                document.addEventListener('click', function (e) {
                    const bell = e.target.closest('.xl-bell');
                    if (!bell) return;
                    const tab = e.target.closest('.xl-panel-tab');
                    if (tab) {
                        bell.querySelectorAll('.xl-panel-tab').forEach(t => { t.classList.toggle('active', t === tab); t.setAttribute('aria-selected', t === tab); });
                        bell.querySelectorAll('[data-list]').forEach(l => l.hidden = l.dataset.list !== tab.dataset.kind);
                        return;
                    }
                    const read = e.target.closest('.xl-panel-read');
                    if (read) {
                        e.preventDefault();
                        post(read.dataset.url, {state: 'READ'}).then(res => {
                            const list = read.closest('[data-list]');
                            read.closest('[data-row]').remove();
                            if (!list.querySelector('[data-row]')) list.innerHTML = '<div class="xl-panel-empty"><i class="la la-check-circle"></i>You\'re all caught up</div>';
                            if (res.counts) paint(bell, res.counts);
                        });
                        return;
                    }
                    if (e.target.closest('.xl-mark-tab')) {
                        e.preventDefault();
                        const kind = bell.querySelector('.xl-panel-tab.active').dataset.kind;
                        post(@json(route('utils.inbox.mark-all')), {kind}).then(res => {
                            const list = bell.querySelector(`[data-list="${kind}"]`);
                            list.innerHTML = '<div class="xl-panel-empty"><i class="la la-check-circle"></i>You\'re all caught up</div>';
                            if (res.counts) paint(bell, res.counts);
                        });
                    }
                });
            })();
        </script>
    @endpush
@endonce
