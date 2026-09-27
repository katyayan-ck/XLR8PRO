<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="mobile-web-app-capable" content="yes" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('/images/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" href="{{ asset('/images/favicon-32x32.png') }}" sizes="32x32">
<link rel="icon" type="image/png" href="{{ asset('/images/favicon-16x16.png') }}" sizes="16x16">
<link rel="manifest" href="{{ asset('/images/site.webmanifest') }}">
<link rel="mask-icon" href="{{ asset('/images/safari-pinned-tab.svg') }}" color="#161c2d">
<link rel="shortcut icon" href="{{ asset('/images/favicon.ico') }}">
<meta name="theme-color" content="#161c2d">
<meta name="apple-mobile-web-app-title" content="Xceler8 DMS">
<meta name="application-name" content="Xceler8 DMS">
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
<script>
/* DEC-066: every AG-Grid date column follows the site date format. Wraps agGrid.createGrid the moment
   AG-Grid loads; date-like columns without their own formatter get XL.formatDate (public/js/xl-ui.js).
   Unparseable values are shown as they are. */
(function () {
    var XL = window.XL = window.XL || {};
    XL.grids = XL.grids || [];
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
    function wrap(ag) {
        if (!ag || ag.__xl || typeof ag.createGrid !== 'function') return ag;
        var original = ag.createGrid;
        ag.createGrid = function (el, options, params) {
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
    if (current) wrap(current);
    XL.wrapAgGrid = wrap;
})();
</script>
