@extends(backpack_view('blank'))

@section('header')
@endsection

@section('content')
<div class="xl-page-head">
    <div>
        <div class="text-body-secondary small text-uppercase fw-semibold">Org · Users</div>
        <h2 class="mb-0 fw-bold">{{ $title }}</h2>
    </div>
    <div class="xl-toolbar">
        <button type="button" id="bulkAdd" class="btn btn-outline-primary btn-sm">
            <i class="la la-plus me-1"></i> Add user
        </button>
        <button type="button" id="bulkSave" class="btn btn-primary btn-sm" disabled>
            <i class="la la-save me-1"></i> Save changes <span id="bulkCount" class="badge bg-body text-body ms-1">0</span>
        </button>
        <a href="{{ route('org.user.import') }}" class="btn btn-outline-secondary btn-sm">
            <i class="la la-file-excel me-1"></i> Workbook import / export
        </a>
        <a href="{{ backpack_url('org/user') }}" class="btn btn-outline-secondary btn-sm">
            <i class="la la-arrow-left me-1"></i> Users
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-2">
        <div class="xl-toolbar mb-2">
            <label for="bulkSearch" class="visually-hidden">Search users</label>
            <input type="search" id="bulkSearch" class="form-control form-control-sm xl-toolbar-search" placeholder="Search code, name, branch…">
            <div class="btn-group btn-group-sm" role="group" aria-label="Show rows">
                <input type="radio" class="btn-check" name="bulkShow" id="bulkShowAll" value="all" checked>
                <label class="btn btn-outline-secondary" for="bulkShowAll">All</label>
                <input type="radio" class="btn-check" name="bulkShow" id="bulkShowChanged" value="changed">
                <label class="btn btn-outline-secondary" for="bulkShowChanged">Changed</label>
                <input type="radio" class="btn-check" name="bulkShow" id="bulkShowFailed" value="failed">
                <label class="btn btn-outline-secondary" for="bulkShowFailed">Failed</label>
            </div>
            <span class="text-body-secondary small ms-auto">
                Double-click a cell to edit. Multi-value cells: pick codes, <code>ALL</code> (no restriction) or
                <code>NONE</code> (primary only). Every change is kept in the employee history.
            </span>
        </div>
        <div class="xl-grid-wrap">
            <div id="bulkLoader" class="xl-grid-loader d-flex" role="status" aria-live="polite">
                <span class="spinner-border text-primary" aria-hidden="true"></span>
                <span class="visually-hidden">Loading users…</span>
            </div>
            <div id="bulkGrid" class="xl-grid xl-bulk-grid"></div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@36.2.0/dist/ag-grid-community.min.js"></script>
<script>
    window.XL_USER_BULK = {
        dataUrl: @json(route('org.user.bulk.data')),
        saveUrl: @json(route('org.user.bulk.save')),
        csrf: @json(csrf_token()),
        headers: @json($headers),
        multi: @json($multi),
        masters: @json($masters),
    };
</script>
<script src="{{ asset('js/xl-user-bulk.js') }}?v={{ filemtime(public_path('js/xl-user-bulk.js')) }}"></script>
@endpush
