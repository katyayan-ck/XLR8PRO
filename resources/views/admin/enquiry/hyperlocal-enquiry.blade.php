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
                        flex-nowrap flex-md-nowrap flex-wrap">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    {{ $title ?? 'Hyperlocal Enquiries' }}
                </h2>
                {{-- <span class="badge bg-light text-dark px-3 py-2">
                    Total: {{ $gridConfig['data']->count() ?? 0 }}
                </span> --}}
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
                        <button id="exportCsv" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                            <img src="{{ asset('images/export-excel.png') }}" alt="Excel"
                                style="height:30px; width:auto;">
                        </button>
                        <button id="exportExcel" class="btn btn-sm text-nowrap d-flex align-items-center gap-2">
                            <img src="{{ asset('images/export-pdf.png') }}" alt="PDF" style="height:30px; width:auto;">
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

    const DEFAULT_VISIBLE_FIELDS = [
        'serial_no',
        'lead_id',
        'name',
        'phone_number',
        'email',
        'call_start_time',
        'call_end_time',
        'call_duration',
        'call_status',
        'call_type',
        'lead_status',
        'lead_intent',
        'lead_type',
        'source',
        'model',
        'dealer_code',
        'enquiry_status',
        'enquiry_date',
        'action'
    ];

    // ✅ Flat columnDefs (Parent headers removed)
    const columnDefs = getCols(DEFAULT_VISIBLE_FIELDS).map(col => {
        // Serial no aur Lead ID ko left pin karein
        if (col.field === 'serial_no' || col.field === 'lead_id') {
            col.pinned = 'left';
        }
        // Action column ko right pin karein aur renderer set karein
        if (col.field === 'action') {
            col.pinned = 'right';
            col.cellRenderer = 'htmlRenderer';
        }
        return col;
    });

    // ✅ Single gridOptions declaration (Removed duplicate)
    const gridOptions = {
        columnDefs: columnDefs, 
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
            htmlRenderer: function(params) {
                if (!params.value) return '';
                return params.value; 
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

    // ✅ Updated Customisation Bubble (Removed columnGroups usage)
    function openColumnBubble() {
        const bubble = document.getElementById('columnBubble');
        const tbody  = document.getElementById('columnBubbleBody');
        if (!gridApi || !bubble || !tbody) return;

        tbody.innerHTML = '';

        // Create a flat list of all fields for customization (since columnGroups is removed)
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
        groupCheckbox.disabled = true; // Disable main select since we removed groups

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
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Hyperlocal Enquiries');
            XLSX.writeFile(workbook, 'hyperlocal-enquiries.xlsx');
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

            doc.text('Hyperlocal Enquiries Report', 40, 30);
            doc.autoTable({
                columns: exportCols,
                body: rows,
                startY: 50,
                styles: { fontSize: 8 },
                headStyles: { fillColor: [13, 110, 253] },
            });

            doc.save('hyperlocal-enquiries.pdf');
        });
    });
</script>
@endpush