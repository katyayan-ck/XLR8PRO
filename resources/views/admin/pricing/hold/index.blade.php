@extends(backpack_view('blank'))

@section('title', $title ?? 'Price Hold Management')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-gradient-primary">
                <h2 class="card-title mb-0 fw-bold text-black">{{ $title }}</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Scope</th>
                                <th>Status</th>
                                <th>Held At</th>
                                <th>Reason</th>
                                <th width="280">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($scopes as $scope)
                                @php $hold = $holds->get($scope); @endphp
                                <tr>
                                    <td class="fw-bold">{{ $scope }}</td>
                                    <td>
                                        @if($hold && $hold->is_held)
                                            <span class="badge bg-danger">ON HOLD</span>
                                        @else
                                            <span class="badge bg-success">OPEN</span>
                                        @endif
                                    </td>
                                    <td>{{ $hold?->held_at?->format('d-M-Y H:i') ?? '—' }}</td>
                                    <td>{{ $hold?->hold_reason ?? '—' }}</td>
                                    <td>
                                        @if($hold && $hold->is_held)
                                            <form action="{{ route('pricing.hold.reopen') }}" method="POST" class="d-inline-flex gap-1">
                                                @csrf
                                                <input type="hidden" name="scope" value="{{ $scope }}">
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reopen reason" style="width:140px">
                                                <button type="submit" class="btn btn-sm btn-success">Reopen</button>
                                            </form>
                                        @else
                                            <form action="{{ route('pricing.hold.apply') }}" method="POST" class="d-inline-flex gap-1">
                                                @csrf
                                                <input type="hidden" name="scope" value="{{ $scope }}">
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Hold reason" style="width:140px">
                                                <button type="submit" class="btn btn-sm btn-danger">Put on Hold</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <a href="{{ route('pricing.workflow.index') }}" class="btn btn-outline-secondary btn-sm mt-2">Back to Workflow</a>
            </div>
        </div>
    </div>
</div>
@endsection