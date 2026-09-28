@extends(backpack_view('blank'))
@section('title', $title)
@section('content')
<div class="card">
    <div class="card-header bg-gradient-primary d-flex justify-content-between">
        <h2 class="card-title mb-0 fw-bold text-black">{{ $title }}</h2>
        <a href="{{ route('pricing.rto.create') }}" class="btn btn-sm btn-light">Add Rule</a>
    </div>
    <div class="card-body">
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th>Permit</th><th>Wheels</th><th>Tax Factor</th>
                    <th>TRC</th><th>RTO Tape</th><th>WEF</th><th>Active</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rules as $r)
                <tr>
                    <td>{{ $r->permit }}</td>
                    <td>{{ $r->wheels }}</td>
                    <td>{{ $r->tax_factor }}</td>
                    <td>{{ $r->registration_fee }}</td>
                    <td>{{ $r->rto_tape }}</td>
                    <td>{{ $r->wef_date?->format('Y-m-d') }}</td>
                    <td>{{ $r->is_active ? 'Yes' : 'No' }}</td>
                    <td><a href="{{ route('pricing.rto.edit', $r->id) }}">Edit</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $rules->links() }}
    </div>
</div>
@endsection