@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $labels = ['TO_ACT' => ['To act', 'la-gavel'], 'RAISED' => ['Raised by me', 'la-paper-plane'], 'TEAM' => ['Team (monitor)', 'la-users'], 'CLOSED' => ['Closed', 'la-check']];
    $statusColor = ['OPEN' => 'blue', 'ACCEPTED' => 'green', 'WITHDRAWN' => 'secondary'];
@endphp
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <ul class="nav nav-tabs card-header-tabs">
                @foreach ($labels as $code => [$label, $icon])
                    <li class="nav-item">
                        <a class="nav-link {{ $box === $code ? 'active' : '' }}" href="{{ route('utils.approvals.index', ['box' => $code]) }}">
                            <i class="la {{ $icon }} me-1"></i>{{ $label }}
                            @if ($counts[$code] && $code !== 'CLOSED') <span class="badge bg-blue text-white ms-1">{{ $counts[$code] }}</span> @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="d-flex gap-2">
                @can('UTL_APPR_REPORT')
                    <a href="{{ route('utils.approvals.report') }}" class="btn btn-sm btn-outline-secondary"><i class="la la-chart-bar"></i> Report</a>
                @endcan
                @can('UTL_APPR_ADMIN')
                    <a href="{{ route('utils.approvals.admin.topics') }}" class="btn btn-sm btn-outline-secondary"><i class="la la-sitemap"></i> Topics &amp; rules</a>
                @endcan
                @can('UTL_APPR_REQUEST')
                    <a href="{{ route('utils.approvals.create') }}" class="btn btn-sm btn-primary"><i class="la la-plus me-1"></i>Raise request</a>
                @endcan
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Request</th><th>Requester</th><th class="text-end">Asked</th><th class="text-end">Grant</th><th>Status</th><th>Opened</th></tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>
                                <a href="{{ route('utils.approvals.show', $r->id) }}" class="fw-medium">{{ $r->topic_title }}</a>
                                <div class="small text-muted">#{{ $r->id }} · {{ $r->topic_code }}@if ($r->source_type) · {{ $r->source_type }} #{{ $r->source_id }}@endif</div>
                            </td>
                            <td class="small">{{ $r->requester?->display_name }}</td>
                            <td class="text-end">{{ $r->value_type === 'PERCENTAGE' ? (float) $r->asked.'%' : number_format((float) $r->asked, 0) }}</td>
                            <td class="text-end">{{ $r->effective_value !== null ? number_format((float) $r->effective_value, 0).' · L'.$r->effective_level : '—' }}</td>
                            <td><span class="badge bg-{{ $statusColor[$r->status] ?? 'secondary' }}-lt">{{ $r->status }}</span></td>
                            <td class="small">{{ site_datetime($r->created_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nothing here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
