@extends(backpack_view('blank'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            {{-- HEADER --}}
            <div
                class="card-header bg-gradient-success d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Stock Report Dashboard
                </h2>
            </div>

            {{-- BODY --}}
            <div class="card-body p-0 bg-light">

                {{-- TOOLBAR --}}
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

                {{-- GRID --}}
                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width: 100%;"></div>
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
<style>
    /* Center normal headers */
    .ag-theme-quartz .center-header .ag-header-cell-label {
        justify-content: center !important;
    }

    /* Center group headers */
    .ag-theme-quartz .ag-header-group-cell-label {
        justify-content: center !important;
    }

    .ag-header-cell-label,
    .ag-header-group-cell-label {
        font-weight: 700 !important;
        justify-content: center !important;
        text-align: center !important;
    }

    .ag-row-pinned {
        background: #f8f9fa !important;
        font-weight: 700;

    }

    .ag-row-pinned .ag-cell {
        text-align: center;
    }

    /* Increase header height so vertical text fit ho */
    .ag-theme-quartz .ag-header {
        height: 90px !important;
    }

    .ag-theme-quartz .ag-header-row.ag-header-row-column {
        height: 140px !important;
    }

    /* Vertical header text */
    .vertical-header .ag-header-cell-text {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        white-space: nowrap;
        text-align: center;
        font-weight: 700;
    }

    /* center */
    .vertical-header .ag-header-cell-label {
        justify-content: center !important;
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

    const columnDefs = [

        { field: 'sno', headerName: 'S.No.', pinned: 'left', width: 80, filter: false },

        {
            headerName: 'VEHICLE INFO',
            children: [
                { field: 'seg',  headerName: 'SEGMENT', width: 140 },
                { field: 'mdl',  headerName: 'MODEL',   width: 160 },
                { field: 'vrnt', headerName: 'VARIANT', width: 220 },
                { field: 'clr',  headerName: 'COLOR',   width: 130 },
            ]
        },

        {
            headerName: 'TOTAL STOCK',
            children: [
                { field: 'total', headerName: 'TOTAL', width: 100, cellClass: 'text-right' },
                { field: 'bkn',   headerName: 'BKN',   width: 80,  cellClass: 'text-right' },
                { field: 'chr',   headerName: 'CHR',   width: 80,  cellClass: 'text-right' },
            ]
        },

        {
            headerName: gridConfig.ovin || 'STOCK VIN-2024',
            children: gridConfig.locbr.map(loc => ({
                field: `ovin_${loc.toLowerCase()}`,
                headerName: loc,
                width: 80,
                cellClass: 'text-right'
            })).concat([
                { field: 'ovin_damage',      headerName: 'DAMAGE',     width: 100, cellClass: 'text-right' },
                { field: 'ovin_dlr_transit', headerName: 'DLR TST',    width: 110, cellClass: 'text-right' },
                { field: 'ovin_oem_transit', headerName: 'OEM TST',    width: 110, cellClass: 'text-right' },
            ])
        },

        {
            headerName: gridConfig.cvin || 'STOCK VIN-2025',
            children: gridConfig.locbr.map(loc => ({
                field: `cvin_${loc.toLowerCase()}`,
                headerName: loc,
                width: 80,
                cellClass: 'text-right'
            })).concat([
                { field: 'cvin_damage',      headerName: 'DAMAGE',     width: 100, cellClass: 'text-right' },
                { field: 'cvin_dlr_transit', headerName: 'DLR TST',    width: 110, cellClass: 'text-right' },
                { field: 'cvin_oem_transit', headerName: 'OEM TST',    width: 110, cellClass: 'text-right' },
            ])
        },

        {
            headerName: 'GLOBAL DATA',
            children: [
                { field: 'tst_max_age',  headerName: 'TST MAX AGE', width: 140 },
                { field: 'stock_max_age', headerName: 'PHY MAX AGE', width: 140 },
                { field: 'stock_gt_60',  headerName: 'AGE > 60D',   width: 120, cellClass: 'text-right' },
                { field: 'bkng',         headerName: 'BOOKED',      width: 120, cellClass: 'text-right' },
                { field: 'enq',          headerName: 'HOT ENQ',     width: 120, cellClass: 'text-right' },
                { field: 'lordr',        headerName: 'LIVE ORDERS', width: 130, cellClass: 'text-right' },
            ]
        }
    ];

    const gridOptions = {
        columnDefs,
        rowData: gridConfig.data || [],
        pagination: true,
        paginationPageSize: 20,
        rowHeight: 25,
        paginationPageSizeSelector: [10, 20, 50, 100],
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

            updateFooter();
        },

        onFilterChanged: updateFooter,
        onSortChanged: updateFooter,
        onRowDataUpdated: updateFooter
    };

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

        // Excel Export with multi-row headers
        document.getElementById('exportCsv')?.addEventListener('click', () => {
            // ── 1. Get locCount FIRST ──
            const locCount = gridConfig.locbr ? gridConfig.locbr.length : 0;

            // ── 2. Now define column starts (safe to use locCount) ──
            const VEHICLE_START = 1;
            const TOTAL_START   = VEHICLE_START + 4;               // after S.No. + SEGMENT + MODEL + VARIANT + COLOR
            const OVIN_START    = TOTAL_START + 3;                 // after TOTAL + BKN + CHR
            const OVIN_END      = OVIN_START + locCount + 3;       // locs + DAMAGE + DLR TST + OEM TST
            const CVIN_START    = OVIN_END;
            const CVIN_END      = CVIN_START + locCount + 3;
            const GLOBAL_START  = CVIN_END;

            // Titles – prefer config value, fallback to sensible defaults
            const ovinTitle = gridConfig.ovin || 'STOCK VIN-2025';
            const cvinTitle = gridConfig.cvin || 'STOCK VIN-2026';

            // Debug (remove after testing)
            console.log('Exporting with:', {
                locCount,
                ovinTitle,
                cvinTitle,
                gridConfig_ovin: gridConfig.ovin,
                gridConfig_cvin: gridConfig.cvin
            });

            // Header Row 1 – place title exactly at start of each group
            const headerRow1 = new Array(GLOBAL_START + 6).fill('');
            headerRow1[VEHICLE_START] = 'VEHICLE INFO';
            headerRow1[TOTAL_START]   = 'TOTAL STOCK';
            headerRow1[OVIN_START]    = ovinTitle;
            headerRow1[CVIN_START]    = cvinTitle;
            headerRow1[GLOBAL_START]  = 'GLOBAL DATA';

            // Header Row 2
            const headerRow2 = [
                'S.No.',
                'SEGMENT', 'MODEL', 'VARIANT', 'COLOR',
                'TOTAL', 'BKN', 'CHR',
                ...Array(locCount).fill('').map((_, i) => gridConfig.locbr?.[i] || `LOC${i+1}`),
                'DAMAGE', 'DLR TST', 'OEM TST',
                ...Array(locCount).fill('').map((_, i) => gridConfig.locbr?.[i] || `LOC${i+1}`),
                'DAMAGE', 'DLR TST', 'OEM TST',
                'TST MAX AGE', 'PHY MAX AGE', 'AGE > 60D', 'BOOKED', 'HOT ENQ', 'LIVE ORDERS'
            ];

            // Data rows (same as before)
            const dataRows = [];
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const d = node.data;
                dataRows.push([
                    d.sno, d.seg, d.mdl, d.vrnt, d.clr,
                    d.total, d.bkn, d.chr,
                    ...gridConfig.locbr.map(loc => d[`ovin_${loc?.toLowerCase() || ''}`] || 0),
                    d.ovin_damage || 0, d.ovin_dlr_transit || 0, d.ovin_oem_transit || 0,
                    ...gridConfig.locbr.map(loc => d[`cvin_${loc?.toLowerCase() || ''}`] || 0),
                    d.cvin_damage || 0, d.cvin_dlr_transit || 0, d.cvin_oem_transit || 0,
                    d.tst_max_age || 0, d.stock_max_age || 0, d.stock_gt_60 || 0,
                    d.bkng || 0, d.enq || 0, d.lordr || 0
                ]);
            });

            const footer = getFooterTotals();

const footerRow = [
    'TOTAL',
    '', '', '', '',
    footer.total || 0,
    footer.bkn || 0,
    footer.chr || 0,
    ...gridConfig.locbr.map(loc => footer[`ovin_${loc.toLowerCase()}`] || 0),
    footer.ovin_damage || 0,
    footer.ovin_dlr_transit || 0,
    footer.ovin_oem_transit || 0,

    ...gridConfig.locbr.map(loc => footer[`cvin_${loc.toLowerCase()}`] || 0),
    footer.cvin_damage || 0,
    footer.cvin_dlr_transit || 0,
    footer.cvin_oem_transit || 0,

    '', '',
    footer.stock_gt_60 || 0,
    footer.bkng || 0,
    footer.enq || 0,
    footer.lordr || 0
];

const aoa = [headerRow1, headerRow2, ...dataRows, footerRow];
            const ws = XLSX.utils.aoa_to_sheet(aoa);
            const footerIndex = 2 + dataRows.length; // headerRow1 + headerRow2 + dataRows

for (let col = 0; col < footerRow.length; col++) {
    const cellAddress = XLSX.utils.encode_cell({ r: footerIndex, c: col });

    if (!ws[cellAddress]) continue;

    ws[cellAddress].s = {
        font: { bold: true }
    };
}

            // Merges – now safe because locCount is defined early
            ws['!merges'] = [
                { s: { r: 0, c: VEHICLE_START },     e: { r: 0, c: TOTAL_START - 1 } },
                { s: { r: 0, c: TOTAL_START },       e: { r: 0, c: OVIN_START - 1 } },
                { s: { r: 0, c: OVIN_START },        e: { r: 0, c: CVIN_START - 1 } },
                { s: { r: 0, c: CVIN_START },        e: { r: 0, c: GLOBAL_START - 1 } },
                { s: { r: 0, c: GLOBAL_START },      e: { r: 0, c: headerRow2.length - 1 } }
            ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Stock Report');

            const today = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(wb, `stock-report-${today}.xlsx`, { cellStyles: true });
            XLSX.writeFile(wb, `stock-report-${today}.xlsx`);
        });

        // PDF Export with multi-row headers
        document.getElementById('exportPdf')?.addEventListener('click', () => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

            doc.setFontSize(14);
            doc.text("Stock Summary Report", 40, 40);

            const headerRow1 = [
                '', 'VEHICLE INFO', '', '', '', 'TOTAL STOCK', '', '',
                gridConfig.ovin || 'STOCK VIN-2024', '', '', '', '', '',
                gridConfig.cvin || 'STOCK VIN-2025', '', '', '', '', '',
                'GLOBAL DATA', '', '', '', ''
            ];

            const headerRow2 = [
                'S.No.', 'SEGMENT', 'MODEL', 'VARIANT', 'COLOR',
                'TOTAL', 'BKN', 'CHR',
                ...gridConfig.locbr.map(loc => loc), 'DAMAGE', 'DLR TST', 'OEM TST',
                ...gridConfig.locbr.map(loc => loc), 'DAMAGE', 'DLR TST', 'OEM TST',
                'TST MAX AGE', 'PHY MAX AGE', 'AGE > 60D', 'BOOKED', 'HOT ENQ', 'LIVE ORDERS'
            ];

            const body = [];
            gridApi.forEachNodeAfterFilterAndSort(node => {
                const d = node.data;
                body.push([
                    d.sno, d.seg, d.mdl, d.vrnt, d.clr,
                    d.total, d.bkn, d.chr,
                    ...gridConfig.locbr.map(loc => d[`ovin_${loc.toLowerCase()}`] || 0),
                    d.ovin_damage, d.ovin_dlr_transit, d.ovin_oem_transit,
                    ...gridConfig.locbr.map(loc => d[`cvin_${loc.toLowerCase()}`] || 0),
                    d.cvin_damage, d.cvin_dlr_transit, d.cvin_oem_transit,
                    d.tst_max_age, d.stock_max_age, d.stock_gt_60, d.bkng, d.enq, d.lordr
                ]);
            });

            doc.autoTable({
                head: [headerRow1, headerRow2],
                body,
                startY: 60,
                styles: { fontSize: 8, cellPadding: 4, overflow: 'linebreak', halign: 'center' },
                headStyles: [{
                    fillColor: [40, 167, 69],
                    textColor: 255,
                    fontStyle: 'bold'
                }, {
                    fillColor: [60, 187, 89],
                    textColor: 255,
                    fontStyle: 'bold'
                }],
                alternateRowStyles: { fillColor: [245, 245, 245] },
                margin: { top: 60, left: 30, right: 30 },
                didParseCell: function (data) {
                    if (data.row.index === 0) {
                        if (data.column.index === 1) data.cell.colSpan = 4;
                        if (data.column.index === 5) data.cell.colSpan = 3;
                        if (data.column.index === 8) data.cell.colSpan = gridConfig.locbr.length + 3;
                        if (data.column.index === 8 + gridConfig.locbr.length + 3) data.cell.colSpan = gridConfig.locbr.length + 3;
                        if (data.column.index === 8 + gridConfig.locbr.length + 3 + gridConfig.locbr.length + 3) data.cell.colSpan = 6;
                    }
                }
            });

            doc.save('stock-summary-report.pdf');
        });
    });
    function updateFooter() {
        if (!gridApi) return;

        const sums = {};
        const cols = gridApi.getAllDisplayedColumns();

        // initialize footer values
        cols.forEach(col => {
            const field = col.getColId();
            sums[field] = null;   // use null instead of ''
        });

        gridApi.forEachNodeAfterFilterAndSort(node => {
            const data = node.data;

            cols.forEach(col => {
                const field = col.getColId();
                const val = data[field];

                // sum only real numbers
                if (typeof val === 'number' && !isNaN(val)) {

                    if (sums[field] === null) {
                        sums[field] = 0;
                    }

                    sums[field] += val;
                }
            });
        });

        // keep S.No blank
        sums['sno'] = null;

        gridApi.setGridOption('pinnedBottomRowData', [sums]);
    }

    function getFooterTotals() {

        const totals = {};
        const cols = gridApi.getAllDisplayedColumns();

        cols.forEach(col => {
            totals[col.getColId()] = 0;
        });

        gridApi.forEachNodeAfterFilterAndSort(node => {
            const d = node.data;

            cols.forEach(col => {
                const field = col.getColId();
                const val = d[field];

                if (typeof val === 'number' && !isNaN(val)) {
                    totals[field] += val;
                }
            });
        });

        totals['sno'] = ''; // keep blank

        return totals;
    }
</script>
@endpush
