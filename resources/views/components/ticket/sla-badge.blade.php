@props(['ticket'])
{{-- SLA badge (FRS §6.5). Accepts a Ticket model or the TicketService DTO. --}}
@php
    $sla = is_array($ticket) ? $ticket['sla'] : app(\App\Services\Platform\Ticket\TicketService::class)->sla($ticket);
    $left = $sla['minutes_left'];
    $human = $left === null ? '' : (abs($left) >= 1440 ? intdiv(abs($left), 1440).'d '.intdiv(abs($left) % 1440, 60).'h' : intdiv(abs($left), 60).'h '.(abs($left) % 60).'m');
    [$color, $text] = match ($sla['state']) {
        'breached' => ['red', 'Breached '.$human.' ago'],
        'due_soon' => ['orange', 'Due in '.$human],
        'paused' => ['secondary', 'Paused · '.$human.' left'],
        'done' => ['green', 'Done'],
        default => ['green', 'Due in '.$human],
    };
@endphp
<span class="badge bg-{{ $color }}-lt" title="{{ $sla['due_at'] ? 'Due '.site_datetime($sla['due_at']) : '' }}"><i class="la la-clock me-1"></i>{{ $text }}</span>
