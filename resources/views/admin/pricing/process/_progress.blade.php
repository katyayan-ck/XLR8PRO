{{--
    Running-step card for the pricing process screens (DEC-073): shows the queued job's message / error and polls
    pricing.workflow.status every 2 s while the step runs, then reloads the page to show the result.
    @include('admin.pricing.process._progress', ['session' => $session, 'step' => 'prices'])
--}}
@php
    $pp = $session->progress ?? [];
    $ppRunning = ($pp['step'] ?? null) === $step && ($pp['state'] ?? null) === 'running';
    $ppFailed = ($pp['step'] ?? null) === $step && ($pp['state'] ?? null) === 'failed';
@endphp
<div class="card mb-3 {{ $ppRunning || $ppFailed ? '' : 'd-none' }}" data-pp-poll="{{ $ppRunning ? route('pricing.workflow.status', $session->id) : '' }}">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="spinner-border text-primary {{ $ppFailed ? 'd-none' : '' }}" role="status"><span class="visually-hidden">Working…</span></div>
        <div>
            <div class="fw-medium" data-pp-message>{{ $pp['message'] ?? '' }}</div>
            @if ($ppFailed)
                <div class="text-danger small">{{ $pp['error'] ?? 'The step failed.' }}</div>
            @endif
            @if ($ppRunning)
                <div class="text-body-secondary small">Runs in the background — you can leave this page.</div>
            @endif
        </div>
    </div>
</div>

@once
    @push('after_scripts')
    <script>
    (function () {
        document.querySelectorAll('[data-pp-poll]').forEach(function (card) {
            const url = card.getAttribute('data-pp-poll');
            if (!url) {
                return;
            }
            const msg = card.querySelector('[data-pp-message]');
            const timer = setInterval(async function () {
                try {
                    const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) {
                        return;
                    }
                    const p = (await res.json()).progress || {};
                    if (p.message) {
                        msg.textContent = p.message;
                    }
                    if (p.state !== 'running') {
                        clearInterval(timer);
                        window.location.reload();
                    }
                } catch (e) {
                    // transient network error — keep polling
                }
            }, 2000);
        });
    })();
    </script>
    @endpush
@endonce
