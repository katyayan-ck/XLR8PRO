@extends(backpack_view('blank'))

@section('title', 'Spare Orders')

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
                        Spare Orders
                    </h2>
                    <div class="d-flex align-items-center gap-3">
                        <a href="{{ backpack_url('spare-request/create') }}"
                            class="btn btn-primary btn-sm fw-bold shadow-sm">
                            <i class="fa fa-plus"></i> Add New Request
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
        { field: 'serial_no',           headerName: 'S.No.',              width: 80, pinned: 'left' },
        { field: 'posting_date',        headerName: 'Posting Date',       width: 120, filter: true },
        { field: 'req_no',              headerName: 'Xceler8 Req No',     width: 140, filter: true, pinned: 'left' },
        { field: 'branch_name',         headerName: 'Service Branch',     width: 150, filter: true },
        { field: 'service_category',    headerName: 'Service Category',   width: 140, filter: true },
        { field: 'workshop_type',       headerName: 'Workshop Type',      width: 130, filter: true },
        { field: 'model',               headerName: 'Model',              width: 120, filter: true },
        { field: 'variant',             headerName: 'Variant',            width: 130, filter: true },
        { field: 'cust_name',           headerName: 'Customer Name',      minWidth: 180, filter: true },
        { field: 'cust_mobile',         headerName: 'Contact No',         width: 130, filter: true },
        { field: 'regn_no',             headerName: 'Vehicle Reg. No',    width: 140, filter: true },
        { field: 'ro_number',           headerName: 'RO Number',          width: 130, filter: true },
        { field: 'ro_date',             headerName: 'RO Date',            width: 120, filter: true },
        { field: 'ro_age',              headerName: 'RO Age',             width: 100, filter: true },
        { field: 'parts_count',         headerName: 'Parts (SKU) Count',  width: 140, type: 'numericColumn' },
        { field: 'parts_qty',           headerName: 'Parts (SKU) Qty',    width: 140, type: 'numericColumn' },
        { field: 'remark',              headerName: 'Remarks',            minWidth: 200, filter: true },
        {
            field: 'action',
            headerName: 'Action',
            pinned: 'right',
            width: 160,
            sortable: false,
            filter: false,
            cellRenderer: params => `
                <div class="d-flex gap-2">
                    <a href="${backpack_url('spare-request/' + params.data.id)}" class="btn btn-sm btn-info py-1 px-2">View</a>
                    <a href="${backpack_url('spare-request/' + params.data.id + '/edit')}" class="btn btn-sm btn-primary py-1 px-2">Edit</a>
                </div>
            `
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
            loadSpareList();
        }
    };

    function loadSpareList() {
        fetch('{{ route("spare-request.data") }}')   // We'll add this route
            .then(response => response.json())
            .then(result => {
                gridApi.setGridOption('rowData', result);
                setTimeout(() => gridApi.autoSizeAllColumns(), 300);
            })
            .catch(error => {
                console.error(error);
                alert('Failed to load data');
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
            XLSX.utils.book_append_sheet(wb, ws, "Spare Orders");
            XLSX.writeFile(wb, `spare-orders-${new Date().toISOString().slice(0,10)}.xlsx`);
        });
    });
</script>
@endpush
