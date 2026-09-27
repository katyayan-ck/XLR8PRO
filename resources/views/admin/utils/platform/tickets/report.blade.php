@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
<div class="container-fluid">
    <form method="GET" action="{{ route('utils.tickets.report') }}" class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label small">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
            <div><label class="form-label small">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
            <div>
                <label class="form-label small">Branch</label>
                <select name="branch" class="form-select form-select-sm">
                    <option value="">All branches</option>
                    @foreach ($branches as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['branch'] ?? '') === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-sm btn-primary">Apply</button>
            <a href="{{ route('utils.tickets.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">Back to tickets</a>
        </div>
    </form>

    <div class="row row-cards mb-3">
        @foreach (['Open' => $report['open'], 'Breached (open)' => $report['breached_open'], 'Breached (all)' => $report['breached_total'], 'Resolved' => $report['resolved'], 'Mean time to resolve' => $report['mttr_hours'] !== null ? $report['mttr_hours'].' h' : '—'] as $label => $value)
            <div class="col-sm-6 col-lg">
                <div class="card card-sm"><div class="card-body">
                    <div class="subheader">{{ $label }}</div>
                    <div class="h1 mb-0">{{ $value }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Open by category</h3></div>
                <table class="table card-table">
                    @forelse ($report['open_by_category'] as $code => $n)
                        <tr><td>{{ $categories[$code] ?? $code }}</td><td class="text-end fw-bold">{{ $n }}</td></tr>
                    @empty
                        <tr><td class="text-muted">No open tickets.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Open by priority</h3></div>
                <table class="table card-table">
                    @forelse ($report['by_priority'] as $code => $n)
                        <tr><td>{{ $code }}</td><td class="text-end fw-bold">{{ $n }}</td></tr>
                    @empty
                        <tr><td class="text-muted">No open tickets.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
