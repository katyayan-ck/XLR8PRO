@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div
                    class="card-header bg-gradient-primary
                        d-flex justify-content-between align-items-center
                        flex-nowrap flex-md-nowrap flex-wrap">
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        {{ isset($title) ? trim(explode('(', $title)[0]) : 'Hyperlocal Enquiries' }}
                    </h2>
                </div>

                <div class="card-body p-0" style="background:#f8fafc">
                    <div
                        class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">
                        <div class="d-flex align-items-center gap-2 flex-nowrap">
                            <input type="text" id="quickFilter" class="form-control w-100 w-md-auto"
                                style="width:360px; min-width:260px;" placeholder="Smart Search...">
                            <button id="resetAll" class="btn btn-outline-danger btn-sm text-nowrap">
                                Reset
                            </button>
                        </div>

                        <div class="d-flex gap-2 flex-wrap justify-content-center">
                            <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm">
                                Default Headers
                            </button>

                            <div class="position-relative d-inline-block">
                                <button id="btnCustomiseHeaders" class="btn btn-danger btn-sm">
                                    Customise Headers
                                </button>

                                <div id="columnBubble"
                                    style="
                                display:none;
                                position:absolute;
                                top:110%;
                                left:0;
                                width:260px;
                                background:#fff;
                                border:1px solid #ddd;
                                border-radius:6px;
                                box-shadow:0 8px 20px rgba(0,0,0,.15);
                                z-index:9999;">
                                    <div class="d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
                                        <strong style="font-size:13px;">Customise Headers</strong>
                                        <button id="closeColumnBubble"
                                            class="btn btn-sm btn-link text-danger p-0">✕</button>
                                    </div>

                                    <div class="p-2 border-bottom">
                                        <input type="text" id="columnSearch" class="form-control form-control-sm"
                                            placeholder="Search headers...">
                                    </div>

                                    <div style="max-height:260px; overflow:auto;">
                                        <table class="table table-sm mb-0">
                                            <tbody id="columnBubbleBody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <button id="btnAllHeaders" class="btn btn-info btn-sm">
                                All Headers
                            </button>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
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

                    <!-- GRID CONTAINER WITH LOADER WRAPPER -->
                    <div style="position: relative;">
                        <div id="gridLoader"
                            style="display:none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 1000; justify-content: center; align-items: center;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 240px); width:100%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_styles')
    <link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">

    <style>
        .ag-theme-quartz .center-header .ag-header-cell-label,
        .ag-theme-quartz .ag-header-cell-label {
            justify-content: center !important;
            text-align: center !important;
        }

        .ag-theme-quartz .ag-header-group-cell-label {
            justify-content: center !important;
            text-align: center !important;
            width: 100% !important;
        }

        .ag-theme-quartz .ag-header-group-cell {
            text-align: center !important;
        }

        .ag-pinned-left-cols-container .ag-header-cell,
        .ag-pinned-left-cols-container .ag-header-group-cell,
        .ag-pinned-right-cols-container .ag-header-cell,
        .ag-pinned-right-cols-container .ag-header-group-cell {
            background-color: #f0f8ff !important;
            font-weight: 600;
        }

        .ag-header-group-cell-label {
            padding: 0 4px !important;
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
        const LIST_TYPE = @json($gridConfig['list_type'] ?? 'hyperlocal');

        function getCols(fields) {
            return ALL_COLUMNS.filter(col => fields.includes(col.field));
        }

        let gridApi;
        let currentSearchText = '';

        const DEFAULT_VISIBLE_FIELDS = [
            'serial_no',
            'lead_id',
            'name',
            'phone_number',
            'call_start_time',
            'call_recording_url',
            'call_duration',
            'call_status',
            'call_type',
            'notes',
            'lead_status',
            'client_crm_status',
            'model',
            'dealer_code',
            'action'
        ];

        const columnDefs = getCols(DEFAULT_VISIBLE_FIELDS).map(col => {
            if (col.field === 'serial_no' || col.field === 'lead_id') {
                col.pinned = 'left';
            }
            if (col.field === 'action') {
                col.pinned = 'right';
                col.cellRenderer = 'htmlRenderer';
            }
            return col;
        });

        const dataSource = {
            getRows: function(params) {
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';

                fetch('{{ backpack_url('enquiries/data') }}', {
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

                        // Auto-size the action column dynamically based on rendered buttons
                        setTimeout(() => {
                            if (gridApi) {
                                gridApi.autoSizeColumns(['action']);
                            }
                        }, 100); // 100ms delay gives the browser time to paint the HTML buttons
                    })
                    .catch(err => {
                        if (loader) loader.style.display = 'none';
                        console.error('Failed to load hyperlocal enquiries', err);
                        params.failCallback();
                    });
            }
        };

        const gridOptions = {
            columnDefs: columnDefs,
            rowModelType: 'infinite',
            datasource: dataSource,
            pagination: true,
            paginationPageSize: 50,
            cacheBlockSize: 50,
            rowHeight: 30,
            animateRows: true,

            defaultColDef: {
                sortable: true,
                filter: true,
                resizable: true,
                headerClass: 'center-header',
                cellStyle: {
                    textAlign: 'center'
                },
                suppressHeaderMenuButton: false,
            },

            components: {
                htmlRenderer: function(params) {
                    if (!params.value) return '';
                    return params.value;
                }
            },

            onGridReady: params => {
                gridApi = params.api;

                const allFields = columnDefs.map(col => col.field);

                gridApi.setColumnsVisible(allFields, false);
                gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);

                setTimeout(() => {
                    const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
                    gridApi.autoSizeColumns(visibleIds, false);
                }, 400);
            }
        };

        function openColumnBubble() {
            const bubble = document.getElementById('columnBubble');
            const tbody = document.getElementById('columnBubbleBody');
            const searchInput = document.getElementById('columnSearch');

            if (!gridApi || !bubble || !tbody) return;

            tbody.innerHTML = '';
            if (searchInput) searchInput.value = '';

            const allFields = columnDefs.map(col => col.field).filter(f => f !== 'action');

            allFields.forEach(field => {
                const colDef = columnDefs.find(c => c.field === field);
                if (!colDef) return;

                const tr = document.createElement('tr');

                const tdCheck = document.createElement('td');
                tdCheck.style.paddingLeft = '40px';
                tdCheck.className = 'text-center';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';

                const col = gridApi.getColumn(field);
                checkbox.checked = col ? col.isVisible() : false;

                checkbox.addEventListener('change', () => {
                    gridApi.setColumnsVisible([field], checkbox.checked);
                });

                tdCheck.appendChild(checkbox);

                const tdLabel = document.createElement('td');
                tdLabel.innerText = colDef.headerName;

                tr.appendChild(tdCheck);
                tr.appendChild(tdLabel);
                tbody.appendChild(tr);
            });

            document.querySelectorAll('#columnBubbleBody tr').forEach(row => row.style.display = '');
            bubble.style.display = 'block';
        }

        document.getElementById('btnCustomiseHeaders')?.addEventListener('click', e => {
            e.stopPropagation();
            openColumnBubble();
        });

        document.getElementById('columnSearch')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#columnBubbleBody tr');

            rows.forEach(row => {
                const labelTd = row.querySelector('td:nth-child(2)');
                if (labelTd) {
                    const text = labelTd.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                }
            });
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
            const allFields = columnDefs.map(col => col.field);
            gridApi.setColumnsVisible(allFields, true);
            setTimeout(() => {
                const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
                gridApi.autoSizeColumns(visibleIds, false);
            }, 200);
        });

        document.getElementById('btnDefaultHeaders')?.addEventListener('click', () => {
            const allFields = columnDefs.map(col => col.field);

            gridApi.setColumnsVisible(allFields, false);
            gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);

            setTimeout(() => {
                const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
                gridApi.autoSizeColumns(visibleIds, false);
            }, 200);
        });

        document.addEventListener('DOMContentLoaded', () => {
            const gridDiv = document.querySelector('#myGrid');
            gridApi = agGrid.createGrid(gridDiv, gridOptions);

            function debounce(fn, delay) {
                let timer;
                return (...args) => {
                    clearTimeout(timer);
                    timer = setTimeout(() => fn(...args), delay);
                };
            }

            document.getElementById('quickFilter')?.addEventListener('input', debounce(e => {
                currentSearchText = e.target.value.trim();
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';
                gridApi.setGridOption('datasource', {
                    ...dataSource
                });
            }, 400));

            document.getElementById('resetAll')?.addEventListener('click', () => {
                document.getElementById('quickFilter').value = '';
                currentSearchText = '';
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';
                gridApi.setFilterModel(null);
                gridApi.applyColumnState({
                    defaultState: {
                        sort: null
                    }
                });
                gridApi.setGridOption('datasource', {
                    ...dataSource
                });
            });

            document.getElementById('exportCsv')?.addEventListener('click', () => {
                const searchTerm = document.getElementById('quickFilter').value.trim();
                const params = new URLSearchParams({
                    searchText: searchTerm,
                    list_type: LIST_TYPE
                });
                window.location.href = '{{ backpack_url('enquiries/export') }}?' + params.toString();
            });

            document.getElementById('exportPdf')?.addEventListener('click', () => {
                const {
                    jsPDF
                } = window.jspdf;
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
                    if (!node.data) return;
                    const row = {};
                    visibleColumns.forEach(col => {
                        row[col.field] = node.data[col.field];
                    });
                    rows.push(row);
                });

                doc.text('Hyperlocal Enquiries Report', 40, 30);
                doc.autoTable({
                    columns: exportCols,
                    body: rows,
                    startY: 50,
                    styles: {
                        fontSize: 8
                    },
                    headStyles: {
                        fillColor: [13, 110, 253]
                    },
                });

                doc.save('hyperlocal-enquiries.pdf');
            });
        });
    </script>
@endpush
