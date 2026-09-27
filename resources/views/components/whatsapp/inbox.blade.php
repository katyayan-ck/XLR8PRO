@props(['threads' => null, 'box' => 'QUEUE', 'active' => null])
{{-- WhatsApp agent inbox (FRS §15.3). --}}
@php
    $threads ??= app(\App\Services\Platform\Comms\WhatsAppService::class)->inbox(backpack_user()->id, $box);
    $contacts = app(\App\Services\Platform\Comms\ContactService::class);
    $labels = ['MINE' => 'Mine', 'QUEUE' => 'Unassigned', 'ALL' => 'All', 'DONE' => 'Done'];
@endphp
<div class="card">
    <div class="card-header">
        <ul class="nav nav-pills">
            @foreach ($labels as $code => $label)
                <li class="nav-item"><a class="nav-link py-1 px-2 {{ $box === $code ? 'active' : '' }}" href="{{ route('utils.whatsapp.index', ['box' => $code]) }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    </div>
    <div class="list-group list-group-flush xl-scroll-y">
        @forelse ($threads as $t)
            <a href="{{ route('utils.whatsapp.show', ['threadId' => $t->id, 'box' => $box]) }}" class="list-group-item list-group-item-action {{ $active === $t->id ? 'active' : '' }}">
                <div class="d-flex justify-content-between">
                    <strong>{{ $t->title ?? ($t->person_code ?? $contacts->mask('+'.$t->wa_id)) }}</strong>
                    @if ($t->unread) <span class="badge bg-green text-white">{{ $t->unread }}</span> @endif
                </div>
                <div class="small {{ $active === $t->id ? '' : 'text-muted' }}">
                    {{ $t->label }} · {{ $t->last_message_at?->diffForHumans() ?? 'no messages' }}
                    @if ($t->sessionOpen()) · <i class="la la-comment-dots" title="Session open"></i> @endif
                    @if ($t->ref_type) · {{ $t->ref_type }} #{{ $t->ref_id }} @endif
                </div>
            </a>
        @empty
            <div class="list-group-item text-muted">No conversations.</div>
        @endforelse
    </div>
    @if ($threads->hasPages()) <div class="card-footer">{{ $threads->links() }}</div> @endif
</div>
