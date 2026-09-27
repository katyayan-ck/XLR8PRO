@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $envelope = backpack_user()->can('UTL_COMM_SEND') ? (array) $row->envelope : $row->publicEnvelope();
    $mask = fn ($list) => collect((array) $list)->map(fn ($a) => $contacts->mask($a))->implode(', ');
@endphp
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">{{ $row->channel }} #{{ $row->id }} <span class="badge bg-secondary-lt">{{ $row->status }}</span></h3>
                    @can('UTL_COMM_SEND')
                        <form method="POST" action="{{ route('utils.comms.outbox.resend', $row->id) }}" onsubmit="return confirm('Send the same message again?')">
                            @csrf <button class="btn btn-sm btn-outline-primary" @disabled($row->template_code === 'otp.sms')><i class="la la-redo"></i> Resend</button>
                        </form>
                    @endcan
                </div>
                <div class="card-body">
                    <dl class="row small">
                        <dt class="col-3">To</dt><dd class="col-9">{{ $row->channel === 'EMAIL' ? $mask($envelope['to'] ?? [$row->to_address]) : $contacts->mask($row->to_address) }} @if ($row->to_person_code) ({{ $row->to_person_code }}) @endif</dd>
                        @if (! empty($envelope['cc'])) <dt class="col-3">Cc</dt><dd class="col-9">{{ $mask($envelope['cc']) }}</dd> @endif
                        @if (! empty($envelope['bcc'])) <dt class="col-3">Bcc</dt><dd class="col-9">{{ $mask($envelope['bcc']) }}</dd> @endif
                        @if (! empty($envelope['from'])) <dt class="col-3">From</dt><dd class="col-9">{{ $envelope['from'] }}</dd> @endif
                        @if (! empty($envelope['header'])) <dt class="col-3">Header</dt><dd class="col-9">{{ $envelope['header'] }}</dd> @endif
                        <dt class="col-3">Template</dt><dd class="col-9">{{ $row->template_code ? $row->template_code.' v'.$row->template_version : 'raw' }} · {{ $row->category }}</dd>
                        <dt class="col-3">Driver</dt><dd class="col-9">{{ $row->driver }} · attempts {{ $row->attempts }} · provider id {{ $row->provider_message_id ?? '—' }}</dd>
                        <dt class="col-3">Timing</dt><dd class="col-9">queued {{ site_datetime($row->created_at) }} · sent {{ site_datetime($row->sent_at, '—') }} · delivered {{ site_datetime($row->delivered_at, '—') }}</dd>
                        @if ($row->ref_type) <dt class="col-3">Record</dt><dd class="col-9">{{ $row->ref_type }} #{{ $row->ref_id }}</dd> @endif
                        @if ($row->error) <dt class="col-3">Error</dt><dd class="col-9 text-danger">{{ $row->error }}</dd> @endif
                        <dt class="col-3">Idempotency</dt><dd class="col-9"><code class="small">{{ $row->idempotency_key }}</code></dd>
                    </dl>
                    @if ($row->subject) <div class="fw-medium mb-1">{{ $row->subject }}</div> @endif
                    <pre class="border rounded p-2 small xl-pre">{{ $row->body_preview }}</pre>
                    <div class="small text-muted">Preview with personal data masked. The full snapshot is stored encrypted for resend.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Sends of this message</h3></div>
                <div class="list-group list-group-flush">
                    @foreach ($copies as $c)
                        <a href="{{ route('utils.comms.outbox.show', $c->id) }}" class="list-group-item {{ $c->id === $row->id ? 'active' : '' }}">#{{ $c->id }} · {{ $c->status }} · {{ $c->driver }}</a>
                    @endforeach
                </div>
            </div>
            @if ($sandbox)
                <div class="card"><div class="card-body small">Delivered to the <strong>sandbox</strong> ({{ $sandbox->driver }}) — no real recipient received it.</div></div>
            @endif
        </div>
    </div>
</div>
@endsection
