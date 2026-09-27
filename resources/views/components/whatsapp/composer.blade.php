@props(['thread'])
{{-- Agent composer (FRS §15.7): free-form only inside the session window, else an approved template. --}}
@php
    $open = $thread->sessionOpen();
    $templates = \App\Models\Comms\CommTemplate::query()->where('channel', 'WHATSAPP')->whereHas('active')->orderBy('code')->pluck('name', 'code');
@endphp
<div class="card-footer">
    @if (! $open)
        <div class="alert alert-warning py-2 small mb-2">The 24-hour window is closed — only an approved template can be sent.</div>
    @else
        <div class="small text-muted mb-2">Session open until {{ site_datetime($thread->session_expires_at) }}.</div>
    @endif
    <ul class="nav nav-tabs mb-2" role="tablist">
        @if ($open)
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#wa-text">Message</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#wa-poll">Poll</a></li>
        @endif
        <li class="nav-item"><a class="nav-link {{ $open ? '' : 'active' }}" data-bs-toggle="tab" href="#wa-template">Template</a></li>
    </ul>
    <div class="tab-content">
        @if ($open)
            <form id="wa-text" class="tab-pane active" method="POST" action="{{ route('utils.whatsapp.send', $thread->id) }}">
                @csrf <input type="hidden" name="mode" value="TEXT">
                <div class="d-flex gap-2"><textarea name="text" rows="2" maxlength="4000" required class="form-control" placeholder="Type a reply"></textarea><button class="btn btn-success">Send</button></div>
            </form>
            <form id="wa-poll" class="tab-pane" method="POST" action="{{ route('utils.whatsapp.send', $thread->id) }}">
                @csrf <input type="hidden" name="mode" value="POLL">
                <input type="text" name="question" maxlength="250" required class="form-control mb-2" placeholder="Question">
                <div class="d-flex gap-2"><textarea name="options" rows="3" required class="form-control" placeholder="One option per line"></textarea><button class="btn btn-success">Send poll</button></div>
            </form>
        @endif
        <form id="wa-template" class="tab-pane {{ $open ? '' : 'active' }}" method="POST" action="{{ route('utils.whatsapp.send', $thread->id) }}">
            @csrf <input type="hidden" name="mode" value="TEMPLATE">
            <div class="d-flex gap-2">
                <select name="template" class="form-select" required>
                    @foreach ($templates as $code => $name) <option value="{{ $code }}">{{ $name }} ({{ $code }})</option> @endforeach
                </select>
                <input type="text" name="vars_json" class="form-control" placeholder='{"title":"…","body":"…"}'>
                <button class="btn btn-success" @disabled($templates->isEmpty())>Send template</button>
            </div>
        </form>
    </div>
</div>
