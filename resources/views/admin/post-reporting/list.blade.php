@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Post Reporting Lines</h2>
        <a href="{{ backpack_url('post-reporting/create') }}" class="btn btn-primary ml-auto">
            <i class="la la-plus"></i> Add Reporting Line
        </a>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0" id="prTable">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Post</th>
                        <th>Reports To</th>
                        <th>Topic</th>
                        <th>Param Type</th>
                        <th>Param Value</th>
                        <th>Priority</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gridData as $row)
                    <tr>
                        <td>{{ $row['serial_no'] }}</td>
                        <td><code>{{ $row['post_code'] }}</code></td>
                        <td><code>{{ $row['reports_to_code'] }}</code></td>
                        <td>{{ $row['topic'] }}</td>
                        <td>{{ $row['param_type'] ?? '—' }}</td>
                        <td><span class="badge badge-light">{{ $row['param_value'] }}</span></td>
                        <td class="text-center">{{ $row['priority'] }}</td>
                        <td>{{ $row['from_date'] }}</td>
                        <td>{{ $row['to_date'] }}</td>
                        <td>
                            @if($row['is_active'])
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Closed</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ backpack_url('post-reporting/'.$row['id'].'/edit') }}" class="btn btn-sm btn-secondary">
                                <i class="la la-edit"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No reporting lines found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('after_scripts')
<script>$(document).ready(function(){ $('#prTable').DataTable({ order: [[7,'desc']], pageLength: 25 }); });</script>
@endpush