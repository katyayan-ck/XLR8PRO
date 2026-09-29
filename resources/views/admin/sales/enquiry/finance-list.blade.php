@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid"></section>
@endsection

@push('after_styles')
    <style>
        .ag-theme-quartz .center-header .ag-header-cell-label,
        .ag-theme-quartz .ag-header-cell-label {
            justify-content: center !important;
            text-align: center !important;
        }

        .ag-cell.action-cell {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            white-space: nowrap !important;
            padding: 0 10px !important;
        }

        .ag-cell.action-cell .btn {
            white-space: nowrap !important;
            width: max-content !important;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h2 class="card-title mb-0 fw-bold text-body text-nowrap">
                        {{ isset($title) ? trim(explode('(', $title)[0]) : 'Finance List' }}
                    </h2>
                </div>
                <div class="card-body p-0" style="background: var(--tblr-bg-surface-secondary)">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-surface">
                        <div class="d-flex align-items-center gap-2 flex-nowrap">
                            <input type="text" id="quickFilter" class="form-control w-100 w-md-auto"
                                style="width:360px; min-width:260px;" placeholder="Smart Search...">
                            <button id="resetAll" class="btn btn-outline-danger btn-sm text-nowrap">Reset</button>
                        </div>

                        <div class="d-flex gap-2 flex-wrap justify-content-center">
                            <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm text-nowrap">Default Headers</button>
                            <div class="position-relative d-inline-block">
                                <button id="btnCustomiseHeaders" class="btn btn-red btn-sm text-nowrap">Customise Headers</button>
                                <div id="columnBubble" class="card shadow"
                                    style="display:none; position:absolute; top:110%; left:0; width:320px; max-width:calc(100vw - 32px); z-index:1050;">
                                    <div class="d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
                                        <strong class="small">Customise Headers</strong>
                                        <button id="closeColumnBubble" class="btn btn-sm btn-link text-danger p-0" aria-label="Close">✕</button>
                                    </div>

                                    <div class="p-2 border-bottom">
                                        <input type="text" id="columnSearch" class="form-control form-control-sm"
                                            placeholder="Search headers...">
                                    </div>

                                    <div class="xl-scroll-y" style="max-height:260px;">
                                        <table class="table table-sm mb-0">
                                            <tbody id="columnBubbleBody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <button id="btnAllHeaders" class="btn btn-blue btn-sm text-nowrap">All Headers</button>
                        </div>

                        <div class="d-flex gap-2 flex-nowrap">
                            <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2" title="Export to Excel">
                                <img src="{{ asset('images/export-excel.png') }}" alt="Excel" style="height:30px; width:auto;">
                            </button>
                            <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2" title="Export to PDF">
                                <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">
                            </button>
                        </div>
                    </div>

                    <!-- GRID CONTAINER WITH LOADER WRAPPER -->
                    <div style="position: relative;">
                        <div id="gridLoader"
                            style="display:none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 1000; justify-content: center; align-items: center;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/ag-grid-community@36.2.0/dist/ag-grid-community.min.js"></script>
    {{-- stage 30-09: export libraries, pinned and cached by Basset (.ai/rules/ui.md) --}}
    @basset('https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js')
    @basset('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js')
    @basset('https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js')
    <script>
        const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);
        const LIST_TYPE = @json($gridConfig['list_type'] ?? 'finance');
        let gridApi;
        let currentSearchText = '';

        function getCols(fields) {
            return ALL_COLUMNS.filter(col => fields.includes(col.field));
        }

        const DEFAULT_VISIBLE_FIELDS = [
            'serial_no', 'x8_enquiry_no', 'x8_enquiry_date',
            'first_name', 'mobile',
            'model_name', 'variant_name',
            'dms_enquiry_stage', 'fin_mode', 'financier_name', 'loan_status', 'sc_code',
            'action'
        ];

        const columnGroups = [{
                headerName: 'Enquiry Details',
                headerClass: 'ag-header-center',
                children: getCols(['serial_no', 'x8_enquiry_no', 'x8_enquiry_date']).map(c => {
                    c.pinned = 'left';
                    return c;
                })
            },
            {
                headerName: 'Customer',
                headerClass: 'ag-header-center',
                children: getCols(['first_name', 'mobile'])
            },
            {
                headerName: 'Vehicle',
                headerClass: 'ag-header-center',
                children: getCols(['model_name', 'variant_name'])
            },
            {
                headerName: 'Finance Detail',
                headerClass: 'ag-header-center',
                children: getCols(['dms_enquiry_stage', 'fin_mode', 'financier_name', 'loan_status', 'sc_code'])
            },
            {
                headerName: 'Action',
                headerClass: 'ag-header-center',
                children: getCols(['action']).map(col => {
                    col.pinned = 'right';
                    col.cellRenderer = 'htmlRenderer';
                    col.autoHeight = true;
                    col.cellClass = 'action-cell';
                    col.suppressSizeToFit = true;
                    delete col.width;
                    return col;
                })
            }
        ];

        const dataSource = {
            getRows: function(params) {
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';

                fetch('{{ backpack_url('sales/enquiry/grid-data') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            startRow: params.startRow,
                            endRow: params.endRow,
                            sortModel: params.sortModel,
                            filterModel: params.filterModel,
                            searchText: currentSearchText,
                            list_type: LIST_TYPE
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (loader) loader.style.display = 'none';
                        params.successCallback(data.rows || [], data.lastRow ?? 0);
                        setTimeout(() => { if (gridApi) gridApi.autoSizeColumns(['action']); }, 100);
                    })
                    .catch(err => {
                        if (loader) loader.style.display = 'none';
                        console.error('Failed to load finance enquiries', err);
                        params.failCallback();
                    });
            }
        };

        const gridOptions = {
            columnDefs: columnGroups,
            rowModelType: 'infinite',
            datasource: dataSource,
            pagination: true,
            paginationPageSize: 50,
            cacheBlockSize: 50,
            rowHeight: 35,
            defaultColDef: {
                sortable: true,
                filter: true,
                resizable: true,
                cellStyle: { textAlign: 'center' }
            },
            components: { htmlRenderer: params => params.value || '' },
            onGridReady: params => {
                gridApi = params.api;
                const allFields = [];
                columnGroups.forEach(g => { if (g.children) g.children.forEach(c => allFields.push(c.field)) });
                gridApi.setColumnsVisible(allFields, false);
                gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);
                setTimeout(() => gridApi.autoSizeColumns(gridApi.getAllDisplayedColumns().map(c => c.getColId()), false), 400);
            }
        };

        function openColumnBubble() {
            const bubble = document.getElementById('columnBubble');
            const tbody = document.getElementById('columnBubbleBody');
            const searchInput = document.getElementById('columnSearch');
            
            if (!gridApi || !bubble || !tbody) return;
            tbody.innerHTML = '';
            if (searchInput) searchInput.value = '';

            const allFlatColumns = ALL_COLUMNS;
            allFlatColumns.forEach(col => {
                if (!col.field) return;

                const tr = document.createElement('tr');
                const tdCheck = document.createElement('td');
                tdCheck.style.width = '40px';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = gridApi.getColumn(col.field)?.isVisible() ?? false;

                if (['serial_no', 'action'].includes(col.field)) {
                    checkbox.disabled = true;
                }

                checkbox.addEventListener('change', () => {
                    gridApi.setColumnsVisible([col.field], checkbox.checked);
                });

                tdCheck.appendChild(checkbox);
                const tdLabel = document.createElement('td');
                tdLabel.textContent = col.headerName || col.field;
                tr.append(tdCheck, tdLabel);
                tbody.appendChild(tr);
            });
            
            document.querySelectorAll('#columnBubbleBody tr').forEach(row => row.style.display = '');
            bubble.style.display = 'block';
        }

        function debounce(fn, delay) {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        }

        document.addEventListener('DOMContentLoaded', () => {
            gridApi = agGrid.createGrid(document.querySelector('#myGrid'), gridOptions);

            document.getElementById('columnSearch')?.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                document.querySelectorAll('#columnBubbleBody tr').forEach(row => {
                    const text = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });

            document.getElementById('quickFilter')?.addEventListener('input', debounce(e => {
                currentSearchText = e.target.value.trim();
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';
                if (gridApi) gridApi.setGridOption('datasource', { ...dataSource });
            }, 400));

            document.getElementById('resetAll').addEventListener('click', () => {
                document.getElementById('quickFilter').value = '';
                currentSearchText = '';
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';
                
                gridApi.setFilterModel(null);
                gridApi.applyColumnState({ defaultState: { sort: null } });
                gridApi.setGridOption('datasource', { ...dataSource });
            });

            document.getElementById('btnCustomiseHeaders').addEventListener('click', e => {
                e.stopPropagation();
                openColumnBubble();
            });

            document.getElementById('closeColumnBubble').addEventListener('click', () => {
                document.getElementById('columnBubble').style.display = 'none';
            });

            document.getElementById('columnBubble').addEventListener('click', e => e.stopPropagation());

            document.addEventListener('click', () => {
                const bubble = document.getElementById('columnBubble');
                if (bubble?.style.display === 'block') bubble.style.display = 'none';
            });

            document.getElementById('btnAllHeaders').addEventListener('click', () => {
                const allFields = [];
                columnGroups.forEach(g => { if (g.children) g.children.forEach(c => allFields.push(c.field)) });
                gridApi.setColumnsVisible(allFields, true);
                setTimeout(() => gridApi.autoSizeColumns(gridApi.getAllDisplayedColumns().map(c => c.getColId()), false), 200);
            });

            document.getElementById('btnDefaultHeaders').addEventListener('click', () => {
                const allFields = [];
                columnGroups.forEach(g => { if (g.children) g.children.forEach(c => allFields.push(c.field)) });
                gridApi.setColumnsVisible(allFields, false);
                gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);
                setTimeout(() => gridApi.autoSizeColumns(gridApi.getAllDisplayedColumns().map(c => c.getColId()), false), 200);
            });

            document.getElementById('exportCsv').addEventListener('click', () => {
                const params = new URLSearchParams({ searchText: currentSearchText, list_type: LIST_TYPE });
                window.location.href = '{{ backpack_url('sales/enquiry/export-legacy') }}?' + params.toString();
            });

            document.getElementById('exportPdf').addEventListener('click', () => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'pt', 'a4');
                const visibleColumns = gridApi.getAllDisplayedColumns()
                    .map(col => col.getColDef())
                    .filter(col => col.field && col.field !== 'action');
                const headers = visibleColumns.map(col => col.headerName);
                const rows = [];

                gridApi.forEachNodeAfterFilterAndSort(node => {
                    if (!node.data) return;
                    rows.push(visibleColumns.map(col => node.data[col.field] ?? ''));
                });

                doc.autoTable({
                    head: [headers],
                    body: rows,
                    styles: { fontSize: 7 },
                    headStyles: { fillColor: [41, 128, 185] },
                });
                doc.save(`finance-enquiries-${new Date().toISOString().slice(0, 10)}.pdf`);
            });
        });
    </script>
@endpush