@props(['model', 'title' => 'History & remarks', 'filter' => 'combined', 'composer' => true])
@php
    $chat = app(\App\Services\Platform\Chat\ChatService::class);
    $timeline = $chat->timeline($model, backpack_user()?->id)[$filter] ?? [];
    $refType = $chat->refType($model);
    $following = backpack_user() ? $chat->isSubscribed($model, backpack_user()->id) : false;
@endphp
<div class="card chat-thread">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0"><i class="la la-comments me-1"></i>{{ $title }}</h3>
        @if ($refType)
            <form method="POST" action="{{ route('utils.chat.subscribe') }}" class="mb-0">
                @csrf
                <input type="hidden" name="ref_type" value="{{ $refType }}">
                <input type="hidden" name="ref_id" value="{{ $model->getKey() }}">
                <input type="hidden" name="follow" value="{{ $following ? 0 : 1 }}">
                <button class="btn btn-sm {{ $following ? 'btn-secondary' : 'btn-outline-secondary' }}"><i class="la la-eye me-1"></i>{{ $following ? 'Following' : 'Follow' }}</button>
            </form>
        @endif
    </div>
    <div class="card-body" style="max-height: 480px; overflow-y: auto;">
        @forelse ($timeline as $entry)
            <div class="d-flex gap-2 mb-3 {{ $entry['parent_id'] ? 'ms-5' : '' }}">
                <span class="avatar avatar-sm {{ $entry['kind'] === 'EVENT' ? 'bg-secondary-lt' : 'bg-primary-lt' }}">
                    <i class="la {{ $entry['kind'] === 'EVENT' ? 'la-history' : 'la-comment' }}"></i>
                </span>
                <div class="flex-grow-1">
                    <div class="small text-muted">
                        <strong class="text-body">{{ $entry['actor_name'] }}</strong>
                        @if ($entry['kind'] === 'EVENT' && $entry['action_label'])
                            <span class="badge bg-secondary-lt ms-1">{{ $entry['action_label'] }}</span>
                        @endif
                        @if ($entry['is_internal'])
                            <span class="badge bg-warning-lt ms-1">Internal</span>
                        @endif
                        · <span title="{{ $entry['time_iso'] }}">{{ $entry['time_human'] }}</span>
                        @if ($entry['edited'])
                            · <em>edited</em>
                        @endif
                    </div>
                    @if ($entry['removed'])
                        <div class="text-muted fst-italic">Remark removed</div>
                    @else
                        <div class="text-wrap" style="white-space: pre-line;">{{ $entry['kind'] === 'REMARK' ? $entry['body'] : $entry['title'] }}</div>
                        @foreach ($entry['files'] as $file)
                            <x-docs.preview :doc="$file" compact />
                        @endforeach
                        @if ($entry['kind'] === 'REMARK' && ($entry['can_delete'] ?? false))
                            <form method="POST" action="{{ route('utils.chat.destroy', $entry['id']) }}" class="d-inline" onsubmit="return confirm('Remove this remark?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-link btn-sm p-0 text-danger">Remove</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="text-muted text-center py-3">No history yet.</div>
        @endforelse
    </div>
    @if ($composer)
        <div class="card-footer">
            <x-chat.composer :model="$model" :allow-internal="backpack_user()?->can('UTL_CHAT_MODERATE')" />
        </div>
    @endif
</div>
