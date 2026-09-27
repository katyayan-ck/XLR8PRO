@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $can = $ticket['user_can'];
    $targets = collect($can)->filter(fn ($r) => str_starts_with($r, 'status:'))->map(fn ($r) => substr($r, 7))->values();
    $labels = ['ACKNOWLEDGED' => 'Acknowledge', 'INPROGRESS' => 'Start work', 'WAITING_USER' => 'Wait for requester', 'RESOLVED' => 'Resolve', 'CLOSED' => 'Close', 'REOPENED' => 'Reopen'];
    $ids = fn (string $key) => collect($ticket['people'][$key] ?? [])->pluck('id')->all();
@endphp
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="card-title mb-1">{{ $ticket['title'] }}</h3>
                        <div class="small text-muted">{{ $ticket['number'] }} · {{ $ticket['category_label'] }} · requested by {{ $ticket['requester']['name'] }}</div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge {{ $ticket['priority'] === 'P1' ? 'bg-red text-white' : 'bg-secondary-lt' }}">{{ $ticket['priority'] }}</span>
                        <x-ticket.sla-badge :ticket="$ticket" />
                        <span class="badge bg-blue text-white fs-5">{{ $ticket['status'] }}</span>
                    </div>
                </div>
                <div class="card-body">
                    @if ($ticket['details'])
                        <div class="mb-3">{!! $ticket['details'] !!}</div>
                    @endif
                    @if ($ticket['close_reason'])
                        <div class="alert alert-secondary py-2">Force-closed: {{ $ticket['close_reason'] }}</div>
                    @endif
                    @if ($targets->isNotEmpty())
                        <form method="POST" action="{{ route('utils.tickets.transition', $ticket['id']) }}" class="border rounded p-2 mb-3">
                            @csrf
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <select name="to" class="form-select form-select-sm" required>
                                    @foreach ($targets as $to)
                                        <option value="{{ $to }}">{{ $labels[$to] ?? $to }}{{ $to === 'CLOSED' && $ticket['status'] !== 'RESOLVED' ? ' (force, reason required)' : '' }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="remark" maxlength="5000" class="form-control form-control-sm flex-grow-1" placeholder="Note (required to force-close)">
                                <button class="btn btn-sm btn-primary">Update status</button>
                            </div>
                        </form>
                    @endif
                    @if (in_array('remark', $can, true))
                        <form method="POST" action="{{ route('utils.tickets.remark', $ticket['id']) }}" enctype="multipart/form-data">
                            @csrf
                            <textarea name="body" rows="2" maxlength="5000" class="form-control mb-2" placeholder="Reply… (@username to mention)"></textarea>
                            <div class="d-flex gap-2">
                                <div class="flex-grow-1"><x-ui.upload name="file" /></div>
                                <button class="btn btn-sm btn-outline-primary ms-auto"><i class="la la-reply me-1"></i>Post reply</button>
                            </div>
                        </form>
                    @else
                        <div class="text-muted small"><i class="la la-eye me-1"></i>You can read this ticket but not reply.</div>
                    @endif
                </div>
            </div>

            <x-chat.thread :model="$model" title="Conversation" :composer="false" />
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Owner</dt><dd class="col-7">{{ $ticket['owner']['name'] ?? 'Unassigned' }}</dd>
                        @foreach (['assignees' => 'Assignees', 'followers' => 'Followers', 'snoopers' => 'Snoopers'] as $key => $label)
                            <dt class="col-5">{{ $label }}</dt>
                            <dd class="col-7">{{ collect($ticket['people'][$key])->pluck('name')->join(', ') ?: '—' }}</dd>
                        @endforeach
                        <dt class="col-5">SLA due</dt><dd class="col-7">{{ site_datetime($ticket['sla']['due_at'], '—') }}</dd>
                        @if ($ticket['ref_type'])
                            <dt class="col-5">Linked to</dt>
                            <dd class="col-7">
                                @if ($ticket['ref_url'])
                                    <a href="{{ $ticket['ref_url'] }}">{{ config('platform.entities.'.$ticket['ref_type'].'.label', $ticket['ref_type']) }} #{{ $ticket['ref_id'] }}</a>
                                @else
                                    {{ $ticket['ref_type'] }} #{{ $ticket['ref_id'] }}
                                @endif
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>

            @if (in_array('manage', $can, true))
                <form method="POST" action="{{ route('utils.tickets.update', $ticket['id']) }}" class="card mb-3">
                    @csrf @method('PUT')
                    <div class="card-header"><h3 class="card-title mb-0">Manage</h3></div>
                    <div class="card-body row g-2">
                        <div class="col-6">
                            <label class="form-label small">Priority</label>
                            <select name="priority" class="form-select form-select-sm">
                                @foreach ($priorities as $code => $label)
                                    <option value="{{ $code }}" @selected($ticket['priority'] === $code)>{{ $code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Category</label>
                            <select name="category" class="form-select form-select-sm">
                                @foreach ($categories as $code => $label)
                                    <option value="{{ $code }}" @selected($ticket['category'] === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Owner</label>
                            <select name="owner_id" class="form-select form-select-sm" data-xl="select2" data-placeholder="Unassigned">
                                <option value="">Unassigned</option>
                                @foreach ($team as $id => $label)
                                    <option value="{{ $id }}" @selected(($ticket['owner']['id'] ?? null) === $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @foreach (['assignees' => 'Assignees', 'followers' => 'Followers', 'snoopers' => 'Snoopers'] as $key => $label)
                            <div class="col-12">
                                <label class="form-label small">{{ $label }}</label>
                                <x-ui.select :name="$key.'[]'" :options="$team" :selected="$ids($key)" multiple class="form-select-sm" />
                            </div>
                        @endforeach
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-sm btn-primary">Save</button></div>
                </form>
            @endif

            <x-docs.uploader :model="$model" title="Attachments" />
        </div>
    </div>
</div>
@endsection
