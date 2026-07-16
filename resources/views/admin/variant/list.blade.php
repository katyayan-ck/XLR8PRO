@extends(backpack_view('blank'))

@section('title', 'All Variants')

@push('after_styles')
    <link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
    <style>
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .ag-theme-quartz .center-header .ag-header-cell-label {
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
        const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);

        function getCols(fields) {
            return ALL_COLUMNS.filter(col => fields.includes(col.field));
        }

        let gridApi;

        // Default Visible Columns
        const DEFAULT_VISIBLE_FIELDS = [
                'serial_no',
                'segment',
                'sub_segment',
                'model',

                'code',
                'oem_name',
                'custom_name',
                'display_name',

                'fuel_type',
                'seating_capacity',

                'transmission',

                'is_active',
                'action'
            ];

        // ==================== COLUMN DEFINITION (FLAT - No Grouping) ====================
        // const columnDefs = [
        //     ...getCols(['serial_no', 'brand', 'segment', 'sub_segment', 'model']).map(col => {
        //         if (col.field === 'serial_no') col.pinned = 'left';
        //         return col;
        //     }),

        //     ...getCols(['name', 'oem_code', 'custom_name']),

        //     ...getCols(['seating_capacity', 'wheels', 'gvw', 'cc_capacity']),

        //     ...getCols(['is_active']),

        //     ...getCols(['action']).map(col => {
        //         col.pinned = 'right';
        //         col.width = 140;
        //         col.sortable = false;
        //         col.filter = false;
        //         col.cellRenderer = 'htmlRenderer';
        //         return col;
        //     })
        // ];
        const columnDefs = [

            ...getCols([
                'serial_no',
                'segment',
                'sub_segment',
                'model'
            ]),

            ...getCols([
                'code',
                'oem_name',
                'custom_name',
                'display_name'
            ]),

            ...getCols([
                'permit',
                'fuel_type',
                'body_type',
                'body_make',
                'status'
            ]),

            ...getCols([
                'seating_capacity',
                'wheels',
                'gvw',
                'cc_capacity'
            ]),

            ...getCols([
                'transmission',
                'drivetrain',
                'is_csd',
                'csd_index',
                'is_active'
            ]),

            ...getCols(['action']).map(col => {
                col.pinned = 'right';
                col.width = 140;
                col.sortable = false;
                col.filter = false;
                col.cellRenderer = 'htmlRenderer';
                return col;
            })
        ];
        const allFlatColumns = ALL_COLUMNS;

        const gridOptions = {
            columnDefs: columnDefs,
            rowData: @json($gridConfig['data'] ?? []),
            pagination: true,
            paginationPageSize: 50,
            paginationPageSizeSelector: [20, 50, 100, 200],
            rowHeight: 28,
            animateRows: true,

            defaultColDef: {
                sortable: true,
                filter: true,
                resizable: true,
                headerClass: 'center-header',
                cellStyle: { textAlign: 'center' },
            },

            components: {
                htmlRenderer: params => params.value || '',
            },

            onGridReady: (params) => {
                gridApi = params.api;

                const allFields = columnDefs
                    .flatMap(group => Array.isArray(group) ? group : (group.children || [group]))
                    .map(col => col.field)
                    .filter(Boolean);

                gridApi.setColumnsVisible(allFields, false);
                gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);

                setTimeout(() => {
                    const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
                    gridApi.autoSizeColumns(visibleIds, false);
                }, 300);
            }
        };

        // ==================== Customise Headers Popup (Flat Version) ====================
        function openColumnBubble() {
            const bubble = document.getElementById('columnBubble');
            const tbody = document.getElementById('columnBubbleBody');
            if (!gridApi || !bubble || !tbody) return;

            tbody.innerHTML = '';

            const allFlatColumns = ALL_COLUMNS;

            allFlatColumns.forEach(col => {
                if (!col.field) return;

                const tr = document.createElement('tr');

                const tdCheck = document.createElement('td');
                tdCheck.style.width = '40px';
                tdCheck.className = 'text-center';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = gridApi.getColumn(col.field)?.isVisible() ?? false;

                // Disable primary columns (Hierarchy + Variant Details)
                if ([
                    'serial_no',
                    'segment',
                    'sub_segment',
                    'model',
                    'oem_name',
                    'custom_name',
                    'display_name'
                ].includes(col.field)) {
                    checkbox.disabled = true;
                }

                // Disable action column
                if (col.field === 'action') {
                    checkbox.disabled = true;
                }

                checkbox.addEventListener('change', () => {
                    gridApi.setColumnsVisible([col.field], checkbox.checked);
                });

                tdCheck.appendChild(checkbox);

                const tdLabel = document.createElement('td');
                tdLabel.innerText = col.headerName || col.field;

                tr.appendChild(tdCheck);
                tr.appendChild(tdLabel);
                tbody.appendChild(tr);
            });

            bubble.style.display = 'block';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const gridDiv = document.querySelector('#myGrid');
            agGrid.createGrid(gridDiv, gridOptions);

            
            document.getElementById('quickFilter')?.addEventListener('input', e => {
                gridApi.setGridOption('quickFilterText', e.target.value);
            });

            
            document.getElementById('resetAll')?.addEventListener('click', () => {
                gridApi.setFilterModel(null);
                gridApi.setSortModel(null);
                gridApi.setGridOption('quickFilterText', '');
                document.getElementById('quickFilter').value = '';
            });

            
            document.getElementById('btnCustomiseHeaders')?.addEventListener('click', e => {
                e.stopPropagation();
                openColumnBubble();
            });

            document.getElementById('closeColumnBubble')?.addEventListener('click', () => {
                document.getElementById('columnBubble').style.display = 'none';
            });

            document.getElementById('columnBubble')?.addEventListener('click', e => e.stopPropagation());

            document.addEventListener('click', () => {
                const bubble = document.getElementById('columnBubble');
                if (bubble) bubble.style.display = 'none';
            });

            
            document.getElementById('btnAllHeaders')?.addEventListener('click', () => {
                const allFields = columnDefs
                    .flatMap(group => group.children || [group])
                    .map(col => col.field)
                    .filter(Boolean);

                gridApi.setColumnsVisible(allFields, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 200);
            });

            
            document.getElementById('btnDefaultHeaders')?.addEventListener('click', () => {
                const allFields = columnDefs
                    .flatMap(group => group.children || [group])
                    .map(col => col.field)
                    .filter(Boolean);

                gridApi.setColumnsVisible(allFields, false);
                gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);

                setTimeout(() => {
                    const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
                    gridApi.autoSizeColumns(visibleIds, false);
                }, 200);
            });

            
            document.getElementById('exportCsv')?.addEventListener('click', () => {
                const visibleColumns = gridApi.getAllDisplayedColumns()
                    .map(col => col.getColDef())
                    .filter(col => col.field && col.field !== 'action');

                const rows = [];
                gridApi.forEachNodeAfterFilterAndSort(node => {
                    const row = {};
                    visibleColumns.forEach(col => {
                        row[col.headerName] = node.data[col.field] ?? '';
                    });
                    rows.push(row);
                });

                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.json_to_sheet(rows);
                XLSX.utils.book_append_sheet(wb, ws, "Variants");
                XLSX.writeFile(wb, `variants-${new Date().toISOString().slice(0, 10)}.xlsx`);
            });

            
            document.getElementById('exportPdf')?.addEventListener('click', () => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'pt', 'a4');

                const visibleColumns = gridApi.getAllDisplayedColumns()
                    .map(col => col.getColDef())
                    .filter(col => col.field && col.field !== 'action');

                const exportCols = visibleColumns.map(col => ({
                    header: col.headerName,
                    dataKey: col.field
                }));

                const rows = [];
                gridApi.forEachNodeAfterFilterAndSort(node => {
                    const row = {};
                    visibleColumns.forEach(col => row[col.field] = node.data[col.field] ?? '');
                    rows.push(row);
                });

                doc.text('All Variants Report', 40, 30);
                doc.autoTable({
                    columns: exportCols,
                    body: rows,
                    startY: 50,
                    styles: { fontSize: 8 },
                    headStyles: { fillColor: [41, 128, 185] },
                });

                doc.save(`variants-${new Date().toISOString().slice(0, 10)}.pdf`);
            });
        });
    </script>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <!-- HEADER -->
                <div
                    class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        {{ $title ?? 'All Variants' }}
                    </h2>

                    <div class="d-flex align-items-center gap-3 flex-nowrap">
                        <a href="{{ backpack_url('variant/create') }}" class="btn btn-blue btn-sm fw-bold shadow-sm">
                            <i class="la la-plus me-1"></i> Add New Variant
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

                        <div class="d-flex gap-2 flex-nowrap justify-content-center">
                            <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm text-nowrap">Default
                                Headers</button>

                            <div class="position-relative d-inline-block">
                                <button id="btnCustomiseHeaders" class="btn btn-red btn-sm text-nowrap">Customise
                                    Headers</button>
                                <div id="columnBubble"
                                    style="display:none; position:absolute; top:110%; left:0; width:320px; background:#fff; border:1px solid #ddd; border-radius:6px; box-shadow:0 8px 20px rgba(0,0,0,.15); z-index:9999;">
                                    <div class="d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
                                        <strong style="font-size:13px;">Customise Headers</strong>
                                        <button id="closeColumnBubble"
                                            class="btn btn-sm btn-link text-danger p-0">✕</button>
                                    </div>
                                    <div style="max-height:260px; overflow:auto;">
                                        <table class="table table-sm mb-0">
                                            <tbody id="columnBubbleBody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <button id="btnAllHeaders" class="btn btn-blue btn-sm text-nowrap">All Headers</button>
                        </div>

                        <div class="d-flex gap-2 flex-nowrap">
                            <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                                <img src="{{ asset('images/export-excel.png') }}" alt="Excel"
                                    style="height:30px; width:auto;">
                            </button>
                            <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                                <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">
                            </button>
                        </div>
                    </div>

                    <!-- AG Grid -->
                    <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection