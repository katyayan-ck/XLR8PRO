@extends(backpack_view('blank'))


@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div
                    class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        {{ $title ?? 'Unassigned Long Enquiries' }}
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
        let gridApi;

        const columnDefs = [

            ...ALL_COLUMNS
            .filter(col => [

                'serial_no',
                'x8_enquiry_no',
                'x8_enquiry_date',
                //'x8_enquiry_assign_date',
                // 'oem_enquiry_no',
                // 'oem_enquiry_date',
               // 'oem_enquiry_assign_date',
                'oem_long_enquiry_no',
                'oem_long_enquiry_date',
                'oem_long_enquiry_status',
                //'oem_quick_enquiry_assign_date',
                
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
                'oem_otf_no'

            ].includes(col.field))

            .map(col => {

                switch (col.field) {

                    // Date columns
                    case 'enquiry_date':
                    case 'likely_purchase_date':
                    case 'followup_date':
                    case 'dob':
                    case 'marriage_date':
                    case 'booking_date':
                    case 'oem_booking_date':
                    case 'cre_next_fup_date':
                    case 'oem_enquiry_date':
                    case 'oem_long_enquiry_date':

                        col.filter = 'agDateColumnFilter';
                        break;

                        // Number columns
                    case 'mobile':
                    case 'zipcode':

                        col.filter = 'agNumberColumnFilter';
                        break;

                        // Everything else
                    default:

                        col.filter = 'agTextColumnFilter';
                }

                col.floatingFilter = true;

                return col;

            }),

            ...ALL_COLUMNS
            .filter(col => col.field === 'action')
            .map(col => {

                col.pinned = 'right';
                col.width = 140;
                col.sortable = false;
                col.filter = false;
                col.floatingFilter = false;
                col.cellRenderer = 'htmlRenderer';

                return col;

            })

        ];

        const gridOptions = {
            columnDefs: columnDefs,
            rowData: @json($gridConfig['data'] ?? []),
            pagination: true,
            paginationPageSize: 50,
            rowHeight: 28,
            animateRows: true,
            defaultColDef: {
                sortable: true,
                filter: true,
                floatingFilter: true,
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
                //'oem_enquiry_no',
                //'oem_enquiry_date',
                'oem_long_enquiry_no',
                'oem_long_enquiry_date',
                'oem_long_enquiry_status',
                //'x8_enquiry_assign_date',
                //'oem_enquiry_assign_date',
                //'oem_quick_enquiry_assign_date',
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
                'likely_purchase_in_days',                
                // 'fuel_type',
                // 'transmission',
                // 'drivetrain',
                // 'seating',
                // 'color_name',
                // 'tehsil',
                // 'district',
                // 'city',
                // 'sc_code',
                'dealer_branch',
                'dealer_location',                
                // 'followup_type',
                // 'followup_date',
                //'followup_time',
                 'customer_type',
                // 'occupation_type',
               
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
                'pincode',
                'address',    
                // 'has_ev',
                'purchase_type',
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

                if (['serial_no', 'long_enq_no', 'action'].includes(col.field)) {
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

            document.getElementById('quickFilter').addEventListener('input', e => {
                gridApi.setGridOption('quickFilterText', e.target.value);
            });

            document.getElementById('resetAll').addEventListener('click', () => {
                gridApi.setFilterModel(null);
                document.getElementById('quickFilter').value = '';
                gridApi.setGridOption('quickFilterText', '');
                gridApi.setSortModel(null);
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
                //'oem_enquiry_no',
                //'oem_enquiry_date',
                'oem_long_enquiry_no',
                'oem_long_enquiry_date',
                'oem_long_enquiry_status',
                //'x8_enquiry_assign_date',
                //'oem_enquiry_assign_date',
                //'oem_quick_enquiry_assign_date',
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
                'likely_purchase_in_days',                
                // 'fuel_type',
                // 'transmission',
                // 'drivetrain',
                // 'seating',
                // 'color_name',
                // 'tehsil',
                // 'district',
                // 'city',
                // 'sc_code',
                'dealer_branch',
                'dealer_location',                
                // 'followup_type',
                // 'followup_date',
                //'followup_time',
                'customer_type',
                // 'occupation_type',
               
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
                'pincode',    
                // 'has_ev',
                'address',
                'purchase_type',
                // 'remarks',
                'action'

                ];
                const allCols = gridApi.getAllGridColumns().map(c => c.getColId());

                gridApi.setColumnsVisible(allCols, false);
                gridApi.setColumnsVisible(defaultFields, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 200);
            });

            document.getElementById('exportCsv').addEventListener('click', () => {
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
                XLSX.utils.book_append_sheet(wb, ws, "Unassigned Long Enquiries");
                XLSX.writeFile(wb,
                    `unassigned-long-enquiries-${new Date().toISOString().slice(0, 10)}.xlsx`);
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

                doc.save(`unassigned-long-enquiries-${new Date().toISOString().slice(0, 10)}.pdf`);
            });
        });

        function redirectToEnquiryList(selectElement) {
            if (selectElement.value) {
                window.location.href = selectElement.value;
            }
        }
    </script>
@endpush
