@extends(backpack_view('blank'))

@section('header')
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <!-- HEADER -->
            <div
                class="card-header bg-gradient-primary d-flex justify-content-between align-items-center flex-nowrap flex-md-nowrap flex-wrap gap-3">
                <h2 class="card-title mb-0 fw-bold text-black text-nowrap">
                    {{ $title ?? 'All Users' }}
                </h2>

                <div class="d-flex align-items-center gap-3 flex-nowrap">
                    @if (backpack_user() && backpack_user()->can('ORG_USER_CREATE'))
                        <a href="{{ backpack_url('org/user/create') }}" class="btn btn-blue btn-sm fw-bold shadow-sm">
                            <i class="la la-plus me-1"></i> New User
                        </a>
                    @endif
                </div>
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
                        <button id="btnAllHeaders" class="btn btn-blue btn-sm text-nowrap">All Headers</button>
                    </div>
                </div>

                <!-- AG Grid -->
                <div id="myGrid" class="ag-theme-quartz" style="height: calc(93vh - 260px); width:100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Suspend/Revoke/Activate confirmation modal -->
<div class="modal fade" id="userActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userActionModalTitle">Confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userActionModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="userActionConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_styles')
<link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
<style>
    .ag-theme-quartz .center-header .ag-header-cell-label { justify-content: center !important; }
</style>
@endpush

@push('after_scripts')
<script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>

<script>
    const ALL_COLUMNS = @json($gridConfig['columns'] ?? []);
    const CSRF_TOKEN = @json(csrf_token());

    function getCols(fields) {
        return ALL_COLUMNS.filter(col => fields.includes(col.field));
    }

    const defaultFields = ['serial_no','user_type','username','mobile','email','designation','branch','role','is_active','action'];

    let gridApi;

    const columnDefs = [
        ...getCols(['serial_no']).map(col => ({ ...col, pinned: 'left', width: 70 })),
        ...getCols(['user_type', 'username']).map(col => ({ ...col, pinned: 'left' })),
        ...getCols(['mobile', 'email']),
        ...getCols(['designation', 'branch', 'location', 'department', 'division', 'vertical', 'segment']),
        ...getCols(['role', 'is_active']),
        ...getCols(['action']).map(col => ({
            ...col, pinned: 'right', width: 260, sortable: false, filter: false, cellRenderer: 'htmlRenderer'
        })),
    ];

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
            cellStyle: { textAlign: 'center' },
            minWidth: 120,
        },
        components: {
            htmlRenderer: params => params.value || ''
        },
        onGridReady: params => {
            gridApi = params.api;
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));
            gridApi.setColumnsVisible(allCols, false);
            gridApi.setColumnsVisible(defaultFields, true);
            setTimeout(() => gridApi.autoSizeAllColumns(), 300);
        }
    };

    let pendingAction = null;
    let pendingUserId = null;

    function openActionModal(action, userId) {
        pendingAction = action;
        pendingUserId = userId;

        const titles = { suspend: 'Suspend User', revoke: 'Revoke Access', activate: 'Activate User' };
        const bodies = {
            suspend: 'This will deactivate the account but keep their role intact. They can be re-activated at any time.',
            revoke: 'This will deactivate the account AND strip their role plus any permission overrides. Reactivating afterwards will not restore the role automatically — this is a hard cutoff, typically for someone who has left.',
            activate: 'This will reactivate the account. Note: if access was previously revoked, the role is not restored automatically.',
        };

        document.getElementById('userActionModalTitle').textContent = titles[action] || 'Confirm';
        document.getElementById('userActionModalBody').textContent = bodies[action] || '';

        const confirmBtn = document.getElementById('userActionConfirmBtn');
        confirmBtn.className = action === 'activate' ? 'btn btn-success' : (action === 'revoke' ? 'btn btn-danger' : 'btn btn-warning');

        new bootstrap.Modal(document.getElementById('userActionModal')).show();
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
        });

        document.getElementById('btnAllHeaders').addEventListener('click', () => {
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));
            gridApi.setColumnsVisible(allCols, true);
            setTimeout(() => gridApi.autoSizeAllColumns(), 200);
        });

        document.getElementById('btnDefaultHeaders').addEventListener('click', () => {
            const allCols = [];
            gridApi.getAllGridColumns().forEach(col => allCols.push(col.getColId()));
            gridApi.setColumnsVisible(allCols, false);
            gridApi.setColumnsVisible(defaultFields, true);
            setTimeout(() => gridApi.autoSizeAllColumns(), 200);
        });

        document.getElementById('myGrid').addEventListener('click', e => {
            const btn = e.target.closest('.user-action');
            if (!btn) return;
            e.preventDefault();
            openActionModal(btn.dataset.action, btn.dataset.id);
        });

        document.getElementById('userActionConfirmBtn').addEventListener('click', () => {
            if (!pendingAction || !pendingUserId) return;

            fetch(`{{ url('admin/org/user') }}/${pendingUserId}/${pendingAction}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            }).then(() => window.location.reload());
        });
    });
</script>
@endpush
