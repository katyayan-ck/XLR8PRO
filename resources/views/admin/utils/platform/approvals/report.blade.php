@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $t = $summary['totals'];
    $groups = ['topic_code' => 'Topic', 'branch_code' => 'Branch', 'effective_level' => 'Winning level', 'item_key' => 'Item', 'source_type' => 'Source', 'fy' => 'FY'];
    $num = fn ($v) => $v === null ? '—' : number_format((float) $v, 0);
@endphp
<div class="container-fluid">
    <form method="GET" action="{{ route('utils.approvals.report') }}" class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label small">FY</label><input type="text" name="fy" value="{{ $filters['fy'] ?? '' }}" placeholder="26-27" class="form-control form-control-sm" style="width: 90px;"></div>
            <div>
                <label class="form-label small">Branch</label>
                <select name="branch" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($branches as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['branch'] ?? '') === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="form-label small">Topic (prefix)</label><input type="text" name="topic" value="{{ $filters['topic'] ?? '' }}" placeholder="DISCOUNT" class="form-control form-control-sm"></div>
            <div><label class="form-label small">Item</label><input type="text" name="item" value="{{ $filters['item'] ?? '' }}" class="form-control form-control-sm"></div>
            <div><label class="form-label small">Winning level</label><input type="number" name="level" value="{{ $filters['level'] ?? '' }}" class="form-control form-control-sm" style="width: 90px;"></div>
            <div>
                <label class="form-label small">Group by</label>
                <select name="group" class="form-select form-select-sm">
                    @foreach ($groups as $code => $label)
                        <option value="{{ $code }}" @selected($groupBy === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-sm btn-primary">Apply</button>
            <a href="{{ route('utils.approvals.report.export', request()->query()) }}" class="btn btn-sm btn-outline-success ms-auto"><i class="la la-file-excel"></i> Export xlsx</a>
        </div>
    </form>

    <div class="row row-cards mb-3">
        @foreach (['Opened' => $t['opened'], 'Accepted' => $t['accepted'], 'Withdrawn' => $t['withdrawn'], 'Open' => $t['open'], 'Granted / asked' => $t['grant_ratio'] !== null ? $t['grant_ratio'].'%' : '—', 'Auto-approved' => $t['auto_share'] !== null ? $t['auto_share'].'%' : '—', 'Avg time to close' => $t['avg_hours_to_close'] !== null ? $t['avg_hours_to_close'].' h' : '—'] as $label => $value)
            <div class="col-sm-6 col-lg">
                <div class="card card-sm"><div class="card-body"><div class="subheader">{{ $label }}</div><div class="h2 mb-0">{{ $value }}</div></div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">By {{ strtolower($groups[$groupBy] ?? 'topic') }}</h3></div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>{{ $groups[$groupBy] ?? 'Group' }}</th><th class="text-end">Opened</th><th class="text-end">Accepted</th><th class="text-end">Withdrawn</th><th class="text-end">Open</th><th class="text-end">Asked (acc.)</th><th class="text-end">Granted</th><th class="text-end">Auto %</th><th class="text-end">Hours</th></tr></thead>
                        <tbody>
                            @forelse ($summary['rows'] as $row)
                                <tr>
                                    <td><a href="{{ route('utils.approvals.report', array_merge(request()->query(), $groupBy === 'topic_code' ? ['topic' => $row['group']] : ($groupBy === 'branch_code' ? ['branch' => $row['group']] : []))) }}">{{ $row['group'] }}</a></td>
                                    <td class="text-end">{{ $row['opened'] }}</td>
                                    <td class="text-end">{{ $row['accepted'] }}</td>
                                    <td class="text-end">{{ $row['withdrawn'] }}</td>
                                    <td class="text-end">{{ $row['open'] }}</td>
                                    <td class="text-end">{{ $num($row['asked_accepted']) }}</td>
                                    <td class="text-end">{{ $num($row['granted']) }}</td>
                                    <td class="text-end">{{ $row['auto_share'] ?? '—' }}</td>
                                    <td class="text-end">{{ $row['avg_hours_to_close'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-muted text-center py-3">No requests match.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Counters per level</h3></div>
                <table class="table card-table">
                    <thead><tr><th>Level</th><th class="text-end">Counters</th><th class="text-end">People</th></tr></thead>
                    @forelse ($summary['by_level'] as $row)
                        <tr><td>L{{ $row['level'] }}</td><td class="text-end">{{ $row['counters'] }}</td><td class="text-end">{{ $row['actors'] }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">No counters.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
