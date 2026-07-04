{{-- resources/views/admin/booking/list.blade.php --}}
@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">

</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-gradient-primary
                        d-flex justify-content-between align-items-center
                        flex-nowrap flex-md-nowrap flex-wrap gap-3">

                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    {{ $title ?? 'All Live Bookings' }}
                </h2>

                <div class="d-flex align-items-center gap-3 flex-nowrap">

                    <a href="{{ backpack_url('booking/create') }}" class="btn btn-blue btn-sm fw-bold shadow-sm">
                        <i class="la la-plus me-1"></i> Add New Booking
                    </a>

                    <select id="statusFilter" class="form-select form-select-sm bg-white text-dark border-0 shadow-sm"
                        style="min-width: 200px; max-width: 260px;">
                        <option value="{{ backpack_url('booking') }}" {{ Route::currentRouteName()==='booking.index'
                            ? 'selected' : '' }}>
                            All Live Bookings
                        </option>
                        <option value="{{ backpack_url('booking/hold') }}" {{ Route::currentRouteName()==='booking.hold'
                            ? 'selected' : '' }}>
                            On-Hold Bookings
                        </option>
                        <option value="{{ backpack_url('booking/invoiced') }}" {{
                            Route::currentRouteName()==='booking.invoiced' ? 'selected' : '' }}>
                            Invoiced Bookings
                        </option>
                        <option value="{{ backpack_url('booking/cancelled') }}" {{
                            Route::currentRouteName()==='booking.cancelled' ? 'selected' : '' }}>
                            Cancelled Bookings
                        </option>
                    </select>

                </div>
            </div>



            <div class="card-body p-0" style="background:#f8fafc">

                <div
                    class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">

                    {{-- LEFT: Search + Reset --}}
                    <div class="d-flex align-items-center gap-2 flex-nowrap">
                        <input type="text" id="quickFilter" class="form-control w-100 w-md-auto"
                            style="width:360px; min-width:260px;" placeholder="Smart Search...">

                        <button id="resetAll" class="btn btn-outline-danger btn-sm text-nowrap">
                            Reset
                        </button>
                    </div>

                    <div class="d-flex gap-2 flex-nowrap justify-content-center">

                        <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm text-nowrap">
                            Default Headers
                        </button>

                        <div class="position-relative d-inline-block">

                            <button id="btnCustomiseHeaders" class="btn btn-red btn-sm text-nowrap">
                                Customise Headers
                            </button>

                            <div id="columnBubble" style="display:none;
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
                                    <button id="closeColumnBubble" class="btn btn-sm btn-link text-danger p-0">
                                        ✕
                                    </button>
                                </div>

                                <div style="max-height:260px; overflow:auto;">
                                    <table class="table table-sm mb-0">
                                        <tbody id="columnBubbleBody"></tbody>
                                    </table>
                                </div>
                            </div>

                        </div>

                        <button id="btnAllHeaders" class="btn btn-blue btn-sm text-nowrap">
                            All Headers
                        </button>




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


                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
            </div>

            @if(session('info'))
            <div class="card-footer text-center py-4 text-muted">
                {{ session('info') }}
            </div>
            @endif
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

    .ag-theme-quartz .ag-header-group-cell-label {
        justify-content: center !important;
    }

    #columnBubble {
        width: 320px;
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

    const DEFAULT_COLUMNS_BY_STATUS = {
        live: [
            'serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count',
            'b_cat', 'col_type', 'booking_amount','accessories_amount',
            'name', 'mobile', 'branch_name', 'location_name',
            'model', 'variant', 'color', 'seating', 'chassis_no',
            'b_source', 'consultant',
            'del_date', 'fin_mode', 'financier_short_name', 'loan_status',
            'buyer_type', 'dms_otf', 'dms_so',
            'livecount', 'stockcount',
            'action'
        ],
        hold: [
            'serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count',
            'b_cat', 'col_type', 'booking_amount',
            'name', 'mobile', 'branch_name', 'location_name',
            'model', 'variant', 'color', 'seating',
             'chassis_no',
            'b_source', 'consultant',
            'action'
        ],
        invoiced: [
            'serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count',
            'inv_no', 'inv_date', 'b_cat',  'name', 'mobile', 'branch_name', 'location_name',
            'model', 'variant', 'color', 'seating', 'chassis_no', 'b_source',
            'consultant',
            'action'
        ],
        cancelled: [
            'serial_no', 'booking_no', 'created_at', 'booking_date', 'days_count',
            'cancel_date',
            'b_cat', 'col_type', 'booking_amount',
            'name', 'mobile', 'branch_name', 'location_name',
            'model', 'variant', 'color', 'seating', 'chassis_no',
            'b_source', 'consultant',
            'action'
        ]
    };

    function getCurrentStatus() {
        const route = "{{ Route::currentRouteName() }}";
        if (route === 'booking.hold') return 'hold';
        if (route === 'booking.invoiced') return 'invoiced';
        if (route === 'booking.cancelled') return 'cancelled';
        return 'live';
    }

    
    let columnDefs;
    const STATUS = getCurrentStatus();

    if (STATUS === 'live') {
        columnDefs = [
            {
                headerName: 'Primary',
                children: getCols([
                    'serial_no',
                    'booking_no',
                    'created_at',
                    'booking_date',
                    'days_count'
                ]).map(col => {
                    if (col.field === 'serial_no' || col.field === 'booking_no') {
                        col.pinned = 'left';
                    }
                    return col;
                })
            },
            {
                headerName: 'Customer',
                children: getCols([
                    'b_type',
                    'b_cat',
                    'col_type',
                    'col_by',
                    'booking_amount',
                    'receipt_no',
                    'receipt_date',
                    'name',
                    'care_of',
                    'care_of_type',
                    'mobile',
                    'alt_mobile',
                    'gender',
                    'occ',
                    'pan_no',
                    'adhar_no',
                    'gstn',
                    'c_dob',
                    'customer_age',
                    'branch_name',
                    'location_name'
                ])
            },
            {
                headerName: 'Vehicle',
                children: getCols([
                    'segment',
                    'model',
                    'variant',
                    'color',
                    'seating',
                    'accessories_amount',
                    'chassis_no'
                ])
            },
            {
                headerName: 'Booking Detail',
                children: getCols([
                    'status',
                    'b_mode',
                    'online_bk_ref_no',
                    'b_source',
                    'dsa_name',
                    'consultant',
                    'del_type',
                    'del_date',
                    'fin_mode',
                    'financier',
                    'financier_short_name',
                    'loan_status'
                ])
            },
            {
                headerName: 'Purchase Type Details',
                children: getCols([
                    'buyer_type',
                    'exist_oem1',
                    'vh1_detail',
                    'exist_oem2',
                    'vh2_detail',
                    'registration_no',
                    'make_year',
                    'odo_reading',
                    'expected_price',
                    'offered_price',
                    'exchange_bonus',
                    'price_gap'
                ])
            },
            {
                headerName: 'Referred',
                children: getCols([
                    'r_name',
                    'r_mobile',
                    'r_model',
                    'r_variant',
                    'r_chassis'
                ])
            },
            {
                headerName: 'DMS',
                children: getCols([
                    'dms_no',
                    'dms_otf',
                    'otf_date',
                    'dms_so',

                ])
            },
            {
                headerName: 'Stock',
                children: getCols([
                    'livecount',
                    'stockcount'
                ])
            },
            {
                headerName: 'Actions',
                children: getCols(['action']).map(col => {
                    col.pinned = 'right';
                    return col;
                })
            }
        ];
    } else if (STATUS === 'hold') {
        columnDefs = [
            {
                headerName: 'Primary',
                children: getCols([
                    'serial_no',
                    'booking_no',
                    'created_at',
                    'booking_date',
                    'days_count'
                ]).map(col => {
                    if (col.field === 'serial_no' || col.field === 'booking_no') {
                        col.pinned = 'left';
                    }
                    return col;
                })
            },
            {
                headerName: 'Customer',
                children: getCols([
                    'b_type',
                    'b_cat',
                    'col_type',
                    'col_by',
                    'booking_amount',
                    'receipt_no',
                    'receipt_date',
                    'name',
                    'care_of',
                    'care_of_type',
                    'mobile',
                    'alt_mobile',
                    'gender',
                    'occ',
                    'pan_no',
                    'adhar_no',
                    'gstn',
                    'c_dob',
                    'customer_age',
                    'branch_name',
                    'location_name'
                ])
            },
            {
                headerName: 'Vehicle',
                children: getCols([
                    'segment',
                    'model',
                    'variant',
                    'color',
                    'seating',
                    'accessories_amount',
                    'chassis_no'
                ])
            },
            {
                headerName: 'Booking Detail',
                children: getCols([
                    'status',
                    'b_mode',
                    'online_bk_ref_no',
                    'b_source',
                    'dsa_name',
                    'consultant',
                    'del_type',
                    'del_date',
                    'fin_mode',
                    'financier',
                    'financier_short_name',
                    'loan_status'
                ])
            },
            {
                headerName: 'Purchase Type Details',
                children: getCols([
                    'buyer_type',
                    'exist_oem1',
                    'vh1_detail',
                    'exist_oem2',
                    'vh2_detail',
                    'registration_no',
                    'make_year',
                    'odo_reading',
                    'expected_price',
                    'offered_price',
                    'exchange_bonus',
                    'price_gap'
                ])
            },
            {
                headerName: 'Referred',
                children: getCols([
                    'r_name',
                    'r_mobile',
                    'r_model',
                    'r_variant',
                    'r_chassis'
                ])
            },
            {
                headerName: 'DMS',
                children: getCols([
                    'dms_no',
                    'dms_otf',
                    'otf_date',
                    'dms_so',

                ])
            },
            {
                headerName: 'Stock',
                children: getCols([
                    'livecount',
                    'stockcount'
                ])
            },
            {
                headerName: 'Actions',
                children: getCols(['action']).map(col => {
                    col.pinned = 'right';
                    return col;
                })
            }
        ];
    } else if (STATUS === 'invoiced') {
        columnDefs = [
            {
                headerName: 'Primary',
                children: getCols([
                    'serial_no',
                    'booking_no',
                    'created_at',
                    'booking_date',
                    'days_count',
                    'inv_no',
                    'inv_date'
                ]).map(col => {
                    if (col.field === 'serial_no' || col.field === 'booking_no') {
                        col.pinned = 'left';
                    }
                    return col;
                })
            },
            {
                headerName: 'Customer',
                children: getCols([
                    'b_type',
                    'b_cat',
                    'col_type',
                    'col_by',
                    'booking_amount',
                    'receipt_no',
                    'receipt_date',
                    'name',
                    'care_of',
                    'care_of_type',
                    'mobile',
                    'alt_mobile',
                    'gender',
                    'occ',
                    'pan_no',
                    'adhar_no',
                    'gstn',
                    'c_dob',
                    'customer_age',
                    'branch_name',
                    'location_name'
                ])
            },
            {
                headerName: 'Vehicle',
                children: getCols([
                    'segment',
                    'model',
                    'variant',
                    'color',
                    'seating',
                    'accessories_amount',
                    'chassis_no'
                ])
            },
            {
                headerName: 'Booking Detail',
                children: getCols([
                    'status',
                    'b_mode',
                    'online_bk_ref_no',
                    'b_source',
                    'dsa_name',
                    'consultant',
                    'del_type',
                    'del_date',
                    'fin_mode',
                    'financier',
                    'financier_short_name',
                    'loan_status'
                ])
            },
            {
                headerName: 'Purchase Type Details',
                children: getCols([
                    'buyer_type',
                    'exist_oem1',
                    'vh1_detail',
                    'exist_oem2',
                    'vh2_detail',
                    'registration_no',
                    'make_year',
                    'odo_reading',
                    'expected_price',
                    'offered_price',
                    'exchange_bonus',
                    'price_gap'
                ])
            },
            {
                headerName: 'Referred',
                children: getCols([
                    'r_name',
                    'r_mobile',
                    'r_model',
                    'r_variant',
                    'r_chassis'
                ])
            },
            {
                headerName: 'DMS',
                children: getCols([
                    'dms_no',
                    'dms_otf',
                    'otf_date',
                    'dms_so',

                ])
            },
            {
                headerName: 'Stock',
                children: getCols([
                    'livecount',
                    'stockcount'
                ])
            },
            {
                headerName: 'Insurance',
                children: getCols([
                    'insurance_source',
                    'insurance_company',
                    'insurance_short_name',
                    'policy_no',
                    'policy_date',
                    'policy_type'

                ])
            },
            {
                headerName: 'RTO',
                children: getCols([
                    'rto_sale_type',
                    'rto_permit',
                    'rto_body_type',
                    'registration_type',
                    'registration_no_type',
                    'trc_number',
                    'trc_payment_bank_ref_no',
                    'application_no',
                    'tax_payment_bank_ref_no',
                    'vehicle_registration_no'
                ])
            },
           {
                headerName: 'DO',
                children: getCols([
                    'instrument_type',
                    'loan_amount_dealer',
                    'margin_money',
                    'file_charge',
                    'net_payment_amount'
                ])
            },
            {
                headerName: 'Actions',
                children: getCols(['action']).map(col => {
                    col.pinned = 'right';
                    return col;
                })
            }
        ];
    } else if (STATUS === 'cancelled') {
        columnDefs = [
            {
                headerName: 'Primary',
                children: getCols([
                    'serial_no',
                    'booking_no',
                    'created_at',
                    'booking_date',
                    'days_count',
                    'cancel_date'
                ]).map(col => {
                    if (col.field === 'serial_no' || col.field === 'booking_no') {
                        col.pinned = 'left';
                    }
                    return col;
                })
            },
            {
                headerName: 'Customer',
                children: getCols([
                    'b_type',
                    'b_cat',
                    'col_type',
                    'col_by',
                    'booking_amount',
                    'receipt_no',
                    'receipt_date',
                    'name',
                    'care_of',
                    'care_of_type',
                    'mobile',
                    'alt_mobile',
                    'gender',
                    'occ',
                    'pan_no',
                    'adhar_no',
                    'gstn',
                    'c_dob',
                    'customer_age',
                    'branch_name',
                    'location_name'
                ])
            },
            {
                headerName: 'Vehicle',
                children: getCols([
                    'segment',
                    'model',
                    'variant',
                    'color',
                    'seating',
                    'accessories_amount',
                    'chassis_no'
                ])
            },
            {
                headerName: 'Booking Detail',
                children: getCols([
                    'status',
                    'b_mode',
                    'online_bk_ref_no',
                    'b_source',
                    'dsa_name',
                    'consultant',
                    'del_type',
                    'del_date',
                    'fin_mode',
                    'financier',
                    'financier_short_name',
                    'loan_status'
                ])
            },
            {
                headerName: 'Purchase Type Details',
                children: getCols([
                    'buyer_type',
                    'exist_oem1',
                    'vh1_detail',
                    'exist_oem2',
                    'vh2_detail',
                    'registration_no',
                    'make_year',
                    'odo_reading',
                    'expected_price',
                    'offered_price',
                    'exchange_bonus',
                    'price_gap'
                ])
            },
            {
                headerName: 'Referred',
                children: getCols([
                    'r_name',
                    'r_mobile',
                    'r_model',
                    'r_variant',
                    'r_chassis'
                ])
            },
            {
                headerName: 'DMS',
                children: getCols([
                    'dms_no',
                    'dms_otf',
                    'otf_date',
                    'dms_so',

                ])
            },
            {
                headerName: 'Stock',
                children: getCols([
                    'livecount',
                    'stockcount'
                ])
            },
            {
                headerName: 'Actions',
                children: getCols(['action']).map(col => {
                    col.pinned = 'right';
                    return col;
                })
            }
        ];
    }

    
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
            resizable: true,
            headerClass: 'center-header',
            cellStyle: { textAlign: 'center' }
        },
        components: {
            htmlRenderer: params => params.value || ''
        },
        onGridReady: params => {
            gridApi = params.api;

            const status = getCurrentStatus();
            const defaultFields = DEFAULT_COLUMNS_BY_STATUS[status] || [];

            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));

            gridApi.setColumnsVisible(allCols, false);
            gridApi.setColumnsVisible(defaultFields, true);

            setTimeout(() => {
                const visibleIds = [];
                gridApi.getAllDisplayedColumns().forEach(column => visibleIds.push(column.getColId()));
                gridApi.autoSizeColumns(visibleIds);
            }, 300);
        }
    };

    
    function openColumnBubble() {
        const bubble = document.getElementById('columnBubble');
        const tbody = document.getElementById('columnBubbleBody');
        if (!gridApi || !bubble || !tbody) return;

        tbody.innerHTML = '';

        columnDefs.forEach(group => {
            const groupName = group.headerName;
            const children = group.children || [];

            if (groupName === 'Actions') return;

            const groupTr = document.createElement('tr');
            const groupCheckTd = document.createElement('td');
            groupCheckTd.style.width = '30px';
            const groupCheckbox = document.createElement('input');
            groupCheckbox.type = 'checkbox';

            const fields = children.map(c => c.field).filter(Boolean);
            const anyVisible = fields.some(f => {
                const col = gridApi.getColumn(f);
                return col && col.isVisible();
            });

            groupCheckbox.checked = anyVisible;
            if (groupName === 'Primary') {
                groupCheckbox.checked = true;
                groupCheckbox.disabled = true;
            }

            groupCheckbox.addEventListener('change', () => {
                gridApi.setColumnsVisible(fields, groupCheckbox.checked);
                tbody.querySelectorAll(`[data-group="${groupName}"] input`)
                    .forEach(cb => cb.checked = groupCheckbox.checked);
            });

            groupCheckTd.appendChild(groupCheckbox);

            const groupLabelTd = document.createElement('td');
            groupLabelTd.innerHTML = `<strong>${groupName}</strong>`;

            groupTr.appendChild(groupCheckTd);
            groupTr.appendChild(groupLabelTd);
            tbody.appendChild(groupTr);

            children.forEach(col => {
                if (!col.field) return;

                const tr = document.createElement('tr');
                tr.dataset.group = groupName;

                const tdCheck = document.createElement('td');
                tdCheck.style.paddingLeft = '25px';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';

                const column = gridApi.getColumn(col.field);
                checkbox.checked = column ? column.isVisible() : false;

                if (groupName === 'Primary') {
                    checkbox.checked = true;
                    checkbox.disabled = true;
                }

                checkbox.addEventListener('change', () => {
                    gridApi.setColumnsVisible([col.field], checkbox.checked);
                });

                tdCheck.appendChild(checkbox);

                const tdLabel = document.createElement('td');
                tdLabel.innerText = col.headerName;

                tr.appendChild(tdCheck);
                tr.appendChild(tdLabel);
                tbody.appendChild(tr);
            });
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
            gridApi.setGridOption('quickFilterText', '');
            document.getElementById('quickFilter').value = '';
        });

        document.getElementById('btnCustomiseHeaders')?.addEventListener('click', e => {
            e.stopPropagation();
            e.preventDefault();
            openColumnBubble();
        });

        document.getElementById('closeColumnBubble')?.addEventListener('click', e => {
            e.stopPropagation();
            e.preventDefault();
            document.getElementById('columnBubble').style.display = 'none';
        });

        document.getElementById('columnBubble')?.addEventListener('click', e => {
            e.stopPropagation();
        });

        document.addEventListener('click', e => {
            const bubble = document.getElementById('columnBubble');
            if (bubble && bubble.style.display === 'block') {
                bubble.style.display = 'none';
            }
        });

        document.getElementById('btnAllHeaders')?.addEventListener('click', () => {
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));
            gridApi.setColumnsVisible(allCols, true);
            setTimeout(() => {
                const visibleIds = [];
                gridApi.getAllDisplayedColumns().forEach(col => visibleIds.push(col.getColId()));
                gridApi.autoSizeColumns(visibleIds);
            }, 200);
        });

        document.getElementById('btnDefaultHeaders')?.addEventListener('click', () => {
            const status = getCurrentStatus();
            const defaultFields = DEFAULT_COLUMNS_BY_STATUS[status] || [];

            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));

            gridApi.setColumnsVisible(allCols, false);
            gridApi.setColumnsVisible(defaultFields, true);

            setTimeout(() => {
                const visibleIds = [];
                gridApi.getAllDisplayedColumns().forEach(col => visibleIds.push(col.getColId()));
                gridApi.autoSizeColumns(visibleIds);
            }, 200);
        });

        document.getElementById('statusFilter')?.addEventListener('change', function() {
            if (this.value) window.location.href = this.value;
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

            const worksheet = XLSX.utils.json_to_sheet(rows);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Live Bookings');
            XLSX.writeFile(workbook, `live-bookings-${new Date().toISOString().slice(0,10)}.xlsx`);
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
                visibleColumns.forEach(col => {
                    row[col.field] = node.data[col.field];
                });
                rows.push(row);
            });

            doc.text('Live Bookings Report', 40, 30);
            doc.autoTable({
                columns: exportCols,
                body: rows,
                startY: 50,
                styles: { fontSize: 8 },
                headStyles: { fillColor: [33, 150, 243] }
            });

            doc.save('live-bookings.pdf');
        });
    });
</script>
@endpush