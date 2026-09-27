@props(['thread', 'messages' => null, 'docs' => null])
{{-- One WhatsApp conversation (FRS WA-06): text, media docs, poll answers, delivery state. --}}
@php
    $messages ??= \App\Models\Comms\WaMessage::query()->where('thread_id', $thread->id)->latest('id')->limit(100)->get()->reverse()->values();
    $docs ??= \App\Models\Utilities\Docs\Document::query()->whereIn('id', $messages->pluck('doc_id')->filter())->with('media')->get()->keyBy('id');
    $docsService = app(\App\Services\Platform\Docs\DocsService::class);
@endphp
<div class="card-body" style="max-height: 60vh; overflow-y: auto; background: var(--tblr-bg-surface-secondary, #f5f7fb);">
    @forelse ($messages as $m)
        <div class="d-flex mb-2 {{ $m->direction === 'OUT' ? 'justify-content-end' : '' }}">
            <div class="p-2 rounded-3 shadow-sm {{ $m->direction === 'OUT' ? 'bg-green-lt' : 'bg-white' }}" style="max-width: 75%;">
                @if ($m->doc_id && ($doc = $docs[$m->doc_id] ?? null))
                    <x-docs.preview :doc="$docsService->dto($doc)" compact />
                @endif
                @if ($m->text) <div class="text-wrap" style="white-space: pre-line;">{{ $m->text }}</div> @endif
                @if ($m->payload['poll_answer'] ?? null)
                    <div class="small text-green">Poll answer: <strong>{{ $m->payload['poll_answer']['answer'] }}</strong></div>
                @endif
                @if ($m->payload['degraded'] ?? false) <div class="small text-muted">sent as a numbered list (poll not supported by the provider)</div> @endif
                <div class="small text-muted text-end">{{ $m->type !== 'TEXT' ? $m->type.' · ' : '' }}{{ $m->created_at?->format('d M H:i') }}@if ($m->direction === 'OUT') · {{ strtolower($m->status) }}@endif</div>
            </div>
        </div>
    @empty
        <div class="text-muted text-center">No messages yet.</div>
    @endforelse
</div>
