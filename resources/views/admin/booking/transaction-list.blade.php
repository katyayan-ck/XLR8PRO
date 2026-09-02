{{-- resources/views/admin/booking/transaction-list.blade.php --}}
@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-gradient-success
                        d-flex justify-content-between align-items-center
                        flex-nowrap flex-md-nowrap flex-wrap gap-3">

                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    {{ $title ?? 'OTF Listings' }}
                </h2>

                {{-- <div class="d-flex align-items-center gap-3 flex-nowrap">
                    <span class="badge bg-light text-dark px-3 py-2">
                        Status: Live (1) &amp; Pending (8)
                    </span>
                    <a href="{{ backpack_url('booking/create') }}" class="btn btn-light btn-sm fw-bold shadow-sm">
                        <i class="la la-plus me-1"></i> Add New Booking
                    </a>
                </div> --}}
            </div>

            <div class="card-body p-0" style="background:#f8fafc">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-white">

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
                            <button id="btnCustomiseHeaders" class="btn btn-danger btn-sm text-nowrap">
                                Customise Headers
                            </button>

                            <div id="columnBubble" style="display:none;
                                    position:absolute;
                                    top:110%;
                                    left:0;
                                    width:340px;
                                    background:#fff;
                                    border:1px solid #ddd;
                                    border-radius:6px;
                                    box-shadow:0 8px 20px rgba(0,0,0,.15);
                                    z-index:9999;
                                    max-height:500px;
                                    overflow-y:auto;">

                                <div class="d-flex justify-content-between align-items-center px-2 py-1 border-bottom sticky-top bg-white">
                                    <strong style="font-size:13px;">Customise Headers</strong>
                                    <button id="closeColumnBubble" class="btn btn-sm btn-link text-danger p-0">
                                        ✕
                                    </button>
                                </div>

                                <div style="max-height:450px; overflow:auto;">
                                    <table class="table table-sm mb-0">
                                        <tbody id="columnBubbleBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <button id="btnAllHeaders" class="btn btn-primary btn-sm text-nowrap">
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
    .ag-theme-quartz .ag-header-cell {
        font-weight: 600 !important;
    }
    .ag-theme-quartz .ag-header-group-cell {
        background-color: #d4edda !important;
        font-weight: 700 !important;
    }
    #columnBubble {
        width: 340px;
    }
    .text-right {
        text-align: right !important;
        padding-right: 10px !important;
    }
    .fw-bold {
        font-weight: 700 !important;
    }
    .bg-success-light {
        background-color: #d4edda !important;
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

    // Default visible fields for Transaction/OTF listing
    const DEFAULT_VISIBLE_FIELDS = [
        'serial_no', 'branch_name', 'customer_name', 'segment', 'model', 'variant',
        'chassis_no', 'body_type', 'sale_type', 'permit', 'registration_no_type',
        'votf_no', 'fsc_name', 'dms_otf',
        'ex_showroom_price', 'insurance_type', 'insurance_amount',
        'registration_type', 'registration_amount',
        'accessories', 'accessories_amount',
        'maxicare', 'vltd_device', 'coating', 'coating_price',
        'ppf', 'rto_yellow_tape', 'kazam', 'shield', 'shield_price',
        'rsa', 'rsa_amount', 'cod_charges',
        'charger_swapping', 'charger_swapping_amount',
        'tcs', 'total_receivable',
        'corporate_discount', 'exchange_bonus',
        'total_discount', 'net_receivable',
        'financier', 'loan_amount', 'final_balance',
        'vehicle_delivery_on', 'action'
    ];

    // Group definitions
    const columnGroups = [
        {
            headerName: 'Basic Info',
            headerClass: 'ag-header-center',
            children: getCols([
                'serial_no', 'branch_name', 'location_name', 'customer_name',
                'customer_address', 'customer_tehsil', 'customer_district',
                'segment', 'model', 'variant', 'chassis_no'
            ]).map(col => {
                if (col.field === 'serial_no') {
                    col.pinned = 'left';
                }
                return col;
            })
        },
        {
            headerName: 'Vehicle Details',
            headerClass: 'ag-header-center',
            children: getCols([
                'body_type', 'sale_type', 'permit', 'registration_no_type', 'registration_category'
            ])
        },
        {
            headerName: 'OTF / DMS',
            headerClass: 'ag-header-center',
            children: getCols([
                'votf_no', 'fsc_name', 'fsc_mile_id', 'dms_no', 'dms_otf'
            ])
        },
        {
            headerName: 'Price Details',
            headerClass: 'ag-header-center',
            children: getCols([
                'ex_showroom_price', 'insurance_company', 'insurance_type', 'insurance_covers',
                'insurance_amount', 'registration_type', 'registration_amount',
                'accessories', 'accessories_amount',
                'maxicare', 'vltd_device', 'coating', 'coating_price',
                'ppf', 'rto_yellow_tape', 'kazam', 'incidental_charges',
                'shield', 'shield_price', 'rsa', 'rsa_amount',
                'fastag', 'cod_charges',
                'charger_swapping', 'charger_swapping_amount', 'charger_swapping_option',
                'tcs', 'total_receivable'
            ])
        },
        {
            headerName: 'Discounts',
            headerClass: 'ag-header-center',
            children: getCols([
                'cash_scheme_oem', 'csd_discount', 'fame_subsidy',
                'dealer_discount', 'accessories_discount', 'shield_scheme',
                'corporate_discount', 'loyalty_bonus',
                'exchange_bonus', 'green_bonus', 'welcome_bonus',
                'accessories_spl_disc', 'ceramic_discount', 'ppf_discount',
                'charger_swapping_discount', 'charger_swapping_discount_type',
                'other_cash_discount', 'special_cash_discount',
                'total_discount', 'net_receivable'
            ])
        },
        {
            headerName: 'Finance',
            headerClass: 'ag-header-center',
            children: getCols([
                'financier', 'financier_branch',
                'loan_amount', 'file_charge', 'margin_money', 'financier_subvention',
                'do_amount', 'receipt_details', 'receipt_total',
                'do_settlement_difference', 'expected_balance',
                'discount_through_jv', 'final_balance'
            ])
        },
        {
            headerName: 'Delivery',
            headerClass: 'ag-header-center',
            children: getCols([
                'financier_verified', 'vehicle_delivery_on',
                'do_number_delivery', 'do_number_ta',
                'do_amount_ta', 'do_voucher_date'
            ])
        },
        {
            headerName: 'Other',
            headerClass: 'ag-header-center',
            children: getCols([
                'brokerage_amount', 'mm_support_receivable',
                'liquidation_scheme_receivable', 'other_discount_receivable',
                'registration_service_charge_receivable',
                'registration_service_charge_received',
                'gst_slab', 'oem_model_code', 'booking_no'
            ])
        },
        {
            headerName: 'Actions',
            headerClass: 'ag-header-center',
            children: getCols(['action']).map(col => {
                col.pinned = 'right';
                return col;
            })
        }
    ];

    const gridOptions = {
        columnDefs: columnGroups,
        rowData: @json($gridConfig['data'] ?? []),
        pagination: true,
        paginationPageSize: 50,
        rowHeight: 30,
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

            // Hide all → show defaults
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));

            gridApi.setColumnsVisible(allCols, false);
            gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);

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

        columnGroups.forEach(group => {
            const groupName = group.headerName;
            const children = group.children || [];

            if (groupName === 'Actions') return;

            const groupTr = document.createElement('tr');
            groupTr.style.background = '#f0f0f0';

            const groupCheckTd = document.createElement('td');
            groupCheckTd.style.width = '30px';
            groupCheckTd.className = 'text-center';

            const groupCheckbox = document.createElement('input');
            groupCheckbox.type = 'checkbox';

            const fields = children.map(c => c.field).filter(Boolean);
            const visibleCount = fields.filter(f => {
                const col = gridApi.getColumn(f);
                return col && col.isVisible();
            }).length;

            groupCheckbox.checked = visibleCount === fields.length && visibleCount > 0;
            groupCheckbox.indeterminate = visibleCount > 0 && visibleCount < fields.length;

            if (groupName === 'Basic Info') {
                groupCheckbox.checked = true;
                groupCheckbox.disabled = true;
            }

            groupCheckbox.addEventListener('change', () => {
                gridApi.setColumnsVisible(fields, groupCheckbox.checked);
                tbody.querySelectorAll(`tr[data-group="${groupName}"] input`)
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

                if (groupName === 'Basic Info') {
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
        gridApi = agGrid.createGrid(gridDiv, gridOptions);

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
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));

            gridApi.setColumnsVisible(allCols, false);
            gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);

            setTimeout(() => {
                const visibleIds = [];
                gridApi.getAllDisplayedColumns().forEach(col => visibleIds.push(col.getColId()));
                gridApi.autoSizeColumns(visibleIds);
            }, 200);
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
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Transaction OTF');
            XLSX.writeFile(workbook, `transaction-otf-${new Date().toISOString().slice(0,10)}.xlsx`);
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

            doc.text('Transaction / OTF Report', 40, 30);
            doc.autoTable({
                columns: exportCols,
                body: rows,
                startY: 50,
                styles: { fontSize: 7 },
                headStyles: { fillColor: [40, 167, 69] },
                columnStyles: {
                    ...Object.fromEntries(
                        ['ex_showroom_price', 'insurance_amount', 'registration_amount',
                         'accessories_amount', 'maxicare', 'vltd_device', 'coating_price',
                         'ppf', 'rto_yellow_tape', 'kazam', 'incidental_charges',
                         'shield_price', 'rsa_amount', 'fastag', 'cod_charges',
                         'charger_swapping_amount', 'tcs', 'total_receivable',
                         'cash_scheme_oem', 'csd_discount', 'fame_subsidy',
                         'dealer_discount', 'accessories_discount', 'shield_scheme',
                         'corporate_discount', 'loyalty_bonus', 'exchange_bonus',
                         'green_bonus', 'welcome_bonus', 'accessories_spl_disc',
                         'ceramic_discount', 'ppf_discount', 'charger_swapping_discount',
                         'other_cash_discount', 'special_cash_discount',
                         'total_discount', 'net_receivable',
                         'loan_amount', 'file_charge', 'margin_money',
                         'financier_subvention', 'do_amount', 'receipt_total',
                         'do_settlement_difference', 'expected_balance',
                         'discount_through_jv', 'final_balance',
                         'do_amount_ta', 'brokerage_amount', 'mm_support_receivable',
                         'liquidation_scheme_receivable', 'other_discount_receivable',
                         'registration_service_charge_receivable',
                         'registration_service_charge_received'].map(f => [f, { halign: 'right' }])
                    )
                }
            });

            doc.save('transaction-otf.pdf');
        });
    });
</script>
@endpush