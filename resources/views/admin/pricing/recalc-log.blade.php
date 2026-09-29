@extends(backpack_view('blank'))

@section('title', $title)

{{-- Automatic pricing recalculation runs (DEC-083) — read-only. --}}
@section('content')
<div class="container-xl">
    <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Pricing</div>
            <h2 class="mb-0">{{ $title }}</h2>
        </div>
        <div class="text-body-secondary small text-end">
            <div>Waiting to recalculate: <strong>{{ number_format($pending) }}</strong> change(s)</div>
            <div>App sync stamp: <strong>{{ $stamp ? site_datetime($stamp) : '—' }}</strong></div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr><th>#</th><th>Started</th><th>Status</th><th class="text-end">Vehicles</th><th class="text-end">Published</th><th class="text-end">Skipped</th><th class="text-end">Failed</th><th>Triggered by</th></tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr>
                            <td>{{ $run->id }}</td>
                            <td class="text-nowrap">{{ site_datetime($run->started_at) }}</td>
                            <td><span class="badge {{ ['done' => 'bg-green-lt', 'failed' => 'bg-red-lt'][$run->status] ?? 'bg-azure-lt' }}">{{ ucfirst($run->status) }}</span></td>
                            <td class="text-end">{{ number_format($run->vehicles) }}</td>
                            <td class="text-end text-green">{{ number_format($run->published) }}</td>
                            <td class="text-end text-body-secondary">{{ number_format($run->skipped) }}</td>
                            <td class="text-end {{ $run->failed ? 'text-danger' : '' }}">{{ number_format($run->failed) }}</td>
                            <td class="small">
                                {{ implode(', ', array_slice((array) $run->reasons, 0, 4)) }}{{ count((array) $run->reasons) > 4 ? ' …' : '' }}
                                @foreach (array_slice((array) $run->failures, 0, 3) as $failure)
                                    <div class="text-danger">{{ $failure['code'] }} — {{ $failure['message'] }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-body-secondary">No automatic recalculation yet — it runs a minute after a pricing master changes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($runs->hasPages())
            <div class="card-footer">{{ $runs->links() }}</div>
        @endif
    </div>
</div>
@endsection
