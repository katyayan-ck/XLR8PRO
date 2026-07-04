
@extends(backpack_view('blank'))

@section('title', 'Parts Ordering Report')

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
<style>
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .ag-theme-quartz .center-header .ag-header-cell-label,
    .ag-theme-quartz .ag-header-group-cell-label {
        justify-content: center !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <!-- HEADER -->
                <div
                    class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        Parts Ordering Report
                    </h2>
                    <div class="d-flex align-items-center gap-3 flex-nowrap">
                        <a href="{{ backpack_url('spare/partwise-requirement') }}"
                            class="btn btn-secondary btn-sm fw-bold shadow-sm">
                            ← Back to Partwise Requirement
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

                        <div class="d-flex gap-2 flex-nowrap">
                            <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                                <img src="{{ asset('images/export-excel.png') }}" alt="Excel"
                                    style="height:30px; width:auto;">
                            </button>
                            <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                                <img src="{{ asset('images/export-pdf.png') }}" alt="PDF"
                                    style="height:30px; width:auto;">
                            </button>
                        </div>
                    </div>

                    <!-- AG Grid -->
                    <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
    let gridApi;

    const columnDefs = [
        { field: 'serial_no',           headerName: 'S.No',           width: 80, pinned: 'left' },
        { field: 'part_number',         headerName: 'Part Number',    pinned: 'left', filter: true, width: 140 },
        { field: 'part_description',    headerName: 'Description',    minWidth: 280, filter: true },
        { field: 'mrp',                 headerName: 'MRP',            width: 110 },
        { field: 'ndp',                 headerName: 'NDP',            width: 110 },
        { field: 'total_consumption',   headerName: 'Consumption',    width: 130, type: 'numericColumn' },
        { field: 'total_required_qty',  headerName: 'Total Req Qty',  width: 130, type: 'numericColumn' },
        { field: 'physical_stock_qty',  headerName: 'Physical Stock', width: 130, type: 'numericColumn' },
        { field: 'mat_in_transit_qty',  headerName: 'In Transit',     width: 110, type: 'numericColumn' },
        { field: 'back_order_qty',      headerName: 'Back Order',     width: 110, type: 'numericColumn' },
        { field: 'total_stock_qty',     headerName: 'Total Stock',    width: 120, type: 'numericColumn' },
        { field: 'net_requirement',     headerName: 'Net Req',        width: 120, type: 'numericColumn' },
        { field: 'to_order_suggested',  headerName: 'To Order',       width: 120, type: 'numericColumn' },
        { field: 'order_value',         headerName: 'Order Value',    width: 140, type: 'numericColumn' },
        {
            field: 'status',
            headerName: 'Status',
            width: 130,
            cellRenderer: params => params.value || '<span class="badge bg-secondary">N/A</span>'
        },
        {
            field: 'action',
            headerName: 'Action',
            pinned: 'right',
            width: 140,
            sortable: false,
            filter: false,
            cellRenderer: params => params.value
        }
    ];

    const gridOptions = {
        columnDefs: columnDefs,
        rowData: [],
        pagination: true,
        paginationPageSize: 50,
        rowHeight: 28,
        animateRows: true,
        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            headerClass: 'center-header',
            cellStyle: { textAlign: 'center' }
        },
        onGridReady: (params) => {
            gridApi = params.api;
            loadOrderingData();
        }
    };

    function loadOrderingData() {
        fetch('{{ route("spare.orderingreport.data") }}')
            .then(response => response.json())
            .then(result => {
                gridApi.setGridOption('rowData', result);
                setTimeout(() => gridApi.autoSizeAllColumns(), 300);
            })
            .catch(error => {
                console.error('Error loading data:', error);
                alert('Failed to load ordering report data. Please try again.');
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#myGrid');
        agGrid.createGrid(gridDiv, gridOptions);

        // Quick Filter
        document.getElementById('quickFilter').addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        document.getElementById('resetAll').addEventListener('click', () => {
            gridApi.setFilterModel(null);
            document.getElementById('quickFilter').value = '';
            gridApi.setGridOption('quickFilterText', '');
            gridApi.setSortModel(null);
        });

        // Export CSV
        document.getElementById('exportCsv').addEventListener('click', () => {
            const rows = [];
            gridApi.forEachNodeAfterFilterAndSort(node => rows.push(node.data));
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.json_to_sheet(rows);
            XLSX.utils.book_append_sheet(wb, ws, "Ordering Report");
            XLSX.writeFile(wb, `parts-ordering-report-${new Date().toISOString().slice(0,10)}.xlsx`);
        });

        // Export PDF (placeholder)
        document.getElementById('exportPdf').addEventListener('click', () => {
            alert("PDF Export feature coming soon...");
        });
    });
</script>
@endpush
