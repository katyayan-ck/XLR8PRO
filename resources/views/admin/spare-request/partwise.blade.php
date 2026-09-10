@extends(backpack_view('blank'))

@section('title', 'Spare Parts Allotment')

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
                        Spare Parts Allotment
                    </h2>
                    <div class="d-flex align-items-center gap-3 flex-nowrap">
                        <a href="{{ backpack_url('spare-request') }}"
                            class="btn btn-secondary btn-sm fw-bold shadow-sm">
                            ← Back to Spare Requests
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
        { field: 'serial_no',              headerName: 'S.No.',                  width: 80, pinned: 'left' },
        { field: 'ro_age',                 headerName: 'RO Age (Days)',          width: 110, filter: true },
        { field: 'part_number',            headerName: 'Part Number',            pinned: 'left', filter: true, width: 140 },
        { field: 'part_description',       headerName: 'Part Description',       filter: true, minWidth: 250 },

        { field: 'total_required_qty',     headerName: 'Total Required Qty',     width: 140, type: 'numericColumn' },
        { field: 'total_ro_count',         headerName: 'Total RO Count',         width: 120 },
        { field: 'total_cs_count',         headerName: 'Total CS Count',         width: 130 },

        { field: 'workshop_req_qty',       headerName: 'Workshop Req Qty',       width: 150, type: 'numericColumn' },
        { field: 'workshop_ro_count',      headerName: 'Workshop RO Count',      width: 160 },
        { field: 'workshop_cs_count',      headerName: 'Workshop CS Count',      width: 170 },

        { field: 'bodyshop_req_qty',       headerName: 'Bodyshop Req Qty',       width: 150, type: 'numericColumn' },
        { field: 'bodyshop_ro_count',      headerName: 'Bodyshop RO Count',      width: 170 },
        { field: 'bodyshop_cs_count',      headerName: 'Bodyshop CS Count',      width: 180 },

        { field: 'physical_stock_qty',     headerName: 'Physical Stock Qty',     width: 150 },
        { field: 'mat_in_transit_qty',     headerName: 'Mat in Transit Qty',     width: 150 },
        { field: 'back_order_qty',         headerName: 'Back Order Qty',         width: 140 },
        { field: 'total_stock_qty',        headerName: 'Total Stock Qty',        width: 140 },

        { field: 'allotted_qty',           headerName: 'Allotted Qty',           width: 130 },
        { field: 'issued_qty',             headerName: 'Issued Qty',             width: 120 },
        { field: 'returned_qty',           headerName: 'Returned Qty',           width: 130 },
        { field: 'balance_qty',            headerName: 'Balance Qty',            width: 130 },

        {
            field: 'status',
            headerName: 'Status',
            width: 130,
            cellRenderer: params => params.value || '<span class="badge bg-secondary">N/A</span>'
        },
        {
            field: 'action',
            headerName: 'Allotment',
            pinned: 'right',
            width: 130,
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
        rowHeight: 42,
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
            loadPartwiseData();
        }
    };

    function loadPartwiseData() {
        fetch('{{ route("spare.partwise.data") }}')
            .then(response => response.json())
            .then(result => {
                gridApi.setGridOption('rowData', result);
                setTimeout(() => gridApi.autoSizeAllColumns(), 300);
            })
            .catch(error => {
                console.error('Error loading data:', error);
                alert('Failed to load data. Please try again.');
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#myGrid');
        agGrid.createGrid(gridDiv, gridOptions);

        document.getElementById('quickFilter').addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        document.getElementById('resetAll').addEventListener('click', () => {
            gridApi.setFilterModel(null);
            document.getElementById('quickFilter').value = '';
            gridApi.setGridOption('quickFilterText', '');
            gridApi.setSortModel(null);
        });

        document.getElementById('exportCsv').addEventListener('click', () => {
            const rows = [];
            gridApi.forEachNodeAfterFilterAndSort(node => rows.push(node.data));
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.json_to_sheet(rows);
            XLSX.utils.book_append_sheet(wb, ws, "Spare Parts Allotment");
            XLSX.writeFile(wb, `spare-allotment-${new Date().toISOString().slice(0,10)}.xlsx`);
        });
    });
</script>
@endpush