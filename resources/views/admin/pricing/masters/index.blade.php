@extends(backpack_view('blank'))

@section('title', $title)

{{--
    Pricing master list (DEC-083): AG Grid of the live rows (history toggle), create / edit / remove, .xlsx export and a
    queued import with progress. A save or import outside the Pricing Process queues the automatic recalculation.
--}}
@section('content')
@php
    $key = $master->key();
    $columns = $master->columns();
    $replacesAll = method_exists($master, 'importReplacesAll') && $master->importReplacesAll();
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <div class="text-body-secondary small">Pricing</div>
            <h2 class="mb-0"><i class="la {{ $master->icon() }} me-1" aria-hidden="true"></i>{{ $master->label() }}</h2>
            @if ($master->description())
                <div class="text-body-secondary small mt-1">{{ $master->description() }}</div>
            @endif
        </div>
        <a href="{{ route('pricing.masters.export', $key) }}" class="btn btn-outline-secondary"><i class="la la-download me-1"></i>Export</a>
        @if ($canManage && ! ($locked && $master->recalculates()))
            <a href="{{ route('pricing.masters.create', $key) }}" class="btn btn-primary"><i class="la la-plus me-1"></i>New</a>
        @endif
    </div>

    @foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $flash => $tone)
        @if (session($flash))
            <div class="alert alert-{{ $tone }}" role="alert">{{ session($flash) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif
    @if ($locked && $master->recalculates())
        <div class="alert alert-warning" role="alert"><i class="la la-lock me-1"></i>A Pricing Process is open — this list is read-only until it completes or is discarded.</div>
    @endif

    <div class="row g-3">
        <div class="col-12 {{ $canManage ? 'col-xl-9' : '' }}">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <div class="me-auto text-body-secondary small" id="pm-meta">Loading…</div>
                    <label class="form-check form-switch mb-0" for="pm-history">
                        <input class="form-check-input" type="checkbox" id="pm-history">
                        <span class="form-check-label small">Include expired</span>
                    </label>
                    <label class="visually-hidden" for="pm-search">Search</label>
                    <input type="search" id="pm-search" class="form-control form-control-sm w-auto" placeholder="Search…">
                </div>
                <div id="pm-grid" style="height: calc(100vh - 300px); min-height: 24rem"></div>
            </div>
        </div>

        @if ($canManage)
            <div class="col-12 col-xl-3">
                <form action="{{ route('pricing.masters.import', $key) }}" method="POST" enctype="multipart/form-data" class="card">
                    @csrf
                    <div class="card-header"><h3 class="card-title">Import</h3></div>
                    <div class="card-body">
                        <p class="small text-body-secondary">
                            @if ($replacesAll)
                                Upload the exported sheet (same layout as the Pricing Process workbook). The whole list is replaced at the WEF: current rows expire, the sheet's rows go live. Blank = no rule, 0 = a zero rule.
                            @else
                                Upload the exported sheet. A row with an ID updates that row; a row without an ID is added.@if ($master->hasWef()) A different WEF keeps the old version as history.@endif
                            @endif
                        </p>
                        <div class="mb-3">
                            <label class="form-label required" for="pm-file">Workbook (.xlsx)</label>
                            <x-ui.upload name="file" accept=".xlsx" id="pm-file" :max-kb="20480" required />
                        </div>
                        @if ($master->hasWef())
                            <div class="mb-3">
                                <label class="form-label required" for="pm-wef">WEF</label>
                                <x-ui.date name="wef_date" id="pm-wef" :value="old('wef_date', now()->toDateString())" required />
                            </div>
                        @endif
                        <button type="submit" class="btn btn-primary w-100" @disabled($locked && $master->recalculates())><i class="la la-upload me-1"></i>Import</button>
                    </div>
                    @if ($imports->isNotEmpty())
                        <div class="list-group list-group-flush small">
                            @foreach ($imports as $import)
                                <div class="list-group-item" data-import="{{ $import->id }}" data-status="{{ $import->status }}">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span class="text-truncate" title="{{ $import->file_name }}">{{ $import->file_name }}</span>
                                        <span class="badge pm-status {{ ['done' => 'bg-green-lt', 'failed' => 'bg-red-lt'][$import->status] ?? 'bg-azure-lt' }}">{{ ucfirst($import->status) }}</span>
                                    </div>
                                    <div class="text-body-secondary pm-message">{{ $import->message }}</div>
                                    <div class="text-body-secondary">{{ site_datetime($import->created_at) }}</div>
                                    @php $issues = array_slice((array) ($import->result['issues'] ?? []), 0, 5); @endphp
                                    @foreach ($issues as $issue)
                                        <div class="text-danger">Row {{ $issue['row'] }} — {{ $issue['reason'] }}</div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                </form>
            </div>
        @endif
    </div>
</div>

@if ($canManage)
    <form id="pm-delete" method="POST" class="d-none">@csrf @method('DELETE')</form>
@endif
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@36.2.0/dist/ag-grid-community.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('pm-grid');
    if (!el || !window.agGrid) { return; }
    var columns = @json($columns);
    var canManage = @json($canManage && ! ($locked && $master->recalculates()));
    var rowsUrl = @json(route('pricing.masters.rows', $key));
    var editUrl = @json(route('pricing.masters.edit', [$key, '__ID__']));
    var deleteUrl = @json(route('pricing.masters.destroy', [$key, '__ID__']));
    var money = function (p) { return p.value == null || p.value === '' ? '' : Number(p.value).toLocaleString('en-IN', { maximumFractionDigits: 2 }); };

    var defs = columns.map(function (c) {
        var def = { field: c.field, headerName: c.label, width: c.width || 130, pinned: c.pinned || null };
        if (c.type === 'number') { Object.assign(def, { type: 'rightAligned', valueFormatter: money, filter: 'agNumberColumnFilter' }); }
        else if (c.type === 'date') { def.valueFormatter = function (p) { return p.value ? (window.XL && XL.formatDate ? XL.formatDate(p.value) : p.value) : ''; }; }
        else if (c.type === 'bool') { def.valueFormatter = function (p) { return p.value ? 'Yes' : 'No'; }; }
        else { def.filter = 'agTextColumnFilter'; }
        return def;
    });
    if (canManage) {
        defs.push({ headerName: '', colId: 'actions', pinned: 'right', width: 110, sortable: false, filter: false,
            cellRenderer: function (p) {
                var wrap = document.createElement('div');
                wrap.className = 'd-flex gap-1';
                var edit = document.createElement('a');
                edit.className = 'btn btn-sm btn-outline-primary';
                edit.href = editUrl.replace('__ID__', p.data.id);
                edit.setAttribute('aria-label', 'Edit');
                edit.innerHTML = '<i class="la la-pen"></i>';
                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'btn btn-sm btn-outline-danger';
                del.setAttribute('aria-label', 'Remove');
                del.innerHTML = '<i class="la la-trash"></i>';
                del.addEventListener('click', function () {
                    var go = function () { var f = document.getElementById('pm-delete'); f.action = deleteUrl.replace('__ID__', p.data.id); f.submit(); };
                    if (window.Swal) {
                        Swal.fire({ icon: 'warning', title: 'Remove this row?', text: 'Rows with a WEF are expired today (kept as history).', showCancelButton: true, confirmButtonText: 'Remove' })
                            .then(function (r) { if (r.isConfirmed) { go(); } });
                    } else if (window.confirm('Remove this row?')) { go(); }
                });
                wrap.append(edit, del);
                return wrap;
            } });
    }

    var api = agGrid.createGrid(el, {
        rowData: null, columnDefs: defs,
        defaultColDef: { sortable: true, resizable: true, filter: true },
        enableCellTextSelection: true,
        overlayNoRowsTemplate: '<span class="text-body-secondary">No rows yet.</span>',
    });
    window.XL && (XL.grids = (XL.grids || []).concat([api]));

    function load() {
        var history = document.getElementById('pm-history').checked ? 1 : 0;
        fetch(rowsUrl + '?history=' + history, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
            .then(function (data) {
                api.setGridOption('rowData', data.rows);
                document.getElementById('pm-meta').textContent = data.rows.length.toLocaleString('en-IN') + ' row(s)' + (history ? ' incl. expired' : '');
            })
            .catch(function () { document.getElementById('pm-meta').textContent = 'The list could not be loaded — refresh to try again.'; api.setGridOption('rowData', []); });
    }
    document.getElementById('pm-history').addEventListener('change', load);
    document.getElementById('pm-search').addEventListener('input', function (e) { api.setGridOption('quickFilterText', e.target.value); });
    load();

    // import progress: poll running / queued imports until they finish, then reload the rows
    var statusUrl = @json(route('pricing.masters.import-status', [$key, '__ID__']));
    document.querySelectorAll('[data-import]').forEach(function (item) {
        if (['queued', 'running'].indexOf(item.dataset.status) === -1) { return; }
        var timer = setInterval(function () {
            fetch(statusUrl.replace('__ID__', item.dataset.import), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (s) {
                    item.querySelector('.pm-message').textContent = s.message || '';
                    var badge = item.querySelector('.pm-status');
                    badge.textContent = s.status.charAt(0).toUpperCase() + s.status.slice(1);
                    if (s.status === 'done' || s.status === 'failed') {
                        clearInterval(timer);
                        badge.className = 'badge pm-status ' + (s.status === 'done' ? 'bg-green-lt' : 'bg-red-lt');
                        (s.issues || []).slice(0, 5).forEach(function (i) {
                            var d = document.createElement('div');
                            d.className = 'text-danger';
                            d.textContent = 'Row ' + i.row + ' — ' + i.reason;
                            item.appendChild(d);
                        });
                        load();
                    }
                })
                .catch(function () { clearInterval(timer); });
        }, 2500);
    });
});
</script>
@endpush
