/* Dynamic dashboard (DEC-072): fills each card rendered by admin/dashboard/index.blade.php from
   GET {data-url}/{key}?period=…  Response: {ok, type, data}. Charts are ApexCharts bound to Tabler tokens and rebuilt
   when the colour mode / primary colour / font changes (same pattern as the dev UI kit dashboard). */
(function () {
    'use strict';

    var root = document.getElementById('xl-dashboard');
    if (!root) { return; }
    var baseUrl = root.getAttribute('data-url');
    var period = root.getAttribute('data-period');
    var charts = {};       // key -> {el, spec, instance}

    function token(name, fallback) {
        return (window.XL && XL.theme && XL.theme.token(name)) || fallback || '';
    }

    function inr(v) {
        var n = Number(v) || 0;
        return '₹ ' + n.toLocaleString('en-IN', { maximumFractionDigits: n >= 1000 ? 0 : 2 });
    }

    function fmt(v, format) {
        if (format === 'money') { return inr(v); }
        return (Number(v) || 0).toLocaleString('en-IN');
    }

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (text !== undefined && text !== null) { e.textContent = text; }
        return e;
    }

    function renderKpi(card, d) {
        card.querySelector('[data-slot=value]').textContent = fmt(d.value, d.format);
        card.querySelector('[data-slot=hint]').textContent = d.hint || '';
        var ul = card.querySelector('[data-slot=lines]');
        ul.innerHTML = '';
        (d.lines || []).forEach(function (line) {
            var li = el('li', 'd-flex justify-content-between gap-2');
            li.appendChild(el('span', 'text-body-secondary', line.label));
            var val = el('span', 'fw-medium' + (line.tone ? ' text-' + line.tone : ''), fmt(line.value, line.format));
            li.appendChild(val);
            ul.appendChild(li);
        });
    }

    function renderList(card, d) {
        var box = card.querySelector('[data-slot=list]');
        box.innerHTML = '';
        if (!d.rows || !d.rows.length) {
            box.appendChild(el('p', 'text-body-secondary p-3 mb-0', d.empty || 'Nothing to show.'));
            return;
        }
        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table card-table table-vcenter mb-0');
        var thead = el('thead');
        var hr = el('tr');
        d.columns.forEach(function (c) { hr.appendChild(el('th', null, c)); });
        thead.appendChild(hr);
        var tbody = el('tbody');
        d.rows.forEach(function (row) {
            var tr = el('tr');
            row.forEach(function (cell) { tr.appendChild(el('td', null, cell)); });
            tbody.appendChild(tr);
        });
        table.appendChild(thead);
        table.appendChild(tbody);
        wrap.appendChild(table);
        box.appendChild(wrap);
    }

    function chartOptions(kind, d) {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        var palette = ['--tblr-primary', '--tblr-azure', '--tblr-green', '--tblr-orange', '--tblr-purple', '--tblr-teal', '--tblr-pink', '--tblr-yellow']
            .map(function (n) { return token(n); });
        var base = {
            chart: { type: kind === 'donut' ? 'donut' : 'bar', height: 280, fontFamily: token('--tblr-body-font-family'),
                foreColor: token('--tblr-secondary'), background: 'transparent', toolbar: { show: false }, parentHeightOffset: 0 },
            theme: { mode: dark ? 'dark' : 'light' },
            grid: { borderColor: token('--tblr-border-color'), strokeDashArray: 4 },
            tooltip: { theme: dark ? 'dark' : 'light', y: { formatter: function (v) { return fmt(v, d.format); } } },
            dataLabels: { enabled: false },
            legend: { labels: { colors: token('--tblr-secondary') } },
            colors: palette,
        };
        if (kind === 'donut') {
            return Object.assign(base, {
                series: (d.series[0] || { data: [] }).data, labels: d.labels,
                stroke: { colors: [token('--tblr-bg-surface')] }, legend: { position: 'bottom', labels: { colors: token('--tblr-secondary') } },
                plotOptions: { pie: { donut: { size: '70%', labels: { show: true, value: { color: token('--tblr-body-color'), formatter: function (v) { return fmt(v, d.format); } },
                    total: { show: true, label: 'Total', color: token('--tblr-secondary'), formatter: function (w) { return fmt(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0), d.format); } } } } } },
            });
        }
        // "funnel" = stage counts as labelled horizontal bars (a true funnel hides the small stages next to a large first one)
        var funnel = kind === 'funnel';
        return Object.assign(base, {
            series: d.series, colors: funnel ? palette.slice(0, d.labels.length) : [palette[0]],
            plotOptions: { bar: { horizontal: funnel, distributed: funnel, borderRadius: 4, columnWidth: '55%', barHeight: '65%',
                dataLabels: { position: funnel ? 'top' : 'center' } } },
            dataLabels: funnel ? { enabled: true, offsetX: 28, style: { colors: [token('--tblr-body-color')] }, formatter: function (v) { return fmt(v, d.format); } } : { enabled: false },
            xaxis: { categories: d.labels, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: funnel ? {} : { labels: { formatter: function (v) { return fmt(v, d.format); } } },
            legend: { show: false },
        });
    }

    function drawChart(key) {
        var c = charts[key];
        if (!c || !window.ApexCharts) { return; }
        if (c.instance) { c.instance.destroy(); }
        c.el.innerHTML = '';
        var total = (c.data.series || []).reduce(function (s, x) { return s + (x.data || []).reduce(function (a, b) { return a + (Number(b) || 0); }, 0); }, 0);
        if (!c.data.labels || !c.data.labels.length || total === 0) {
            c.el.appendChild(el('p', 'text-body-secondary mb-0', 'No data for this period.'));
            c.instance = null;
            return;
        }
        c.instance = new ApexCharts(c.el, chartOptions(c.kind, c.data));
        c.instance.render();
    }

    function fail(card) {
        var slot = card.querySelector('[data-slot=value]') || card.querySelector('[data-slot=chart]') || card.querySelector('[data-slot=list]');
        if (slot) { slot.innerHTML = ''; slot.appendChild(el('span', 'text-body-secondary small', 'Could not load')); }
    }

    function load(card) {
        var key = card.getAttribute('data-widget');
        var url = baseUrl + '/' + encodeURIComponent(key) + '?period=' + encodeURIComponent(period);
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
            .then(function (res) {
                var type = card.getAttribute('data-type');
                if (type === 'kpi') { renderKpi(card, res.data); }
                else if (type === 'list') { renderList(card, res.data); }
                else {
                    charts[key] = { el: card.querySelector('[data-slot=chart]'), kind: card.getAttribute('data-chart') || 'bar', data: res.data, instance: null };
                    drawChart(key);
                }
            })
            .catch(function () { fail(card); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        root.querySelectorAll('[data-widget]').forEach(load);
        if (window.XL && XL.theme && XL.theme.onChange) {
            var last = null;
            XL.theme.onChange(function (state) {
                var k = [document.documentElement.getAttribute('data-bs-theme'), state.primary, state.base, state.font].join('|');
                if (k !== last) { last = k; Object.keys(charts).forEach(drawChart); }
            });
        }
    });
})();
