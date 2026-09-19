@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid"></section>
@endsection

@push('after_styles')
    <link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
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
                <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
                    <!-- NEW: Removed the count from the heading -->
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        {{ isset($title) ? trim(explode('(', $title)[0]) : 'Finance List' }}
                    </h2>
                </div>
                <div class="card-body p-0" style="background:#f8fafc">
                    <div
                        class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-bottom bg-white">
                        <div class="d-flex align-items-center gap-2">
                            <input type="text" id="quickFilter" class="form-control" style="width:360px;"
                                placeholder="Smart Search...">
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
                        <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 200px); width:100%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
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

        // Restored Server-Side DataSource for Memory Safety
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
                            searchText: currentSearchText, // Sends global search to backend
                            list_type: LIST_TYPE
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        // 2. Hide the custom HTML loader
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
                        // 3. Hide the custom HTML loader on error
                        if (loader) loader.style.display = 'none';
                        console.error('Failed to load finance enquiries', err);
                        params.failCallback();
                    });
            }
        };

        // Restored Infinite Row Model
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
                cellStyle: {
                    textAlign: 'center'
                }
            },
            components: {
                htmlRenderer: params => params.value || ''
            },
            onGridReady: params => {
                gridApi = params.api;
                const allFields = [];
                columnGroups.forEach(g => {
                    if (g.children) g.children.forEach(c => allFields.push(c.field))
                });
                gridApi.setColumnsVisible(allFields, false);
                gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);
                setTimeout(() => gridApi.autoSizeColumns(gridApi.getAllDisplayedColumns().map(c => c.getColId()),
                    false), 400);
            }
        };

        function debounce(fn, delay) {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        }

        document.addEventListener('DOMContentLoaded', () => {
            gridApi = agGrid.createGrid(document.querySelector('#myGrid'), gridOptions);

            // Backend Global Search (Forces grid to fetch new matching data)
            document.getElementById('quickFilter')?.addEventListener('input', debounce(e => {
                currentSearchText = e.target.value.trim();
                
                // Show the custom HTML loader
                const loader = document.getElementById('gridLoader');
                if (loader) loader.style.display = 'flex';
                
                gridApi.setGridOption('datasource', {
                    ...dataSource
                });
            }, 400));
        });
    </script>
@endpush
