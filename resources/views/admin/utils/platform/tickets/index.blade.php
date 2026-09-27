@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $labels = ['REQUESTED' => ['Requested', 'la-paper-plane'], 'ASSIGNED' => ['Assigned', 'la-user-check'], 'FOLLOWED' => ['Followed', 'la-bell'], 'SNOOPED' => ['Snooped', 'la-eye']];
    if ($isDesk) {
        $labels['QUEUE'] = ['Desk queue', 'la-inbox'];
    }
@endphp
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <ul class="nav nav-tabs card-header-tabs">
                @foreach ($labels as $code => [$label, $icon])
                    <li class="nav-item">
                        <a class="nav-link {{ $box === $code ? 'active' : '' }}" href="{{ route('utils.tickets.index', ['box' => $code]) }}">
                            <i class="la {{ $icon }} me-1"></i>{{ $label }}
                            @if ($counts[$code]) <span class="badge bg-blue text-white ms-1">{{ $counts[$code] }}</span> @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="d-flex gap-2">
                <form method="GET" action="{{ route('utils.tickets.index') }}" class="d-flex gap-2">
                    <input type="hidden" name="box" value="{{ $box }}">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Any status</option>
                        @foreach (\App\Services\Platform\Ticket\TicketService::STATUSES as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">Any category</option>
                        @foreach ($categories as $code => $label)
                            <option value="{{ $code }}" @selected(($filters['category'] ?? '') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Number or title">
                    <button class="btn btn-sm btn-outline-primary">Filter</button>
                </form>
                @can('UTL_TCKT_REPORT')
                    <a href="{{ route('utils.tickets.report') }}" class="btn btn-sm btn-outline-secondary"><i class="la la-chart-bar"></i> Report</a>
                @endcan
                @can('UTL_TCKT_CREATE')
                    <a href="{{ route('utils.tickets.create') }}" class="btn btn-sm btn-primary"><i class="la la-plus me-1"></i>New ticket</a>
                @endcan
            </div>
        </div>
        <x-ticket.inbox :box="$box" :rows="$rows" />
        @if ($rows->hasPages())
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
