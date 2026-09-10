@extends(backpack_view('blank'))

@section('header')


@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            <div
                class="card-header bg-gradient-success d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Live Order Report Dashboard
                </h2>
            </div>

            <div class="card-body p-0 bg-light">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white"
                    style="border-radius: 15px">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="text" id="quickFilter" class="form-control" style="width: 360px; min-width: 260px;"
                            placeholder="Smart Search...">
                        <button id="resetAll" class="btn btn-outline-danger btn-sm">
                            Reset
                        </button>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">

                            <img src="{{ asset('images/export-excel.png') }}" alt="Excel"
                                style="height:30px; width:auto;">

                        </button>

                        <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">

                            <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">

                        </button>
                    </div>
                </div>

                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width: 100%;"></div>
            </div>

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
<style>
    .ag-theme-quartz .center-header .ag-header-cell-label {
        justify-content: center !important;
    }

    .ag-theme-quartz .ag-header-group-cell-label {
        justify-content: center !important;
    }

    .ag-header-cell-label,
    .ag-header-group-cell-label {
        font-weight: 700 !important;
        justify-content: center !important;
        text-align: center !important;
    }
</style>
@endpush

@push('after_scripts')
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<script>
    const gridConfig = @json($gridConfig ?? []);

    let gridApi;

    const columnDefs = (gridConfig.columns || []).map(col => ({
        headerName: col.headerName,
        field: col.field,
        sortable: true,
        filter: true,
        resizable: true,
        pinned: col.pinned || false,
        width: col.width || 150,
        cellClass: col.cellClass || '',
        type: col.type || null,
    }));

    const gridOptions = {
    columnDefs,
    rowData: gridConfig.data || [],
    pagination: true,
    paginationPageSize: 50,
    rowHeight: 30,
    paginationPageSizeSelector: [20, 50, 100, 200],
    animateRows: true,

    defaultColDef: {
        sortable: true,
        filter: true,
        resizable: true,
        headerClass: 'center-header',
        cellStyle: { textAlign: 'center' }
    },

    onGridReady: params => {
        gridApi = params.api;

        setTimeout(() => {
            const allColumnIds = [];
            gridApi.getAllDisplayedColumns().forEach(column => {
                allColumnIds.push(column.getColId());
            });
            gridApi.autoSizeColumns(allColumnIds);
        }, 300);
    }
};

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

        document.getElementById('exportCsv')?.addEventListener('click', () => {

    const visibleColumns = gridApi.getAllDisplayedColumns()
        .map(c => c.getColDef());

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
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Live Orders');
    XLSX.writeFile(workbook, 'live-orders-' + new Date().toISOString().slice(0,10) + '.xlsx');
});

        document.getElementById('exportPdf')?.addEventListener('click', () => {

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'pt', 'a4');

    const visibleColumns = gridApi.getAllDisplayedColumns()
        .map(c => c.getColDef());

    const cols = visibleColumns.map(col => ({
        header: col.headerName,
        dataKey: col.field
    }));

    const rows = [];
    gridApi.forEachNodeAfterFilterAndSort(node => {
        const r = {};
        visibleColumns.forEach(col => {
            r[col.field] = node.data[col.field] ?? '';
        });
        rows.push(r);
    });

    doc.text('Live Order Report', 40, 30);

    doc.autoTable({
        columns: cols,
        body: rows,
        startY: 50,
        styles: { fontSize: 8 },
        headStyles: { fillColor: [40, 167, 69] }
    });

    doc.save('live-orders.pdf');
});
    });
</script>
@endpush