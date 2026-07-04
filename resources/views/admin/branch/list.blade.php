@extends(backpack_view('blank'))

@section('header')
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <!-- HEADER -->
            <div
                class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    {{ $title ?? 'All Branches' }}
                </h2>

                <div class="d-flex align-items-center gap-3 flex-nowrap">
                    <a href="{{ backpack_url('branch/create') }}" class="btn btn-blue btn-sm fw-bold shadow-sm">
                        <i class="la la-plus me-1"></i> Add New Branch
                    </a>
                </div>
            </div>

            <!-- BODY -->
            <div class="card-body p-0" style="background:#f8fafc">
                <div
                    class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">
                    <div class="d-flex align-items-center gap-2 flex-nowrap">
                        <input type="text" id="quickFilter" class="form-control w-100 w-md-auto"
                            style="width:360px; min-width:260px;" placeholder="Smart Search...">
                        <button id="resetAll" class="btn btn-outline-danger btn-sm text-nowrap">Reset</button>
                    </div>

                    <div class="d-flex gap-2 flex-nowrap justify-content-center">
                        <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm text-nowrap">Default
                            Headers</button>

                        <div class="position-relative d-inline-block">
                            <button id="btnCustomiseHeaders" class="btn btn-red btn-sm text-nowrap">Customise
                                Headers</button>
                            <div id="columnBubble"
                                style="display:none; position:absolute; top:110%; left:0; width:320px; background:#fff; border:1px solid #ddd; border-radius:6px; box-shadow:0 8px 20px rgba(0,0,0,.15); z-index:9999;">
                                <div class="d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
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

                        <button id="btnAllHeaders" class="btn btn-blue btn-sm text-nowrap">All Headers</button>
                    </div>

                    <div class="d-flex gap-2 flex-nowrap">
                        <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                            <img src="{{ asset('images/export-excel.png') }}" alt="Excel"
                                style="height:30px; width:auto;">
                        </button>
                        <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                            <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">
                        </button>
                    </div>
                </div>

                <!-- AG Grid -->
                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
<style>
    .ag-theme-quartz .center-header .ag-header-cell-label,
    .ag-theme-quartz .ag-header-group-cell-label {
        justify-content: center !important;
    }

    /* Extra safety for group headers */
    .ag-theme-quartz .ag-header-group-cell {
        text-align: center !important;
        justify-content: center !important;
    }

    #columnBubble {
        width: 320px;
    }
