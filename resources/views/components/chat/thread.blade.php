@props(['model', 'title' => 'History & remarks', 'filter' => 'combined', 'composer' => true])
{{-- Record timeline (FRS §3): system events + remarks, replies indented, files inline, follow toggle. --}}
@php
    $chat = app(\App\Services\Platform\Chat\ChatService::class);
    $timeline = $chat->timeline($model, backpack_user()?->id)[$filter] ?? [];
    $refType = $chat->refType($model);
    $following = backpack_user() ? $chat->isSubscribed($model, backpack_user()->id) : false;
@endphp
<div class="card chat-thread">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="card-title mb-0"><i class="la la-comments me-1"></i>{{ $title }} <span class="text-muted small ms-1">{{ count($timeline) }}</span></h3>
        @if ($refType)
            <form method="POST" action="{{ route('utils.chat.subscribe') }}" class="mb-0">
                @csrf
                <input type="hidden" name="ref_type" value="{{ $refType }}">
                <input type="hidden" name="ref_id" value="{{ $model->getKey() }}">
                <input type="hidden" name="follow" value="{{ $following ? 0 : 1 }}">
                <button class="btn btn-sm {{ $following ? 'btn-secondary' : 'btn-outline-secondary' }}"><i class="la {{ $following ? 'la-bell-slash' : 'la-bell' }} me-1"></i>{{ $following ? 'Following' : 'Follow' }}</button>
            </form>
        @endif
    </div>
    <div class="card-body xl-scroll-y">
        @if ($timeline === [])
            <div class="xl-empty"><i class="la la-history"></i>No history yet.</div>
        @else
            <div class="xl-timeline">
                @foreach ($timeline as $entry)
                    @php $isEvent = $entry['kind'] === 'EVENT'; @endphp
                    <div class="xl-timeline-item {{ $isEvent ? 'is-event' : 'is-remark' }} {{ $entry['parent_id'] ? 'is-reply' : '' }}">
                        <span class="xl-timeline-avatar"><i class="la {{ $isEvent ? 'la-history' : 'la-comment' }}"></i></span>
                        <div class="xl-timeline-content">
                            <div class="xl-timeline-meta">
                                <strong>{{ $entry['actor_name'] }}</strong>
                                @if ($isEvent && $entry['action_label']) <span class="badge bg-secondary-lt ms-1">{{ $entry['action_label'] }}</span> @endif
                                @if ($entry['is_internal']) <span class="badge bg-warning-lt ms-1">Internal</span> @endif
                                · <time datetime="{{ $entry['time_iso'] }}" title="{{ site_datetime($entry['time_iso']) }}">{{ $entry['time_human'] }}</time>
                                @if ($entry['edited']) · <em>edited</em> @endif
                            </div>
                            @if ($entry['removed'])
                                <div class="xl-bubble fst-italic text-muted">Remark removed</div>
                            @else
                                <div class="xl-bubble">{{ $isEvent ? $entry['title'] : $entry['body'] }}</div>
                                @foreach ($entry['files'] as $file)
                                    <x-docs.preview :doc="$file" compact />
                                @endforeach
                                @if (! $isEvent && ($entry['can_delete'] ?? false))
                                    <form method="POST" action="{{ route('utils.chat.destroy', $entry['id']) }}" class="d-inline" onsubmit="return confirm('Remove this remark?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link btn-sm p-0 text-danger">Remove</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @if ($composer)
        <div class="card-footer">
            <x-chat.composer :model="$model" :allow-internal="backpack_user()?->can('UTL_CHAT_MODERATE')" />
        </div>
    @endif
</div>
