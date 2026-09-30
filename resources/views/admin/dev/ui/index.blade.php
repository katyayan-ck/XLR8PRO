@extends('admin.dev.ui._layout')

@section('kit')
@php
    $cards = [
        'forms' => 'Inputs, groups, validation, Select2, site-format pickers, drop-zone uploads, switches, select groups, a full two-column form and a wizard.',
        'lists' => 'Card tables with toolbar, avatars, status and actions; an AG-Grid that follows the theme; list groups; empty states; pagination.',
        'elements' => 'Buttons, badges, alerts, avatars, cards, dropdowns, modals, toasts, tabs, accordion, progress, steps, timeline, ribbons, placeholders.',
        'dashboard' => 'A CRM dashboard: KPI cards with sparklines, revenue and pipeline charts (ApexCharts, theme-aware), leads, activity and team tables.',
        'chat' => 'A chat / inbox interface: conversation list with search and unread counts, bubbles, attachments, typing indicator, composer.',
        'pages' => 'Profile header, settings layout, invoice, pricing cards, kanban board, activity feed and error / empty pages.',
    ];
@endphp
<div class="row row-cards">
    <div class="col-12">
        <div class="card card-md">
            <div class="card-stamp card-stamp-lg"><div class="card-stamp-icon bg-primary"><i class="la la-swatchbook"></i></div></div>
            <div class="card-body">
                <h3 class="h1 mb-2">Xceler8 UI kit</h3>
                <p class="text-secondary mb-3 col-lg-8">
                    Reference screens for developers. Everything here is plain Tabler 1.4 markup plus the shared layer
                    (<code>xl-ui</code>, <code>xl-theme</code>, <code>&lt;x-ui.*&gt;</code> components). Copy the markup, keep the
                    classes, never add hex colours or per-view CDN tags. Try the <strong>Appearance</strong> panel: colour mode,
                    scheme, base, font, radius and menu layout change every sample live, including the grid and the charts.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-blue-lt">Tabler 1.4</span>
                    <span class="badge bg-green-lt">Dark mode safe</span>
                    <span class="badge bg-purple-lt">360 / 768 / desktop</span>
                    <span class="badge bg-orange-lt">Site date format</span>
                    <span class="badge bg-azure-lt">Free / OSS only</span>
                </div>
            </div>
        </div>
    </div>
    @foreach ($cards as $key => $text)
        <div class="col-sm-6 col-lg-4">
            <a href="{{ route('dev.ui.show', ['page' => $key]) }}" class="card card-link card-link-pop h-100 text-reset text-decoration-none">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <span class="avatar bg-primary-lt"><i class="la {{ $pages[$key][1] }} fs-2"></i></span>
                        <h3 class="card-title mb-0">{{ $pages[$key][0] }}</h3>
                    </div>
                    <p class="text-secondary mb-0">{{ $text }}</p>
                </div>
            </a>
        </div>
    @endforeach
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Rules of thumb</h3></div>
            <div class="list-group list-group-flush">
                <div class="list-group-item"><i class="la la-palette text-primary me-2"></i>Colours: <code>bg-*-lt</code>, <code>text-secondary</code>, <code>var(--tblr-*)</code> — never <code>#hex</code> or <code>bg-surface</code>.</div>
                <div class="list-group-item"><i class="la la-calendar text-primary me-2"></i>Dates: <code>&lt;x-ui.date&gt;</code>, <code>@@sitedate</code>, <code>XL.formatDate()</code>.</div>
                <div class="list-group-item"><i class="la la-list text-primary me-2"></i>Multi-selects: <code>&lt;x-ui.select multiple&gt;</code> (Select2). Uploads: <code>&lt;x-ui.upload&gt;</code>.</div>
                <div class="list-group-item"><i class="la la-table text-primary me-2"></i>AG-Grid: don't pass <code>theme</code>; the global hook applies the Tabler-bound theme.</div>
                <div class="list-group-item"><i class="la la-mobile text-primary me-2"></i>Responsive: grid classes, <code>.table-responsive</code>, no fixed pixel widths, tap targets ≥ 40px.</div>
                <div class="list-group-item"><i class="la la-chart-bar text-primary me-2"></i>Charts: ApexCharts via <code>@@basset</code> (pinned 3.54.1), colours from <code>XL.theme.token()</code>, re-render on <code>XL.theme.onChange</code> — see the dashboard source.</div>
            </div>
        </div>
    </div>
</div>
@endsection
