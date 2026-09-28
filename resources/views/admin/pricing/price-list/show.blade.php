@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Price List (DEC-081): read-only AG Grid of one list's published NV prices on a date, laid out like the reference PDFs —
    vehicle · add-ons (insurance / RTO / other charges bifurcated on hover) · standard discounts · conditional discounts ·
    on-road at the default selections. Rows come from pricing.price-list.rows (projected + cached server-side).
--}}
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small"><a href="{{ route('pricing.price-list.index', ['date' => $date]) }}" class="text-reset">Price List</a> · NV · default selections</div>
            <h2 class="mb-0">{{ $label }}</h2>
        </div>
        <form method="GET" action="{{ route('pricing.price-list.show', $list) }}" class="d-flex align-items-end gap-2">
            <div>
                <label class="form-label mb-1 small" for="pl-date">Prices as on</label>
                <x-ui.date name="date" id="pl-date" :value="$date" />
            </div>
            <button type="submit" class="btn btn-outline-primary">Show</button>
        </form>
    </div>

    <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto" aria-label="Price lists">
        @foreach ($lists as $key => $def)
            <li class="nav-item">
                <a class="nav-link text-nowrap {{ $key === $list ? 'active' : '' }}" href="{{ route('pricing.price-list.show', ['list' => $key, 'date' => $date]) }}" @if ($key === $list) aria-current="page" @endif>{{ $def['label'] }}</a>
            </li>
        @endforeach
    </ul>

    <div id="pl-hold" class="alert alert-warning d-none" role="alert"><i class="la la-pause-circle me-1"></i>This price list is on hold — quotations and bookings are paused until it is reopened.</div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto text-body-secondary small" id="pl-meta">Loading…</div>
            <label class="visually-hidden" for="pl-search">Search</label>
            <input type="search" id="pl-search" class="form-control form-control-sm w-auto" placeholder="Search model, variant, colour, code…">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pl-csv"><i class="la la-download me-1"></i>CSV</button>
        </div>
        <div id="pl-grid" style="height: calc(100vh - 290px); min-height: 26rem"></div>
        <div class="card-footer text-body-secondary small">
            Hover Insurance, RTO or Other charges for the break-up. On-road = ex-showroom + add-ons + TCS − standard discounts; conditional discounts are not deducted.
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@36.2.0/dist/ag-grid-community.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('pl-grid');
    if (!el || !window.agGrid) { return; }
    var money = function (p) { return p.value == null || p.value === '' ? '' : Number(p.value).toLocaleString('en-IN', { maximumFractionDigits: 0 }); };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; };

    /** Break-up tooltip: {title, label: amount, …} as a small table. */
    function BreakupTooltip() {}
    BreakupTooltip.prototype.init = function (params) {
        var tip = params.value || {};
        var html = '<div class="card card-sm shadow-sm"><div class="card-body p-2 small">';
        if (tip.title) { html += '<div class="fw-medium mb-1">' + esc(tip.title) + '</div>'; }
        html += '<table class="table table-sm table-borderless mb-0">';
        Object.keys(tip).forEach(function (k) {
            if (k === 'title') { return; }
            html += '<tr><td class="ps-0 py-0">' + esc(k) + '</td><td class="text-end pe-0 py-0">' + Number(tip[k]).toLocaleString('en-IN', { maximumFractionDigits: 0 }) + '</td></tr>';
        });
        this.gui = document.createElement('div');
        this.gui.innerHTML = html + '</table></div></div>';
    };
    BreakupTooltip.prototype.getGui = function () { return this.gui; };

    var num = function (field, headerName, extra) {
        return Object.assign({ field: field, headerName: headerName, type: 'rightAligned', valueFormatter: money, width: 120, filter: 'agNumberColumnFilter' }, extra || {});
    };
    var tipped = function (field, headerName, tipField) {
        return num(field, headerName, { tooltipValueGetter: function (p) { return p.data && p.data[tipField]; }, tooltipComponent: BreakupTooltip, cellClass: 'text-decoration-underline text-decoration-dotted' });
    };

    function columns(data) {
        var conditional = [];
        if (data.exchange.length) {
            conditional.push({ headerName: 'Exchange', children: data.exchange.map(function (s) {
                return num(undefined, s, { colId: 'exchange:' + s, valueGetter: function (p) { return p.data.exchange[s] || 0; } });
            }) });
        }
        if (data.corporate.length) {
            conditional.push({ headerName: 'Corporate', children: data.corporate.map(function (c) {
                return num(undefined, c, { colId: 'corporate:' + c, valueGetter: function (p) { return p.data.corporate[c] || 0; } });
            }) });
        }
        var defs = [
            { headerName: 'Vehicle', children: [
                { field: 'model', headerName: 'Model', pinned: 'left', width: 150, filter: 'agTextColumnFilter' },
                { field: 'variant', headerName: 'Variant', pinned: 'left', width: 230, filter: 'agTextColumnFilter', tooltipField: 'code' },
                { field: 'colour', headerName: 'Colour', width: 150, filter: 'agTextColumnFilter' },
                num('ex_showroom', 'Ex-showroom', { width: 130 }),
            ] },
            { headerName: 'Add-ons', children: [
                num('incidental', 'Incidental'),
                num('fastag_trc', 'FASTag + TRC', { width: 130 }),
                tipped('other_charges', 'Other charges', 'other_tip'),
                num('rsa', 'RSA', { tooltipValueGetter: function (p) { return p.data && p.data.rsa_years ? p.data.rsa_years + ' year(s)' : null; } }),
                num('shield', 'Shield'),
                num('accessories', 'Accessories', { width: 125 }),
                tipped('insurance', 'Insurance', 'insurance_tip'),
                tipped('rto', 'RTO', 'rto_tip'),
            ] },
            { headerName: 'Standard discounts', children: [
                num('disc_consumer', 'Consumer scheme', { width: 150 }),
                num('disc_cash', 'Cash'),
                num('disc_accessory', 'Accessory'),
                num('disc_shield', 'Shield'),
                num('disc_rsa', 'RSA'),
                num('disc_total', 'Total discount', { width: 140, cellClass: 'fw-medium' }),
            ] },
        ];
        if (conditional.length) { defs.push({ headerName: 'Conditional discounts', children: conditional }); }
        defs.push({ headerName: 'On-road', children: [
            num('tcs', 'TCS', { width: 110 }),
            num('invoice', 'Invoice', { width: 130 }),
            num('on_road', 'On-road', { width: 140, pinned: 'right', cellClass: 'fw-bold text-primary' }),
        ] });

        // hide an amount column that is zero on every row (keeps the grid as dense as the PDFs)
        var rows = data.rows;
        (function hideEmpty(list) {
            list.forEach(function (c) {
                if (c.children) { hideEmpty(c.children); return; }
                if (c.type === 'rightAligned' && !c.valueGetter && c.field !== 'on_road' && c.field !== 'ex_showroom') {
                    c.hide = !rows.some(function (r) { return Number(r[c.field]) !== 0; });
                }
            });
        })(defs);

        return defs;
    }

    var api = agGrid.createGrid(el, {
        rowData: null,
        columnDefs: [],
        defaultColDef: { sortable: true, resizable: true, filter: true, suppressHeaderMenuButton: false },
        tooltipShowDelay: 150,
        enableCellTextSelection: true,
        overlayNoRowsTemplate: '<span class="text-body-secondary">Nothing is published for this list on this date.</span>',
        getRowId: function (p) { return p.data.code; },
    });
    window.XL && (XL.grids = (XL.grids || []).concat([api]));

    var url = @json(route('pricing.price-list.rows', ['list' => $list, 'date' => $date]));
    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
        .then(function (data) {
            api.setGridOption('columnDefs', columns(data));
            api.setGridOption('rowData', data.rows);
            document.getElementById('pl-hold').classList.toggle('d-none', !data.hold);
            var wef = (data.wef || []).map(function (d) { return window.XL && XL.formatDate ? XL.formatDate(d) : d; }).join(', ');
            document.getElementById('pl-meta').textContent = data.rows.length.toLocaleString('en-IN') + ' vehicle(s)' + (wef ? ' · WEF ' + wef : '');
        })
        .catch(function () {
            document.getElementById('pl-meta').textContent = 'The price list could not be loaded — refresh to try again.';
            api.setGridOption('rowData', []);
        });

    document.getElementById('pl-search').addEventListener('input', function (e) { api.setGridOption('quickFilterText', e.target.value); });
    document.getElementById('pl-csv').addEventListener('click', function () {
        api.exportDataAsCsv({ fileName: 'price-list-{{ $list }}-{{ $date }}.csv', processCellCallback: function (p) { return p.value && typeof p.value === 'object' ? '' : p.value; } });
    });
});
</script>
@endpush
