@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
{{-- Vehicle content (DEC-092): each model's specifications, images, brochure and trims (features + galleries). --}}
<div class="xl-page-head">
    <div>
        <div class="text-body-secondary small text-uppercase fw-semibold">Vehicles</div>
        <h2 class="mb-0 fw-bold">{{ $title }}</h2>
    </div>
    <div class="xl-toolbar">
        <label for="contentSearch" class="visually-hidden">Search models</label>
        <input type="search" id="contentSearch" class="form-control form-control-sm xl-toolbar-search" placeholder="Search model…">
    </div>
</div>

{{-- Workbooks (DEC-092 Phase 3): export ours; import ours or the OEM sample format (matched by name, report below) --}}
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title mb-0">Workbooks</h3></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('vehicle.content.export', 'specs') }}" class="btn btn-outline-primary btn-sm"><i class="la la-file-excel me-1"></i> Export specifications</a>
            <a href="{{ route('vehicle.content.export', 'features') }}" class="btn btn-outline-primary btn-sm"><i class="la la-file-excel me-1"></i> Export features</a>
        </div>
        @if (backpack_user()->can('VEH_CONT_EDIT'))
            <form method="POST" action="{{ route('vehicle.content.import') }}" enctype="multipart/form-data" class="row g-2">
                @csrf
                <div class="col-12 col-md-3">
                    <label for="import-kind" class="form-label">Workbook</label>
                    <select id="import-kind" name="kind" class="form-select form-select-sm">
                        <option value="specs">Specifications</option>
                        <option value="features">Features</option>
                    </select>
                </div>
                <div class="col-12 col-md-9">
                    <label for="import-file" class="form-label">File — our export (codes) or the OEM sheet (matched by name)</label>
                    <x-ui.upload name="file" id="import-file" accept=".xlsx,.xls" required />
                    @error('file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-sm"><i class="la la-upload me-1"></i> Import</button>
                    <span class="small text-body-secondary ms-2">Unknown items are added; a blank cell keeps what is stored; “-” / “-NA-” = not applicable.</span>
                </div>
            </form>
        @endif

        @if ($report = session('content_import'))
            <div class="alert alert-info mt-3 mb-0" role="status">
                <div class="fw-semibold mb-1">{{ $report['kind'] === 'specs' ? 'Specifications' : 'Features' }} import ({{ $report['format'] === 'sample' ? 'OEM sample format' : 'our workbook' }}):
                    {{ $report['values'] }} values, {{ count($report['items_added']) }} new items, {{ count($report['unmatched']) }} columns not matched.</div>
                @if ($report['unmatched'] !== [])
                    <details><summary>Not matched — rename in the master or use our workbook (codes)</summary>
                        <ul class="small mb-0 mt-1">@foreach ($report['unmatched'] as $u)<li>{{ $u }}</li>@endforeach</ul>
                    </details>
                @endif
                @if ($report['items_added'] !== [])
                    <details><summary>New items</summary>
                        <ul class="small mb-0 mt-1">@foreach ($report['items_added'] as $i)<li>{{ $i }}</li>@endforeach</ul>
                    </details>
                @endif
            </div>
        @endif
    </div>
</div>

@forelse ($segments as $segment => $models)
    <div class="card mb-3 xl-content-segment">
        <div class="card-header"><h3 class="card-title mb-0">{{ $segment }} <span class="badge bg-secondary-lt ms-1">{{ count($models) }}</span></h3></div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr><th>Model</th><th class="text-center">Specifications</th><th class="text-center">Images</th><th class="text-center">Brochure</th><th class="text-center">Trims</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($models as $m)
                        <tr data-search="{{ strtolower($m['code'].' '.$m['name']) }}">
                            <td><div class="fw-medium">{{ $m['name'] }}</div><code class="small">{{ $m['code'] }}</code></td>
                            <td class="text-center">{!! $m['specs'] ? '<span class="badge bg-green-lt">'.$m['specs'].'</span>' : '<span class="text-body-secondary">—</span>' !!}</td>
                            <td class="text-center">{!! $m['images'] ? '<span class="badge bg-green-lt">'.$m['images'].'</span>' : '<span class="text-body-secondary">—</span>' !!}</td>
                            <td class="text-center">@if ($m['brochure'])<i class="la la-file-pdf text-danger" aria-label="Brochure uploaded"></i>@else<span class="text-body-secondary">—</span>@endif</td>
                            <td class="text-center">{{ $m['trims'] }}</td>
                            <td class="text-end"><a href="{{ route('vehicle.content.model', $m['code']) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card"><div class="xl-empty"><i class="la la-car" aria-hidden="true"></i>No active models.</div></div>
@endforelse
@endsection

@push('after_scripts')
<script>
    document.getElementById('contentSearch').addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('.xl-content-segment').forEach((card) => {
            let any = false;
            card.querySelectorAll('tr[data-search]').forEach((tr) => {
                const ok = q === '' || tr.dataset.search.includes(q);
                tr.classList.toggle('d-none', !ok);
                any = any || ok;
            });
            card.classList.toggle('d-none', !any);
        });
    });
</script>
@endpush
