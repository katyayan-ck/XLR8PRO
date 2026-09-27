@props(['box' => 'REQUESTED', 'rows' => null, 'limit' => 10])
{{-- A ticket inbox or the desk QUEUE (FRS TCK-04). --}}
@php
    $rows ??= app(\App\Services\Platform\Ticket\TicketService::class)->inbox(backpack_user()->id, $box, [], $limit);
    $statusColor = ['NEW' => 'blue', 'ACKNOWLEDGED' => 'azure', 'INPROGRESS' => 'cyan', 'WAITING_USER' => 'orange', 'RESOLVED' => 'green', 'CLOSED' => 'secondary', 'REOPENED' => 'red'];
@endphp
<div class="table-responsive">
    <table class="table table-vcenter card-table">
        <thead>
            <tr><th>Ticket</th><th>Status</th><th>Priority</th><th>Category</th><th>Requester</th><th>SLA</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $ticket)
                <tr>
                    <td>
                        <a href="{{ route('utils.tickets.show', $ticket->id) }}" class="fw-medium">{{ $ticket->title }}</a>
                        <div class="small text-muted">{{ $ticket->number }}</div>
                    </td>
                    <td><span class="badge bg-{{ $statusColor[$ticket->status] ?? 'secondary' }}-lt">{{ $ticket->status }}</span></td>
                    <td><span class="badge {{ $ticket->priority === 'P1' ? 'bg-red text-white' : 'bg-secondary-lt' }}">{{ $ticket->priority }}</span></td>
                    <td class="small">{{ $ticket->category }}</td>
                    <td class="small">{{ $ticket->requester?->display_name }}</td>
                    <td><x-ticket.sla-badge :ticket="$ticket" /></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No tickets here.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
