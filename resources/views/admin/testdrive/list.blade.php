@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid"></section>
@endsection

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
<style>
    .ag-theme-quartz .center-header .ag-header-cell-label,
    .ag-theme-quartz .ag-header-cell-label { justify-content: center !important; text-align: center !important; }
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
                    <div class="d-flex align-items-center gap-2 flex-nowrap">
                        <input type="text" id="quickFilter" class="form-control" style="width:360px;" placeholder="Smart Search...">
                        <button id="resetAll" class="btn btn-sm btn-outline-danger">Reset</button>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ backpack_url('testdrive/create') }}" class="btn btn-sm btn-primary">
                            <i class="la la-plus"></i> Schedule Test Drive
                        </a>
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
    let gridApi;
    const gridOptions = {
        columnDefs: @json($gridConfig['columns']),
        rowData: @json($gridConfig['data']),
        pagination: true,
        paginationPageSize: 50,
        rowHeight: 35,
        defaultColDef: {
            sortable: true,
            filter: true,
            resizable: true,
            headerClass: 'center-header',
            cellStyle: { textAlign: 'center' }
        },
        components: { htmlRenderer: params => params.value || '' },
        onGridReady: params => {
            gridApi = params.api;
            gridApi.sizeColumnsToFit();
        }
    };

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
    });
</script>
@endpush