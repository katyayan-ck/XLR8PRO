@extends('admin.dev.ui._layout')

@section('kit')
@php
    $kpis = [
        ['Enquiries', '1,284', '+12%', 'up', 'kit-spark-1', 'Last 30 days'],
        ['Test drives', '416', '+4%', 'up', 'kit-spark-2', 'Conversion 32%'],
        ['Bookings', '213', '-3%', 'down', 'kit-spark-3', '₹ 18.4 Cr value'],
        ['Deliveries', '187', '+9%', 'up', 'kit-spark-4', '26 this week'],
    ];
    $deals = [
        ['Mohd. Irfan', 'MI', 'Harrier Fearless', 2154000, 'Negotiation', 'bg-orange-lt', 70],
        ['Vikram Rathore', 'VR', 'Safari Accomplished', 2489000, 'Quote sent', 'bg-azure-lt', 45],
        ['Rahul Sharma', 'RS', 'Nexon Creative +', 845000, 'Booking', 'bg-green-lt', 90],
        ['Anita Verma', 'AV', 'Punch Adventure', 712000, 'Test drive', 'bg-purple-lt', 30],
    ];
@endphp
<div class="row row-deck row-cards">

    {{-- KPI cards --}}
    @foreach ($kpis as [$label, $value, $delta, $dir, $chart, $hint])
        <div class="col-sm-6 col-xl-3">
            <div class="card xl-kpi">
                <div class="card-body pb-0">
                    <div class="d-flex align-items-center">
                        <div class="subheader">{{ $label }}</div>
                        <div class="ms-auto">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-secondary small" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Last 30 days</a>
                                <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item active" href="#">Last 30 days</a><a class="dropdown-item" href="#">This quarter</a><a class="dropdown-item" href="#">This FY</a></div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mt-1">
                        <div class="xl-kpi-value">{{ $value }}</div>
                        <span class="{{ $dir === 'up' ? 'text-green' : 'text-red' }} d-inline-flex align-items-center small fw-medium">
                            {{ $delta }} <i class="la {{ $dir === 'up' ? 'la-arrow-up' : 'la-arrow-down' }} ms-1"></i>
                        </span>
                    </div>
                    <div class="text-secondary small">{{ $hint }}</div>
                </div>
                <div id="{{ $chart }}" class="xl-kpi-chart"></div>
            </div>
        </div>
    @endforeach

    {{-- Revenue --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Booking value vs target</h3>
                <div class="card-actions">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Range">
                        <button type="button" class="btn active">FY</button><button type="button" class="btn">Quarter</button><button type="button" class="btn">Month</button>
                    </div>
                </div>
            </div>
            <div class="card-body"><div id="kit-revenue" class="xl-chart"></div></div>
        </div>
    </div>

    {{-- Lead sources --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Lead sources</h3></div>
            <div class="card-body"><div id="kit-sources" class="xl-chart"></div></div>
        </div>
    </div>

    {{-- Funnel --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Sales funnel</h3></div>
            <div class="card-body"><div id="kit-funnel" class="xl-chart"></div></div>
        </div>
    </div>

    {{-- Deals --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Hot deals</h3>
                <div class="card-actions"><a href="#" class="btn btn-sm">View all</a></div>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th>Customer</th><th class="d-none d-md-table-cell">Vehicle</th><th>Stage</th><th class="text-end">Value</th><th class="d-none d-sm-table-cell w-25">Probability</th></tr></thead>
                    <tbody>
                        @foreach ($deals as [$name, $initials, $vehicle, $amount, $stage, $bg, $prob])
                            <tr>
                                <td><div class="d-flex align-items-center"><span class="avatar avatar-sm me-2 bg-primary-lt">{{ $initials }}</span><span class="fw-medium">{{ $name }}</span></div></td>
                                <td class="d-none d-md-table-cell text-secondary">{{ $vehicle }}</td>
                                <td><span class="badge {{ $bg }}">{{ $stage }}</span></td>
                                <td class="text-end text-nowrap">₹ {{ number_format($amount / 100000, 2) }} L</td>
                                <td class="d-none d-sm-table-cell">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress progress-sm flex-fill"><div class="progress-bar" style="width: {{ $prob }}%" role="progressbar" aria-valuenow="{{ $prob }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $prob }}%"></div></div>
                                        <span class="small text-secondary">{{ $prob }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Model mix --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Bookings by model</h3></div>
            <div class="card-body"><div id="kit-models" class="xl-chart"></div></div>
        </div>
    </div>

    {{-- Activity --}}
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Recent activity</h3></div>
            <div class="card-body">
                <div class="divide-y">
                    @foreach ([['PM', 'bg-purple-lt', 'Priya booked a Nexon for Rahul Sharma', '12 min ago'], ['KS', 'bg-green-lt', 'Karan closed a test drive in Ajmer', '1 h ago'], ['DR', 'bg-orange-lt', 'Deepak raised an extra-discount request', '3 h ago'], ['NK', 'bg-azure-lt', 'Neha uploaded KYC for BK/0142', 'yesterday']] as [$ini, $bg, $text, $when])
                        <div>
                            <div class="row g-2">
                                <div class="col-auto"><span class="avatar avatar-sm {{ $bg }}">{{ $ini }}</span></div>
                                <div class="col"><div class="text-truncate-2">{{ $text }}</div><div class="text-secondary small">{{ $when }}</div></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Leaderboard + follow-ups --}}
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Today's follow-ups</h3></div>
            <div class="list-group list-group-flush">
                @foreach ([['10:30', 'Call Sneha Joshi', 'Tiago · finance docs'], ['12:00', 'Test drive — Arjun Gupta', 'Harrier · Jaipur'], ['15:45', 'Delivery — Pooja Nair', 'Punch · Kota'], ['17:30', 'Quote revision', 'Safari · GM approval']] as [$time, $title, $sub])
                    <label class="list-group-item d-flex gap-3 align-items-start">
                        <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" aria-label="Done">
                        <span class="flex-fill"><span class="d-block fw-medium">{{ $title }}</span><span class="text-secondary small">{{ $sub }}</span></span>
                        <span class="badge bg-secondary-lt">{{ $time }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
@basset('https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js', true, ['integrity' => 'sha256-VvsSKf53yMxm8x6hJb6p7TejhAyXtmoj5EyFHP9xeys=', 'crossorigin' => 'anonymous'])
<script>
/* Theme-aware ApexCharts: every colour is read from Tabler tokens, and charts are rebuilt when the colour mode,
   primary colour or font changes (XL.theme.onChange). Copy this pattern for real dashboards. */
document.addEventListener('DOMContentLoaded', function () {
    if (!window.ApexCharts || !window.XL || !XL.theme) { return; }
    var charts = [];

    function t(name) { return XL.theme.token(name); }
    function base(type, height, extra) {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        return Object.assign({
            chart: { type: type, height: height, fontFamily: t('--tblr-body-font-family'), foreColor: t('--tblr-secondary'), background: 'transparent',
                toolbar: { show: false }, animations: { enabled: true }, parentHeightOffset: 0, sparkline: { enabled: false } },
            theme: { mode: dark ? 'dark' : 'light' },
            grid: { borderColor: t('--tblr-border-color'), strokeDashArray: 4, padding: { top: -10, right: 0, left: -4, bottom: -4 } },
            tooltip: { theme: dark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            legend: { labels: { colors: t('--tblr-secondary') }, fontFamily: t('--tblr-body-font-family') },
        }, extra);
    }
    function spark(id, type, data, colour) {
        return new ApexCharts(document.getElementById(id), base(type, 48, {
            chart: { type: type, height: 48, sparkline: { enabled: true }, animations: { enabled: false } },
            series: [{ name: 'Value', data: data }], colors: [t(colour)],
            stroke: { width: type === 'bar' ? 0 : 2, curve: 'smooth' },
            fill: { opacity: type === 'area' ? .16 : 1 },
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 2 } },
            tooltip: { enabled: false },
        }));
    }
    var months = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];

    function build() {
        charts.forEach(function (c) { c.destroy(); });
        charts = [
            spark('kit-spark-1', 'area', [31, 40, 28, 51, 42, 60, 55, 72, 68, 80], '--tblr-primary'),
            spark('kit-spark-2', 'bar', [12, 18, 14, 20, 16, 22, 19, 25, 21, 24], '--tblr-green'),
            spark('kit-spark-3', 'line', [22, 20, 24, 19, 21, 18, 20, 17, 19, 18], '--tblr-orange'),
            spark('kit-spark-4', 'area', [10, 14, 12, 18, 16, 21, 19, 24, 22, 26], '--tblr-purple'),
            new ApexCharts(document.getElementById('kit-revenue'), base('area', 280, {
                series: [
                    { name: 'Booked (₹ Cr)', data: [11.2, 12.8, 10.9, 14.1, 15.6, 18.4, 21.2, 16.9, 13.4, 12.1, 14.8, 19.6] },
                    { name: 'Target (₹ Cr)', data: [12, 12, 12, 14, 14, 16, 20, 18, 14, 13, 14, 18] },
                ],
                colors: [t('--tblr-primary'), t('--tblr-secondary')],
                stroke: { width: [2, 2], curve: 'smooth', dashArray: [0, 5] },
                fill: { type: ['gradient', 'solid'], opacity: [1, 0], gradient: { opacityFrom: .3, opacityTo: .02 } },
                xaxis: { categories: months, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { formatter: function (v) { return '₹ ' + v.toFixed(0) + ' Cr'; } } },
                legend: { position: 'top', horizontalAlign: 'right', labels: { colors: t('--tblr-secondary') } },
            })),
            new ApexCharts(document.getElementById('kit-sources'), base('donut', 280, {
                series: [38, 24, 17, 12, 9],
                labels: ['Walk-in', 'Website', 'Referral', 'Campaign', 'Social'],
                colors: [t('--tblr-primary'), t('--tblr-azure'), t('--tblr-green'), t('--tblr-orange'), t('--tblr-purple')],
                stroke: { colors: [t('--tblr-bg-surface')] },
                legend: { position: 'bottom', labels: { colors: t('--tblr-secondary') } },
                plotOptions: { pie: { donut: { size: '72%', labels: { show: true, value: { color: t('--tblr-body-color') }, total: { show: true, label: 'Leads', color: t('--tblr-secondary') } } } } },
            })),
            new ApexCharts(document.getElementById('kit-funnel'), base('bar', 280, {
                series: [{ name: 'Count', data: [1284, 416, 302, 213, 187] }],
                colors: [t('--tblr-primary')],
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%', distributed: false, isFunnel: true } },
                dataLabels: { enabled: true, formatter: function (v, o) { return o.w.globals.labels[o.dataPointIndex] + ': ' + v; }, style: { colors: [t('--tblr-primary-fg') || t('--tblr-white')] } },
                xaxis: { categories: ['Enquiry', 'Test drive', 'Quote', 'Booking', 'Delivery'] },
                legend: { show: false },
            })),
            new ApexCharts(document.getElementById('kit-models'), base('bar', 280, {
                series: [{ name: 'This FY', data: [142, 131, 96, 64, 44, 38] }, { name: 'Last FY', data: [120, 98, 104, 71, 39, 30] }],
                colors: [t('--tblr-primary'), t('--tblr-border-color')],
                plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
                xaxis: { categories: ['Nexon', 'Punch', 'Tiago', 'Altroz', 'Harrier', 'Safari'], axisBorder: { show: false }, axisTicks: { show: false } },
                legend: { position: 'top', horizontalAlign: 'right', labels: { colors: t('--tblr-secondary') } },
            })),
        ];
        charts.forEach(function (c) { c.render(); });
    }

    build();
    var last = null;
    XL.theme.onChange(function (state) {
        var key = [document.documentElement.getAttribute('data-bs-theme'), state.primary, state.base, state.font].join('|');
        if (key !== last) { last = key; build(); }
    });
    last = [document.documentElement.getAttribute('data-bs-theme'), XL.theme.get().primary, XL.theme.get().base, XL.theme.get().font].join('|');
});
</script>
@endpush
