@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php $statusColor = ['QUEUED' => 'blue', 'RETRY' => 'orange', 'SENT' => 'cyan', 'DELIVERED' => 'green', 'READ' => 'green', 'FAILED' => 'red', 'BOUNCED' => 'red', 'SUPPRESSED' => 'secondary']; @endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ($stats as $status => $n)
            <span class="badge bg-{{ $statusColor[$status] ?? 'secondary' }}-lt fs-5">{{ $status }} {{ $n }}</span>
        @endforeach
        <span class="text-muted small align-self-center">last 7 days</span>
    </div>
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item"><a class="nav-link {{ $tab === 'outbox' ? 'active' : '' }}" href="{{ route('utils.comms.outbox') }}">Outbox</a></li>
                <li class="nav-item"><a class="nav-link {{ $tab === 'sandbox' ? 'active' : '' }}" href="{{ route('utils.comms.outbox', ['tab' => 'sandbox']) }}">Sandbox (not delivered to anyone)</a></li>
            </ul>
        </div>
        @if ($tab === 'outbox')
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('utils.comms.outbox') }}" class="xl-toolbar">
                    <select name="channel" class="form-select form-select-sm"><option value="">Any channel</option>
                        @foreach (['EMAIL', 'SMS', 'WHATSAPP'] as $c) <option value="{{ $c }}" @selected(($filters['channel'] ?? '') === $c)>{{ $c }}</option> @endforeach
                    </select>
                    <select name="status" class="form-select form-select-sm"><option value="">Any status</option>
                        @foreach (array_keys($statusColor) as $s) <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option> @endforeach
                    </select>
                    <input type="text" name="template" value="{{ $filters['template'] ?? '' }}" class="form-control form-control-sm" placeholder="Template code">
                    <input type="text" name="person" value="{{ $filters['person'] ?? '' }}" class="form-control form-control-sm" placeholder="Person code">
                    <input type="text" name="ref_type" value="{{ $filters['ref_type'] ?? '' }}" class="form-control form-control-sm" placeholder="Ref type">
                    <input type="text" name="ref_id" value="{{ $filters['ref_id'] ?? '' }}" class="form-control form-control-sm" placeholder="Ref id">
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Address or subject">
                    <button class="btn btn-sm btn-outline-primary">Filter</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>#</th><th>Channel</th><th>To</th><th>Subject / preview</th><th>Template</th><th>Status</th><th>When</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td><a href="{{ route('utils.comms.outbox.show', $r->id) }}">{{ $r->id }}</a></td>
                                <td>{{ $r->channel }} <div class="small text-muted">{{ $r->driver }}</div></td>
                                <td class="small">{{ $contacts->mask($r->to_address) }}@if ($r->to_person_code)<div class="text-muted">{{ $r->to_person_code }}</div>@endif</td>
                                <td class="small text-wrap xl-cell-wide">{{ $r->subject ? $r->subject.' — ' : '' }}{{ \Illuminate\Support\Str::limit($r->body_preview, 120) }}</td>
                                <td class="small">{{ $r->template_code ? $r->template_code.' v'.$r->template_version : 'raw' }}</td>
                                <td><span class="badge bg-{{ $statusColor[$r->status] ?? 'secondary' }}-lt">{{ $r->status }}</span>@if ($r->error)<div class="small text-danger text-wrap">{{ \Illuminate\Support\Str::limit($r->error, 60) }}</div>@endif</td>
                                <td class="small">{{ $r->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Nothing sent yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages()) <div class="card-footer">{{ $rows->links() }}</div> @endif
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>#</th><th>Channel</th><th>To</th><th>Payload</th><th>Outbox</th><th>When</th></tr></thead>
                    <tbody>
                        @forelse ($sandbox as $s)
                            @php $p = json_decode((string) $s->payload, true) ?: []; @endphp
                            <tr>
                                <td>{{ $s->id }}</td>
                                <td>{{ $s->channel }}</td>
                                <td class="small">{{ $contacts->mask($s->to_address) }}</td>
                                <td class="small text-wrap xl-cell-wide">{{ \Illuminate\Support\Str::limit($p['subject'] ?? $p['text'] ?? ($p['template']['body'] ?? json_encode($p)), 200) }}</td>
                                <td>@if ($s->outbox_id)<a href="{{ route('utils.comms.outbox.show', $s->outbox_id) }}">#{{ $s->outbox_id }}</a>@endif</td>
                                <td class="small"><span title="{{ site_datetime($s->created_at) }}">{{ \Illuminate\Support\Carbon::parse($s->created_at)->diffForHumans() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">The sandbox is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sandbox->hasPages()) <div class="card-footer">{{ $sandbox->links() }}</div> @endif
        @endif
    </div>
</div>
@endsection
