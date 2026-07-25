{{-- resources/views/booking/invoiced.blade.php --}}
@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h2>
        <i class="la la-file-invoice text-info"></i>
        Invoiced Bookings
        <small class="d-none d-md-inline">Invoiced Orders</small>
    </h2>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            <div class="card-header bg-gradient-info
                        d-flex justify-content-between align-items-center
                        flex-nowrap flex-md-nowrap flex-wrap">
                <h3 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Invoiced Dashboard
                </h3>
            </div>

            <div class="card-body p-0" style="background:#f8fafc">

                <div class="d-flex justify-content-between align-items-center
                            flex-wrap gap-2
                            p-3 border-bottom bg-white">

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="text" id="quickFilter" class="form-control w-100 w-md-auto"
                            style="width:360px; min-width:260px;" placeholder="Smart Search...">



                        <button id="resetAll" class="btn btn-outline-danger btn-sm text-nowrap">
                            Reset
                        </button>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-2 mt-md-0">
                        <button id="exportCsv" class="btn btn-success btn-sm text-nowrap w-100 w-md-auto">
                            <i class="la la-file-excel-o"></i> Excel
                        </button>

                        <button id="exportExcel" class="btn btn-danger btn-sm text-nowrap w-100 w-md-auto">
                            <i class="la la-file-pdf-o"></i> PDF
                        </button>
                    </div>
                </div>

                <div id="myGrid" class="ag-theme-quartz" style="height: calc(110vh - 240px); width:100%;">
                </div>
            </div>
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
    let gridApi;

    const columnDefs = [
        { field: 'booking_no', headerName: 'Booking No.', pinned: 'left', width: 150 },
        { field: 'booking_date', headerName: 'Booking Date', width: 130 },
        { field: 'inv_date', headerName: 'Invoice Date', width: 130 },
        { field: 'branch_id', headerName: 'Branch', width: 160 },
        { field: 'location', headerName: 'Location', width: 160 },
        { field: 'segment_id', headerName: 'Segment', width: 120 },
        { field: 'model', headerName: 'Model', width: 200 },
        { field: 'variant', headerName: 'Variant', width: 300 },
        { field: 'color', headerName: 'Color', width: 120 },
        { field: 'name', headerName: 'Customer', width: 200 },
        { field: 'mobile', headerName: 'Mobile', width: 140 },
        { field: 'collector_name', headerName: 'Collector', width: 180 },
        { field: 'financier', headerName: 'Financier', width: 160 },
        { field: 'chassis_no', headerName: 'Chassis No.', width: 150 },
        { field: 'del_date', headerName: 'Delivery Date', width: 130 },
        { field: 'accessories', headerName: 'Accessories', width: 180 },
        {
            field: 'action',
            headerName: 'Action',
            pinned: 'right',
            width: 140,
            cellRenderer: params => params.value || ''
        },
    ];

    const gridOptions = {
        columnDefs,
        rowHeight: 30,
        pagination: true,
        paginationPageSize: window.innerWidth < 768 ? 10 : 50,
        // sideBar: true,
        animateRows: true,
        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#myGrid');
        gridApi = agGrid.createGrid(gridDiv, gridOptions);

        // Load data
        fetch("{{ route('booking.invoiced.list') }}")
            .then(res => res.json())
            .then(res => {
                gridApi.setGridOption('rowData', res.data);
            })
            .catch(err => console.error('Data load error:', err));

        document.getElementById('quickFilter')?.addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        document.getElementById('resetAll')?.addEventListener('click', () => {
            gridApi.setFilterModel(null);
            gridApi.setGridOption('quickFilterText', '');
            document.getElementById('quickFilter').value = '';
        });

        document.getElementById('exportCsv')?.addEventListener('click', () => {
            const rows = [];
            const exportCols = columnDefs.filter(c => c.field !== 'action');
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const row = {};
                exportCols.forEach(col => {
                    row[col.headerName] = node.data[col.field] ?? '';
                });
                rows.push(row);
            });
            const ws = XLSX.utils.json_to_sheet(rows);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Invoiced Bookings');
            XLSX.writeFile(wb, 'invoiced-bookings.xlsx');
        });

        document.getElementById('exportExcel')?.addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'pt', 'a4');
            const exportCols = columnDefs.filter(c => c.field !== 'action')
                .map(c => ({ header: c.headerName, dataKey: c.field }));
            const rows = [];
            gridApi.forEachNodeAfterFilterAndSort(n => rows.push(n.data));
            doc.text('Invoiced Bookings Report', 40, 30);
            doc.autoTable({
                columns: exportCols,
                body: rows,
                startY: 50,
                styles: { fontSize: 8 },
                headStyles: { fillColor: [23, 162, 184] }, 
            });
            doc.save('invoiced-bookings.pdf');
        });
    });
</script>
@endpush