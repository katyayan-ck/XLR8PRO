{{--
    =====================================================================
    resources/views/admin/booking/list.blade.php
    ---------------------------------------------------------------------
    Booking List / Grid View  (Live | On-Hold | Invoiced | Cancelled)
    ---------------------------------------------------------------------
    OPTIMIZED VERSION — see changelog.md for a full previous-vs-new diff.

    What changed at a glance (details + reasoning live in changelog.md):
      1. The four near-identical `columnDefs` blocks (~500 lines, one per
         status) were collapsed into a single data-driven GROUP_DEFS
         catalogue + a buildColumnDefs() function. One source of truth —
         add/rename a column once, it is correct everywhere.
      2. The "Customise Headers" bubble is now a proper column-manager
         panel: each column GROUP is a collapsible card (click the
         chevron) that can be DRAGGED to reorder (via SortableJS), and
         the chosen order + visibility + collapsed state is remembered
         per status in localStorage, so the layout a user builds sticks
         across page loads.
      3. Toolbar density/spacing cleaned up, icon-only export buttons
         now have accessible labels/tooltips.
      4. Every functional block below carries a short comment explaining
         what it does and why — per the request to document the logic.
      5. No behavioural change to routing, export formats, the quick
         filter, or the openVOTF() flow — those contracts are unchanged
         so the controller and other pages keep working as-is.
    =====================================================================
--}}
@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid"></section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">

            {{-- ============================================================
                 PAGE HEADER
                 Title (server-set per status by the controller) + primary
                 action + the status switcher. The status switcher is a
                 plain <select> that navigates via JS on change — it is not
                 a form because each status is its own route
                 (booking.index / .hold / .invoiced / .cancelled), not a
                 query-string filter.
            ============================================================ --}}
            <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-wrap gap-3">

                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    {{ $title ?? 'All Live Bookings' }}
                </h2>

                <div class="d-flex align-items-center gap-3 flex-nowrap">

                    <a href="{{ backpack_url('sales/booking/create') }}" class="btn btn-blue btn-sm fw-bold shadow-sm">
                        <i class="la la-plus me-1"></i> Add New Booking
                    </a>

                    <select id="statusFilter"
                            class="form-select form-select-sm bg-white text-dark border-0 shadow-sm"
                            style="min-width: 200px; max-width: 260px;"
                            aria-label="Switch booking status view">
                        <option value="{{ backpack_url('sales/booking') }}" {{ Route::currentRouteName() === 'booking.index' ? 'selected' : '' }}>
                            All Live Bookings
                        </option>
                        <option value="{{ backpack_url('sales/booking/hold') }}" {{ Route::currentRouteName() === 'booking.hold' ? 'selected' : '' }}>
                            On-Hold Bookings
                        </option>
                        <option value="{{ backpack_url('sales/booking/invoiced') }}" {{ Route::currentRouteName() === 'booking.invoiced' ? 'selected' : '' }}>
                            Invoiced Bookings
                        </option>
                        <option value="{{ backpack_url('sales/booking/cancelled') }}" {{ Route::currentRouteName() === 'booking.cancelled' ? 'selected' : '' }}>
                            Cancelled Bookings
                        </option>
                    </select>
                </div>
            </div>

            <div class="card-body p-0" style="background:#f8fafc">

                {{-- ========================================================
                     TOOLBAR
                     Left  : smart search (ag-Grid quick filter) + reset
                     Middle: header/column controls (default / customise /
                             all) — "customise" opens the draggable card panel
                     Right : export (Excel / PDF), icon-only but labelled
                             for accessibility
                ======================================================== --}}
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">

                    <div class="d-flex align-items-center gap-2 flex-nowrap">
                        <input type="text" id="quickFilter" class="form-control form-control-sm"
                               style="width:320px; min-width:220px;" placeholder="Smart Search...">
                        <button id="resetAll" class="btn btn-outline-danger btn-sm text-nowrap" title="Clear search &amp; filters">
                            Reset
                        </button>
                    </div>

                    <div class="d-flex gap-2 flex-nowrap justify-content-center">
                        <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm text-nowrap" title="Reset to the recommended column set">
                            Default Headers
                        </button>

                        {{-- Column manager trigger + panel. The panel itself is built
                             entirely by JS from GROUP_DEFS (see script below) so the
                             markup here is just the anchor + empty shell. --}}
                        <div class="position-relative d-inline-block">
                            <button id="btnCustomiseHeaders" class="btn btn-red btn-sm text-nowrap" title="Show/hide and reorder column groups">
                                Customise Headers
                            </button>

                            <div id="columnPanel" class="column-panel" style="display:none;">
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
                                    <strong style="font-size:13px;">Customise Headers</strong>
                                    <small class="text-muted">drag <i class="la la-arrows-alt"></i> to reorder</small>
                                    <button id="closeColumnPanel" class="btn btn-sm btn-link text-danger p-0" aria-label="Close">✕</button>
                                </div>
                                <div id="columnCards" class="column-cards"></div>
                            </div>
                        </div>

                        <button id="btnAllHeaders" class="btn btn-blue btn-sm text-nowrap" title="Show every available column">
                            All Headers
                        </button>
                    </div>

                    <div class="d-flex gap-2 flex-nowrap">
                        <button id="exportCsv" class="btn btn-sm text-nowrap" title="Export visible rows to Excel" aria-label="Export to Excel">
                            <img src="{{ asset('images/export-excel.png') }}" alt="" style="height:30px; width:auto;">
                        </button>
                        <button id="exportPdf" class="btn btn-sm text-nowrap" title="Export visible rows to PDF" aria-label="Export to PDF">
                            <img src="{{ asset('images/export-pdf.png') }}" alt="" style="height:30px; width:auto;">
                        </button>
                    </div>
                </div>

                {{-- The data grid itself. ag-Grid Community renders a dense,
                     sortable/filterable table — this stays a grid (not cards)
                     because the whole point of this screen is scanning ~90
                     columns x 50 rows at once, which only a table layout
                     supports; "cards" belong on the column-picker above and
                     on the add/edit form, not on tabular data. --}}
                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
            </div>

            @if(session('info'))
                <div class="card-footer text-center py-4 text-muted">
                    {{ session('info') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
{{-- Maps the grid onto Tabler's own design tokens (light + dark mode) -
     see .ai/rules/conventions.md section 13 and public/css/ag-grid-tabler-theme.css --}}
<link rel="stylesheet" href="{{ asset('css/ag-grid-tabler-theme.css') }}">

<style>
    .ag-theme-quartz .center-header .ag-header-cell-label,
    .ag-theme-quartz .ag-header-group-cell-label {
        justify-content: center !important;
    }

    /* ---- Column manager panel (dropdown anchored under "Customise Headers") ---- */
    .column-panel {
        position: absolute;
        top: 110%;
        left: 0;
        width: 320px;
        max-height: 420px;
        display: flex;
        flex-direction: column;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 10px 26px rgba(0,0,0,.16);
        z-index: 9999;
        overflow: hidden;
    }
    .column-cards { overflow-y: auto; padding: 8px; }

    /* Each column GROUP is a small card: header (drag handle, select-all,
       collapse chevron) + a collapsible body listing its columns. */
    .col-group-card {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 6px;
        background: #fff;
    }
    .col-group-card.dragging { opacity: .4; }
    .col-group-card__header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        cursor: default;
        background: #f8fafc;
        border-bottom: 1px solid transparent;
    }
    .col-group-card__header.is-open { border-bottom-color: #e5e7eb; }
    .col-group-card__handle { cursor: grab; color: #9ca3af; }
    .col-group-card__handle:active { cursor: grabbing; }
    .col-group-card__title { flex: 1; font-size: 13px; font-weight: 600; }
    .col-group-card__chevron {
        border: none; background: none; padding: 0 4px; color: #6b7280;
        transition: transform .15s ease;
    }
    .col-group-card__chevron.is-open { transform: rotate(90deg); }
    .col-group-card__body { display: none; padding: 4px 10px 8px 28px; }
    .col-group-card__body.is-open { display: block; }
    .col-group-card__body label {
        display: block; font-size: 12.5px; padding: 2px 0; cursor: pointer;
    }
    /* Placeholder ag-Grid shows in the card list while dragging */
    .sortable-ghost { opacity: .3; }
</style>
@endpush


@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
{{-- SortableJS powers the drag-to-reorder column-group cards below. --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>

<script>
    // =====================================================================
    // DATA SOURCE
    // ALL_COLUMNS is the flat catalogue of every column ag-Grid knows about
    // (headerName/field/valueFormatter/etc.), built server-side by
    // BookingCrudController::getAgGridColumns(). getCols() just looks up
    // the subset needed for a given group by field name.
    // =====================================================================
    const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);

    function getCols(fields) {
        return fields
            .map(field => ALL_COLUMNS.find(col => col.field === field))
            .filter(Boolean)
            // clone so mutating (pinned, etc.) never corrupts ALL_COLUMNS,
            // which is reused every time buildColumnDefs() runs
            .map(col => ({ ...col }));
    }

    let gridApi;

    // =====================================================================
    // STATUS RESOLUTION
    // The four booking-status pages (live/hold/invoiced/cancelled) share
    // this one view; the controller sets the active route, and we branch
    // purely on route name to know which status we're rendering.
    // =====================================================================
    function getCurrentStatus() {
        const route = "{{ Route::currentRouteName() }}";
        if (route === 'booking.hold') return 'hold';
        if (route === 'booking.invoiced') return 'invoiced';
        if (route === 'booking.cancelled') return 'cancelled';
        return 'live';
    }
    const STATUS = getCurrentStatus();

    // =====================================================================
    // COLUMN GROUP CATALOGUE (single source of truth)
    // ---------------------------------------------------------------------
    // Replaces four ~120-line copy-pasted `columnDefs` arrays (one per
    // status) that only differed in the "Primary" group and in whether the
    // Insurance/RTO/DO groups were present. Now there is exactly one
    // definition per group; buildColumnDefs() below assembles the grid's
    // columnDefs for the current status from it.
    //
    //   fields          — used when the group is identical on every status
    //   fieldsByStatus  — used when the group's column list differs by
    //                     status (only "Primary" needs this: invoiced adds
    //                     inv_no/inv_date, cancelled adds cancel_date)
    //   statuses        — restricts a group to specific statuses
    //                     (Insurance/RTO/DO only make sense once invoiced)
    //   pin             — 'left' | 'right', applied to the listed pinFields
    //                     (or every column in the group if pinFields is
    //                     omitted, as with the single-column Actions group)
    // =====================================================================
    const GROUP_DEFS = [
        {
            name: 'Primary',
            pin: 'left',
            pinFields: ['serial_no', 'booking_no'],
            fieldsByStatus: {
                live:      ['serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count'],
                hold:      ['serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count'],
                invoiced:  ['serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count', 'inv_no', 'inv_date'],
                cancelled: ['serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count', 'cancel_date'],
            },
        },
        {
            name: 'Customer',
            fields: [
                'b_type', 'b_cat', 'col_type', 'col_by', 'booking_amount', 'receipt_no', 'receipt_date',
                'name', 'care_of', 'care_of_type', 'mobile', 'alt_mobile', 'gender', 'occ',
                'pan_no', 'adhar_no', 'gstn', 'c_dob', 'customer_age', 'branch_name', 'location_name',
            ],
        },
        {
            name: 'Vehicle',
            fields: ['segment', 'model', 'variant', 'color', 'seating', 'accessories_amount', 'chassis_no'],
        },
        {
            name: 'Booking Detail',
            fields: [
                'status', 'b_mode', 'online_bk_ref_no', 'b_source', 'dsa_name', 'consultant',
                'del_type', 'del_date', 'fin_mode', 'financier', 'financier_short_name', 'loan_status',
            ],
        },
        {
            name: 'Purchase Type Details',
            fields: [
                'buyer_type', 'exist_oem1', 'vh1_detail', 'exist_oem2', 'vh2_detail',
                'registration_no', 'make_year', 'odo_reading', 'expected_price',
                'offered_price', 'exchange_bonus', 'price_gap',
            ],
        },
        {
            name: 'Referred',
            fields: ['r_name', 'r_mobile', 'r_model', 'r_variant', 'r_chassis'],
        },
        {
            name: 'DMS',
            fields: ['dms_no', 'dms_otf', 'otf_date', 'dms_so'],
        },
        {
            name: 'Stock',
            fields: ['livecount', 'stockcount'],
        },
        {
            name: 'Insurance',
            statuses: ['invoiced'],
            fields: ['insurance_source', 'insurance_company', 'insurance_short_name', 'policy_no', 'policy_date', 'policy_type'],
        },
        {
            name: 'RTO',
            statuses: ['invoiced'],
            fields: [
                'rto_sale_type', 'rto_permit', 'rto_body_type', 'registration_type', 'registration_no_type',
                'trc_number', 'trc_payment_bank_ref_no', 'application_no', 'tax_payment_bank_ref_no', 'vehicle_registration_no',
            ],
        },
        {
            name: 'DO',
            statuses: ['invoiced'],
            fields: ['instrument_type', 'loan_amount_dealer', 'margin_money', 'file_charge', 'net_payment_amount'],
        },
        {
            name: 'Actions',
            pin: 'right',
            locked: true, // always visible, not draggable/hideable — see renderColumnCards()
            fields: ['action'],
        },
    ];

    // Groups that apply to the current status, in their catalogue (default) order.
    function groupsForStatus(status) {
        return GROUP_DEFS.filter(g => !g.statuses || g.statuses.includes(status));
    }

    // Builds ag-Grid's columnDefs (grouped headers) for the current status,
    // in the given group-name order (defaults to catalogue order).
    function buildColumnDefs(status, order) {
        const groups = groupsForStatus(status);
        const byName = Object.fromEntries(groups.map(g => [g.name, g]));
        const orderedNames = (order && order.length)
            ? [...order.filter(n => byName[n]), ...groups.map(g => g.name).filter(n => !order.includes(n))]
            : groups.map(g => g.name);

        return orderedNames.map(name => {
            const g = byName[name];
            const fieldList = g.fieldsByStatus ? g.fieldsByStatus[status] : g.fields;
            const children = getCols(fieldList).map(col => {
                if (g.pin && (!g.pinFields || g.pinFields.includes(col.field))) {
                    col.pinned = g.pin;
                }
                return col;
            });
            return { headerName: g.name, groupId: g.name, children };
        }).filter(g => g.children.length); // defensively drop empty groups
    }

    // =====================================================================
    // DEFAULT (curated) COLUMN SET PER STATUS
    // This is intentionally a smaller, hand-picked subset of ALL_COLUMNS —
    // the columns a user needs to see *first*, before opting into the
    // full catalogue via "All Headers" or the column-manager panel.
    // =====================================================================
    const DEFAULT_COLUMNS_BY_STATUS = {
        live: [
            'serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count',
            'b_cat', 'col_type', 'booking_amount', 'accessories_amount',
            'name', 'mobile', 'branch_name', 'location_name',
            'model', 'variant', 'color', 'seating', 'chassis_no',
            'b_source', 'consultant',
            'del_date', 'fin_mode', 'financier_short_name', 'loan_status',
            'buyer_type', 'dms_otf', 'dms_so',
            'livecount', 'stockcount',
            'action',
        ],
        hold: [
            'serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count',
            'b_cat', 'col_type', 'booking_amount',
            'name', 'mobile', 'branch_name', 'location_name',
            'model', 'variant', 'color', 'seating', 'chassis_no',
            'b_source', 'consultant',
            'action',
        ],
        invoiced: [
            'serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count',
            'inv_no', 'inv_date', 'b_cat', 'name', 'mobile', 'branch_name', 'location_name',
            'model', 'variant', 'color', 'seating', 'chassis_no', 'b_source',
            'consultant',
            'action',
        ],
        cancelled: [
            'serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count',
            'cancel_date',
            'b_cat', 'col_type', 'booking_amount',
            'name', 'mobile', 'branch_name', 'location_name',
            'model', 'variant', 'color', 'seating', 'chassis_no',
            'b_source', 'consultant',
            'action',
        ],
    };

    // =====================================================================
    // PER-USER LAYOUT PERSISTENCE (group order / visibility / collapsed)
    // Stored client-side (localStorage), scoped per status, so it is
    // strictly a personal convenience — never sent to or read by the
    // server, and safe to ignore if unavailable (private browsing, etc.).
    // =====================================================================
    const LAYOUT_KEY = 'xlr8BookingGridLayout:' + STATUS;

    function loadLayout() {
        try {
            const raw = localStorage.getItem(LAYOUT_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null; // corrupted/blocked storage — fall back to defaults
        }
    }

    function saveLayout(layout) {
        try {
            localStorage.setItem(LAYOUT_KEY, JSON.stringify(layout));
        } catch (e) {
            // storage unavailable — layout simply won't persist this session
        }
    }

    // Current in-memory layout state, seeded from storage or defaults.
    let layoutState = loadLayout() || {
        order: groupsForStatus(STATUS).map(g => g.name),
        visibleFields: DEFAULT_COLUMNS_BY_STATUS[STATUS] || [],
        collapsed: {}, // { groupName: true } for collapsed cards
    };

    // =====================================================================
    // GRID SETUP
    // =====================================================================
    const gridOptions = {
        columnDefs: buildColumnDefs(STATUS, layoutState.order),
        rowData: @json($gridConfig['data'] ?? []),
        pagination: true,
        paginationPageSize: 50,
        rowHeight: 28,
        animateRows: true,
        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            headerClass: 'center-header',
            cellStyle: { textAlign: 'center' },
        },
        components: {
            // Server sends pre-rendered HTML (action buttons, badges) for
            // certain cells; this renderer simply trusts and injects it.
            htmlRenderer: params => params.value || '',
        },
        onGridReady: params => {
            gridApi = params.api;
            applyVisibility(layoutState.visibleFields);
            autoSizeVisibleColumns();
        },
    };

    function autoSizeVisibleColumns() {
        setTimeout(() => {
            const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
            gridApi.autoSizeColumns(visibleIds);
        }, 300);
    }

    function allColumnIds() {
        return gridApi.getAllGridColumns().map(c => c.getColId());
    }

    function applyVisibility(fields) {
        const all = allColumnIds();
        gridApi.setColumnsVisible(all, false);
        gridApi.setColumnsVisible(fields, true);
    }

    // =====================================================================
    // COLUMN-MANAGER PANEL (collapsible + draggable group cards)
    // ---------------------------------------------------------------------
    // Renders one card per column group. Each card can be:
    //   - expanded/collapsed (chevron)            → layoutState.collapsed
    //   - toggled on/off as a whole (group check)  → layoutState.visibleFields
    //   - individual columns toggled inside it      → layoutState.visibleFields
    //   - dragged to reorder relative to other cards → layoutState.order
    // Every change re-applies to the grid immediately and persists to
    // localStorage, so "Customise Headers" behaves like a live layout
    // editor rather than a one-shot dialog.
    // =====================================================================
    let sortableInstance = null;

    function renderColumnCards() {
        const container = document.getElementById('columnCards');
        container.innerHTML = '';

        const groups = groupsForStatus(STATUS);
        const byName = Object.fromEntries(groups.map(g => [g.name, g]));
        const order = layoutState.order.filter(n => byName[n]);
        // Append any group missing from a stale saved order (e.g. after a deploy
        // that added a new group) so nothing becomes permanently unreachable.
        groups.forEach(g => { if (!order.includes(g.name)) order.push(g.name); });

        order.forEach(name => {
            const g = byName[name];
            const fieldList = g.fieldsByStatus ? g.fieldsByStatus[STATUS] : g.fields;
            const cols = getCols(fieldList);
            const isCollapsed = !!layoutState.collapsed[name];
            const isLocked = !!g.locked; // e.g. "Actions" — always on, not draggable

            const card = document.createElement('div');
            card.className = 'col-group-card';
            card.dataset.group = name;

            const anyVisible = cols.some(c => layoutState.visibleFields.includes(c.field));

            card.innerHTML = `
                <div class="col-group-card__header ${isCollapsed ? '' : 'is-open'}">
                    ${isLocked ? '<span style="width:14px;"></span>' : '<span class="col-group-card__handle la la-ellipsis-v" title="Drag to reorder"></span>'}
                    <input type="checkbox" class="grp-toggle" ${anyVisible ? 'checked' : ''} ${isLocked ? 'disabled' : ''}>
                    <span class="col-group-card__title">${name}</span>
                    <button type="button" class="col-group-card__chevron ${isCollapsed ? '' : 'is-open'}" aria-label="Toggle ${name}">▶</button>
                </div>
                <div class="col-group-card__body ${isCollapsed ? '' : 'is-open'}">
                    ${cols.map(c => `
                        <label>
                            <input type="checkbox" class="col-toggle" data-field="${c.field}"
                                ${layoutState.visibleFields.includes(c.field) ? 'checked' : ''}
                                ${isLocked ? 'disabled' : ''}>
                            ${c.headerName}
                        </label>
                    `).join('')}
                </div>
            `;

            // Expand/collapse this card.
            card.querySelector('.col-group-card__chevron').addEventListener('click', () => {
                layoutState.collapsed[name] = !layoutState.collapsed[name];
                saveLayout(layoutState);
                renderColumnCards();
            });

            // Group-level "select all / none" for this card's columns.
            card.querySelector('.grp-toggle').addEventListener('change', e => {
                const checked = e.target.checked;
                cols.forEach(c => {
                    layoutState.visibleFields = checked
                        ? [...new Set([...layoutState.visibleFields, c.field])]
                        : layoutState.visibleFields.filter(f => f !== c.field);
                });
                saveLayout(layoutState);
                applyVisibility(layoutState.visibleFields);
                autoSizeVisibleColumns();
                renderColumnCards();
            });

            // Individual column checkboxes.
            card.querySelectorAll('.col-toggle').forEach(cb => {
                cb.addEventListener('change', e => {
                    const field = e.target.dataset.field;
                    layoutState.visibleFields = e.target.checked
                        ? [...new Set([...layoutState.visibleFields, field])]
                        : layoutState.visibleFields.filter(f => f !== field);
                    saveLayout(layoutState);
                    applyVisibility(layoutState.visibleFields);
                    autoSizeVisibleColumns();
                });
            });

            container.appendChild(card);
        });

        // (Re)attach drag-to-reorder. Locked groups (Actions) are excluded
        // via the `filter` option so they can't be dragged or displaced.
        if (sortableInstance) sortableInstance.destroy();
        sortableInstance = new Sortable(container, {
            handle: '.col-group-card__handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            filter: '[data-group="Actions"]',
            onEnd: () => {
                const newOrder = Array.from(container.querySelectorAll('.col-group-card')).map(el => el.dataset.group);
                layoutState.order = newOrder;
                saveLayout(layoutState);
                gridApi.setGridOption('columnDefs', buildColumnDefs(STATUS, layoutState.order));
                applyVisibility(layoutState.visibleFields);
                autoSizeVisibleColumns();
            },
        });
    }

    function openColumnPanel() {
        renderColumnCards();
        document.getElementById('columnPanel').style.display = 'block';
    }

    // =====================================================================
    // BOOT
    // =====================================================================
    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#myGrid');
        agGrid.createGrid(gridDiv, gridOptions);

        // --- Smart search (ag-Grid's built-in quick filter) ---
        document.getElementById('quickFilter')?.addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        document.getElementById('resetAll')?.addEventListener('click', () => {
            gridApi.setFilterModel(null);
            gridApi.setGridOption('quickFilterText', '');
            document.getElementById('quickFilter').value = '';
        });

        // --- Column-manager panel open/close ---
        document.getElementById('btnCustomiseHeaders')?.addEventListener('click', e => {
            e.stopPropagation();
            e.preventDefault();
            openColumnPanel();
        });
        document.getElementById('closeColumnPanel')?.addEventListener('click', e => {
            e.stopPropagation();
            e.preventDefault();
            document.getElementById('columnPanel').style.display = 'none';
        });
        document.getElementById('columnPanel')?.addEventListener('click', e => e.stopPropagation());
        document.addEventListener('click', () => {
            const panel = document.getElementById('columnPanel');
            if (panel && panel.style.display === 'block') panel.style.display = 'none';
        });

        // --- "All Headers" / "Default Headers" shortcuts ---
        document.getElementById('btnAllHeaders')?.addEventListener('click', () => {
            layoutState.visibleFields = allColumnIds();
            saveLayout(layoutState);
            applyVisibility(layoutState.visibleFields);
            autoSizeVisibleColumns();
        });

        document.getElementById('btnDefaultHeaders')?.addEventListener('click', () => {
            layoutState.visibleFields = DEFAULT_COLUMNS_BY_STATUS[STATUS] || [];
            layoutState.order = groupsForStatus(STATUS).map(g => g.name);
            saveLayout(layoutState);
            gridApi.setGridOption('columnDefs', buildColumnDefs(STATUS, layoutState.order));
            applyVisibility(layoutState.visibleFields);
            autoSizeVisibleColumns();
        });

        // --- Status switcher (navigates to a different booking-status route) ---
        document.getElementById('statusFilter')?.addEventListener('change', function () {
            if (this.value) window.location.href = this.value;
        });

        // --- Export: Excel (visible columns, filtered+sorted rows) ---
        document.getElementById('exportCsv')?.addEventListener('click', () => {
            const visibleColumns = gridApi.getAllDisplayedColumns()
                .map(col => col.getColDef())
                .filter(col => col.field && col.field !== 'action');

            const rows = [];
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const row = {};
                visibleColumns.forEach(col => { row[col.headerName] = node.data[col.field] ?? ''; });
                rows.push(row);
            });

            const worksheet = XLSX.utils.json_to_sheet(rows);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Live Bookings');
            XLSX.writeFile(workbook, `live-bookings-${new Date().toISOString().slice(0, 10)}.xlsx`);
        });

        // --- Export: PDF (same row/column selection as Excel export) ---
        document.getElementById('exportPdf')?.addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'pt', 'a4');

            const visibleColumns = gridApi.getAllDisplayedColumns()
                .map(col => col.getColDef())
                .filter(col => col.field && col.field !== 'action');

            const exportCols = visibleColumns.map(col => ({ header: col.headerName, dataKey: col.field }));

            const rows = [];
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const row = {};
                visibleColumns.forEach(col => { row[col.field] = node.data[col.field]; });
                rows.push(row);
            });

            doc.text('Live Bookings Report', 40, 30);
            doc.autoTable({
                columns: exportCols,
                body: rows,
                startY: 50,
                styles: { fontSize: 8 },
                headStyles: { fillColor: [33, 150, 243] },
            });
            doc.save('live-bookings.pdf');
        });
    });

    // =========================================================================
    // VOTF ("view/open the Transaction Form") — unchanged contract.
    // Calls BookingCrudController::otfProcess() via GET /admin/booking/otf-form/{id}.
    //   - JSON {status:'quotation_missing', quotation_url} → confirm + redirect
    //   - JSON {status:'success', redirect_url}            → redirect
    //   - HTML                                              → replaces the
    //     current document with the returned OTF page (server-rendered flow,
    //     not an SPA fragment — kept as-is since changing this is a controller
    //     + downstream-view contract change outside this optimisation's scope).
    // =========================================================================
    function openVOTF(bookingId) {
        const url = "{{ backpack_url('sales/booking/otf-form') }}/" + bookingId;

        fetch(url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html, application/json' },
        })
        .then(async response => {
            const contentType = response.headers.get('content-type') || '';

            if (contentType.includes('application/json')) {
                const data = await response.json();

                if (data.status === 'quotation_missing') {
                    Swal.fire({
                        title: 'Quotation Required',
                        text: 'This booking has no quotation. Do you want to create a quotation for this booking?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes',
                        cancelButtonText: 'No',
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#d33',
                    }).then(result => {
                        if (result.isConfirmed) window.location.href = data.quotation_url;
                    });
                    return;
                }

                if (data.status === 'success' && data.redirect_url) {
                    window.location.href = data.redirect_url;
                    return;
                }

                throw new Error(data.message || 'Unexpected response.');
            }

            const html = await response.text();
            document.open();
            document.write(html);
            document.close();
        })
        .catch(error => {
            console.error('VOTF Error:', error);
            Swal.fire({ title: 'Error', text: error.message || 'Unable to process this booking.', icon: 'error' });
        });
    }
</script>
@endpush
