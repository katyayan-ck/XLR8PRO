@extends(backpack_view('blank'))

@section('title', $title ?? 'Insurance Defaults')

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
                                <th>Model Code</th>
                                <th>Permit</th>
                                <th>Default Company</th>
                                <th>Priority 2</th>
                                <th>Priority 3</th>
                                <th>WEF</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($defaults as $d)
                                <tr>
                                    <td class="fw-bold">{{ $d->model_code }}</td>
                                    <td>{{ $d->permit }}</td>
                                    <td>{{ $d->default_company }}</td>
                                    <td>{{ $d->company_priority_2 ?? '—' }}</td>
                                    <td>{{ $d->company_priority_3 ?? '—' }}</td>
                                    <td>{{ $d->wef_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td>
                                        @if($d->is_active)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No insurance defaults found. Import via Second Pass or seed manually.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $defaults->links() }}
            </div>
        </div>
    </div>
</div>
@endsection