/*
 * Org → Users → Bulk edit (DEC-089 Phase C, to-do W11).
 *
 * An AG-Grid of every employee user in the users-workbook columns. Single-value master cells and multi-value cells are
 * edited with one filter-like picker (search, check / uncheck; multi lists start with ALL and end with NONE where
 * allowed); dependent lists follow the row (primary location ← primary branch, primary division ← primary department,
 * add-on locations ← primary + add-on branches, sub-segments ← segments, models ← sub-segments / segments). Edited and
 * new rows are posted to the server, which saves each through UserRowService (same rules and history as the workbook)
 * and returns per-row results shown in place. Config comes from window.XL_USER_BULK (bulk.blade.php).
 */
(function () {
    'use strict';

    const cfg = window.XL_USER_BULK;
    const gridEl = document.getElementById('bulkGrid');
    if (!cfg || !gridEl || !window.agGrid) {
        return;
    }

    const ALL = 'ALL';
    const NONE = 'NONE';
    const CHUNK = 200;
    const N = cfg.masters.names || {};
    const C = cfg.masters.children || {};
    const KEYS = Object.keys(cfg.headers);
    const TYPE = {
        primary_branch: 'branch', addon_branch: 'branch', primary_location: 'location', addon_location: 'location',
        primary_department: 'department', addon_department: 'department', primary_division: 'division',
        addon_division: 'division', designation: 'designation', reporting_manager: 'employee', vertical: 'vertical',
        segment: 'segment', sub_segment: 'sub_segment', models: 'model',
    };
    const STATES = {
        new: ['New', 'bg-azure-lt'], changed: ['Changed', 'bg-yellow-lt'], failed: ['Failed', 'bg-red-lt'], saved: ['Saved', 'bg-green-lt'],
    };

    const up = (v) => String(v ?? '').trim().toUpperCase();
    const split = (v) => String(v ?? '').split(/[,;\n]+/).map(up).filter(Boolean);
    const codes = (type) => Object.keys(N[type] || {});
    const kids = (map, parents) => [...new Set(parents.flatMap((p) => (C[map] || {})[p] || []))];
    const real = (list) => list.filter((c) => c !== ALL && c !== NONE);
    const notify = (text, type) => (window.XL && XL.notify ? XL.notify(text, type) : window.console && console.warn(text));

    /** Codes a cell of this row may take (the server applies the same rules; held codes are added by the picker). */
    function options(key, d) {
        switch (key) {
            case 'primary_location': return (C['location<branch'] || {})[up(d.primary_branch)] || [];
            case 'primary_division': return (C['division<department'] || {})[up(d.primary_department)] || [];
            case 'addon_branch': return codes('branch').filter((c) => c !== up(d.primary_branch));
            case 'addon_department': return codes('department').filter((c) => c !== up(d.primary_department));
            case 'addon_location': return childOptions(d, 'location', 'location<branch', d.primary_branch, d.addon_branch, d.primary_location);
            case 'addon_division': return childOptions(d, 'division', 'division<department', d.primary_department, d.addon_department, d.primary_division);
            case 'sub_segment': {
                const segs = real(split(d.segment));
                return segs.length ? kids('sub_segment<segment', segs) : codes('sub_segment');
            }
            case 'models': {
                const subs = real(split(d.sub_segment));
                const segs = real(split(d.segment));
                return subs.length ? kids('model<sub_segment', subs) : (segs.length ? kids('model<segment', segs) : codes('model'));
            }
            default: return codes(TYPE[key]);
        }
    }

    /** Add-on children: the primary parent's other children + all children of the add-on parents (ALL parents = any). */
    function childOptions(d, type, map, primaryParent, addonParents, primaryChild) {
        const parents = split(addonParents);
        const pool = parents[0] === ALL ? codes(type) : kids(map, [up(primaryParent), ...real(parents)]);

        return pool.filter((c) => c !== up(primaryChild));
    }

    /** Filter-like picker: search, check / uncheck, ALL / NONE for multi cells, radio-style for single cells. */
    class Picker {
        init(p) {
            this.p = p;
            this.key = p.colDef.field;
            this.multi = cfg.multi.includes(this.key);
            this.allowNone = this.multi && this.key !== 'vertical';
            this.names = N[TYPE[this.key]] || {};
            this.value = p.value ?? '';
            const current = this.multi ? split(p.value) : (up(p.value) ? [up(p.value)] : []);
            this.selected = new Set(current);
            let opts = options(this.key, p.data);
            real(current).forEach((c) => { if (!opts.includes(c)) { opts = [c, ...opts]; } });   // held codes stay visible
            this.entries = this.multi ? [ALL, ...opts, ...(this.allowNone ? [NONE] : [])] : opts;

            this.el = document.createElement('div');
            this.el.className = 'xl-picker';
            this.el.innerHTML = '<input type="search" class="form-control form-control-sm" placeholder="Search…" aria-label="Search options">'
                + '<div class="xl-picker-list" role="listbox"></div>'
                + '<div class="xl-picker-foot"></div>';
            this.search = this.el.querySelector('input');
            this.list = this.el.querySelector('.xl-picker-list');
            this.search.addEventListener('input', () => this.render());
            this.search.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); this.done(); } });
            this.foot(this.el.querySelector('.xl-picker-foot'));
            this.render();
        }

        foot(el) {
            const button = (text, cls, fn, title) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn btn-sm ' + cls;
                b.textContent = text;
                if (title) { b.title = title; }
                b.addEventListener('click', fn);
                el.appendChild(b);
            };
            if (this.multi) {
                button('OK', 'btn-primary', () => this.done());
                button('Keep stored', 'btn-outline-secondary', () => { this.selected.clear(); this.done(); }, 'Blank cell: the saved codes stay as they are');
            } else {
                button('Clear', 'btn-outline-secondary', () => { this.selected.clear(); this.done(); });
            }
            button('Cancel', 'btn-link', () => this.p.api.stopEditing(true));
        }

        render() {
            const q = this.search.value.trim().toUpperCase();
            const type = this.multi ? 'checkbox' : 'radio';
            this.list.textContent = '';
            this.entries.forEach((code) => {
                const name = code === ALL ? 'Every code (no restriction)' : (code === NONE ? 'None (primary only)' : (this.names[code] ?? 'not in the active list'));
                if (q && !code.includes(q) && !String(name).toUpperCase().includes(q)) {
                    return;
                }
                const row = document.createElement('label');
                row.className = 'xl-picker-item' + (code === ALL || code === NONE ? ' is-special' : '');
                const box = document.createElement('input');
                box.type = type;
                box.name = 'xl-picker-' + this.key;
                box.className = 'form-check-input m-0';
                box.checked = this.selected.has(code);
                box.addEventListener('change', () => this.toggle(code, box.checked));
                const text = document.createElement('span');
                text.innerHTML = '<strong></strong> <small class="text-body-secondary"></small>';
                text.querySelector('strong').textContent = code;
                text.querySelector('small').textContent = name;
                row.append(box, text);
                this.list.appendChild(row);
            });
            if (!this.list.childElementCount) {
                this.list.innerHTML = '<div class="xl-empty py-3">No matching codes</div>';
            }
        }

        toggle(code, on) {
            if (!this.multi) {
                this.selected = new Set([code]);
                this.done();

                return;
            }
            if (on && (code === ALL || code === NONE)) {
                this.selected = new Set([code]);
            } else if (on) {
                this.selected.delete(ALL);
                this.selected.delete(NONE);
                this.selected.add(code);
            } else {
                this.selected.delete(code);
            }
            this.render();
        }

        done() {
            const picked = [...this.selected];
            if (!this.multi) {
                this.value = picked[0] ?? '';
            } else if (picked.includes(ALL) || picked.includes(NONE)) {
                this.value = picked.includes(ALL) ? ALL : NONE;
            } else {
                this.value = picked.sort().join(', ');
            }
            this.p.api.stopEditing();
        }

        getGui() { return this.el; }

        afterGuiAttached() { this.search.focus(); }

        getValue() { return this.value; }

        isPopup() { return true; }
    }

    function stateCell(p) {
        const s = STATES[p.value];
        if (!s) {
            return '';
        }
        const span = document.createElement('span');
        span.className = 'badge ' + s[1];
        span.textContent = s[0];

        return span;
    }

    const columnDefs = [
        { field: '_state', headerName: 'Status', pinned: 'left', width: 96, editable: false, cellRenderer: stateCell,
            tooltipValueGetter: (p) => (p.data._messages || []).join('\n') || null },
        ...KEYS.map((key) => {
            const def = { field: key, headerName: cfg.headers[key] };
            if (key === 'emp_code') {
                Object.assign(def, { pinned: 'left', width: 120, editable: (p) => p.data._state === 'new' });
            } else if (key === 'name') {
                Object.assign(def, { pinned: 'left', width: 190 });
            } else if (TYPE[key]) {
                Object.assign(def, {
                    cellEditor: Picker, cellEditorPopup: true, width: cfg.multi.includes(key) ? 190 : 140,
                    tooltipValueGetter: (p) => split(p.value).map((c) => c + (N[TYPE[key]] && N[TYPE[key]][c] ? ' · ' + N[TYPE[key]][c] : '')).join('\n') || null,
                });
            }

            return def;
        }),
    ];

    let show = 'all';
    let gridApi;

    function pending() {
        const nodes = [];
        gridApi.forEachNode((n) => { if (['new', 'changed', 'failed'].includes(n.data._state)) { nodes.push(n); } });

        return nodes;
    }

    function updateCount() {
        const count = pending().length;
        document.getElementById('bulkCount').textContent = count;
        document.getElementById('bulkSave').disabled = count === 0;
    }

    function onChange(e) {
        if (e.oldValue === e.newValue || e.colDef.field === '_state') {
            return;
        }
        const d = e.data;
        d._state = d._state === 'new' ? 'new' : 'changed';
        // A primary parent change clears a child that no longer belongs (blank → the parent's same-code child).
        const deps = { primary_branch: ['primary_location', 'location<branch'], primary_department: ['primary_division', 'division<department'] };
        const dep = deps[e.colDef.field];
        if (dep && d[dep[0]] && !((C[dep[1]] || {})[up(d[e.colDef.field])] || []).includes(up(d[dep[0]]))) {
            d[dep[0]] = '';
        }
        e.api.refreshCells({ rowNodes: [e.node], force: true });
        updateCount();
    }

    function loader(on) {
        document.getElementById('bulkLoader').classList.toggle('d-none', !on);
        document.getElementById('bulkLoader').classList.toggle('d-flex', on);
    }

    async function load() {
        loader(true);
        try {
            const res = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            gridApi.setGridOption('rowData', (await res.json()).rows || []);
        } catch (err) {
            notify('Users could not be loaded (' + err.message + ').', 'error');
        } finally {
            loader(false);
            updateCount();
        }
    }

    async function save() {
        const nodes = pending();
        if (!nodes.length) {
            return;
        }
        gridApi.stopEditing();
        loader(true);
        const totals = { created: 0, updated: 0, failed: 0 };
        try {
            for (let i = 0; i < nodes.length; i += CHUNK) {
                const chunk = nodes.slice(i, i + CHUNK);
                const rows = chunk.map((n) => Object.fromEntries(KEYS.map((k) => [k, n.data[k] ?? ''])));
                const res = await fetch(cfg.saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: JSON.stringify({ rows }),
                });
                const body = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(body.message || ('HTTP ' + res.status));
                }
                (body.rows || []).forEach((r) => {
                    const node = chunk[r.row];
                    if (!node) {
                        return;
                    }
                    totals[r.status] = (totals[r.status] || 0) + 1;
                    node.data._state = r.status === 'failed' ? 'failed' : 'saved';
                    node.data._messages = r.messages || [];
                    if (r.emp_code) {
                        node.data.emp_code = r.emp_code;
                    }
                });
            }
        } catch (err) {
            notify('Save stopped: ' + err.message, 'error');
        } finally {
            loader(false);
        }

        // Reload the saved truth (derived values such as a defaulted location), keeping rows that failed as typed.
        const failed = pending().filter((n) => n.data._state === 'failed').map((n) => ({ ...n.data }));
        await load();
        if (failed.length) {
            const byCode = new Map(failed.filter((d) => d.emp_code).map((d) => [up(d.emp_code), d]));
            const update = [];
            gridApi.forEachNode((n) => {
                const kept = byCode.get(up(n.data.emp_code));
                if (kept) {
                    byCode.delete(up(n.data.emp_code));
                    update.push(Object.assign(n.data, kept));
                }
            });
            const add = failed.filter((d) => !d.emp_code || byCode.has(up(d.emp_code))).map((d) => ({ ...d, _state: 'failed' }));
            gridApi.applyTransaction({ update, add, addIndex: 0 });
            updateCount();
        }
        notify(`Saved: ${totals.created} created, ${totals.updated} updated, ${totals.failed} failed.`, totals.failed ? 'warning' : 'success');
    }

    gridApi = agGrid.createGrid(gridEl, {
        columnDefs,
        rowData: [],
        defaultColDef: { editable: true, resizable: true, sortable: true, filter: true, minWidth: 90, width: 150 },
        rowClassRules: { 'xl-row-failed': (p) => p.data && p.data._state === 'failed' },
        stopEditingWhenCellsLoseFocus: true,
        undoRedoCellEditing: true,
        tooltipShowDelay: 300,
        animateRows: false,
        isExternalFilterPresent: () => show !== 'all',
        doesExternalFilterPass: (node) => (show === 'changed' ? ['new', 'changed'].includes(node.data._state) : node.data._state === 'failed'),
        onCellValueChanged: onChange,
    });

    document.getElementById('bulkSearch').addEventListener('input', (e) => gridApi.setGridOption('quickFilterText', e.target.value));
    document.querySelectorAll('input[name="bulkShow"]').forEach((r) => r.addEventListener('change', () => { show = r.value; gridApi.onFilterChanged(); }));
    document.getElementById('bulkSave').addEventListener('click', save);
    document.getElementById('bulkAdd').addEventListener('click', () => {
        const res = gridApi.applyTransaction({ add: [{ _state: 'new' }], addIndex: 0 });
        gridApi.ensureIndexVisible(0);
        if (res && res.add && res.add[0]) {
            gridApi.startEditingCell({ rowIndex: res.add[0].rowIndex, colKey: 'emp_code' });
        }
        updateCount();
    });
    window.addEventListener('beforeunload', (e) => {
        if (pending().some((n) => n.data._state !== 'failed')) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    load();
}());
