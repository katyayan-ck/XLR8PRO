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
                        {{ $title ?? 'OTF Bookings' }}
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
                            <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2"
                                title="Export CSV">
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

                    <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 240px); width:100%;"></div>
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

@push('after_scripts')
    <script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <script>
        const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);
        const LIST_TYPE = @json($gridConfig['list_type'] ?? 'otf');

        let gridApi;
        let currentSearchText = '';

        const DEFAULT_VISIBLE_FIELDS = [
            'serial_no',
            'booking_no',
            'booking_date',
            'sc_code',
            'booking_status',
            'cancellation_date',
            'segment',
            'model',
            'variant',
            'color',
            'oem_model_code',
            'customer_code',
            'customer_name',
            'customer_address',
            'customer_city',
            'customer_tehsil',
            'customer_district',
            'pan_number',
            'tan_number',
            'aadhaar_number',
            'invoice_no',
            'evaluation_id',
            'so_number',
            'otf_number',
            'action'
        ];

        const columnDefs = ALL_COLUMNS.map(col => {
            if (col.field === 'serial_no' || col.field === 'booking_no') {
                col.pinned = 'left';
            }
            if (col.field === 'action') {
                col.pinned = 'right';
                col.cellRenderer = 'htmlRenderer';
                col.cellClass = 'action-cell';
            }
            return col;
        });

        const dataSource = {
            getRows: function(params) {
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
                        params.successCallback(data.rows || [], data.lastRow ?? 0);
                    })
                    .catch(err => {
                        console.error('Failed to load OTF bookings', err);
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
            paginationPageSizeSelector: [20, 50, 100, 200],
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
                    return params.value || '';
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
            if (!gridApi || !bubble || !tbody) return;

            tbody.innerHTML = '';

            const allFields = columnDefs.map(col => col.field).filter(f => f !== 'action');

            const groupTr = document.createElement('tr');
            groupTr.style.background = '#f0f0f0';

            const groupCheckTd = document.createElement('td');
            groupCheckTd.style.width = '30px';
            groupCheckTd.className = 'text-center';

            const groupCheckbox = document.createElement('input');
            groupCheckbox.type = 'checkbox';

            const visibleCount = allFields.filter(f => {
                const col = gridApi.getColumn(f);
                return col && col.isVisible();
            }).length;

            groupCheckbox.checked = visibleCount === allFields.length && allFields.length > 0;
            groupCheckbox.indeterminate = visibleCount > 0 && visibleCount < allFields.length;
            groupCheckbox.disabled = true;

            groupCheckTd.appendChild(groupCheckbox);

            const groupLabelTd = document.createElement('td');
            groupLabelTd.colSpan = 2;
            groupLabelTd.innerHTML = `<strong>All Headers</strong>`;

            groupTr.appendChild(groupCheckTd);
            groupTr.appendChild(groupLabelTd);
            tbody.appendChild(groupTr);

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

            bubble.style.display = 'block';
        }

        function debounce(fn, delay) {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        }

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

            document.getElementById('quickFilter')?.addEventListener('input', debounce(e => {
                currentSearchText = e.target.value.trim();
                gridApi.setGridOption('datasource', dataSource);
            }, 400));

            document.getElementById('resetAll')?.addEventListener('click', () => {
                document.getElementById('quickFilter').value = '';
                currentSearchText = '';
                gridApi.setFilterModel(null);
                gridApi.applyColumnState({
                    defaultState: {
                        sort: null
                    }
                });
                gridApi.setGridOption('datasource', dataSource);
            });

            document.getElementById('exportCsv')?.addEventListener('click', () => {
                const params = new URLSearchParams({
                    searchText: currentSearchText,
                    list_type: LIST_TYPE
                });
                window.location.href = '{{ backpack_url('enquiries/export') }}?' + params.toString();
            });

            $('#exportPdf').on('click', function() {
                const selectedRows = gridApi ? gridApi.getSelectedRows() : [];

                if (!selectedRows || selectedRows.length === 0) {
                    alert('Please select at least one record to export PDF.');
                    return;
                }

                {{-- 
                    When a dedicated PDF export route is created, un-comment the request below:
                    const selectedIds = selectedRows.map(row => row.id);
                    $.ajax({
                        url: "{{ url('admin/otf-bookings/export-pdf') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            ids: selectedIds
                        },
                        xhrFields: {
                            responseType: 'blob'
                        },
                        success: function(blob) {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'otf_bookings_' + new Date().toISOString().slice(0, 10) + '.pdf';
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            window.URL.revokeObjectURL(url);
                        },
                        error: function(xhr, status, error) {
                            console.error('PDF Export Error:', error);
                            alert('Failed to generate PDF export. Please try again.');
                        }
                    });
                --}}
            });
        });
    </script>
@endpush
