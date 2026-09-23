@php
    $gridConfig = [
        'columns' => [
            ['field' => 'serial_no',          'headerName' => 'S.No.'],
            ['field' => 'receipt_no',         'headerName' => 'Receipt No.'],
            ['field' => 'receipt_date',       'headerName' => 'Receipt Date'],
            ['field' => 'customer_name',      'headerName' => 'Customer Name'],
            ['field' => 'care_of',            'headerName' => 'Care Of'],
            ['field' => 'address',            'headerName' => 'Address'],
            ['field' => 'contact_no',          'headerName' => 'Contact No.'],
            ['field' => 'on_account_of',      'headerName' => 'On A/c Of'],
            ['field' => 'xceler8_enq_no',     'headerName' => 'Xceler8 Enq No.'],
            ['field' => 'xceler8_booking_no', 'headerName' => 'Xceler8 Booking No.'],
            ['field' => 'votf_no',            'headerName' => 'VOTF No.'],
            ['field' => 'registration_no',    'headerName' => 'Registration No.'],
            ['field' => 'chassis_no',         'headerName' => 'Chassis No.'],
            ['field' => 'payment_mode',       'headerName' => 'Mode of Payment'],
            ['field' => 'amount',             'headerName' => 'Amount'],
            ['field' => 'transaction_date',   'headerName' => 'Transaction Date'],
            ['field' => 'instrument_no',      'headerName' => 'Instrument No.'],
            ['field' => 'transaction_no',     'headerName' => 'Transaction No.'],
            ['field' => 'bank_name',          'headerName' => 'Bank Name'],
            ['field' => 'action',             'headerName' => 'Action'],
        ],

        'data' => $gridConfig['data'] ?? [],
    ];
@endphp

@extends(backpack_view('blank'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                    <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                        {{ $title ?? 'Issue Receipt List' }}
                    </h2>

                    <div class="d-flex align-items-center gap-3 flex-nowrap">
                        <a href="{{ backpack_url('accounts/receipt/create') }}" class="btn btn-blue btn-sm fw-bold shadow-sm">
                            <i class="la la-plus me-1"></i>Add Receipt
                        </a>
                    </div>
                </div>

                <div class="card-body p-0" style="background: var(--tblr-bg-surface-secondary)">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">
                        <div class="d-flex align-items-center gap-2 flex-nowrap">
                            <input type="text" id="quickFilter" class="form-control w-100 w-md-auto" style="width:360px; min-width:260px;" placeholder="Smart Search...">
                            <button id="resetAll" class="btn btn-outline-danger btn-sm text-nowrap">Reset</button>
                        </div>

                        <div class="d-flex gap-2 flex-nowrap justify-content-center">
                            <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm text-nowrap">Default Headers</button>
                            <div class="position-relative d-inline-block">
                                <button id="btnCustomiseHeaders" class="btn btn-red btn-sm text-nowrap">Customise Headers</button>
                                <div id="columnBubble" style="display:none; position:absolute; top:110%; left:0; width:320px; background: var(--tblr-card-bg); border:1px solid #ddd; border-radius:6px; box-shadow:0 8px 20px rgba(0,0,0,.15); z-index:9999;">
                                    <div class="d-flex justify-content-between align-items-center px-2 py-1 border-bottom">
                                        <strong style="font-size:13px;">Customise Headers</strong>
                                        <button id="closeColumnBubble" class="btn btn-sm btn-link text-danger p-0">✕</button>
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
                                <img src="{{ asset('images/export-excel.png') }}" alt="Excel" style="height:30px; width:auto;">
                            </button>
                            <button id="exportPdf" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                                <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">
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

        function htmlRenderer(params) {
            return params.value || '';
        }

        const columnDefs = ALL_COLUMNS.map(col => {
            if (col.field === 'action') {
                return {
                    ...col,
                    pinned: 'right',
                    width: 120,
                    sortable: false,
                    filter: false,
                    cellRenderer: htmlRenderer
                };
            }
            return col;
        });

        const gridOptions = {
            columnDefs: columnDefs,
            rowData: @json($gridConfig['data'] ?? []),
            pagination: true,
            paginationPageSize: 50,
            rowHeight: 32,
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
                setTimeout(() => gridApi.autoSizeAllColumns(), 300);
            }
        };

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

            // Column customization logic
            document.getElementById('btnCustomiseHeaders').addEventListener('click', e => {
                e.stopPropagation();
                openColumnBubble();
            });

            document.getElementById('closeColumnBubble').addEventListener('click', () => {
                document.getElementById('columnBubble').style.display = 'none';
            });

            document.getElementById('btnAllHeaders').addEventListener('click', () => {
                const allCols = gridApi.getAllGridColumns().map(c => c.getColId());
                gridApi.setColumnsVisible(allCols, true);
                setTimeout(() => gridApi.autoSizeAllColumns(), 200);
            });
        });

        function openColumnBubble() {
            const bubble = document.getElementById('columnBubble');
            const tbody = document.getElementById('columnBubbleBody');
            if (!gridApi || !bubble || !tbody) return;

            tbody.innerHTML = '';
            ALL_COLUMNS.forEach(col => {
                if (!col.field) return;

                const tr = document.createElement('tr');
                const tdCheck = document.createElement('td');
                tdCheck.style.width = '40px';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = gridApi.getColumn(col.field)?.isVisible() ?? false;

                if (['receipt_no', 'customer_name', 'action'].includes(col.field)) {
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
    </script>
@endpush