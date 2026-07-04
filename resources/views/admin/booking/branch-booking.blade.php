@extends(backpack_view('blank'))

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">

<style>
    body {
        background: #f8f9fa;
    }

    #branchGrid {
        display: block;
    }

    /* Group Header Colors – exact match from your screenshots */

    .ag-header-cell-label,
    .ag-header-group-cell-label {
        font-weight: 700 !important;
        font-size: 13px;
        justify-content: center !important;
        text-align: center !important;
    }
</style>
@endpush

@section('header')

@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div
                class="card-header bg-gradient-success d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Branch Dashboard
                </h2>
            </div>

            {{-- TOOLBAR --}}
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

                        {{-- <span>Excel</span> --}}
                    </button>

                    <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">

                        <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">

                        {{-- <span>PDF</span> --}}
                    </button>

                </div>
            </div>

            {{-- GRID --}}
            <div id="branchGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
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
        else if (col.headerName === 'STOCK') headerClass = 'group-stock';
        else if (col.headerName === 'SELECTED BRANCH LOCATIONS') headerClass = 'group-selected-branch';
        else if (col.headerName === 'OTHER') headerClass = 'group-other';
        else if (col.headerName === 'HOT ENQ') headerClass = 'group-hot-enq';
        else if (col.headerName === 'INT IN FINANCE') headerClass = 'group-finance';
        else if (col.headerName === 'INT IN EXCH') headerClass = 'group-exchange';
        else if (col.headerName === 'GLOBAL INFO') headerClass = 'group-global';
        else if (col.headerName === 'PENDING ACTIONS') headerClass = 'group-pending';

        const isSnoColumn = col.field === 'sno' || col.headerName?.toLowerCase().includes('s.no');

        const columnDef = {
            headerName: col.headerName,
            headerClass: headerClass || 'ag-header-center',
            width: col.width || (col.children ? 180 : 130),
            pinned: col.pinned || (isSnoColumn ? 'left' : false),
            cellClass: col.cellClass || (isSnoColumn ? 'text-center fw-bold' : 'text-center'),
            sortable: col.sortable !== false && !isSnoColumn,   // usually false for sno
            filter: col.filter !== false && !isSnoColumn,       // usually false for sno
            resizable: true,
        };

        // Only set field if it actually exists (prevents issues on group columns)
        if (col.field) {
            columnDef.field = col.field;
        }

        if (col.children) {
            columnDef.children = col.children.map(child => ({
                headerName: child.headerName,
                field: child.field,
                width: child.width || 110,
                cellClass: child.cellClass || 'text-right ag-right-aligned-cell',
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
            cellStyle: { textAlign: 'center' }
        },

        overlayNoRowsTemplate: '<span class="ag-overlay-no-rows-center">No bookings found for selected filters</span>',

        onGridReady: params => {
            gridApi = params.api;

            // Auto-size all columns after a small delay
            setTimeout(() => {
                const allColumnIds = [];
                gridApi.getColumns().forEach(column => {
                    allColumnIds.push(column.getColId());
                });
                gridApi.autoSizeColumns(allColumnIds, false); // false = skip header
            }, 400);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const gridDiv = document.querySelector('#branchGrid');
        if (!gridDiv) return;

        gridApi = agGrid.createGrid(gridDiv, gridOptions);

        // Quick filter
        document.getElementById('quickFilter')?.addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        // Reset button
        document.getElementById('resetAll')?.addEventListener('click', () => {
            document.getElementById('quickFilter').value = '';
            gridApi.setGridOption('quickFilterText', '');
        });

        // ────────────────────────────────────────────────
        // Excel Export – same improved version
        // ────────────────────────────────────────────────
        document.getElementById('exportExcel')?.addEventListener('click', () => {
            const allColumns = [];

            function collectLeafColumns(cols) {
                cols.forEach(col => {
                    if (col.children) {
                        collectLeafColumns(col.children);
                    } else if (col.field) {
                        allColumns.push({
                            headerName: col.headerName,
                            field: col.field
                        });
                    }
                });
            }

            collectLeafColumns(columnDefs);

            const headers = allColumns.map(c => c.headerName);

            const rows = [headers];

            gridApi.forEachNodeAfterFilterAndSort(node => {
                const row = allColumns.map(c => {
                    let value = node.data?.[c.field];
                    if (typeof value === 'number') value = Number(value);
                    return value ?? '';
                });
                rows.push(row);
            });

            const worksheet = XLSX.utils.aoa_to_sheet(rows);
            worksheet['!cols'] = headers.map(h => ({ wch: Math.max(12, h.length + 5) }));

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Branch Booking');

            const fileName = `Branch_Booking_Report_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(workbook, fileName);
        });

        // PDF Export – same as before
        document.getElementById('exportPdf')?.addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

            doc.setFontSize(16);
            doc.text('Branch Booking Report', 40, 40);

            const exportColumns = [];
            const fieldOrder = [];

            const flattenColumns = (cols) => {
                cols.forEach(col => {
                    if (col.children) {
                        flattenColumns(col.children);
                    } else if (col.field) {
                        exportColumns.push(col.headerName);
                        fieldOrder.push(col.field);
                    }
                });
            };

            flattenColumns(columnDefs);

            const body = [];
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const row = fieldOrder.map(field => node.data?.[field] ?? '');
                body.push(row);
            });

            doc.autoTable({
                head: [exportColumns],
                body: body,
                startY: 60,
                styles: { fontSize: 8, cellPadding: 4, overflow: 'linebreak', halign: 'center' },
                headStyles: { fillColor: [40, 167, 69], textColor: 255 },
                alternateRowStyles: { fillColor: [245, 245, 245] },
                margin: { top: 60, left: 30, right: 30 },
            });

            doc.save('Branch_Booking_Report.pdf');
        });
    });
</script>
@endpush
