@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Dynamic dashboard (DEC-072). Cards come from config/dashboard.php and are shown only when the user's permissions
    allow; public/js/xl-dashboard.js fills each card from /admin/dashboard/widget/{key}?period=… (numbers inside the
    user's data scope). Patterns: dev UI kit dashboard (KPI cards, ApexCharts bound to Tabler tokens).
--}}
@section('content')
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">{{ $greeting }}</div>
                <h2 class="page-title">{{ $user->display_name ?? $user->username }}</h2>
                <div class="text-body-secondary small">{{ $user->primary_designation ?? '' }}</div>
            </div>
            <div class="col-12 col-md-auto">
                <div class="btn-group flex-wrap" role="group" aria-label="Period">
                    @foreach ($periods as $key => $label)
                        <a href="{{ route('backpack.dashboard', ['period' => $key]) }}"
                           class="btn btn-sm {{ $period->key === $key ? 'btn-primary' : 'btn-outline-secondary' }}"
                           @if ($period->key === $key) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
                <div class="text-body-secondary small mt-1 text-md-end">@sitedate($period->from) – @sitedate($period->to)</div>
            </div>
        </div>
    </div>

    @if ($groups === [])
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="la la-th-large fs-1 text-body-secondary" aria-hidden="true"></i>
                <p class="mt-2 mb-0 text-body-secondary">There are no dashboard cards for your role yet.</p>
            </div>
        </div>
    @endif

    <div id="xl-dashboard" data-url="{{ url(config('backpack.base.route_prefix', 'admin').'/dashboard/widget') }}" data-period="{{ $period->key }}">
        @foreach ($groupNames as $groupKey => $groupName)
            @continue(empty($groups[$groupKey]))
            <h3 class="mt-4 mb-2 text-body-secondary text-uppercase small fw-bold">{{ $groupName }}</h3>
            <div class="row row-deck row-cards">
                @foreach ($groups[$groupKey] as $key => $w)
                    <div class="{{ $w['size'] ?? 'col-sm-6 col-xl-3' }}">
                        <div class="card {{ $w['type'] === 'kpi' ? 'xl-kpi' : '' }}" data-widget="{{ $key }}" data-type="{{ $w['type'] }}" data-chart="{{ $w['chart'] ?? '' }}">
                            @if ($w['type'] === 'kpi')
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="subheader">{{ $w['title'] }}</div>
                                        @if (! empty($w['icon']))
                                            <span class="ms-auto avatar avatar-sm bg-primary-lt"><i class="la {{ $w['icon'] }}" aria-hidden="true"></i></span>
                                        @endif
                                    </div>
                                    <div class="xl-kpi-value mt-1" data-slot="value"><span class="placeholder col-4"></span></div>
                                    <div class="text-body-secondary small" data-slot="hint"></div>
                                    <ul class="list-unstyled small mb-0 mt-2" data-slot="lines"></ul>
                                </div>
                                @if (! empty($w['link']) && Route::has($w['link']))
                                    <a class="card-footer text-reset small py-2" href="{{ route($w['link']) }}">Open <i class="la la-arrow-right ms-1"></i></a>
                                @endif
                            @else
                                <div class="card-header"><h3 class="card-title">{{ $w['title'] }}</h3></div>
                                <div class="card-body {{ $w['type'] === 'list' ? 'p-0' : '' }}">
                                    <div data-slot="{{ $w['type'] === 'chart' ? 'chart' : 'list' }}" class="{{ $w['type'] === 'chart' ? 'xl-chart' : '' }}">
                                        <div class="p-3"><span class="placeholder col-12"></span><span class="placeholder col-8"></span></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('after_scripts')
@basset('https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js', true, ['integrity' => 'sha256-VvsSKf53yMxm8x6hJb6p7TejhAyXtmoj5EyFHP9xeys=', 'crossorigin' => 'anonymous'])
<script src="{{ asset('js/xl-dashboard.js') }}?v={{ filemtime(public_path('js/xl-dashboard.js')) }}"></script>
@endpush
