@props(['request'])
{{-- Approval panel (FRS APR-10): ask, levels, counters by level (stale vs active), effective grant, composer. --}}
@php
    $engine = app(\App\Services\Platform\Approval\ApprovalService::class);
    $p = $engine->panel($request, backpack_user()->id);
    $fmt = fn ($v) => match ($request->value_type) {
        'PERCENTAGE' => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%',
        'FLAG' => (float) $v > 0 ? 'Yes' : 'No',
        default => '₹'.number_format((float) $v, 0),
    };
    $statusColor = ['OPEN' => 'blue', 'ACCEPTED' => 'green', 'WITHDRAWN' => 'secondary'][$request->status] ?? 'secondary';
@endphp
<div class="card approval-panel">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="card-title mb-1">{{ $request->topic_title }} <span class="text-muted small">{{ $request->topic_code }}</span></h3>
            <div class="small text-muted">
                #{{ $request->id }} · asked by {{ $request->requester?->display_name }} · mode {{ $request->mode }} · revision {{ $request->ask_revision }}
                @if ($p['source_url']) · <a href="{{ $p['source_url'] }}">{{ config('platform.entities.'.$request->source_type.'.label', $request->source_type) }} #{{ $request->source_id }}</a>@endif
            </div>
        </div>
        <span class="badge bg-{{ $statusColor }} text-white fs-5">{{ $request->status }}{{ $request->auto_accepted ? ' (auto)' : '' }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-sm-4"><div class="text-muted small">Asked</div><div class="h2 mb-0">{{ $fmt($request->asked) }}</div></div>
            <div class="col-sm-4">
                <div class="text-muted small">Effective grant</div>
                <div class="h2 mb-0 {{ $p['effective'] ? 'text-green' : 'text-muted' }}">{{ $p['effective'] ? $fmt($p['effective']['value']).' · L'.$p['effective']['level'] : '—' }}</div>
                @if ($p['below_min']) <div class="small text-orange">Below the configured minimum — confirm before accepting.</div> @endif
            </div>
            <div class="col-sm-4">
                <div class="text-muted small">Your level</div>
                <div class="h2 mb-0">{{ $p['viewer_level'] ? 'L'.$p['viewer_level']['level_no'].' (max '.($p['viewer_level']['max'] !== null ? $fmt($p['viewer_level']['max']) : '∞').')' : '—' }}</div>
                @if ($request->mode === 'LINEAR' && $request->status === 'OPEN') <div class="small text-muted">Now with L{{ $request->current_level }}</div> @endif
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-sm table-vcenter">
                <thead><tr><th>Level</th><th>Designation</th><th class="text-end">Std</th><th class="text-end">Max</th><th>Counters</th></tr></thead>
                <tbody>
                    @foreach (collect($p['levels'])->sortBy('level_no') as $level)
                        @php $atLevel = collect($p['counters'])->where('level', $level['level_no']); @endphp
                        <tr>
                            <td>L{{ $level['level_no'] }}</td>
                            <td>{{ $level['designation_code'] ?? 'Named users' }}</td>
                            <td class="text-end">{{ $level['std'] !== null ? $fmt($level['std']) : '—' }}</td>
                            <td class="text-end">{{ $level['max'] !== null ? $fmt($level['max']) : '∞' }}</td>
                            <td>
                                @foreach ($atLevel as $c)
                                    <div class="small {{ $c['stale'] ? 'text-muted text-decoration-line-through' : '' }}">
                                        <strong>{{ $fmt($c['value']) }}</strong> by {{ $c['actor'] }} · {{ $c['at']?->diffForHumans() }}
                                        @if ($c['winning']) <span class="badge bg-green-lt">winning</span> @endif
                                        @if ($c['stale']) <span class="badge bg-secondary-lt" title="Countered on revision {{ $c['revision'] }}">stale, re-counter</span> @endif
                                        @if ($c['remark']) <div class="text-secondary">{{ $c['remark'] }}</div> @endif
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($p['can_counter'] && ! $p['is_requester'])
            <form method="POST" action="{{ route('utils.approvals.counter', $request->id) }}" class="d-flex flex-wrap gap-2 mb-2">
                @csrf
                @if ($request->value_type === 'FLAG')
                    <select name="value" class="form-select form-select-sm" style="max-width: 140px;"><option value="1">Yes</option><option value="0">No</option></select>
                @else
                    <input type="number" step="0.01" min="0" max="{{ $p['viewer_level']['max'] ?? '' }}" name="value" required class="form-control form-control-sm" style="max-width: 160px;" placeholder="Your counter" value="{{ $request->asked }}">
                @endif
                <input type="text" name="remark" maxlength="1000" class="form-control form-control-sm flex-grow-1" placeholder="Remark (optional)">
                <button class="btn btn-sm btn-primary">Counter as L{{ $p['viewer_level']['level_no'] }}</button>
            </form>
        @endif

        @if ($p['is_requester'] && $request->status === 'OPEN')
            <form method="POST" action="{{ route('utils.approvals.revise', $request->id) }}" class="d-flex flex-wrap gap-2 mb-2">
                @csrf
                <input type="number" step="0.01" min="0" name="asked" required class="form-control form-control-sm" style="max-width: 160px;" placeholder="New ask">
                <input type="text" name="remark" maxlength="1000" class="form-control form-control-sm flex-grow-1" placeholder="Why the ask changed">
                <button class="btn btn-sm btn-outline-primary">Revise ask</button>
            </form>
        @endif

        @if ($p['can_close'])
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('utils.approvals.close', $request->id) }}" onsubmit="return {{ $p['below_min'] ? "confirm('The grant is below the configured minimum. Accept anyway?')" : 'true' }}">
                    @csrf <input type="hidden" name="outcome" value="ACCEPTED">
                    <button class="btn btn-sm btn-success" @disabled(! $p['effective'])>Accept {{ $p['effective'] ? $fmt($p['effective']['value']) : '' }}</button>
                </form>
                <form method="POST" action="{{ route('utils.approvals.close', $request->id) }}" onsubmit="return confirm('Withdraw this request?')">
                    @csrf <input type="hidden" name="outcome" value="WITHDRAWN">
                    <button class="btn btn-sm btn-outline-danger">Withdraw</button>
                </form>
            </div>
        @endif
    </div>
</div>
