<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="mobile-web-app-capable" content="yes" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('/images/apple-touch-icon.png') }}">
@if ($xlFavicon = site_favicon_url())
{{-- DEC-091: the favicon uploaded on Settings → Site replaces the built-in icons --}}
<link rel="icon" href="{{ $xlFavicon }}">
@else
<link rel="icon" type="image/png" href="{{ asset('/images/favicon-32x32.png') }}" sizes="32x32">
<link rel="icon" type="image/png" href="{{ asset('/images/favicon-16x16.png') }}" sizes="16x16">
@endif
<link rel="manifest" href="{{ asset('/images/site.webmanifest') }}">
<link rel="mask-icon" href="{{ asset('/images/safari-pinned-tab.svg') }}" color="#161c2d">
<link rel="shortcut icon" href="{{ site_favicon_url() ?? asset('/images/favicon.ico') }}">
<meta name="theme-color" content="#161c2d">
<meta name="apple-mobile-web-app-title" content="{{ site_title() }}">
<meta name="application-name" content="{{ site_title() }}">
<meta name="msapplication-TileColor" content="#161c2d">
<meta name="msapplication-config" content="{{asset('/images/browserconfig.xml')}}">

{{-- Xceler8 UI layer (DEC-066): site formats + limits for the shared date / select / upload widgets --}}
@php $xlDates = app(\App\Services\DateFormatService::class); @endphp
<meta name="xl-date-format" content="{{ $xlDates->phpFormat() }}">
<meta name="xl-datetime-format" content="{{ $xlDates->phpDateTimeFormat() }}">
<meta name="xl-upload-max-kb" content="{{ (int) setting('docs.max_upload_kb', 10240) }}">
<meta name="xl-upload-types" content="{{ setting('docs.allowed_mimes', '') }}">
<link rel="stylesheet" href="{{ asset('css/xl-ui.css') }}?v={{ @filemtime(public_path('css/xl-ui.css')) }}">
<script defer src="{{ asset('js/xl-ui.js') }}?v={{ @filemtime(public_path('js/xl-ui.js')) }}"></script>
<script defer src="{{ asset('js/xl-theme.js') }}?v={{ @filemtime(public_path('js/xl-theme.js')) }}"></script>
{{-- F1 help pane (DEC-094, W16b) — signed-in admin pages; the article is fetched on the first open only. `article`
     (key + updated) drives the "new" dot on ?; tours run with Driver.js (W16c, approved in DEC-094) --}}
@if (backpack_user())
    @php
        $xlHelpRoute = (string) \Illuminate\Support\Facades\Route::currentRouteName();
        $xlHelpArticle = $xlHelpRoute !== '' ? app(\App\Services\Platform\Help\HelpService::class)->forRoute($xlHelpRoute, backpack_user(), false) : null;
    @endphp
    <meta name="xl-help" content="{{ json_encode(['route' => $xlHelpRoute, 'pane' => route('utils.help.pane'),
        'search' => route('utils.help.search'), 'centre' => route('utils.help.index'), 'labels' => trans('utils.help'),
        'support' => ['store' => route('utils.support.store'), 'list' => route('utils.tickets.index'), 'labels' => trans('utils.support')],
        'article' => $xlHelpArticle ? ['key' => $xlHelpArticle['key'], 'updated' => (string) $xlHelpArticle['updated'], 'tour' => $xlHelpArticle['tour'] !== []] : null]) }}">
    @if ($xlHelpArticle && $xlHelpArticle['tour'] !== [])
        @basset('https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css')
        @basset('https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js')
    @endif
    <script defer src="{{ asset('js/xl-help.js') }}?v={{ @filemtime(public_path('js/xl-help.js')) }}"></script>
    {{-- Diagnostics for support requests (DEC-094, W16d): action / network / error buffer; html2canvas (approved) is cached
         by Basset here but only loaded when a screenshot is taken --}}
    @php
        $xlH2c = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
        try {
            \Backpack\Basset\Facades\Basset::basset($xlH2c, false);
            $xlH2cUrl = \Backpack\Basset\Facades\Basset::isAssetCached($xlH2c) ? \Backpack\Basset\Facades\Basset::getUrl($xlH2c) : $xlH2c;
        } catch (\Throwable $e) {
            $xlH2cUrl = $xlH2c;
        }
    @endphp
    <meta name="xl-diag" content="{{ json_encode(['route' => $xlHelpRoute, 'version' => config('app.version'), 'html2canvas' => $xlH2cUrl]) }}">
    <script defer src="{{ asset('js/xl-diag.js') }}?v={{ @filemtime(public_path('js/xl-diag.js')) }}"></script>
@endif
{{-- Idle auto-logout / screen lock (go-live to-do S1 / S2) — signed-in pages only, never on the lock screen itself --}}
@if (backpack_user() && ! request()->routeIs('xl.session.lock-screen'))
    @php $xlIdle = app(\App\Services\IAM\SessionGuardService::class)->config(); @endphp
    @if ($xlIdle['logout'] > 0 || $xlIdle['lock'] > 0 || $xlIdle['lock_enabled'])
        <meta name="xl-idle" content="{{ json_encode(['logout' => $xlIdle['logout'], 'lock' => $xlIdle['lock'], 'warning' => $xlIdle['warning'],
            'activity' => route('xl.session.activity'), 'lockUrl' => route('xl.session.lock'), 'lockScreen' => route('xl.session.lock-screen'),
            'logoutUrl' => backpack_url('logout'), 'login' => route('backpack.auth.login')]) }}">
        <script defer src="{{ asset('js/xl-idle.js') }}?v={{ @filemtime(public_path('js/xl-idle.js')) }}"></script>
    @endif
