@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    {{-- <h2>
        <i class="la la-money-check text-primary"></i> Int in Finance
        <small class="d-none d-md-inline">Bookings Interested in Finance</small>
    </h2> --}}
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">

            {{-- HEADER --}}
            <div
                class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    Erroneous Finance Entries
                </h2>
            </div>


            {{-- BODY --}}
            <div class="card-body p-0 bg-light">

                {{-- TOOLBAR --}}
                <div
                    class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-bottom bg-white">
                    <div class="d-flex align-items-center gap-2 flex-nowrap">
                        <input type="text" id="quickFilter" class="form-control w-100 w-md-auto"
                            style="width: 360px; min-width: 260px;" placeholder="Smart Search...">
                        <button id="resetAll" class="btn btn-outline-danger btn-sm">
                            Reset
                        </button>
                    </div>

                    <div class="d-flex gap-2 flex-wrap justify-content-center">
                        <button id="btnDefaultHeaders" class="btn btn-secondary btn-sm">
                            Default Headers
                        </button>

                        <div class="position-relative">
                            <button id="btnCustomiseHeaders" class="btn btn-danger btn-sm">
                                Customise Headers
                            </button>

                            <!-- Bubble -->
                            <div id="columnBubble" style="
                                display:none;
                                position:absolute;
                                top:110%;
                                left:0;
                                width:260px;
                                background:#fff;
                                border:1px solid #ddd;
                                border-radius:6px;
                                box-shadow:0 8px 20px rgba(0,0,0,.15);
                                z-index:9999;
                            ">
                                <div class="d-flex justify-content-between px-2 py-1 border-bottom">
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
                        <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                            <img src="{{ asset('images/export-excel.png') }}" alt="Excel"
                                style="height:30px; width:auto;">
                        </button>

                        <button id="exportExcel" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                            <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">
                        </button>
                    </div>
                </div>

                {{-- GRID --}}
                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width: 100%;"></div>
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
    /* Center regular (child/leaf) column headers */
    .ag-theme-quartz .center-header .ag-header-cell-label,
    .ag-theme-quartz .ag-header-cell-label {
        justify-content: center !important;
        text-align: center !important;
    }

    /* Center GROUP / parent headers – this fixes centering issue */
    .ag-theme-quartz .ag-header-group-cell-label {
        justify-content: center !important;
        text-align: center !important;
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
    }

    .ag-theme-quartz .ag-header-group-cell {
        text-align: center !important;
    }

    /* Ensure pinned groups also center */
    .ag-pinned-left-cols-container .ag-header-group-cell-label,
    .ag-pinned-right-cols-container .ag-header-group-cell-label {
        justify-content: center !important;
    }

    /* Pinned columns visual cue – blue theme for finance */
    .ag-pinned-left-cols-container .ag-header-group-cell,
    .ag-pinned-right-cols-container .ag-header-group-cell {
        background-color: #cfe2ff !important;
        /* light blue */
        font-weight: 600;
    }

    .ag-header-group-cell-label {
        padding: 0 6px !important;
    }
</style>
@endpush

@push('after_scripts')
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<script>
    // Import with custom GID
