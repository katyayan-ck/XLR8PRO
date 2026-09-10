@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h2>
        <i class="la la-check-circle text-success"></i> Delivered Bookings
        <small class="d-none d-md-inline">Completed Deliveries</small>
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
                    Delivered Bookings Dashboard
                </h3>
                <div class="d-flex align-items-center gap-2
                        flex-nowrap flex-md-nowrap flex-wrap
                        mt-2 mt-md-0">
                    <label class="text-black mb-0 text-nowrap">Filter:</label>
                    <select id="status_filter" class="form-control w-100 w-md-auto" style="min-width:200px;">
                        <option value="all">All</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="this_year">This Year</option>
                    </select>
                </div>
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
                        <button id="exportPdf" class="btn btn-danger btn-sm text-nowrap w-100 w-md-auto">
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/ag-grid/31.0.1/ag-grid-community.min.js"></script>
<script src="https://unpkg.com/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
            const columnDefs = [
                { headerName: '#', field: 'DT_RowIndex', width: 80, pinned: 'left' },
                { headerName: 'Booking No.', field: 'booking_no', filter: true },
                { headerName: 'Booking Date', field: 'booking_date', filter: true },
                { headerName: 'Inv. Date', field: 'inv_date', filter: true },
                { headerName: 'Inv. No.', field: 'inv_no', filter: true },
                { headerName: 'Chassis No.', field: 'chassis_no', filter: true },
                { headerName: 'Branch', field: 'branch', filter: true },
                { headerName: 'Location', field: 'location', filter: true },
                { headerName: 'Segment', field: 'segment', filter: true },
                { headerName: 'Model', field: 'model', filter: true },
                { headerName: 'Variant', field: 'variant', filter: true },
                { headerName: 'Color', field: 'color', filter: true },
                { headerName: 'Customer', field: 'name', filter: true },
                { headerName: 'Mobile', field: 'mobile', filter: true },
                { headerName: 'Consultant', field: 'consultant', filter: true },
                { headerName: 'Finance Mode', field: 'fin_mode', filter: true },
                { headerName: 'Financier', field: 'financier', filter: true },
                { headerName: 'Action', field: 'action', width: 120, cellRenderer: (params) => params.value, filter: false },
            ];

            const gridOptions = {
                columnDefs: columnDefs,
                rowData: [],
                pagination: true,
                paginationPageSize: 20,
                paginationPageSizeSelector: [10, 20, 50, 100],
                rowSelection: 'multiple',
                animateRows: true,
                filter: true,
                sortable: true,
                sideBar: { toolPanels: ['columns', 'filters'] },
                enableCellTextSelection: true,
                suppressContextMenu: true,
            };

            const gridDiv = document.querySelector('#myGrid');
            const gridApi = agGrid.createGrid(gridDiv, gridOptions);

            const loadData = () => {
                const filter = document.getElementById('status_filter')?.value || 'all';
                fetch(`{{ route('booking.delivered.list') }}?status_filter=${filter}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Server error ' + response.status);
                        return response.json();
                    })
                    .then(data => {
                        if (data.data) {
                            gridApi.setGridOption('rowData', data.data);
                        } else if (data.error) {
                            alert('Error: ' + data.error);
                            gridApi.setGridOption('rowData', []);
                        }
                    })
                    .catch(err => {
                        console.error('Load error:', err);
                        alert('Failed to load data: ' + err.message);
                        gridApi.setGridOption('rowData', []);
                    });
            };

            loadData();

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
                const exportCols = columnDefs.filter(col => col.field !== 'action');
                gridApi.forEachNodeAfterFilterAndSort(node => {
                    const row = {};
                    exportCols.forEach(col => row[col.headerName] = node.data[col.field] ?? '');
                    rows.push(row);
                });
                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Delivered Bookings');
                XLSX.writeFile(wb, 'delivered-bookings.xlsx');
            });

            document.getElementById('exportPdf')?.addEventListener('click', () => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'pt', 'a4');
                const cols = columnDefs.filter(c => c.field !== 'action').map(c => ({ header: c.headerName, dataKey: c.field }));
                const rows = [];
                gridApi.forEachNodeAfterFilterAndSort(node => rows.push(node.data));
                doc.text('Delivered Bookings Report', 40, 30);
                doc.autoTable({
                    columns: cols,
                    body: rows,
                    startY: 50,
                    styles: { fontSize: 8 },
                    headStyles: { fillColor: [0, 123, 255] }  // Info color
                });
                doc.save('delivered-bookings.pdf');
            });

            document.getElementById('status_filter')?.addEventListener('change', loadData);
        });
</script>
@endpush