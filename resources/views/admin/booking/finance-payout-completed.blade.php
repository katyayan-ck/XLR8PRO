@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid"></section>
@endsection

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">

<style>
    .ag-theme-quartz .center-header .ag-header-cell-label {
        justify-content: center !important;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            {{-- HEADER --}}
            <div
                class="card-header bg-gradient-success d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Finance Payout - Completed Dashboard
                </h2>

                <div class="d-flex align-items-center gap-3 flex-wrap">

                    {{-- Payout Status Dropdown --}}
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-black mb-0 text-nowrap">Payout Status:</label>
                        <select id="payout_type" class="form-control form-select" style="min-width: 220px;">
                            <option value="{{ route('finance.payout') }}">Pending Payout</option>
                            <option value="{{ route('finance.payout.completed') }}" selected>Completed Payout</option>
                        </select>
                    </div>

                    {{-- Difference Filter (sirf Completed ke liye) --}}
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-black mb-0 text-nowrap">Difference Filter:</label>
                        <select id="status_filter" class="form-control form-select" style="min-width: 180px;">
                            <option value="all" {{ request('status_filter', 'all' )==='all' ? 'selected' : '' }}>All
                            </option>
                            <option value="short" {{ request('status_filter')==='short' ? 'selected' : '' }}>Short
                            </option>
                            <option value="excess" {{ request('status_filter')==='excess' ? 'selected' : '' }}>Excess
                            </option>
                            <option value="reconciled" {{ request('status_filter')==='reconciled' ? 'selected' : '' }}>
                                Reconciled</option>
                        </select>
                    </div>

                    {{-- <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Financier:</label>

                        <select id="financier_filter" class="form-control form-select" style="min-width:220px;">
                            <option value="">All Financiers</option>

                            @foreach($financiers as $financier)
                            <option value="{{ $financier['id'] }}" {{ request('financier')==$financier['id']
                                ? 'selected' : '' }}>
                                {{ $financier['short_name'] ?? $financier['name'] }}
                            </option>
                            @endforeach
                        </select>
                    </div> --}}

                </div>
            </div>

            {{-- BODY --}}
            <div class="card-body p-0 bg-light">

                {{-- TOOLBAR --}}
                <div
                    class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">
                    <div class="d-flex align-items-center gap-2 flex-nowrap">
                        <input type="text" id="quickFilter" class="form-control w-100 w-md-auto"
                            style="width: 360px; min-width: 260px;" placeholder="Smart Search...">
                        <button id="resetAll" class="btn btn-outline-danger btn-sm">Reset</button>
                    </div>

                    <div class="d-flex gap-2 flex-wrap justify-content-center">
                        <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm text-nowrap">
                            Default Headers
                        </button>

                        <div class="position-relative">
                            <button id="btnCustomiseHeaders" class="btn btn-danger btn-sm text-nowrap">
                                Customise Headers
                            </button>

                            <div id="columnBubble"
                                style="display:none; position:absolute; top:110%; left:0; width:260px; background:#fff; border:1px solid #ddd; border-radius:6px; box-shadow:0 8px 20px rgba(0,0,0,.15); z-index:9999;">
                                <div class="d-flex justify-content-between px-2 py-1 border-bottom">
                                    <strong style="font-size:13px;">Customise Headers</strong>
                                    <button id="closeColumnBubble"
                                        class="btn btn-sm btn-link text-danger p-0">✕</button>
                                </div>
                                <div style="max-height:260px; overflow:auto;">
                                    <table class="table table-sm mb-0">
                                        <tbody id="columnBubbleBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <button id="btnAllHeaders" class="btn btn-info btn-sm text-nowrap">
                            All Headers
                        </button>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button id="exportExcel" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                            <img src="{{ asset('images/export-excel.png') }}" alt="Excel"
                                style="height:30px; width:auto;">
                        </button>

                        <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                            <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">
                        </button>
                    </div>
                </div>

                {{-- GRID --}}
                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width: 100%;"></div>
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

@push('after_scripts')
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<script>
    const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);

    function getCols(fields) {
        return ALL_COLUMNS.filter(col => fields.includes(col.field));
    }

    const DEFAULT_VISIBLE_FIELDS = [
        'serial_no', 'booking_no',
        'inv_no', 'inv_date', 'name', 'mobile', 'branch_name', 'location_name',
        'model', 'variant', 'color', 'seating',
        'consultant', 'fin_mode','financier_short_name',
        'payout_category','do_number','loan_amount_dealer',
                'expected_payout_pct',
                'expected_payout_pct_without_gst', 'gst_rate', 'gst_included','expected_payout_amount_without_gst',
                'expected_payout_amount_without_gst', 'gst_amount', 'sugg_inv_amt', 'loan_amount_fin_payout_sheet', 'inv1_no', 'inv1_name', 'inv1_prov_gst', 'inv2_no' ,'inv2_name', 'inv2_prov_gst',
                'total_prov_with_gst', 'total_prov_without_gst', 'consideration_no_gst', 'diff_without_gst', 'prov_prc_without_gst',
        'action'
    ];

    const columnGroups = [
        {
            headerName: 'Primary',
            headerClass: 'ag-header-center',
            children: getCols(['serial_no','booking_no','created_at','booking_date','days_count','inv_no','inv_date'])
                .map(col => {
                    if (['serial_no', 'booking_no'].includes(col.field)) col.pinned = 'left';
                    return col;
                })
        },
        {
            headerName: 'Customer',
            headerClass: 'ag-header-center',
            children: getCols(['receipt_no','receipt_date','name','mobile','alt_mobile','pan_no','adhar_no','gstn','branch_name','location_name'])
        },
        {
            headerName: 'Vehicle',
            headerClass: 'ag-header-center',
            children: getCols(['segment','model','variant','color','seating','chassis_no'])
        },
        {
            headerName: 'Booking Detail',
            headerClass: 'ag-header-center',
            children: getCols(['consultant', 'del_type','fin_mode','financier','financier_short_name','loan_status'])
        },
        {
            headerName: 'Payout Detail',
            headerClass: 'ag-header-center',
            children: getCols([
                'payout_category','do_number','loan_amount_dealer',
                'expected_payout_pct',
                'expected_payout_pct_without_gst', 'gst_rate', 'gst_included','expected_payout_amount_without_gst',
                'expected_payout_amount_without_gst', 'gst_amount',
            ])
        },
        {
            headerName: 'RTO',
            headerClass: 'ag-header-center',
            children: getCols([
                'sugg_inv_amt', 'loan_amount_fin_payout_sheet', 'inv1_no', 'inv1_name', 'inv1_prov_gst', 'inv2_no' ,'inv2_name', 'inv2_prov_gst',
                'total_prov_with_gst', 'total_prov_without_gst', 'consideration_no_gst', 'diff_without_gst', 'prov_prc_without_gst'
            ])
        },
        {
            headerName: 'Action',
            headerClass: 'ag-header-center',
            children: getCols(['action']).map(col => {
                col.pinned = 'right';
                col.cellRenderer = 'htmlRenderer';
                col.autoHeight = true;
                col.cellClass = 'text-center p-0';
                return col;
            })
        }
    ];

    let gridApi;

    const gridOptions = {
        columnDefs: columnGroups,
        rowData: @json($gridConfig['data'] ?? []),
        pagination: true,
        paginationPageSize: 50,
        paginationPageSizeSelector: [20, 50, 100, 200],
        rowHeight: 30,
        animateRows: true,

        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            headerClass: 'center-header',
            cellStyle: { textAlign: 'center' },
            suppressHeaderMenuButton: false,
        },

        components: {
            htmlRenderer: params => params.value || '',
        },

        onGridReady: params => {
            gridApi = params.api;

            const allFields = [];
            columnGroups.forEach(group => {
                group.children?.forEach(child => {
                    if (child.field) allFields.push(child.field);
                });
            });

            gridApi.setColumnsVisible(allFields, false);
            gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);

            setTimeout(() => {
                const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
                gridApi.autoSizeColumns(visibleIds, false);
            }, 400);
        }
    };

     Bubble
    function openColumnBubble() {
        const bubble = document.getElementById('columnBubble');
        const tbody = document.getElementById('columnBubbleBody');
        if (!gridApi || !bubble || !tbody) return;

        tbody.innerHTML = '';

        columnGroups.forEach(group => {
            const groupName = group.headerName;
            const children = group.children || [];
            if (groupName === 'Action') return;

            const groupTr = document.createElement('tr');
            groupTr.style.background = '#f0f0f0';
            const groupCheckTd = document.createElement('td');
            groupCheckTd.style.width = '30px';
            groupCheckTd.className = 'text-center';

            const groupCheckbox = document.createElement('input');
            groupCheckbox.type = 'checkbox';
            const fields = children.map(c => c.field).filter(Boolean);
            const visibleCount = fields.filter(f => gridApi.getColumn(f)?.isVisible() ?? false).length;

            groupCheckbox.checked = visibleCount === fields.length && visibleCount > 0;
            groupCheckbox.indeterminate = visibleCount > 0 && visibleCount < fields.length;

            if (groupName === 'Primary') {
                groupCheckbox.checked = true;
                groupCheckbox.disabled = true;
            }

            groupCheckbox.addEventListener('change', () => {
                gridApi.setColumnsVisible(fields, groupCheckbox.checked);
                tbody.querySelectorAll(`tr[data-group="${groupName}"] input`).forEach(cb => cb.checked = groupCheckbox.checked);
            });

            groupCheckTd.appendChild(groupCheckbox);
            const groupLabelTd = document.createElement('td');
            groupLabelTd.colSpan = 2;
            groupLabelTd.innerHTML = `<strong>${groupName}</strong>`;
            groupTr.append(groupCheckTd, groupLabelTd);
            tbody.appendChild(groupTr);

            children.forEach(child => {
                if (!child.field) return;
                const tr = document.createElement('tr');
                tr.dataset.group = groupName;

                const tdCheck = document.createElement('td');
                tdCheck.style.paddingLeft = '40px';
                tdCheck.className = 'text-center';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = gridApi.getColumn(child.field)?.isVisible() ?? false;

                if (groupName === 'Primary') {
                    checkbox.disabled = true;
                    checkbox.checked = true;
                }

                checkbox.addEventListener('change', () => gridApi.setColumnsVisible([child.field], checkbox.checked));

                tdCheck.appendChild(checkbox);
                const tdLabel = document.createElement('td');
                tdLabel.innerText = child.headerName;

                tr.append(tdCheck, tdLabel);
                tbody.appendChild(tr);
            });
        });

        bubble.style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#myGrid');
        gridApi = agGrid.createGrid(gridDiv, gridOptions);

        
        document.getElementById('quickFilter')?.addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        document.getElementById('resetAll')?.addEventListener('click', () => {
            gridApi.setFilterModel(null);
            gridApi.setGridOption('quickFilterText', '');
            document.getElementById('quickFilter').value = '';
        });


        document.getElementById('payout_type')?.addEventListener('change', function() {
            window.location.href = this.value;
        });

        document.getElementById('status_filter')?.addEventListener('change', function() {
            const url = new URL(window.location);
            if (this.value === 'all') {
                url.searchParams.delete('status_filter');
            } else {
                url.searchParams.set('status_filter', this.value);
            }
            window.location = url;
        });

        
        document.getElementById('btnCustomiseHeaders')?.addEventListener('click', e => {
            e.stopPropagation();
            openColumnBubble();
        });

        document.getElementById('closeColumnBubble')?.addEventListener('click', () => {
            document.getElementById('columnBubble').style.display = 'none';
        });

        document.getElementById('columnBubble')?.addEventListener('click', e => e.stopPropagation());

        document.addEventListener('click', () => {
            document.getElementById('columnBubble').style.display = 'none';
        });

        document.getElementById('btnAllHeaders')?.addEventListener('click', () => {
            const allFields = [];
            columnGroups.forEach(group => group.children?.forEach(c => c.field && allFields.push(c.field)));
            gridApi.setColumnsVisible(allFields, true);
            setTimeout(() => gridApi.autoSizeColumns(gridApi.getAllDisplayedColumns().map(c => c.getColId()), false), 200);
        });

        document.getElementById('btnDefaultHeaders')?.addEventListener('click', () => {
            const allFields = [];
            columnGroups.forEach(group => group.children?.forEach(c => c.field && allFields.push(c.field)));
            gridApi.setColumnsVisible(allFields, false);
            gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);
            setTimeout(() => gridApi.autoSizeColumns(gridApi.getAllDisplayedColumns().map(c => c.getColId()), false), 200);
        });

        document.getElementById('exportExcel')?.addEventListener('click', () => {
            const visibleColumns = gridApi.getAllDisplayedColumns()
                .map(col => col.getColDef())
                .filter(col => col.field && col.field !== 'action');

            const rows = [];
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const row = {};
                visibleColumns.forEach(col => {
                    row[col.headerName] = node.data[col.field] ?? '';
                });
                rows.push(row);
            });

            const worksheet = XLSX.utils.json_to_sheet(rows);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Finance Payout');
            XLSX.writeFile(workbook, `finance-payout-completed-${new Date().toISOString().slice(0,10)}.xlsx`);
        });

        document.getElementById('exportPdf')?.addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'pt', 'a4');

            const visibleColumns = gridApi.getAllDisplayedColumns()
                .map(col => col.getColDef())
                .filter(col => col.field && col.field !== 'action');

            const exportCols = visibleColumns.map(col => ({
                header: col.headerName,
                dataKey: col.field
            }));

            const rows = [];
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const row = {};
                visibleColumns.forEach(col => row[col.field] = node.data[col.field]);
                rows.push(row);
            });

            doc.text('Finance Payout - Completed Report', 40, 30);
            doc.autoTable({
                columns: exportCols,
                body: rows,
                startY: 50,
                styles: { fontSize: 8 },
                headStyles: { fillColor: [40, 167, 69] },
            });

            doc.save('finance-payout-completed.pdf');
        });

        document.getElementById('financier_filter')?.addEventListener('change', function() {
            const url = new URL(window.location);

            if (this.value) {
                url.searchParams.set('financier', this.value);
            } else {
                url.searchParams.delete('financier');
            }

            window.location = url;
        });
    });
</script>
@endpush