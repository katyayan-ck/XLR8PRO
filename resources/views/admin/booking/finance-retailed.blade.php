@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h2>
        <i class="la la-money-check text-success"></i> Finance Retailed
        <small class="d-none d-md-inline">Finance Retailed Bookings</small>
    </h2>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            {{-- HEADER --}}
            <div class="card-header bg-gradient-success
                        d-flex justify-content-between align-items-center
                        flex-nowrap flex-md-nowrap flex-wrap">
                <h3 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Finance Retailed Dashboard
                </h3>

                <div class="d-flex align-items-center gap-2
                            flex-nowrap flex-md-nowrap flex-wrap
                            mt-2 mt-md-0">
                    <label class="text-white mb-0 text-nowrap">Status:</label>
                    <select id="status_filter" class="form-control w-100 w-md-auto" style="min-width:200px;">
                        <option value="" {{ request('status_filter')=='' ? 'selected' : '' }}>All</option>
                        <option value="some_status" {{ request('status_filter')=='some_status' ? 'selected' : '' }}>
                            Filter 1</option>
                        <option value="all">All</option>
                    </select>
                </div>
            </div>

            <div class="card-body p-0" style="background:#f8fafc">

                <div class="d-flex justify-content-between align-items-center
                            flex-wrap gap-2
                            p-3 border-bottom bg-white">

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="text" id="quickFilter" class="form-control" style="width:340px;"
                            placeholder="Smart Search...">
                        <button id="resetAll" class="btn btn-sm btn-outline-danger">Reset</button>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button id="exportCsv" class="btn btn-sm btn-success">
                            <i class="la la-file-excel-o"></i> Excel
                        </button>
                        <button id="exportPdf" class="btn btn-sm btn-danger">
                            <i class="la la-file-pdf-o"></i> PDF
                        </button>
                    </div>
                </div>

                <div id="myGrid" class="ag-theme-quartz" style="height: calc(110vh - 260px); width:100%;"></div>

            </div>
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
    document.addEventListener('DOMContentLoaded', () => {
    const gridDiv = document.querySelector('#myGrid');
    if (!gridDiv) return;

    const gridConfig = @json($gridConfig);

    const columnDefs = gridConfig.columns.map(col => ({
        headerName: col.headerName,
        field: col.field,
        sortable: true,
        filter: true,
        resizable: true,
        cellRenderer: col.field === 'action' ? params => params.value || '' : null,
    }));

    const gridOptions = {
        columnDefs,
        rowData: gridConfig.data,
        pagination: true,
        paginationPageSize: 50,
        sideBar: true,
        animateRows: true,
        domLayout: 'autoHeight',
    };

    const gridApi = agGrid.createGrid(gridDiv, gridOptions);

    document.getElementById('quickFilter')?.addEventListener('input', e => {
        gridApi.setGridOption('quickFilterText', e.target.value);
    });

    document.getElementById('resetAll')?.addEventListener('click', () => {
        gridApi.setFilterModel(null);
        gridApi.setGridOption('quickFilterText', '');
        document.getElementById('quickFilter').value = '';
    });

    document.getElementById('status_filter')?.addEventListener('change', e => {
        const value = e.target.value;
        window.location.href = `{{ route('finance.booking.retailed') }}?status_filter=${value}`;
    });

    document.getElementById('exportCsv')?.addEventListener('click', () => {
        const rows = [];
        const exportColumns = columnDefs.filter(col => col.field !== 'action');

        gridApi.forEachNodeAfterFilterAndSort(node => {
            const row = {};
            exportColumns.forEach(col => {
                row[col.headerName] = node.data[col.field] ?? '';
            });
            rows.push(row);
        });

        const worksheet = XLSX.utils.json_to_sheet(rows);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Finance Retailed');
        XLSX.writeFile(workbook, 'finance-retailed.xlsx');
    });

    document.getElementById('exportPdf')?.addEventListener('click', () => {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'a4');

        const exportColumns = columnDefs
            .filter(col => col.field !== 'action')
            .map(col => ({ header: col.headerName, dataKey: col.field }));

        const rows = [];
        gridApi.forEachNodeAfterFilterAndSort(node => {
            rows.push(node.data);
        });

        doc.text('Finance Retailed Report', 40, 30);
        doc.autoTable({
            columns: exportColumns,
            body: rows,
            startY: 50,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [40, 167, 69] }, // success green
        });

        doc.save('finance-retailed.pdf');
    });
});
</script>
@endpush