@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Journey — {{ $employee->code }}</h2>
        <p class="ml-2 ml-md-4 mb-0">{{ $employee->person?->display_name ?? $employee->code }}</p>
        <a href="{{ backpack_url('hr/journey') }}" class="btn btn-secondary ml-auto">
            <i class="la la-arrow-left"></i> Back
        </a>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">

    {{-- Employee Summary Card --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <small class="text-muted">Employee Code</small>
                    <p class="mb-0"><code>{{ $employee->code }}</code></p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Current Designation</small>
                    <p class="mb-0">{{ $employee->designation?->name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Primary Branch</small>
                    <p class="mb-0">{{ $employee->primaryBranch?->name ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Employment Status</small>
                    <p class="mb-0">
                        <span class="badge badge-{{ $employee->employment_status === 'active' ? 'success' : 'danger' }}">
                            {{ ucfirst($employee->employment_status) }}
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="card">
        <div class="card-header"><strong>Post Assignment Timeline</strong></div>
        <div class="card-body p-0">
            @if($journey->isEmpty())
                <div class="p-4 text-center text-muted">No post assignments found for this employee.</div>
            @else
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Post Code</th>
                        <th>Designation</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Relieving</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($journey as $i => $row)
                    <tr class="{{ $row['to_date'] === 'Current' ? 'table-success' : '' }}">
                        <td>{{ $i + 1 }}</td>
                        <td><code>{{ $row['post_code'] }}</code></td>
                        <td>{{ $row['designation'] }}</td>
                        <td>{{ $row['branch'] }}</td>
                        <td><span class="badge badge-secondary">{{ $row['assignment_type'] }}</span></td>
                        <td>
                            @if($row['relieving_type'])
                                <span class="badge badge-warning">{{ $row['relieving_type'] }}</span>
                            @else
                                @if($row['to_date'] === 'Current')
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            @endif
                        </td>
                        <td>{{ $row['from_date'] }}</td>
                        <td>{{ $row['to_date'] }}</td>
                        <td><small class="text-muted">{{ $row['duration'] }}</small></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>
@endsection