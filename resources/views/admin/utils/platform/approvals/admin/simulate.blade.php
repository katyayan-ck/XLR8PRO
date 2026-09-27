@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@include('admin.utils.platform.approvals.admin._nav')
<div class="container-fluid">
    <form method="GET" action="{{ route('utils.approvals.admin.simulate') }}" class="card mb-3">
        <div class="card-header"><h3 class="card-title mb-0">Who would see this ask, and would it auto-pass?</h3></div>
        <div class="card-body row g-2">
            <div class="col-md-4">
                <label class="form-label">As user</label>
                <select name="user_id" class="form-select">
                    <option value="">Me</option>
                    @foreach ($team as $id => $label)
                        <option value="{{ $id }}" @selected((int) ($input['user_id'] ?? 0) === $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label required">Item</label>
                <select name="item" class="form-select" required>
                    @foreach ($items as $key => $code)
                        <option value="{{ $key }}" @selected(($input['item'] ?? '') === $key)>{{ $code }} ({{ $key }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Ask</label><input type="number" step="0.01" min="0" name="ask" class="form-control" value="{{ $input['ask'] ?? '' }}"></div>
            @foreach (['model', 'variant', 'segment', 'permit', 'branch', 'channel'] as $dim)
                <div class="col-md-2"><label class="form-label small">{{ ucfirst($dim) }}</label><input type="text" name="scope[{{ $dim }}]" class="form-control form-control-sm" value="{{ $input['scope'][$dim] ?? '' }}"></div>
            @endforeach
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary">Simulate</button></div>
    </form>

    @if ($preview)
        <div class="card">
            <div class="card-body">
                @if (! $preview['ok'])
                    <div class="alert alert-warning mb-0">{{ $preview['message'] }}</div>
                @else
                    <div class="mb-2">
                        Topic <code>{{ implode(' › ', $preview['chain']) }}</code> · mode <strong>{{ $preview['mode'] }}</strong> · matched rule
                        <a href="{{ route('utils.approvals.admin.rules.edit', $preview['rule_id']) }}">#{{ $preview['rule_id'] }}</a>
                        @foreach ($preview['rule_scope'] as $dim => $value) <span class="badge bg-secondary-lt">{{ $dim }}={{ $value }}</span> @endforeach
                    </div>
                    <div class="mb-3">
                        Your level: <strong>{{ $preview['actor_level'] ? 'L'.$preview['actor_level']['level_no'].' (max '.($preview['actor_level']['max'] ?? '∞').')' : 'none' }}</strong> ·
                        Would auto-pass: <strong class="{{ $preview['would_auto_pass'] ? 'text-green' : 'text-muted' }}">{{ $preview['would_auto_pass'] ? 'Yes' : 'No' }}</strong>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>Level</th><th>Designation</th><th>Type</th><th class="text-end">Std</th><th class="text-end">Max</th><th>Visible now</th></tr></thead>
                        @foreach (collect($preview['levels'])->sortBy('level_no') as $l)
                            <tr>
                                <td>L{{ $l['level_no'] }}</td>
                                <td>{{ $l['designation_code'] ?? 'Named users' }}</td>
                                <td>{{ $l['value_type'] }}</td>
                                <td class="text-end">{{ $l['std'] ?? '—' }}</td>
                                <td class="text-end">{{ $l['max'] ?? '∞' }}</td>
                                <td class="small">{{ $l['visible_count'] }} · {{ implode(', ', array_slice($l['visible_users'], 0, 6)) }}{{ $l['visible_count'] > 6 ? '…' : '' }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
