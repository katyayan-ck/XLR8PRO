@extends(backpack_view('blank'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div
                    class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        {{ isset($title) ? trim(explode('(', $title)[0]) : 'Xceler8 Enquiries' }}
                    </h2>

                    <div class="d-flex align-items-center gap-3 flex-nowrap">
                        @if (Route::has('enquiry.add') || Route::has('enquiries.create'))
                            <a href="{{ backpack_url('enquiries/add') }}" class="btn btn-blue btn-sm fw-bold shadow-sm">
                                <i class="la la-plus me-1"></i> Add New Enquiry
                            </a>
                        @endif
                    </div>
                </div>

                <div class="card-body p-0" style="background:#f8fafc">

                    {{-- Optional Import Section --}}
                    @if (Route::has('enquiry.import'))
                        <div class="p-3 border-bottom bg-white">
                            <div class="row align-items-end">
                                <div class="col-md-8">
                                    <h5 class="mb-2 text-dark">
                                        <i class="la la-file-excel-o"></i> Import Enquiries from Excel
                                    </h5>
                                    <small class="text-muted">
                                        Upload Excel file containing enquiry data. First row should contain headers.
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <form action="{{ route('enquiry.import') }}" method="POST"
                                        enctype="multipart/form-data" class="d-flex gap-2">
                                        @csrf
                                        <input type="file" name="excel_file" class="form-control form-control-sm"
                                            accept=".xlsx,.xls" required>
                                        <button type="submit" class="btn btn-success btn-sm px-4 text-nowrap">
                                            <i class="la la-upload"></i> Import
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- HIGHLIGHT FILTERS --}}
                    @isset($highlightCounts)
                        <div class="px-3 py-2 border-bottom bg-white d-flex gap-2 flex-wrap align-items-center">
                            <span class="fw-bold text-muted small me-1">Highlights:</span>
                            @foreach ([
                'missed_fup' => 'Missed Follow-up',
                'today_fup' => "Today's Follow-up",
                'birthday' => 'Birthday',
                'anniversary' => 'Anniversary',
                'exchange' => 'Exchange',
                'pending_eval' => 'Pending Evaluation',
                'delayed' => 'Delayed',
                'wrong_assign' => 'Wrong Assignment',
                'finance' => 'Finance',
                'lost_verif' => 'Lost Verifications',
            ] as $key => $label)
                                <button class="btn btn-outline-primary btn-sm rounded-pill highlight-filter"
                                    data-filter="{{ $key }}">
                                    {{ $label }} <span
                                        class="badge ms-1 count-badge">{{ $highlightCounts[$key] ?? 0 }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endisset

                    {{-- Grid Controls Bar --}}
                    <div
                        class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">
                        <div class="d-flex align-items-center gap-2 flex-nowrap">
                            <input type="text" id="quickFilter" class="form-control w-100 w-md-auto"
                                style="width:360px; min-width:260px;" placeholder="Smart Global Search...">
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

                                    <!-- Search Input for Columns -->
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
                            <button id="btnAllHeaders" class="btn btn-blue btn-sm text-nowrap">All Headers</button>
                        </div>

                        <div class="d-flex gap-2 flex-nowrap">
                            <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2"
                                title="Export Excel">
                                <img src="{{ asset('images/export-excel.png') }}" alt="Excel"
                                    style="height:30px; width:auto;">
                            </button>
                            <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2"
                                title="Export PDF">
                                <img src="{{ asset('images/export-pdf.png') }}" alt="PDF"
                                    style="height:30px; width:auto;">
                            </button>
                        </div>
                    </div>

                    <!-- GRID CONTAINER WITH LOADER WRAPPER -->
<div style="position: relative;">
    <div id="gridLoader" style="display:none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 1000; justify-content: center; align-items: center;">
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

@push('after_styles')
    <link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
    <style>
        .ag-theme-quartz .center-header .ag-header-cell-label {
            justify-content: center !important;
        }

        .highlight-filter.active {
            background-color: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        .highlight-filter .count-badge {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            border-radius: 50rem;
        }

        .highlight-filter.active .count-badge {
            background-color: #fff;
            color: #0d6efd;
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
        const DEFAULT_COLUMNS = @json($gridConfig['defaultColumns'] ?? []);
        const LIST_TYPE = @json($listType ?? 'all');
        let gridApi;

        const columnDefs = [
            ...ALL_COLUMNS.filter(col => col.field !== 'action'),
            ...ALL_COLUMNS.filter(col => col.field === 'action').map(col => {
                col.pinned = 'right';
                col.width = 140;
                col.sortable = false;
                col.filter = false;
                col.cellRenderer = 'htmlRenderer';
                return col;
            })
        ];

        let currentSearchText = '';
        let currentHighlightFilter = '';

        const dataSource = {
            getRows: function(params) {
                // 1. Show the custom HTML loader
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
                        // 2. Hide the custom HTML loader
                        if (loader) loader.style.display = 'none';

                        params.successCallback(data.rows || [], data.lastRow ?? 0);

                        setTimeout(() => {
                            if (gridApi) {
                                gridApi.autoSizeColumns(['action']);
                            }
                        }, 100);
                    })
                    .catch(err => {
                        // 3. Hide the custom HTML loader on error
                        if (loader) loader.style.display = 'none';
                        console.error('Failed to load enquiries', err);
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
            rowHeight: 28,
            animateRows: true,
            defaultColDef: {
                sortable: true,
                filter: true,
                floatingFilter: false,
                resizable: true,
                headerClass: 'center-header',
                cellStyle: {
                    textAlign: 'center'
                }
            },
            components: {
                htmlRenderer: params => params.value || ''
            },
            onGridReady: params => {
                gridApi = params.api;
                const allCols = gridApi.getAllGridColumns().map(col => col.getColId());
                gridApi.setColumnsVisible(allCols, false);
                gridApi.setColumnsVisible(DEFAULT_COLUMNS.length ? DEFAULT_COLUMNS : allCols, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 300);
            }
        };

        function openColumnBubble() {
            const bubble = document.getElementById('columnBubble');
            const tbody = document.getElementById('columnBubbleBody');
            const searchInput = document.getElementById('columnSearch');

            if (!gridApi || !bubble || !tbody) return;

            tbody.innerHTML = '';
            if (searchInput) searchInput.value = '';

            ALL_COLUMNS.forEach(col => {
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
            const gridDiv = document.querySelector('#myGrid');
            agGrid.createGrid(gridDiv, gridOptions);

            document.getElementById('quickFilter').addEventListener('input', debounce(e => {
                currentSearchText = e.target.value.trim();

                // Show the custom HTML loader
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';

                if (gridApi) {
                    gridApi.setGridOption('datasource', {
                        ...dataSource
                    });
                }
            }, 400));

            document.querySelectorAll('.highlight-filter').forEach(btn => {
                btn.addEventListener('click', function() {
                    const filterValue = this.getAttribute('data-filter');

                    if (currentHighlightFilter === filterValue) {
                        currentHighlightFilter = '';
                        this.classList.remove('active');
                    } else {
                        currentHighlightFilter = filterValue;
                        document.querySelectorAll('.highlight-filter').forEach(b => b.classList
                            .remove('active'));
                        this.classList.add('active');
                    }

                    document.getElementById('quickFilter').value = '';
                    currentSearchText = '';

                    const loader = document.getElementById('gridLoader');
                    if (loader) loader.style.display = 'flex';

                    gridApi.setGridOption('datasource', {
                        ...dataSource
                    });
                });
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

            document.getElementById('resetAll').addEventListener('click', () => {
                document.getElementById('quickFilter').value = '';
                currentSearchText = '';

                // Show the custom HTML loader
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';

                if (gridApi) {
                    gridApi.setFilterModel(null);
                    gridApi.applyColumnState({
                        defaultState: {
                            sort: null
                        }
                    });
                    gridApi.setGridOption('datasource', {
                        ...dataSource
                    });
                }
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
                const allCols = gridApi.getAllGridColumns().map(c => c.getColId());
                gridApi.setColumnsVisible(allCols, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 200);
            });

            document.getElementById('btnDefaultHeaders').addEventListener('click', () => {
                const allCols = gridApi.getAllGridColumns().map(c => c.getColId());
                gridApi.setColumnsVisible(allCols, false);
                gridApi.setColumnsVisible(DEFAULT_COLUMNS, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 200);
            });

            document.getElementById('exportCsv').addEventListener('click', () => {
                const params = new URLSearchParams({
                    searchText: currentSearchText,
                    highlightFilter: currentHighlightFilter,
                    list_type: LIST_TYPE
                });
                window.location.href = '{{ backpack_url('enquiries/export') }}?' + params.toString();
            });

            document.getElementById('exportPdf').addEventListener('click', () => {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF();

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
                    styles: {
                        fontSize: 8
                    },
                    headStyles: {
                        fillColor: [41, 128, 185]
                    },
                });

                doc.save(`enquiries-${new Date().toISOString().slice(0, 10)}.pdf`);
            });
        });
    </script>
@endpush
