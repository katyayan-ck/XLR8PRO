@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php $statusColor = ['DIALING' => 'blue', 'RINGING' => 'blue', 'ANSWERED' => 'cyan', 'COMPLETED' => 'green', 'NO_ANSWER' => 'orange', 'BUSY' => 'orange', 'FAILED' => 'red']; @endphp
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <form method="GET" action="{{ route('utils.calls.index') }}" class="xl-toolbar flex-grow-1">
                <input type="text" name="person" value="{{ $filters['person'] ?? '' }}" class="form-control form-control-sm" placeholder="Person code">
                <select name="status" class="form-select form-select-sm"><option value="">Any status</option>
                    @foreach (array_keys($statusColor) as $s) <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option> @endforeach
                </select>
                <x-ui.date name="from" :value="$filters['from'] ?? null" class="form-control-sm" placeholder="From" />
                <x-ui.date name="to" :value="$filters['to'] ?? null" class="form-control-sm" placeholder="To" />
                <button class="btn btn-sm btn-outline-primary">Filter</button>
            </form>
            <x-telephony.click-to-call label="New call" />
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>#</th><th>Customer</th><th>Agent</th><th>Status</th><th>Duration</th><th>Recording</th><th>Disposition</th></tr></thead>
                <tbody>
                    @forelse ($calls as $call)
                        <tr>
                            <td>{{ $call->id }}<div class="small text-muted">{{ $call->direction }} · {{ site_datetime($call->started_at, '—') }}</div></td>
                            <td class="small">{{ $telephony->display($call->direction === 'OUT' ? $call->to_number : $call->from_number) }}@if ($call->person_code)<div class="text-muted">{{ $call->person_code }}</div>@endif @if ($call->ref_type)<div class="text-muted">{{ $call->ref_type }} #{{ $call->ref_id }}</div>@endif</td>
                            <td class="small">{{ $call->agent?->display_name ?? '—' }}</td>
                            <td><span class="badge bg-{{ $statusColor[$call->status] ?? 'secondary' }}-lt">{{ $call->status }}</span></td>
                            <td class="small">{{ $call->duration_seconds !== null ? gmdate('i:s', $call->duration_seconds) : '—' }}</td>
                            <td>
                                @if ($call->recording_doc_id)
                                    <audio controls preload="none" class="w-100" style="height: 32px;"><source src="{{ route('utils.calls.recording', $call->id) }}"></audio>
                                    @can('UTL_COMM_RECORDING_DOWNLOAD') <a href="{{ route('utils.calls.recording', ['callId' => $call->id, 'download' => 1]) }}" class="small">download</a> @endcan
                                @elseif ($call->recording_missing)
                                    <span class="badge bg-red-lt">missing</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('utils.calls.dispose', $call->id) }}" class="xl-toolbar">
                                    @csrf
                                    <select name="disposition" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach ($dispositions as $code => $label) <option value="{{ $code }}" @selected($call->disposition === $code)>{{ $label }}</option> @endforeach
                                    </select>
                                    <input type="text" name="remark" maxlength="500" value="{{ $call->disposition_remark }}" class="form-control form-control-sm" placeholder="Remark">
                                    <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No calls.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($calls->hasPages()) <div class="card-footer">{{ $calls->links() }}</div> @endif
    </div>
</div>
@endsection
