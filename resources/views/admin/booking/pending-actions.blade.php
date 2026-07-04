@extends(backpack_view('blank'))

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">

<style>
    body {
        background: #f8f9fa;
    }

    #myGrid {
        display: block;
    }


    .ag-header-cell-label,
    .ag-header-group-cell-label {
        font-weight: 700 !important;
        font-size: 13px;
        justify-content: center !important;
        text-align: center !important;
    }

    .text-center {
        text-align: center !important;
    }
</style>
@endpush

@section('header')


@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            <div
                class="card-header bg-gradient-success d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Pending Actions Report Dashboard
                </h2>
            </div>


            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white"
                style="border-radius: 15px">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="text" id="quickFilter" class="form-control" style="width:360px; min-width:260px;"
                        placeholder="Smart Search...">
                    <button id="resetAll" class="btn btn-outline-danger btn-sm">Reset</button>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button id="exportExcel" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">

                        <img src="{{ asset('images/export-excel.png') }}" alt="Excel" style="height:30px; width:auto;">

                    </button>

                    <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">

                        <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">

                    </button>

                </div>
            </div>

            <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 220px); width:100%;"></div>
        </div>

        @if(session('info'))
        <div class="alert alert-info mt-3">
            {{ session('info') }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<script>
    const gridConfig = @json($gridConfig ?? []);
    let gridApi;

    const columnDefs = (gridConfig.columns || []).map(col => {
        let headerClass = '';
        if (col.headerName === 'Vehicle Info') headerClass = 'group-vehicle-info';
        else if (col.headerName === 'Bookings') headerClass = 'group-booking';
        else if (col.headerName === 'PENDING ACTIONS') headerClass = 'group-pending';

        const isSnoColumn = col.field === 'sno' || col.headerName?.toLowerCase().includes('s.no');

        const columnDef = {
            headerName: col.headerName,
            headerClass: headerClass || 'text-center',
            width: col.width || (col.children ? 180 : 80),  // S.No. ke liye chhota width
            pinned: col.pinned || (isSnoColumn ? 'left' : false),
            cellClass: col.cellClass || (isSnoColumn ? 'text-center fw-bold' : 'text-center'),
            sortable: col.sortable !== false && !isSnoColumn,   // S.No. pe sort band
            filter: col.filter !== false && !isSnoColumn,       // S.No. pe filter band
            resizable: true,
        };

        if (col.field) {
            columnDef.field = col.field;
        }

        if (col.children) {
            columnDef.children = col.children.map(child => ({
                headerName: child.headerName,
                field: child.field,
                width: child.width || 110,
                cellClass: child.cellClass || 'text-center',
                sortable: true,
                filter: true,
                resizable: true,
            }));
        }

        return columnDef;
    });

    const gridOptions = {
        columnDefs,
        rowData: gridConfig.data || [],
        pagination: true,
        paginationPageSize: 20,
        paginationPageSizeSelector: [10, 20, 50, 100],
        rowHeight: 32,
        animateRows: true,

        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            headerClass: 'text-center',
            cellStyle: { textAlign: 'center' },
            minWidth: 80,
        },

        overlayNoRowsTemplate: '<span class="ag-overlay-no-rows-center">No pending actions data available</span>',

        onGridReady: params => {
            gridApi = params.api;

            setTimeout(() => {
                const allColumnIds = [];
                gridApi.getColumns().forEach(col => allColumnIds.push(col.getColId()));
                gridApi.autoSizeColumns(allColumnIds);
            }, 400);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#myGrid');
        if (!gridDiv) return;

        gridApi = agGrid.createGrid(gridDiv, gridOptions);

        document.getElementById('quickFilter')?.addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        document.getElementById('resetAll')?.addEventListener('click', () => {
            document.getElementById('quickFilter').value = '';
            gridApi.setGridOption('quickFilterText', '');
            gridApi.setFilterModel(null);
        });

        document.getElementById('exportExcel')?.addEventListener('click', () => {
            const allColumns = [];
            function collectLeaves(cols) {
                cols.forEach(col => {
                    if (col.children) collectLeaves(col.children);
                    else if (col.field) allColumns.push({ header: col.headerName, field: col.field });
                });
            }
            collectLeaves(columnDefs);

            const headers = allColumns.map(c => c.header);
            const rows = [headers];

            gridApi.forEachNode(node => {
                if (node.group) return;
                const row = allColumns.map(c => node.data?.[c.field] ?? '');
                rows.push(row);
            });

            const worksheet = XLSX.utils.aoa_to_sheet(rows);
            worksheet['!cols'] = headers.map(h => ({ wch: Math.max(12, h.length + 4) }));

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Pending Actions');
            XLSX.writeFile(workbook, `Pending_Actions_${new Date().toISOString().slice(0,10)}.xlsx`);
        });

        document.getElementById('exportPdf')?.addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

            doc.setFontSize(16);
            doc.text('Pending Actions Report', 40, 40);

            const exportColumns = [];
            const fieldOrder = [];

            function flatten(cols) {
                cols.forEach(col => {
                    if (col.children) flatten(col.children);
                    else if (col.field) {
                        exportColumns.push(col.headerName);
                        fieldOrder.push(col.field);
                    }
                });
            }
            flatten(columnDefs);

            const body = [];
            gridApi.forEachNode(node => {
                if (node.group) return;
                const row = fieldOrder.map(f => node.data?.[f] ?? '');
                body.push(row);
            });

            doc.autoTable({
                head: [exportColumns],
                body,
                startY: 60,
                styles: { fontSize: 8, cellPadding: 4, overflow: 'linebreak', halign: 'center' },
                headStyles: { fillColor: [255, 193, 7], textColor: 0 },
                alternateRowStyles: { fillColor: [245, 245, 245] },
                margin: { top: 60, left: 30, right: 30 },
            });

            doc.save('Pending_Actions_Report.pdf');
        });
    });
</script>
@endpush