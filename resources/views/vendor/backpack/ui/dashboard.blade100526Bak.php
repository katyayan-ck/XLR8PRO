@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid">

    {{-- SAFE MY PROFILE & ACCESS CARD --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="la la-user me-2"></i>
                    <h5 class="mb-0">My Profile & Access</h5>
                    <span class="badge bg-white text-primary ms-auto">
                        {{ $current_user_details['user_type'] ?? 'Emp' }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <strong class="text-muted d-block mb-1">Name</strong>
                            <h5 class="mb-0">{{ $current_user_details['name'] ?? 'N/A' }}</h5>
                            <small class="text-muted">{{ $current_user_details['username'] ?? '—' }}</small>
                        </div>

                        <div class="col-md-3">
                            <strong class="text-muted d-block mb-1">Primary</strong>
                            <div><i class="la la-map-marker"></i> <strong>{{ $current_user_details['primary_branch'] ?? '—' }}</strong></div>
                            <div><i class="la la-building"></i> <strong>{{ $current_user_details['primary_department'] ?? '—' }}</strong></div>
                            <div><i class="la la-briefcase"></i> <strong>{{ $current_user_details['designation'] ?? '—' }}</strong></div>
                            <div><i class="la la-id-badge"></i> <strong>{{ $current_user_details['primary_post'] ?? '—' }}</strong></div>
                        </div>

                        <div class="col-md-3">
                            <strong class="text-muted d-block mb-1">Primary Contact</strong>
                            <div><i class="la la-phone"></i> {{ $current_user_details['primary_mobile'] ?? '—' }}</div>
                            <div><i class="la la-envelope"></i> {{ $current_user_details['primary_email'] ?? '—' }}</div>
                        </div>

                        <div class="col-md-3">
                            <strong class="text-muted d-block mb-1">All Access</strong>
                            <small class="text-muted">Branches:</small> {{ ($current_user_details['all_branches'] ?? collect())->keys()->join(', ') ?: '—' }}<br>
                            <small class="text-muted">Departments:</small> {{ ($current_user_details['all_departments'] ?? collect())->keys()->join(', ') ?: '—' }}<br>
                            <small class="text-muted">Roles:</small> {{ implode(', ', $current_user_details['roles'] ?? []) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Bikaner Motors Analytics</h2>
            <small class="text-muted">System performance overview (dummy analytics)</small>
        </div>
        <span class="badge bg-success px-3 py-2">Auto Refresh</span>
    </div>

    {{-- METRIC CARDS --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card primary">
                <p>Total Vehicles</p>
                <h3 id="vehicles">129</h3>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card success">
                <p>Monthly Revenue</p>
                <h3 id="sales">₹ 6.25 L</h3>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card warning">
                <p>Pending Services</p>
                <h3 id="services">17</h3>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card danger">
                <p>Insurance Expiry</p>
                <h3 id="insurance">5</h3>
            </div>
        </div>
    </div>

    {{-- CHART ROWS (your original charts remain unchanged) --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Revenue Growth</div>
                <div class="card-body">
                    <canvas id="revenueChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Vehicle Category</div>
                <div class="card-body">
                    <canvas id="vehicleChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Daily Service Load</div>
                <div class="card-body">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Sales Channel Split</div>
                <div class="card-body">
                    <canvas id="channelChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Weekly Bookings</div>
                <div class="card-body">
                    <canvas id="bookingChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Payment Status</div>
                <div class="card-body">
                    <canvas id="paymentChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('after_styles')
<style>
    .stat-card {
        padding: 20px;
        border-radius: 14px;
        color: #fff;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .1);
    }
    .primary  { background: linear-gradient(135deg, #1e3c72, #2a5298); }
    .success  { background: linear-gradient(135deg, #11998e, #38ef7d); }
    .warning  { background: linear-gradient(135deg, #f7971e, #ffd200); }
    .danger   { background: linear-gradient(135deg, #cb2d3e, #ef473a); }
</style>
@endpush

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Your original chart scripts remain unchanged
</script>
@endpush