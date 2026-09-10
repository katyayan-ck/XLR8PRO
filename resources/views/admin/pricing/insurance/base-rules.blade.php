@extends(backpack_view('blank'))

@section('title', $title ?? 'Insurance Base Rules')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0 fw-bold text-black">{{ $title }}</h2>
                <a href="{{ route('pricing.workflow.index') }}" class="btn btn-sm btn-light">Back to Workflow</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Permit</th>
                                <th>Fuel</th>
                                <th>Wheels</th>
                                <th>Seating</th>
                                <th>OD Factor</th>
                                <th>OD Discount %</th>
                                <th>TP Basic</th>
                                <th>TP / Passenger</th>
                                <th>WEF</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rules as $r)
                                <tr>
                                    <td class="fw-bold">{{ $r->permit }}</td>
                                    <td>{{ $r->fuel_type ?? '—' }}</td>
                                    <td>{{ $r->wheels ?? '—' }}</td>
                                    <td>{{ $r->seating ?? '—' }}</td>
                                    <td>{{ $r->od_factor }}</td>
                                    <td>{{ $r->od_discount_rate }}</td>
                                    <td>{{ number_format($r->tp_basic, 2) }}</td>
                                    <td>{{ number_format($r->tp_per_passenger, 2) }}</td>
                                    <td>{{ $r->wef_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        @if($r->is_active)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted">No base rules found. Import via Second Pass or seed manually.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $rules->links() }}
            </div>
        </div>
    </div>
</div>
@endsection