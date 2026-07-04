@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Posts</h2>
        <p class="ml-2 ml-md-4 mb-0">Manage IAM Posts and their org/vehicle scopes.</p>
        <a href="{{ backpack_url('post/create') }}" class="btn btn-primary ml-auto">
            <i class="la la-plus"></i> Add Post
        </a>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0" id="postTable">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Post Code</th>
                        <th>Designation</th>
                        <th>Branch</th>
                        <th>Department</th>
                        <th>Division</th>
                        <th>Location</th>
                        <th>Max</th>
                        <th>Current</th>
                        <th>Vacancy</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gridData as $row)
                    <tr>
                        <td>{{ $row['serial_no'] }}</td>
                        <td><code>{{ $row['post_code'] }}</code></td>
                        <td>{{ $row['designation'] }}</td>
                        <td>{{ $row['branch'] }}</td>
                        <td>{{ $row['department'] }}</td>
                        <td>{{ $row['division'] }}</td>
                        <td>{{ $row['location'] }}</td>
                        <td class="text-center">{{ $row['max_occupants'] }}</td>
                        <td class="text-center">{{ $row['current_occupants'] }}</td>
                        <td class="text-center">
                            @if($row['is_vacant'])
                                <span class="badge badge-success">Vacant</span>
                            @else
                                <span class="badge badge-warning">Full</span>
                            @endif
                        </td>
                        <td>
                            @if($row['is_active'])
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $row['created_at'] }}</td>
                        <td>
                            <a href="{{ backpack_url('post/'.$row['id'].'/edit') }}" class="btn btn-sm btn-secondary">
                                <i class="la la-edit"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="13" class="text-center text-muted py-4">No posts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
$(document).ready(function() {
    $('#postTable').DataTable({ order: [[0,'asc']], pageLength: 25 });
});
</script>
@endpush
