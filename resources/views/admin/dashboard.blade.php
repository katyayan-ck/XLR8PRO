@extends(backpack_view('blank'))

@section('content')
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="la la-user"></i> My Profile & Access</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Name:</strong> {{ $current_user_details['name'] ?? 'N/A' }}<br>
                                <strong>Username:</strong> {{ $current_user_details['username'] }}<br>
                                <strong>Type:</strong> {{ $current_user_details['user_type'] }}
                                @if($current_user_details['is_active']) <span class="badge badge-success">Active</span> @else <span class="badge badge-danger">Inactive</span> @endif
                            </div>
                            <div class="col-md-3">
                                <strong>Primary Branch:</strong> {{ $current_user_details['primary_branch'] ?? '—' }}<br>
                                <strong>Primary Location:</strong> {{ $current_user_details['primary_location'] ?? '—' }}<br>
                                <strong>Designation:</strong> {{ $current_user_details['designation'] ?? '—' }}
                            </div>
                            <div class="col-md-3">
                                <strong>Primary Post:</strong> {{ $current_user_details['primary_post'] ?? '—' }}<br>
                                <strong>Primary Mobile:</strong> {{ $current_user_details['primary_mobile'] ?? '—' }}<br>
                                <strong>Primary Email:</strong> {{ $current_user_details['primary_email'] ?? '—' }}
                            </div>
                            <div class="col-md-3">
                                <strong>All Branches:</strong> {{ $current_user_details['all_branches']->keys()->join(', ') ?: '—' }}<br>
                                <strong>All Departments:</strong> {{ $current_user_details['all_departments']->keys()->join(', ') ?: '—' }}<br>
                                <strong>Roles:</strong> {{ implode(', ', $current_user_details['roles'] ?? []) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="mb-0"><i class="la la-dashboard"></i> Dashboard</h1>
                <p class="text-muted small"></p>
            </div>
            <div class="col-md-4 text-right">
                <span class="badge badge-info">{{ now()->format('D, M d Y') }}</span>
            </div>
        </div>

        {{-- ROW 1: INFRASTRUCTURE METRICS --}}
        <div class="row mb-4">
            <div class="col-md-3">
                @include('admin.widgets.stats-card', [
                    'title' => 'Branches',
                    'value' => 0, //$totalBranches,
                    'icon' => 'la-sitemap',
                    'color' => 'primary',
                    'link' => backpack_url('branch'),
                ])
            </div>
            <div class="col-md-3">
                @include('admin.widgets.stats-card', [
                    'title' => 'Locations',
                    'value' => 0, //$totalLocations,
                    'icon' => 'la-map-marker',
                    'color' => 'info',
                    'link' => backpack_url('location'),
                ])
            </div>
            <div class="col-md-3">
                @include('admin.widgets.stats-card', [
                    'title' => 'Departments',
                    'value' => 0, //$totalDepartments,
                    'icon' => 'la-building',
                    'color' => 'success',
                    'link' => backpack_url('department'),
                ])
            </div>
            <div class="col-md-3">
                @include('admin.widgets.stats-card', [
                    'title' => 'Employees',
                    'value' => 0, //$totalEmployees,
                    'icon' => 'la-users',
                    'color' => 'warning',
                    'link' => backpack_url('employee'),
                ])
            </div>
        </div>

        {{-- ROW 2: BUSINESS METRICS --}}
        <div class="row mb-4">
            <div class="col-md-3">
                @include('admin.widgets.stats-card', [
                    'title' => 'Enquiries',
                    'value' => 0, //$totalEnquiries,
                    'subtitle' => '', //$todayEnquiries . ' today',
                    'icon' => 'la-comments',
                    'color' => 'secondary',
                    'link' => backpack_url('enquiry'),
                ])
            </div>
            <div class="col-md-3">
                @include('admin.widgets.stats-card', [
                    'title' => 'Bookings',
                    'value' => 0, //$totalBookings,
                    'subtitle' => '', //$todayBookings . ' today',
                    'icon' => 'la-calendar',
                    'color' => 'danger',
                    'link' => backpack_url('booking'),
                ])
            </div>
            <div class="col-md-3">
                @include('admin.widgets.stats-card', [
                    'title' => 'Total Sales',
                    'value' => '₹' . number_format(0, 0),
                    'subtitle' => '₹' . number_format(0, 0) . ' today',
                    'icon' => 'la-rupee',
                    'color' => 'success',
                    'link' => backpack_url('sale'),
                ])
            </div>
            <div class="col-md-3">
                @include('admin.widgets.stats-card', [
                    'title' => 'Active Users',
                    'value' => 0, //$activeUsers,
                    'icon' => 'la-user-circle',
                    'color' => 'info',
                    'link' => backpack_url('user'),
                ])
            </div>
        </div>

        {{-- MY ACCESS SECTION --}}
        @if (true)
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <strong><i class="la la-info-circle"></i> Your Access Scope:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Branches: <strong></strong></li>
                            <li>Locations: <strong></strong></li>
                            <li>Departments: <strong></strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- RECENT ACTIVITY --}}
        <div class="row mb-4">
            <div class="col-md-6">
                @include('admin.widgets.activity-feed', [
                    'title' => 'Recent Enquiries',
                    'items' => [], //$recentEnquiries,
                    'type' => 'enquiry',
                    'icon' => 'la-comments',
                    'color' => 'secondary',
                ])
            </div>
            <div class="col-md-6">
                @include('admin.widgets.activity-feed', [
                    'title' => 'Recent Bookings',
                    'items' => [], //$recentBookings,
                    'type' => 'booking',
                    'icon' => 'la-calendar',
                    'color' => 'danger',
                ])
            </div>
        </div>

        {{-- QUICK ACTIONS --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="la la-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="{{ backpack_url('branch/create') }}" class="btn btn-primary btn-sm"><i
                                class="la la-plus"></i> New Branch</a>
                        <a href="{{ backpack_url('location/create') }}" class="btn btn-info btn-sm"><i
                                class="la la-plus"></i> New Location</a>
                        <a href="{{ backpack_url('enquiry/create') }}" class="btn btn-secondary btn-sm"><i
                                class="la la-plus"></i> New Enquiry</a>
                        <a href="{{ backpack_url('booking/create') }}" class="btn btn-danger btn-sm"><i
                                class="la la-plus"></i> New Booking</a>
                        <a href="{{ backpack_url('sale/create') }}" class="btn btn-success btn-sm"><i
                                class="la la-plus"></i> New Sale</a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <style>
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stats-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .border-left-primary {
            border-left-color: #007bff !important;
        }

        .border-left-info {
            border-left-color: #17a2b8 !important;
        }

        .border-left-success {
            border-left-color: #28a745 !important;
        }

        .border-left-warning {
            border-left-color: #ffc107 !important;
        }

        .border-left-danger {
            border-left-color: #dc3545 !important;
        }

        .border-left-secondary {
            border-left-color: #6c757d !important;
        }

        .opacity-5 {
            opacity: 0.1;
        }
    </style>
@endsection
