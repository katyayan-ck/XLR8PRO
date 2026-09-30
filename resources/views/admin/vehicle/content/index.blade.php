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
