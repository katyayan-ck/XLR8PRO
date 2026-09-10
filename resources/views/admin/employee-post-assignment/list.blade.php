@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Post Assignments</h2>
        <a href="{{ backpack_url('emp-post-assignment/create') }}" class="btn btn-primary ml-auto">
            <i class="la la-plus"></i> Onboard to Post
        </a>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0" id="epaTable">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Post Code</th>
                        <th>Designation</th>
                        <th>Branch</th>
                        <th>Type</th>
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
                        <td><code>{{ $row['emp_code'] }}</code></td>
                        <td><code>{{ $row['post_code'] }}</code></td>
                        <td>{{ $row['designation'] }}</td>
                        <td>{{ $row['branch'] }}</td>
                        <td><span class="badge badge-secondary">{{ $row['assignment_type'] }}</span></td>
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
                            <a href="{{ backpack_url('emp-post-assignment/'.$row['id'].'/edit') }}" class="btn btn-sm btn-secondary">
                                <i class="la la-edit"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No assignments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('after_scripts')
<script>$(document).ready(function(){ $('#epaTable').DataTable({ order: [[6,'desc']], pageLength: 25 }); });</script>
@endpush