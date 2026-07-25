@extends(backpack_view('blank'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div
                    class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        {{ $title ?? 'Xlr8 Enquiries' }}
                    </h2>

                    <div class="d-flex align-items-center gap-3 flex-nowrap">
                        <a href="{{ backpack_url('enquiries/add') }}"
                            class="btn btn-blue btn-sm fw-bold shadow-sm">
                            <i class="la la-plus me-1"></i> Add New Enquiry
                        </a>

                    </div>
                </div>

                <div class="card-body p-0" style="background:#f8fafc">
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
                                <form action="{{ route('enquiry.import') }}" method="POST" enctype="multipart/form-data"
                                    class="d-flex gap-2">
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
                    <div class="p-3 border-bottom bg-white" id="importStatusPanel" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong id="importStatusTitle">Import in progress…</strong>
                            <span class="text-muted small" id="importStatusPercent">0%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" id="importProgressBar" role="progressbar"style="width: 0%">
                            </div>
                        </div>
                        <div class="small text-muted mt-2" id="importStatusDetail"></div>
                    </div>

                    <div class="p-3 border-bottom bg-white">
                        <h6 class="text-muted mb-2">Recent Imports</h6>
                        <table class="table table-sm mb-0" id="importHistoryTable">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-muted">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

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
        (function() {
            const statusUrlBase = "{{ url('/' . config('backpack.base.route_prefix') . '/enquiry/import/status') }}";
            const historyUrl = "{{ route('enquiry.import.history') }}";
            let pollTimer = null;

            function renderHistory(rows) {
                const tbody = document.querySelector('#importHistoryTable tbody');
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No imports yet</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(r => {
                    const pct = r.total_rows > 0 ? Math.round((r.processed_rows / r.total_rows) * 100) : 0;
                    const badge = r.status === 'completed' ? 'success' :
                        r.status === 'failed' ? 'danger' :
                        'warning';
                    return `<tr>
                <td>${r.file_name}</td>
                <td><span class="badge bg-${badge}">${r.status}</span></td>
                <td>${r.status === 'processing' ? pct + '%' : '-'}</td>
                <td>${r.updated_at}</td>
            </tr>`;
                }).join('');

                // if the newest one is still processing/queued, start polling it
                const newest = rows[0];
                if (newest && (newest.status === 'processing' || newest.status === 'queued')) {
                    startPolling(newest.id);
                }
            }

            function startPolling(id) {
                const panel = document.getElementById('importStatusPanel');
                panel.style.display = 'block';
                if (pollTimer) clearInterval(pollTimer);

                function tick() {
                    fetch(`${statusUrlBase}/${id}`).then(r => r.json()).then(data => {
                        document.getElementById('importProgressBar').style.width = data.percent + '%';
                        document.getElementById('importStatusPercent').innerText = data.percent + '%';
                        document.getElementById('importStatusDetail').innerText =
                            `${data.processed_rows} / ${data.total_rows} rows processed`;

                        if (data.status === 'completed') {
                            document.getElementById('importStatusTitle').innerText = 'Import completed ✅';
                            clearInterval(pollTimer);
                            setTimeout(() => location.reload(), 1500);
                        } else if (data.status === 'failed') {
                            document.getElementById('importStatusTitle').innerText = 'Import failed ❌';
                            document.getElementById('importStatusDetail').innerText = data.error_message ||
                                'Unknown error';
                            clearInterval(pollTimer);
                        }
                    }).catch(() => {});
                }

                tick();
                pollTimer = setInterval(tick, 3000);
            }

            fetch(historyUrl).then(r => r.json()).then(renderHistory);
        })();
    </script>
    <script>
        const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);
        let gridApi;

        const columnDefs = [

            ...ALL_COLUMNS.filter(col => [
                'serial_no',
                'x8_enquiry_no',
                'x8_enquiry_date',
                'x8_enquiry_assign_date',
                'oem_enquiry_no',
                'oem_enquiry_date',
                'oem_enquiry_assign_date',
                'oem_quick_enquiry_no',
                'oem_quick_enquiry_date',
                'oem_quick_enquiry_assign_date',
                'oem_long_enquiry_no',
                'oem_long_enquiry_date',
                'oem_long_enquiry_assign_date',
                'segment_name',
                'model_name',
                'variant_name',
                'first_name',
                'last_name',
                'full_name',
                'mobile',
                'email',
                'gender',
                'enquiry_type',
                'source_name',
                'sub_source',
                'likely_purchase_in_days',                
                'fuel_type',
                'transmission',
                'drivetrain',
                'seating',
                'color_name',
                'tehsil',
                'district',
                'city',
                'sc_code',
                'dealer_branch',
                'dealer_location',                
                'followup_type',
                'followup_date',
                'followup_time',
                // 'person_code',
                // 'reference_details',
                // 'referred_by',
                // 'referee_phone',
                // 'referee_name',
                // 'planned_campaign_name',
                
                // 'activity_type',
                // 'activity_segment',
                // 'activity_model',
                // 'activity_start_date',
                // 'activity_end_date',
                // 'activity_branch',
                // 'activity_location',
                
                'occupation_type',
                'customer_type',
                'occupation_sub_type',                
                'company_name',                
                'dob',
                'marital_status',
                'marriage_date',
                'age_group',
                'usage_area',
                'km_travelled_daily',
                'application_type',
                'application',
                'pincode',
                'address',    
                'has_ev',
                'purchase_type',
                'remarks',
                //'vehicle_no',
                
                'consider_make',
                'consider_model',
                'consider_variant',
                'dms_enquiry_stage',
                'cre_enquiry_stage',
                'cre_next_fup_date',
                'cre_next_fup_time',
                'cre_next_fup_remarks',
                'quotation_no',
                'booking_no',
                'booking_date',
                'oem_booking_no',
                'oem_booking_date',
                'oem_otf_no',
                
                
                
                //'place_of_registration',
                
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

        function debounce(fn, delay) {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        }

        let currentSearchText = '';


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
                            searchText: currentSearchText
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        params.successCallback(data.rows || [], data.lastRow ?? -1);
                    })
                    .catch(err => {
                        console.error('Failed to load enquiries page', err);
                        params.failCallback();
                    });
            }
        };

        const gridOptions = {
            columnDefs: columnDefs,
            rowModelType: 'infinite',
            datasource: dataSource,
            cacheBlockSize: 100,
            maxBlocksInCache: 10,
            infiniteInitialRowCount: 100,
            rowHeight: 28,
            animateRows: true,
            defaultColDef: {
                sortable: true,

                filter: false,
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
                'x8_enquiry_date',
                'oem_enquiry_no',
                'oem_enquiry_date',
                'oem_quick_enquiry_no',
                'segment_name',
                'model_name',
                'variant_name',
                'first_name',
                //'last_name',
                //'full_name',
                'mobile',
               // 'email',
               // 'gender',
              
                'enquiry_type',
                'source_name',
                'sub_source',
                //'likely_purchase_in_days',                
                //'fuel_type',
                //'transmission',
                //'drivetrain',
                //'seating',
                //'color_name',
                'tehsil',
                'district',
                'city',
                'sc_code',
                'dealer_branch',
                'dealer_location',                
                'followup_type',
                'followup_date',
                //'followup_time',
                'customer_type',
                'occupation_type',    
                // 'occupation_sub_type',                
                // 'company_name',                
                // 'dob',
                // 'marital_status',
                // 'marriage_date',
                // 'age_group',
                // 'usage_area',
                // 'km_travelled_daily',
                // 'application_type',
                // 'application',
                // 'pincode',    
                // 'has_ev',
                // 'address',
                // 'purchase_type',
                // 'remarks',
                'action'
                ];

                const allCols = gridApi.getAllGridColumns().map(col => col.getColId());

                gridApi.setColumnsVisible(allCols, false);
                gridApi.setColumnsVisible(defaultFields, true);

                setTimeout(() => gridApi.autoSizeAllColumns(), 300);
            }
        };

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

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = gridApi.getColumn(col.field)?.isVisible() ?? false;

                if (['serial_no', 'enquiry_no', 'full_name', 'action'].includes(col.field)) {
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

            bubble.style.display = 'block';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const gridDiv = document.querySelector('#myGrid');
            agGrid.createGrid(gridDiv, gridOptions);

            document.getElementById('quickFilter').addEventListener('input', debounce(e => {
                currentSearchText = e.target.value.trim();

                gridApi.setGridOption('datasource', dataSource);
            }, 400));

            document.getElementById('resetAll').addEventListener('click', () => {
                document.getElementById('quickFilter').value = '';
                currentSearchText = '';
                gridApi.applyColumnState({
                    defaultState: {
                        sort: null
                    }
                });
                gridApi.setGridOption('datasource', dataSource);
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
                'x8_enquiry_date',
                'oem_enquiry_no',
                'oem_enquiry_date',
                'oem_quick_enquiry_no',
                'segment_name',
                'model_name',
                'variant_name',
                'first_name',
                //'last_name',
                //'full_name',
                'mobile',
               // 'email',
               // 'gender',
               
                'enquiry_type',
                'source_name',
                'sub_source',
                //'likely_purchase_in_days',                
                //'fuel_type',
                //'transmission',
                //'drivetrain',
                //'seating',
                //'color_name',
                'tehsil',
                'district',
                'city',
                'sc_code',
                'dealer_branch',
                'dealer_location',                
                'followup_type',
                'followup_date',
                //'followup_time',
                'customer_type',
                'occupation_type',

                // 'occupation_sub_type',                
                // 'company_name',                
                // 'dob',
                // 'marital_status',
                // 'marriage_date',
                // 'age_group',
                // 'usage_area',
                // 'km_travelled_daily',
                // 'application_type',
                // 'application',
                // 'pincode',  
                // 'address',  
                // 'has_ev',
                // 'purchase_type',
                // 'remarks',
                'action'
                ];
                const allCols = gridApi.getAllGridColumns().map(c => c.getColId());

                gridApi.setColumnsVisible(allCols, false);
                gridApi.setColumnsVisible(defaultFields, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 200);
            });


            document.getElementById('exportCsv').addEventListener('click', () => {
                const params = new URLSearchParams({
                    searchText: currentSearchText
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

                if (rows.length < gridApi.getDisplayedRowCount()) {
                    alert(
                        'PDF export includes only the rows currently loaded in the grid (scroll to load more, then export again). For the full list, use the CSV export instead.'
                    );
                }
            });
        });

        function redirectToEnquiryList(selectElement) {
            if (selectElement.value) {
                window.location.href = selectElement.value;
            }
        }
    </script>
@endpush
