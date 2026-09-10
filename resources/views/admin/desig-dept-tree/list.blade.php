@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Designation Dept Tree</h2>
        <p class="ml-2 ml-md-4 mb-0">Org hierarchy — who reports to whom by designation & department.</p>
        <a href="{{ backpack_url('desig-dept-tree/create') }}" class="btn btn-primary ml-auto">
            <i class="la la-plus"></i> Add Entry
        </a>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0" id="ddtTable">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Designation</th>
                        <th>Branch</th>
                        <th>Department</th>
                        <th>Division</th>
                        <th>Level</th>
                        <th>Rank</th>
                        <th>Reports To</th>
                        <th>Top Mgmt</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gridData as $row)
                    <tr>
                        <td>{{ $row['serial_no'] }}</td>
                        <td>{{ $row['designation'] }}</td>
                        <td>{{ $row['branch'] }}</td>
                        <td>{{ $row['department'] }}</td>
                        <td>{{ $row['division'] }}</td>
                        <td class="text-center"><span class="badge badge-info">L{{ $row['hierarchy_level'] }}</span></td>
                        <td class="text-center">{{ $row['rank'] }}</td>
                        <td>{{ $row['parent_desig'] }}</td>
                        <td class="text-center">
                            @if($row['is_top_mgmt'])
                                <span class="badge badge-warning"><i class="la la-star"></i> Yes</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row['is_active'])
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ backpack_url('desig-dept-tree/'.$row['id'].'/edit') }}" class="btn btn-sm btn-secondary">
                                <i class="la la-edit"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('after_scripts')
<script>$(document).ready(function(){ $('#ddtTable').DataTable({ order: [[5,'asc'],[6,'asc']], pageLength: 25 }); });</script>
@endpush