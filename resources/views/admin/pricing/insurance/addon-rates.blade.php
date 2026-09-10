@extends(backpack_view('blank'))

@section('title', $title ?? 'Insurance Addon Rates')

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
                                <th>Company</th>
                                <th>Permit</th>
                                <th>Addon Slug</th>
                                <th>Addon Name</th>
                                <th>Rate Type</th>
                                <th>Rate Value</th>
                                <th>Applies On</th>
                                <th>WEF</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rates as $r)
                                <tr>
                                    <td class="fw-bold">{{ $r->insurance_company }}</td>
                                    <td>{{ $r->permit }}</td>
                                    <td><code>{{ $r->addon_slug }}</code></td>
                                    <td>{{ $r->addon_name ?? '—' }}</td>
                                    <td>{{ $r->rate_type }}</td>
                                    <td>{{ $r->rate_value }}</td>
                                    <td>{{ $r->applies_on }}</td>
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
                                    <td colspan="9" class="text-center text-muted">No addon rates found. Import via Second Pass or seed manually.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $rates->links() }}
            </div>
        </div>
    </div>
</div>
@endsection