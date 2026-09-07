@extends(backpack_view('blank'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div
                    class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        {{ $title ?? 'Virtual Number Enquiries' }}
                    </h2>
                </div>

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
                                    
                                    <!-- NEW: Search Input for Columns -->
                                    <div class="p-2 border-bottom">
                                        <input type="text" id="columnSearch" class="form-control form-control-sm" placeholder="Search headers...">
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
                                <img src="{{ asset('images/export-pdf.png') }}" alt="PDF"
                                    style="height:30px; width:auto;">
                            </button>
                        </div>
                    </div>

                    <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
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
    </style>
@endpush

@push('after_scripts')
    <script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <script>
        const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);
        const LIST_TYPE = @json($gridConfig['list_type'] ?? 'virtual');
        let gridApi;

        const columnDefs = [

            ...ALL_COLUMNS.filter(col => [
                'serial_no',
                'x8_enquiry_no',
                'virtual_no',
                'call_date',
                'call_duration',
                'call_nature',
                'mobile',
                'alternate_mobile',
                'pincode',
                'vpo',
                'tehsil',
                'district',
                'x8_sc_code',
                'x8_sc_mile_id',
                'x8_sc_branch',
                'x8_sc_location',
                'oem_enquiry_no'
            ].includes(col.field)),

            ...ALL_COLUMNS.filter(col => ['action'].includes(col.field)).map(col => {
                col.pinned = 'right';
                col.width = 140;
                col.sortable = false;
                col.filter = false;
                col.cellRenderer = 'htmlRenderer';
                return col;
            })

        ];

        const gridOptions = {
            columnDefs: columnDefs,
            rowData: [], // Client-Side Model: Start empty, fetch below
            pagination: true,
            paginationPageSize: 50,
            rowHeight: 28,
            animateRows: true,
            defaultColDef: {
                sortable: true,
                filter: true,
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

                const defaultFields = [
                    'serial_no',
                    'x8_enquiry_no',
                    'virtual_no',
                    'call_date',
                    'call_duration',
                    'call_nature',
                    'mobile',
                    'alternate_mobile',
                    'pincode',
                    'vpo',
                    'tehsil',
                    'district',
                    'x8_sc_code',
                    'x8_sc_mile_id',
                    'x8_sc_branch',
                    'x8_sc_location',
                    'oem_enquiry_no',
                    'action'
                ];
                const allCols = gridApi.getAllGridColumns().map(col => col.getColId());
                gridApi.setColumnsVisible(allCols, false);
                gridApi.setColumnsVisible(defaultFields, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 300);

                // Fetch ALL data from the server ONCE when the grid is ready
                fetch('{{ backpack_url('enquiries/data') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        list_type: LIST_TYPE
                    })
                })
                .then(res => res.json())
                .then(data => {
                    gridApi.setGridOption('rowData', data.rows || []); 
                })
                .catch(err => console.error('Failed to load enquiries', err));
            }
        };

        function openColumnBubble() {
            const bubble = document.getElementById('columnBubble');
            const tbody = document.getElementById('columnBubbleBody');
            const searchInput = document.getElementById('columnSearch'); 
            
            if (!gridApi || !bubble || !tbody) return;

            tbody.innerHTML = '';
            
            if(searchInput) searchInput.value = ''; 

            const allFlatColumns = ALL_COLUMNS;

            allFlatColumns.forEach(col => {
                if (!col.field) return;

                const tr = document.createElement('tr');
                const tdCheck = document.createElement('td');
                tdCheck.style.width = '40px';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = gridApi.getColumn(col.field)?.isVisible() ?? false;

                if (['serial_no', 'virtual_no', 'action'].includes(col.field)) {
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

            document.querySelectorAll('#columnBubbleBody tr').forEach(row => {
                row.style.display = '';
            });

            bubble.style.display = 'block';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const gridDiv = document.querySelector('#myGrid');
            agGrid.createGrid(gridDiv, gridOptions);

            function debounce(fn, delay) {
                let timer;
                return (...args) => {
                    clearTimeout(timer);
                    timer = setTimeout(() => fn(...args), delay);
                };
            }

            // Column Customise Header Search Filter Logic
            document.getElementById('columnSearch')?.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('#columnBubbleBody tr');
                
                rows.forEach(row => {
                    const text = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });

            // Instant Frontend Global Search
            document.getElementById('quickFilter')?.addEventListener('input', debounce(e => {
                const searchTerm = e.target.value.trim();
                gridApi.setGridOption('quickFilterText', searchTerm);
            }, 300));

            // Reset All Button
            document.getElementById('resetAll')?.addEventListener('click', () => {
                document.getElementById('quickFilter').value = '';
                gridApi.setGridOption('quickFilterText', '');
                gridApi.setFilterModel(null);
                gridApi.applyColumnState({ defaultState: { sort: null } });
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
                const defaultFields = [
                    'serial_no',
                    'x8_enquiry_no',
                    'virtual_no',
                    'call_date',
                    'call_duration',
                    'call_nature',
                    'mobile',
                    'alternate_mobile',
                    'pincode',
                    'vpo',
                    'tehsil',
                    'district',
                    'x8_sc_code',
                    'x8_sc_mile_id',
                    'x8_sc_branch',
                    'x8_sc_location',
                    'oem_enquiry_no',
                    'action'
                ];
                const allCols = gridApi.getAllGridColumns().map(c => c.getColId());

                gridApi.setColumnsVisible(allCols, false);
                gridApi.setColumnsVisible(defaultFields, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 200);
            });

            document.getElementById('exportCsv').addEventListener('click', () => {
                const searchTerm = document.getElementById('quickFilter').value.trim();
                const params = new URLSearchParams({
                    searchText: searchTerm,
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

                doc.save(`virtual-number-enquiries-${new Date().toISOString().slice(0, 10)}.pdf`);
            });
        });

        function redirectToEnquiryList(selectElement) {
            if (selectElement.value) {
                window.location.href = selectElement.value;
            }
        }
    </script>
@endpush