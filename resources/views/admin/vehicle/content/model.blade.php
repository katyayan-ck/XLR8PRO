@extends(backpack_view('blank'))

@section('title', $title)

@php($tab = request('tab', 'specs'))

@section('content')
{{-- Model content (DEC-092): images + brochure, category-wise specifications, trims. --}}
<div class="xl-page-head">
    <div>
        <div class="text-body-secondary small text-uppercase fw-semibold">Vehicle content · {{ $model->segment_code }}</div>
        <h2 class="mb-0 fw-bold">{{ $model->name }} <code class="small fw-normal">{{ $model->code }}</code></h2>
    </div>
    <div class="xl-toolbar">
        <a href="{{ route('vehicle.content.index') }}" class="btn btn-outline-secondary btn-sm"><i class="la la-arrow-left me-1"></i> All models</a>
    </div>
</div>

<ul class="nav nav-tabs xl-tabs-scroll mb-3" role="tablist">
    @foreach (['specs' => ['Specifications', 'la-list'], 'media' => ['Images & brochure', 'la-images'], 'trims' => ['Trims ('.count($sheet['trims']).')', 'la-clone']] as $key => [$label, $icon])
        <li class="nav-item" role="presentation">
            <a href="#tab-{{ $key }}" class="nav-link @if ($tab === $key) active @endif" data-bs-toggle="tab" role="tab"><i class="la {{ $icon }} me-1"></i>{{ $label }}</a>
        </li>
    @endforeach
</ul>

<div class="tab-content">
    {{-- Specifications --}}
    <div class="tab-pane fade @if ($tab === 'specs') show active @endif" id="tab-specs" role="tabpanel">
        <form method="POST" action="{{ route('vehicle.content.model.specs', $model->code) }}">
            @csrf @method('PUT')
            @forelse ($sheet['categories'] as $category => $items)
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">{{ $category }}</h3></div>
                    <div class="card-body">
                        @foreach ($items as $item)
                            <div class="row g-2 align-items-center mb-2">
                                <label for="spec-{{ $item['code'] }}" class="col-12 col-md-5 col-form-label py-0">{{ $item['name'] }}@if ($item['unit']) <span class="text-body-secondary small">({{ $item['unit'] }})</span>@endif</label>
                                <div class="col-12 col-md-7">
                                    <input type="text" id="spec-{{ $item['code'] }}" name="specs[{{ $item['code'] }}]" maxlength="500"
                                           value="{{ old('specs.'.$item['code'], $item['value']) }}" class="form-control form-control-sm" @disabled(! $canEdit)
                                           placeholder="— (blank = not given; “-” = not applicable)">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card mb-3"><div class="xl-empty"><i class="la la-list" aria-hidden="true"></i>No specification items yet — add the first one below, or import a workbook.</div></div>
            @endforelse
            @if ($canEdit && $sheet['categories'] !== [])
                <div class="d-flex justify-content-end mb-3"><button type="submit" class="btn btn-primary btn-sm"><i class="la la-save me-1"></i> Save specifications</button></div>
            @endif
        </form>

        @if ($canEdit)
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Add a specification item</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vehicle.content.model.spec-item', $model->code) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-12 col-md-4">
                            <label for="new-category" class="form-label">Category</label>
                            <input type="text" id="new-category" name="category" required maxlength="100" list="spec-categories" class="form-control form-control-sm" placeholder="e.g. Engine">
                            <datalist id="spec-categories">@foreach (array_keys($sheet['categories']) as $c)<option value="{{ $c }}">@endforeach</datalist>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="new-name" class="form-label">Specification</label>
                            <input type="text" id="new-name" name="name" required maxlength="150" class="form-control form-control-sm" placeholder="e.g. Displacement">
                        </div>
                        <div class="col-8 col-md-2">
                            <label for="new-unit" class="form-label">Unit</label>
                            <input type="text" id="new-unit" name="unit" maxlength="30" class="form-control form-control-sm" placeholder="cc">
                        </div>
                        <div class="col-4 col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Add</button></div>
                    </form>
                    <p class="small text-body-secondary mb-0 mt-2">Items are shared by all models; each model fills its own value.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Images & brochure --}}
    <div class="tab-pane fade @if ($tab === 'media') show active @endif" id="tab-media" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0">Images</h3></div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    @forelse ($sheet['images'] as $img)
                        <div class="col-6 col-md-3 col-xl-2">
                            <div class="border rounded p-1 text-center">
                                <a href="{{ $img['url'] }}" target="_blank" rel="noopener"><img src="{{ $img['thumb'] }}" alt="{{ $img['name'] }}" class="img-fluid rounded" loading="lazy"></a>
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('vehicle.content.model.media.remove', [$model->code, $img['id']]) }}" class="mt-1" onsubmit="return confirm('Remove this image?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger w-100"><i class="la la-trash me-1"></i>Remove</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-body-secondary small">No images yet.</div>
                    @endforelse
                </div>
                @if ($canEdit)
                    <form method="POST" action="{{ route('vehicle.content.model.images', $model->code) }}" enctype="multipart/form-data">
                        @csrf
                        <label for="model-images" class="form-label">Add images (JPG, PNG, WebP; up to {{ round($maxKb / 1024) }} MB each)</label>
                        <x-ui.upload name="images[]" id="model-images" accept="image/jpeg,image/png,image/webp" multiple required />
                        @error('images') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <button class="btn btn-primary btn-sm mt-2"><i class="la la-upload me-1"></i> Upload images</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Brochure (PDF)</h3></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    @if ($sheet['brochure'])
                        <a href="{{ $sheet['brochure']['url'] }}" target="_blank" rel="noopener"><i class="la la-file-pdf text-danger me-1"></i>{{ $sheet['brochure']['name'] }}</a>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('vehicle.content.model.media.remove', [$model->code, $sheet['brochure']['id']]) }}" class="ms-auto" onsubmit="return confirm('Remove the brochure?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="la la-trash me-1"></i>Remove</button>
                            </form>
                        @endif
                    @else
                        <span class="text-body-secondary small">No brochure yet.</span>
                    @endif
                </div>
                @if ($canEdit)
                    <form method="POST" action="{{ route('vehicle.content.model.brochure', $model->code) }}" enctype="multipart/form-data">
                        @csrf
                        <label for="model-brochure" class="form-label">{{ $sheet['brochure'] ? 'Replace the brochure' : 'Upload the brochure' }}</label>
                        <x-ui.upload name="brochure" id="model-brochure" accept="application/pdf" required />
                        @error('brochure') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <button class="btn btn-primary btn-sm mt-2"><i class="la la-upload me-1"></i> Upload brochure</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Trims --}}
    <div class="tab-pane fade @if ($tab === 'trims') show active @endif" id="tab-trims" role="tabpanel">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Trim</th><th class="text-center">Colours</th><th class="text-center">Features set</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($sheet['trims'] as $t)
                            <tr>
                                <td><div class="fw-medium">{{ $t['name'] }}</div><code class="small">{{ $t['code'] }}</code></td>
                                <td class="text-center">{{ $t['colours'] }}</td>
                                <td class="text-center">{!! $t['features'] ? '<span class="badge bg-green-lt">'.$t['features'].'</span>' : '<span class="text-body-secondary">—</span>' !!}</td>
                                <td class="text-end"><a href="{{ route('vehicle.content.trim', $t['code']) }}" class="btn btn-sm btn-outline-primary">Features &amp; gallery</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="xl-empty">No variants for this model.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