@endif
<script>
/* DEC-066: every AG-Grid date column follows the site date format. Wraps agGrid.createGrid the moment
   AG-Grid loads; date-like columns without their own formatter get XL.formatDate (public/js/xl-ui.js).
   Unparseable values are shown as they are. */
(function () {
    var XL = window.XL = window.XL || {};
    XL.grids = XL.grids || [];
    /* DEC-068: site date formats available synchronously, so view scripts (which run before the deferred xl-ui.js)
       can give their own flatpickr instances a site-format display: altInput: true, altFormat: XL.flatpickrFormat(). */
    XL.dateFormat = @json($xlDates->phpFormat());
    XL.dateTimeFormat = @json($xlDates->phpDateTimeFormat());
    XL.flatpickrFormat = function (withTime) { return withTime ? XL.dateTimeFormat : XL.dateFormat; };
    function isDateColumn(c) {
        var f = String(c.field || ''), h = String(c.headerName || '');
        if (c.valueFormatter || c.cellRenderer || c.xlDate === false) return false;
        return /(_at|_date|Date|^date|_dob|^dob|_wef|^wef)$/.test(f) || (/\b(date|dob|d\.o\.b)\b/i.test(h) && !/days|count|no\.?$/i.test(h));
    }
    function patch(cols) {
        (cols || []).forEach(function (c) {
            if (c.children) { patch(c.children); return; }
            if (!isDateColumn(c)) return;
            var withTime = /_at$/.test(String(c.field || ''));
            c.valueFormatter = function (p) { return p.value && window.XL.formatDate ? window.XL.formatDate(p.value, withTime, p.value) : p.value; };
        });
    }
    /* DEC-067: AG-Grid v33+ themes itself in JS (light Quartz by default), so CSS-only theming never reached it.
       Grids that set no theme get Quartz with Tabler CSS variables as parameters: they follow the colour mode, primary
       colour, font and radius from the Appearance panel live, without re-creating the grid. */
    var tablerTheme = null;
    function theme(ag) {
        if (tablerTheme || !ag.themeQuartz || typeof ag.themeQuartz.withParams !== 'function') return tablerTheme;
        tablerTheme = ag.themeQuartz.withParams({
            browserColorScheme: 'inherit',
            backgroundColor: 'var(--tblr-bg-surface)',
            foregroundColor: 'var(--tblr-body-color)',
            borderColor: 'var(--tblr-border-color)',
            accentColor: 'var(--tblr-primary)',
            chromeBackgroundColor: 'var(--tblr-bg-surface-secondary)',
            headerBackgroundColor: 'var(--tblr-bg-surface-secondary)',
            headerTextColor: 'var(--tblr-body-color)',
            rowHoverColor: 'rgba(var(--tblr-primary-rgb), .06)',
            selectedRowBackgroundColor: 'rgba(var(--tblr-primary-rgb), .12)',
            fontFamily: 'var(--tblr-body-font-family)',
            fontSize: 13,
            headerFontWeight: 600,
            borderRadius: 'var(--tblr-border-radius)',
            wrapperBorderRadius: 'var(--tblr-border-radius)',
            rowHeight: 38,
            headerHeight: 40,
            cellHorizontalPadding: 10,
        });
        return tablerTheme;
    }
    XL.agTheme = function () { return window.agGrid ? theme(window.agGrid) : null; };
    /* v33+ exports are getter-only (assigning ag.createGrid is silently ignored), so the hook returns a copy of the
       module with its own createGrid instead of patching the module in place. */
    function wrap(ag) {
        if (!ag || ag.__xl || typeof ag.createGrid !== 'function') return ag;
        var original = ag.createGrid;
        ag = Object.assign({}, ag);
        ag.createGrid = function (el, options, params) {
            if (options && options.theme === undefined && theme(ag)) {
                options.theme = theme(ag);
                /* legacy views also load styles/ag-theme-*.css, whose fixed light values beat the JS theme: switch those
                   sheets off (the ag-theme-quartz class stays, so view-level tweaks keep working) */
                document.querySelectorAll('link[href*="ag-grid-community"][href*="/styles/"]').forEach(function (l) { l.disabled = true; });
            }
            if (options && options.columnDefs) patch(options.columnDefs);
            var api = original.call(this, el, options, params);
            XL.grids.push(api);
            return api;
        };
        ag.__xl = true;
        return ag;
    }
    var current = window.agGrid;
    try {
        Object.defineProperty(window, 'agGrid', { configurable: true, get: function () { return current; }, set: function (v) { current = wrap(v); } });
    } catch (e) { /* property locked: xl-ui.js wraps on load instead */ }
    if (current) current = wrap(current);
    XL.wrapAgGrid = wrap;
})();
</script>
