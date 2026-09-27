@extends('admin.dev.ui._layout')

@section('kit')
@php
    $people = [
        ['Rahul Sharma', 'RS', 'Nexon Creative +', 'Jaipur', 'HOT', '2026-09-26', 845000, 'Priya Mehta'],
        ['Anita Verma', 'AV', 'Punch Adventure', 'Ajmer', 'WARM', '2026-09-24', 712000, 'Karan Singh'],
        ['Mohd. Irfan', 'MI', 'Harrier Fearless', 'Kota', 'HOT', '2026-09-23', 2154000, 'Priya Mehta'],
        ['Sneha Joshi', 'SJ', 'Tiago XZ', 'Udaipur', 'COLD', '2026-09-20', 598000, 'Deepak Rao'],
        ['Vikram Rathore', 'VR', 'Safari Accomplished', 'Jodhpur', 'LOST', '2026-09-18', 2489000, 'Karan Singh'],
    ];
    $temp = ['HOT' => 'bg-red-lt', 'WARM' => 'bg-orange-lt', 'COLD' => 'bg-azure-lt', 'LOST' => 'bg-secondary-lt'];
@endphp
<div class="row row-cards">

    {{-- ============ Card table ============ --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Enquiries</h3>
                    <p class="card-subtitle">Card table: toolbar, avatars, status badges, row actions, pagination.</p>
                </div>
                <div class="card-actions">
                    <a href="#" class="btn btn-primary"><i class="la la-plus me-1"></i><span class="d-none d-sm-inline">New enquiry</span></a>
                </div>
            </div>
            <div class="card-body border-bottom py-3">
                <div class="xl-toolbar d-flex flex-wrap gap-2 align-items-center">
                    <div class="input-icon flex-grow-1">
                        <span class="input-icon-addon"><i class="la la-search"></i></span>
                        <input type="search" class="form-control" placeholder="Search name, mobile, reference…" aria-label="Search">
                    </div>
                    <select class="form-select w-auto" aria-label="Status"><option>All status</option><option>Hot</option><option>Warm</option><option>Cold</option></select>
                    <x-ui.date name="kit_list_from" class="form-control w-auto" placeholder="From" />
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" data-bs-toggle="dropdown" type="button"><i class="la la-download me-1"></i>Export</button>
                        <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="#">Excel</a><a class="dropdown-item" href="#">CSV</a><a class="dropdown-item" href="#">PDF</a></div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table table-hover">
                    <thead>
                        <tr>
                            <th class="w-1"><input class="form-check-input m-0 align-middle" type="checkbox" aria-label="Select all"></th>
                            <th>Customer</th>
                            <th>Interest</th>
                            <th class="d-none d-md-table-cell">Branch</th>
                            <th>Status</th>
                            <th class="d-none d-lg-table-cell">Created</th>
                            <th class="text-end d-none d-sm-table-cell">Budget</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($people as [$name, $initials, $model, $branch, $status, $date, $budget, $owner])
                            <tr>
                                <td><input class="form-check-input m-0 align-middle" type="checkbox" aria-label="Select {{ $name }}"></td>
                                <td>
                                    <div class="d-flex py-1 align-items-center">
                                        <span class="avatar avatar-sm me-2 bg-primary-lt">{{ $initials }}</span>
                                        <div class="flex-fill">
                                            <div class="fw-medium">{{ $name }}</div>
                                            <div class="text-secondary small">Owner: {{ $owner }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $model }}</td>
                                <td class="d-none d-md-table-cell text-secondary">{{ $branch }}</td>
                                <td><span class="badge {{ $temp[$status] }}">{{ ucfirst(strtolower($status)) }}</span></td>
                                <td class="d-none d-lg-table-cell text-secondary">@sitedate($date)</td>
                                <td class="text-end d-none d-sm-table-cell">₹ {{ number_format($budget) }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-icon btn-ghost-secondary" data-bs-toggle="dropdown" aria-label="Actions for {{ $name }}"><i class="la la-ellipsis-v"></i></button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="#"><i class="la la-eye me-2"></i>View</a>
                                            <a class="dropdown-item" href="#"><i class="la la-pen me-2"></i>Edit</a>
                                            <a class="dropdown-item" href="#"><i class="la la-phone me-2"></i>Call</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="#"><i class="la la-trash me-2"></i>Delete</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                <p class="m-0 text-secondary">Showing <span>1</span> to <span>5</span> of <span>128</span> entries</p>
                <ul class="pagination m-0 ms-auto">
                    <li class="page-item disabled"><a class="page-link" href="#" aria-label="Previous"><i class="la la-angle-left"></i></a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#" aria-label="Next"><i class="la la-angle-right"></i></a></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ============ AG-Grid ============ --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">AG-Grid <span class="badge bg-green-lt ms-2">follows mode, colour, font, radius</span></h3>
                    <p class="card-subtitle">No <code>theme</code> option — the global hook applies the Tabler-bound Quartz theme; date columns use the site format.</p>
                </div>
            </div>
            <div class="card-body">
                <div id="kit-grid"></div>
            </div>
        </div>
    </div>

    {{-- ============ Datagrid / list groups / empty ============ --}}
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Key–value (datagrid)</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item"><div class="datagrid-title">Booking</div><div class="datagrid-content">BK/JPR/2026/0142</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Booked on</div><div class="datagrid-content">@sitedate(now())</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Status</div><div class="datagrid-content"><span class="status status-green"><span class="status-dot status-dot-animated"></span>KYC done</span></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Executive</div><div class="datagrid-content"><div class="d-flex align-items-center"><span class="avatar avatar-xs me-2 bg-purple-lt">PM</span>Priya Mehta</div></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">On-road</div><div class="datagrid-content">₹ 9,42,500</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Finance</div><div class="datagrid-content">HDFC · 84 m</div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">List group</h3></div>
            <div class="list-group list-group-flush list-group-hoverable">
                @foreach (array_slice($people, 0, 4) as [$name, $initials, $model, $branch, $status])
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-auto"><span class="badge {{ $status === 'HOT' ? 'bg-red' : 'bg-secondary' }} badge-dot"></span></div>
                            <div class="col-auto"><span class="avatar avatar-sm bg-primary-lt">{{ $initials }}</span></div>
                            <div class="col text-truncate">
                                <a href="#" class="text-reset d-block text-truncate">{{ $name }}</a>
                                <div class="d-block text-secondary text-truncate small">{{ $model }} · {{ $branch }}</div>
                            </div>
                            <div class="col-auto"><a href="#" class="list-group-item-actions" aria-label="Star"><i class="la la-star text-secondary"></i></a></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-12 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="empty py-4">
                    <div class="empty-icon"><i class="la la-inbox display-6 text-secondary"></i></div>
                    <p class="empty-title">No enquiries match</p>
                    <p class="empty-subtitle text-secondary">Try clearing the filters or pick another branch.</p>
                    <div class="empty-action"><a href="#" class="btn btn-primary"><i class="la la-times me-1"></i>Clear filters</a></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Table variants ============ --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Striped, compact</h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-striped card-table">
                    <thead><tr><th>Model</th><th class="text-end">Bookings</th><th class="text-end">Deliveries</th><th class="text-end">Conv.</th></tr></thead>
                    <tbody>
                        @foreach (['Nexon' => [142, 118], 'Punch' => [131, 120], 'Harrier' => [44, 31], 'Tiago' => [96, 90]] as $model => [$b, $d])
                            <tr><td>{{ $model }}</td><td class="text-end">{{ $b }}</td><td class="text-end">{{ $d }}</td><td class="text-end">{{ round($d / $b * 100) }}%</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end">413</td><td class="text-end">359</td><td class="text-end">87%</td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Progress in rows</h3></div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th>Executive</th><th>Target</th><th class="w-50">Achieved</th></tr></thead>
                    <tbody>
                        @foreach (['Priya Mehta' => 92, 'Karan Singh' => 74, 'Deepak Rao' => 51, 'Neha Kapoor' => 33] as $who => $pct)
                            <tr>
                                <td>{{ $who }}</td><td class="text-secondary">25</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress progress-sm flex-fill"><div class="progress-bar {{ $pct > 80 ? 'bg-green' : ($pct > 50 ? 'bg-primary' : 'bg-orange') }}" style="width: {{ $pct }}%" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $pct }}%"></div></div>
                                        <span class="small text-secondary">{{ $pct }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@36.2.0/dist/ag-grid-community.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('kit-grid');
    if (!el || !window.agGrid) { return; }
    var names = ['Rahul Sharma', 'Anita Verma', 'Mohd. Irfan', 'Sneha Joshi', 'Vikram Rathore', 'Pooja Nair', 'Arjun Gupta', 'Kavita Rao'];
    var models = ['Nexon', 'Punch', 'Harrier', 'Safari', 'Tiago', 'Altroz'];
    var branches = ['Jaipur', 'Ajmer', 'Kota', 'Udaipur', 'Jodhpur'];
    var statuses = ['OPEN', 'BOOKED', 'DELIVERED', 'LOST'];
    var rows = [];
    for (var i = 0; i < 60; i++) {
        var day = new Date(2026, 8, 27 - (i % 28));
        rows.push({
            ref: 'XENQ-' + (4200 + i), customer: names[i % names.length], model: models[i % models.length],
            branch: branches[i % branches.length], status: statuses[i % statuses.length],
            created_at: day.toISOString().slice(0, 10) + ' 10:' + String(10 + (i % 50)).padStart(2, '0'),
            budget: 550000 + ((i * 73331) % 1900000),
        });
    }
    var badge = { OPEN: 'bg-azure-lt', BOOKED: 'bg-green-lt', DELIVERED: 'bg-purple-lt', LOST: 'bg-secondary-lt' };
    agGrid.createGrid(el, {
        rowData: rows,
        columnDefs: [
            { field: 'ref', headerName: 'Reference', pinned: 'left', width: 130 },
            { field: 'customer', flex: 1, minWidth: 160 },
            { field: 'model', width: 120 },
            { field: 'branch', width: 120 },
            { field: 'status', width: 130, cellRenderer: function (p) { return '<span class="badge ' + badge[p.value] + '">' + p.value + '</span>'; } },
            { field: 'created_at', headerName: 'Created', width: 170 },
            { field: 'budget', headerName: 'Budget', width: 140, type: 'rightAligned', valueFormatter: function (p) { return '₹ ' + Number(p.value).toLocaleString('en-IN'); } },
        ],
        defaultColDef: { sortable: true, filter: true, resizable: true },
        rowSelection: { mode: 'multiRow' },
        pagination: true,
        paginationPageSize: 10,
        paginationPageSizeSelector: [10, 25, 50],
        domLayout: 'autoHeight',
    });
});
</script>
@endpush
