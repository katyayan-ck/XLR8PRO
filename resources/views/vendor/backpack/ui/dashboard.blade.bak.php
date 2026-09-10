@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="la la-user me-2"></i>
                    <h5 class="mb-0">My Profile & Access</h5>
                    <span class="badge bg-white text-primary ms-auto">{{ $current_user_details['user_type'] ?? 'Emp' }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Basic Info -->
                        <div class="col-md-3">
                            <strong class="text-muted d-block mb-1">Name</strong>
                            <h5 class="mb-0">{{ $current_user_details['name'] ?? 'N/A' }}</h5>
                            <small class="text-muted">{{ $current_user_details['username'] }}</small>
                        </div>

                        <!-- Primary Assignment -->
                        <div class="col-md-3">
                            <strong class="text-muted d-block mb-1">Primary</strong>
                            <div><i class="la la-map-marker"></i> <strong>{{ $current_user_details['primary_branch'] ?? '—' }}</strong></div>
                            <div><i class="la la-building"></i> <strong>{{ $current_user_details['primary_department'] ?? '—' }}</strong></div>
                            <div><i class="la la-briefcase"></i> <strong>{{ $current_user_details['designation'] ?? '—' }}</strong></div>
                            <div><i class="la la-id-badge"></i> <strong>{{ $current_user_details['primary_post'] ?? '—' }}</strong></div>
                        </div>

                        <!-- Contact -->
                        <div class="col-md-3">
                            <strong class="text-muted d-block mb-1">Primary Contact</strong>
                            <div><i class="la la-phone"></i> {{ $current_user_details['primary_mobile'] ?? '—' }}</div>
                            <div><i class="la la-envelope"></i> {{ $current_user_details['primary_email'] ?? '—' }}</div>
                        </div>

                        <!-- All Access -->
                        <div class="col-md-3">
                            <strong class="text-muted d-block mb-1">All Access</strong>
                            <small class="text-muted">Branches:</small> {{ $current_user_details['all_branches']->keys()->join(', ') ?: '—' }}<br>
                            <small class="text-muted">Departments:</small> {{ $current_user_details['all_departments']->keys()->join(', ') ?: '—' }}<br>
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

    {{-- CHART ROW 1 --}}
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

    {{-- CHART ROW 2 --}}
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

    {{-- CHART ROW 3 (EXTRA) --}}
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
    .dashboard-wrapper {
        background: #f4f6f9;
    }

    .stat-card {
        padding: 20px;
        border-radius: 14px;
        color: #fff;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .1);
    }

    .stat-card p {
        margin: 0;
        font-size: 13px;
        opacity: .85
    }

    .stat-card h3 {
        margin: 0;
        font-weight: 700
    }

    .primary {
        background: linear-gradient(135deg, #1e3c72, #2a5298)
    }

    .success {
        background: linear-gradient(135deg, #11998e, #38ef7d)
    }

    .warning {
        background: linear-gradient(135deg, #f7971e, #ffd200)
    }

    .danger {
        background: linear-gradient(135deg, #cb2d3e, #ef473a)
    }

    .card {
        border-radius: 14px
    }

    .card-header {
        background: #fff
    }

    /* ================================
   COMPACT DASHBOARD MODE
================================ */

    /* Reduce metric card height */
    .stat-card {
        padding: 14px 16px;
        /* was 20px */
        min-height: 80px;
        /* control height */
    }

    .stat-card h3 {
        font-size: 20px;
        /* smaller number */
    }

    .stat-card p {
        font-size: 12px;
    }

    /* Reduce spacing between rows */
    .row.g-3 {
        --bs-gutter-y: 0.75rem;
    }

    /* Compact card headers */
    .card-header {
        padding: 8px 14px;
        font-size: 14px;
    }

    /* Compact card body */
    .card-body {
        padding: 10px 14px;
    }

    /* Fix chart card height */
    .card {
        min-height: 260px;
    }

    /* Control canvas height */
    .card canvas {
        max-height: 160px !important;
    }

    /* Revenue chart slightly taller */
    #revenueChart {
        max-height: 180px !important;
    }
</style>
@endpush
@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    /* ===============================
   REVENUE GROWTH (LINE)
================================ */
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun'],
        datasets: [{
            label: 'Revenue (₹ Lakh)',
            data: [2.1, 2.6, 3.1, 2.8, 3.9, 4.2],
            borderColor: '#1e3c72',
            backgroundColor: 'rgba(30,60,114,0.15)',
            tension: 0.4,
            fill: true,
            pointRadius: 4
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

/* ===============================
   VEHICLE CATEGORY (DOUGHNUT)
================================ */
new Chart(document.getElementById('vehicleChart'), {
    type: 'doughnut',
    data: {
        labels: ['SUV','Sedan','Hatchback'],
        datasets: [{
            data: [55, 40, 33],
            backgroundColor: ['#2a5298','#ef473a','#38ef7d']
        }]
    },
    options: { cutout: '65%' }
});

/* ===============================
   DAILY SERVICE LOAD (BAR)
================================ */
new Chart(document.getElementById('serviceChart'), {
    type: 'bar',
    data: {
        labels: ['Mon','Tue','Wed','Thu','Fri','Sat'],
        datasets: [{
            label: 'Services',
            data: [6, 9, 5, 11, 7, 8],
            backgroundColor: '#f7971e'
        }]
    },
    options: { responsive: true }
});

/* ===============================
   SALES CHANNEL SPLIT (PIE)
================================ */
new Chart(document.getElementById('channelChart'), {
    type: 'pie',
    data: {
        labels: ['Direct','Referral','Online'],
        datasets: [{
            data: [55, 30, 15],
            backgroundColor: ['#1e3c72','#38ef7d','#ffd200']
        }]
    }
});

/* ===============================
   WEEKLY BOOKINGS (LINE)
================================ */
const bookingChart = new Chart(document.getElementById('bookingChart'), {
    type: 'line',
    data: {
        labels: ['Week 1','Week 2','Week 3','Week 4'],
        datasets: [{
            label: 'Bookings',
            data: [120, 160, 140, 190],
            borderColor: '#38ef7d',
            tension: 0.4,
            pointRadius: 4
        }]
    }
});

/* ===============================
   PAYMENT STATUS (DOUGHNUT)
================================ */
new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: ['Received','Pending'],
        datasets: [{
            data: [75, 25],
            backgroundColor: ['#11998e','#cb2d3e']
        }]
    },
    options: { cutout: '65%' }
});

/* ===============================
   FAKE REAL-TIME EFFECT
================================ */
setInterval(() => {
    vehicles.innerText = 120 + Math.floor(Math.random() * 10);
    services.innerText = 10 + Math.floor(Math.random() * 10);
    insurance.innerText = 3 + Math.floor(Math.random() * 5);

    revenueChart.data.datasets[0].data.shift();
    revenueChart.data.datasets[0].data.push(
        (Math.random() * 2 + 2.5).toFixed(1)
    );
    revenueChart.update();
}, 5000);
</script>
@endpush