function importWithGid() {
    const gid = document.getElementById('gidInput').value.trim();
    if (!gid) {
        alert('Please enter GID');
        return;
    }
    const url = `{{ backpack_url('finance/import') }}?gid=${gid}`;
    if (confirm('Import data from Google Sheet with GID: ' + gid + '?')) {
        window.location.href = url;
    }
}



    // ALL_COLUMNS from controller → $gridConfig['columns']
    const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);

    function getCols(fields) {
        return ALL_COLUMNS.filter(col => fields.includes(col.field));
    }

    let gridApi;

    // Default visible fields → only Y marked ones
    const DEFAULT_VISIBLE_FIELDS = [
        // Primary - Y
        'serial_no',
        'booking_no',
        'created_at',           // Entry Date
        'booking_date',
        'days_count',           // Booking Age

        // Customer - Y
        'name',                 // Customer Name
        'mobile',               // Contact No.
        'branch_name',
        'location_name',

        // Vehicle - Y
        'model',
        'variant',
        'color',
        'seating',

        // Booking Detail - Y
        'consultant',           // Sales Consultant
        'del_date',        // Delivery Date
        'fin_mode',         // Finance Mode
        'financier_short_name',      // Financier Short Name
        'loan_status',      // Loan File Status
        'action'
    ];

    // Grouped columns with pinning & centering
    const columnGroups = [
        {
            headerName: 'Primary',
            headerClass: 'ag-header-center',
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
            headerClass: 'ag-header-center',
            children: getCols([
                'receipt_no',
                'receipt_date',
                'name',
                'mobile',
                'alt_mobile',
                'pan_no',
                'adhar_no',
                'gstn',
                'branch_name',
                'location_name'
            ])
        },
        {
            headerName: 'Vehicle',
            headerClass: 'ag-header-center',
            children: getCols([
                'segment',
                'model',
                'variant',
                'color',
                'seating',
                'chassis_no'             // Allotted Chassis No.
            ])
        },
        {
            headerName: 'Booking Detail',
            headerClass: 'ag-header-center',
            children: getCols([
                'consultant',               // Sales Consultant - Y
                'del_type',
                'del_date',            // Delivery Date - Y
                'fin_mode',             // Finance Mode - Y
                'financier',                // Financier - Y
                'financier_short_name',          // Financier Short Name - Y
                'loan_status'          // Loan File Status - Y
            ])
        },
        {
            headerName: 'Action',
            headerClass: 'ag-header-center',
            children: getCols(['action']).map(col => {
                col.pinned = 'right';
                col.cellRenderer = 'htmlRenderer';  // Ensures HTML renders properly
                col.autoHeight = true;
                col.cellClass = 'text-center p-0';
                return col;
            })
        }
    ];

    const gridOptions = {
        columnDefs: columnGroups,
        rowData: @json($gridConfig['data'] ?? []),
        pagination: true,
        paginationPageSize: 50,
        paginationPageSizeSelector: [20, 50, 100, 200],
        rowHeight: 30,
        animateRows: true,

        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            headerClass: 'center-header',
            cellStyle: { textAlign: 'center' },
            suppressHeaderMenuButton: false,
        },

        components: {
            htmlRenderer: params => params.value || '', 
        },

        onGridReady: params => {
            gridApi = params.api;

            const allFields = [];
            columnGroups.forEach(group => {
                if (group.children) {
                    group.children.forEach(child => {
                        if (child.field) allFields.push(child.field);
                    });
                }
            });

            gridApi.setColumnsVisible(allFields, false);
            gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);

            setTimeout(() => {
                const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
                gridApi.autoSizeColumns(visibleIds, false);
            }, 400);
        }
    };

     – grouped + parent/child sync
    function openColumnBubble() {
        const bubble = document.getElementById('columnBubble');
        const tbody  = document.getElementById('columnBubbleBody');
        if (!gridApi || !bubble || !tbody) return;

        tbody.innerHTML = '';

        columnGroups.forEach(group => {
            const groupName = group.headerName;
            const children  = group.children || [];

            if (groupName === 'Action') return;

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

            if (groupName === 'Primary') {
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
            groupLabelTd.colSpan = 2;
            groupLabelTd.innerHTML = `<strong>${groupName}</strong>`;

            groupTr.appendChild(groupCheckTd);
            groupTr.appendChild(groupLabelTd);
            tbody.appendChild(groupTr);

            children.forEach(child => {
                if (!child.field) return;

                const tr = document.createElement('tr');
                tr.dataset.group = groupName;

                const tdCheck = document.createElement('td');
                tdCheck.style.paddingLeft = '40px';
                tdCheck.className = 'text-center';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';

                const col = gridApi.getColumn(child.field);
                checkbox.checked = col ? col.isVisible() : false;

                if (groupName === 'Primary') {
                    checkbox.disabled = true;
                    checkbox.checked = true;
                }

                checkbox.addEventListener('change', () => {
                    gridApi.setColumnsVisible([child.field], checkbox.checked);
                });

                tdCheck.appendChild(checkbox);

                const tdLabel = document.createElement('td');
                tdLabel.innerText = child.headerName;

                tr.appendChild(tdCheck);
                tr.appendChild(tdLabel);
                tbody.appendChild(tr);
            });
        });

        bubble.style.display = 'block';
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
        const allFields = [];
        columnGroups.forEach(group => {
            if (group.children) {
                group.children.forEach(c => {
                    if (c.field) allFields.push(c.field);
                });
            }
        });
        gridApi.setColumnsVisible(allFields, true);
        setTimeout(() => {
            const visibleIds = gridApi.getAllDisplayedColumns().map(c => c.getColId());
            gridApi.autoSizeColumns(visibleIds, false);
        }, 200);
    });

    document.getElementById('btnDefaultHeaders')?.addEventListener('click', () => {
        const allFields = [];
        columnGroups.forEach(group => {
            if (group.children) {
                group.children.forEach(c => {
                    if (c.field) allFields.push(c.field);
                });
            }
        });

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

        document.getElementById('quickFilter')?.addEventListener('input', e => {
            gridApi.setGridOption('quickFilterText', e.target.value);
        });

        document.getElementById('resetAll')?.addEventListener('click', () => {
            gridApi.setFilterModel(null);
            gridApi.setGridOption('quickFilterText', '');
            document.getElementById('quickFilter').value = '';
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
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Int in Finance');
            XLSX.writeFile(workbook, `int-in-finance-${new Date().toISOString().slice(0,10)}.xlsx`);
        });

        
        document.getElementById('exportExcel')?.addEventListener('click', () => {
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

            doc.text('Int in Finance Report', 40, 30);
            doc.autoTable({
                columns: exportCols,
                body: rows,
                startY: 50,
                styles: { fontSize: 8 },
                headStyles: { fillColor: [13, 110, 253] }, // blue theme
            });

            doc.save('int-in-finance.pdf');
        });
    });
</script>
@endpush