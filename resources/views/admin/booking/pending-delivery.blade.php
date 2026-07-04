@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h2>
        <i class="la la-truck text-primary"></i> Pending Deliveries
        <small class="d-none d-md-inline">Invoiced bookings awaiting delivery</small>
    </h2>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            <div
                class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h3 class="card-title mb-0 fw-bold text-black">
                    Pending Deliveries Dashboard
                </h3>
            </div>

            <div class="card-body p-0 bg-light">

                <div
                    class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="text" id="quickFilter" class="form-control" style="width: 360px; min-width: 260px;"
                            placeholder="Smart Search...">
                        <button id="resetAll" class="btn btn-outline-danger btn-sm">
                            Reset
                        </button>
                    </div>

                    <div class="d-flex gap-2 flex-wrap justify-content-center">
                        <button id="btnAllHeaders" class="btn btn-info btn-sm">All Headers</button>
                        <button id="btnDefaultHeaders" class="btn btn-warning btn-sm">Default Headers</button>

                        <div class="position-relative d-inline-block">
                            <button id="btnCustomiseHeaders" class="btn btn-success btn-sm">
                                Customise Headers
                            </button>

                            <div id="columnBubble" style="
            display:none;
            position:absolute;
            top:110%;
            left:0;
            width:260px;
            background:#fff;
            border:1px solid #ddd;
            border-radius:6px;
            box-shadow:0 8px 20px rgba(0,0,0,.15);
            z-index:9999;
        ">
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
                    </div>


                    <div class="d-flex gap-2 flex-wrap">
                        <button id="exportCsv" class="btn btn-success btn-sm">
                            <i class="la la-file-excel-o"></i> Excel
                        </button>
                        <button id="exportExcel" class="btn btn-danger btn-sm">
                            <i class="la la-file-pdf-o"></i> PDF
                        </button>
                    </div>
                </div>

                <div id="myGrid" class="ag-theme-quartz" style="height: calc(110vh - 260px); width: 100%;"></div>
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
@endpush

@push('after_scripts')
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<script>
    const gridConfig = @json($gridConfig ?? []);

    let gridApi;

    const DEFAULT_VISIBLE_FIELDS = [
    'serial_no',
    'booking_no',
    'created_at',
    'name',
    'mobile',
    'model',
    'variant',
    'booking_amount',
    'action'
];


    const columnDefs = (gridConfig.columns || []).map(col => ({
        headerName: col.headerName,
        field: col.field,
        sortable: true,
        filter: true,
        resizable: true,
        pinned: col.pinned || false,
        width: col.width || 150,
        cellRenderer: col.field === 'action'
            ? params => params.value || ''
            : null,
        cellClass: col.cellClass || '',
    }));

    const gridOptions = {
        columnDefs,
        rowData: gridConfig.data || [],
        pagination: true,
        paginationPageSize: 50,
        paginationPageSizeSelector: [20, 50, 100, 200],
        animateRows: true,
        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
        },
        components: {
            htmlRenderer: (params) => params.value || '',
        },
        onGridReady: params => {
    gridApi = params.api;

    const allCols = gridApi.getColumnDefs().map(c => c.field);
    gridApi.setColumnsVisible(allCols, false);
    gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);
    }
    };
    function openColumnBubble() {
    const tbody = document.getElementById('columnBubbleBody');
    tbody.innerHTML = '';

    const state = gridApi.getColumnState();

    gridApi.getColumnDefs().forEach(col => {
        if (!col.field || col.field === 'action') return;

        const visible = !state.find(s => s.colId === col.field)?.hide;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-center" style="width:30px;">
                <input type="checkbox" ${visible ? 'checked' : ''}>
            </td>
            <td style="font-size:13px;">${col.headerName}</td>
        `;

        tr.querySelector('input').addEventListener('change', e => {
            gridApi.applyColumnState({
                state: [{ colId: col.field, hide: !e.target.checked }],
                applyOrder: false
            });
        });

        tbody.appendChild(tr);
    });

    document.getElementById('columnBubble').style.display = 'block';
}

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
    const allCols = gridApi.getColumnDefs().map(c => c.field);
    gridApi.setColumnsVisible(allCols, true);
});

document.getElementById('btnDefaultHeaders')?.addEventListener('click', () => {
    const allCols = gridApi.getColumnDefs().map(c => c.field);
    gridApi.setColumnsVisible(allCols, false);
    gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);
});


    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#myGrid');
        gridApi = agGrid.createGrid(gridDiv, gridOptions);

        // Quick Search
        document.getElementById('quickFilter')?.addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        // Reset
        document.getElementById('resetAll')?.addEventListener('click', () => {
            gridApi.setFilterModel(null);
            gridApi.setGridOption('quickFilterText', '');
            document.getElementById('quickFilter').value = '';
        });

        // Excel Export (action exclude)
        document.getElementById('exportCsv')?.addEventListener('click', () => {
    const visibleColumns = gridApi.getAllDisplayedColumns()
        .map(col => col.getColDef())
        .filter(col => col.field && col.field !== 'action');

    const rows = [];
    gridApi.forEachNodeAfterFilterAndSort(node => {
        const row = {};
        visibleColumns.forEach(col => {
            row[col.headerName] = node.data[col.field];
        });
        rows.push(row);
    });

    const ws = XLSX.utils.json_to_sheet(rows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Report');
    XLSX.writeFile(wb, 'report.xlsx');
});


        document.getElementById('exportExcel')?.addEventListener('click', () => {
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
        visibleColumns.forEach(col => {
            row[col.field] = node.data[col.field];
        });
        rows.push(row);
    });

    doc.text('Report', 40, 30);
    doc.autoTable({
        columns: exportCols,
        body: rows,
        startY: 50,
        styles: { fontSize: 8 },
    });

    doc.save('report.pdf');
});

    });
</script>
@endpush