</style>
@endpush

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

    let gridApi;

        const columnDefs = [
    ...getCols([
    'serial_no',
    'code',
    'branch_code',
    'name',
    'short_name'
]).map(col => {
    // ✅ LEFT PIN (freeze)
    if (['serial_no', 'code', 'branch_code'].includes(col.field)) {
        col.pinned = 'left';
        col.lockPinned = true; // optional (user remove na kar sake)
    }

    // optional width
    if (col.field === 'serial_no') col.width = 60;
    if (col.field === 'code') col.width = 120;

    return col;
}),

    ...getCols([
        'description',
        'address'
    ]),

    ...getCols([
        'phone',
        'email'
    ]),

    ...getCols([
        'city',
        'state',
        'pincode',
        'country'
    ]),

    ...getCols([
        'latitude',
        'longitude'
    ]),

    ...getCols([
        'is_head_office',
        'is_active'
    ]),

    ...getCols(['action']).map(col => {
        col.pinned = 'right';
        col.width = 140;
        col.sortable = false;
        col.filter = false;
        col.cellRenderer = 'htmlRenderer';
        return col;
    })
];

    const gridOptions = {
        columnDefs: columnDefs,
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
            minWidth: 130,   // ✅ ADD
            wrapText: true,  // ✅ ADD
            autoHeight: true // ✅ ADD
        },
        components: {
            htmlRenderer: params => params.value || ''
        },
        onGridReady: params => {
            gridApi = params.api;
            const defaultFields = [
    'serial_no',
    'code',
    'branch_code',
    'name',
    'short_name',
    'description',
    'phone',
    'email',
    'address',
    'city',
    'state',
    'pincode',
    'country',
    'latitude',
    'longitude',
    'is_head_office',
    'is_active',
    'action'
];
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));
            gridApi.setColumnsVisible(allCols, false);
            gridApi.setColumnsVisible(defaultFields, true);
            setTimeout(() => gridApi.autoSizeAllColumns(), 300);
        }
    };

        // Updated openColumnBubble for Flat Columns (No Grouping)
    function openColumnBubble() {
        const bubble = document.getElementById('columnBubble');
        const tbody = document.getElementById('columnBubbleBody');
        if (!gridApi || !bubble || !tbody) return;

        tbody.innerHTML = '';

        // Sab columns ko ek saath flat list mein show karo
        const allFlatColumns = [
            ...getCols(['serial_no', 'code', 'name', 'short_name']),

            ...getCols(['description']), // ✅ ADD

            ...getCols(['phone', 'email']),

            ...getCols(['address']), // ✅ ADD

            ...getCols(['city', 'state', 'pincode']),

            ...getCols(['country', 'latitude', 'longitude']), // ✅ ADD
        ];

        allFlatColumns.forEach(col => {
            if (!col.field) return;

            const tr = document.createElement('tr');
            const tdCheck = document.createElement('td');
            tdCheck.style.width = '40px';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            const column = gridApi.getColumn(col.field);
            checkbox.checked = column ? column.isVisible() : false;

            // Primary columns ko disable kar sakte ho (optional)
            if (['serial_no', 'code','branch_code', 'name', 'short_name'].includes(col.field)) {
                checkbox.checked = true;
                checkbox.disabled = true;
            }

            // Action column ko bhi hide nahi karne dena chahte ho toh
            if (col.field === 'action') {
                checkbox.checked = true;
                checkbox.disabled = true;
            }

            checkbox.addEventListener('change', () => {
                gridApi.setColumnsVisible([col.field], checkbox.checked);
            });

            tdCheck.appendChild(checkbox);

            const tdLabel = document.createElement('td');
            tdLabel.innerText = col.headerName || col.field;

            tr.appendChild(tdCheck);
            tr.appendChild(tdLabel);
            tbody.appendChild(tr);
        });

        bubble.style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#myGrid');
        agGrid.createGrid(gridDiv, gridOptions);

        // Quick Filter
        document.getElementById('quickFilter').addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        document.getElementById('resetAll').addEventListener('click', () => {
    // Clear column filters
    gridApi.setFilterModel(null);

    // Clear quick filter input
    document.getElementById('quickFilter').value = '';

    // Clear AG Grid quick filter (IMPORTANT)
    gridApi.setGridOption('quickFilterText', '');

    // Optional: reset sorting also
    gridApi.setSortModel(null);
});

        document.getElementById('btnCustomiseHeaders').addEventListener('click', e => {
            e.stopPropagation();
            openColumnBubble();
        });

        document.getElementById('closeColumnBubble').addEventListener('click', () => {
            document.getElementById('columnBubble').style.display = 'none';
        });

        document.getElementById('columnBubble').addEventListener('click', e => e.stopPropagation());

        document.addEventListener('click', e => {
            const bubble = document.getElementById('columnBubble');
            if (bubble && bubble.style.display === 'block') bubble.style.display = 'none';
        });

        document.getElementById('btnAllHeaders').addEventListener('click', () => {
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));
            gridApi.setColumnsVisible(allCols, true);
            setTimeout(() => gridApi.autoSizeAllColumns(), 200);
        });

        document.getElementById('btnDefaultHeaders').addEventListener('click', () => {
            const defaultFields = [
    'serial_no',
    'code',
    'branch_code',
    'name',
    'short_name',
    'description',
    'phone',
    'email',
    'address',
    'city',
    'state',
    'pincode',
    'country',
    'latitude',
    'longitude',
    'is_head_office',
    'is_active',
    'action'
];
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));
            gridApi.setColumnsVisible(allCols, false);
            gridApi.setColumnsVisible(defaultFields, true);
            setTimeout(() => gridApi.autoSizeAllColumns(), 200);
        });

        // Excel Export
        document.getElementById('exportCsv').addEventListener('click', () => {
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

            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.json_to_sheet(rows);
            XLSX.utils.book_append_sheet(wb, ws, "Branches");
            XLSX.writeFile(wb, `branches-${new Date().toISOString().slice(0,10)}.xlsx`);
        });
        document.getElementById('exportPdf').addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            const visibleColumns = gridApi.getAllDisplayedColumns()
                .map(col => col.getColDef())
                .filter(col => col.field && col.field !== 'action');

            const headers = visibleColumns.map(col => col.headerName);

            const rows = [];
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const row = [];
                visibleColumns.forEach(col => {
                    row.push(node.data[col.field] ?? '');
                });
                rows.push(row);
            });

            doc.autoTable({
                head: [headers],
                body: rows,
                styles: { fontSize: 8 },
                headStyles: { fillColor: [41, 128, 185] },
            });

            doc.save(`branches-${new Date().toISOString().slice(0,10)}.pdf`);
        });
    });
</script>
@endpush