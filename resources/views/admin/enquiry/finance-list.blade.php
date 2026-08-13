@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid"></section>
@endsection

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
<style>
    .ag-theme-quartz .center-header .ag-header-cell-label,
    .ag-theme-quartz .ag-header-cell-label { justify-content: center !important; text-align: center !important; }
    .ag-cell.action-cell { display: flex !important; align-items: center !important; justify-content: center !important; white-space: nowrap !important; padding: 0 10px !important; }
    .ag-cell.action-cell .btn { white-space: nowrap !important; width: max-content !important; }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">{{ $title }}</h2>
            </div>
            <div class="card-body p-0" style="background:#f8fafc">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-bottom bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="quickFilter" class="form-control" style="width:360px;" placeholder="Smart Search...">
                    </div>
                </div>
                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 200px); width:100%;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script>
    const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);
    function getCols(fields) { return ALL_COLUMNS.filter(col => fields.includes(col.field)); }
    
    let gridApi;

    const DEFAULT_VISIBLE_FIELDS = [
        'serial_no', 'x8_enquiry_no', 'x8_enquiry_date',
        'first_name', 'mobile',
        'model_name', 'variant_name',
        'dms_enquiry_stage', 'fin_mode', 'financier_name', 'loan_status', 'sc_code',
        'action'
    ];

    const columnGroups = [
        {
            headerName: 'Enquiry Details',
            headerClass: 'ag-header-center',
            children: getCols(['serial_no', 'x8_enquiry_no', 'x8_enquiry_date']).map(c => { c.pinned = 'left'; return c; })
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
                col.pinned = 'right'; col.cellRenderer = 'htmlRenderer'; 
                col.autoHeight = true; col.cellClass = 'action-cell'; col.suppressSizeToFit = true; delete col.width;
                return col;
            })
        }
    ];

    const gridOptions = {
        columnDefs: columnGroups,
        rowData: @json($gridConfig['data'] ?? []),
        pagination: true,
        paginationPageSize: 50,
        rowHeight: 35,
        defaultColDef: { sortable: true, filter: true, resizable: true, cellStyle: { textAlign: 'center' } },
        components: { htmlRenderer: params => params.value || '' },
        onGridReady: params => {
            gridApi = params.api;
            const allFields = [];
            columnGroups.forEach(g => { if(g.children) g.children.forEach(c => allFields.push(c.field)) });
            gridApi.setColumnsVisible(allFields, false);
            gridApi.setColumnsVisible(DEFAULT_VISIBLE_FIELDS, true);
            setTimeout(() => gridApi.autoSizeColumns(gridApi.getAllDisplayedColumns().map(c => c.getColId()), false), 400);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        gridApi = agGrid.createGrid(document.querySelector('#myGrid'), gridOptions);
        document.getElementById('quickFilter')?.addEventListener('input', e => gridApi.setGridOption('quickFilterText', e.target.value));
    });
</script>
@endpush