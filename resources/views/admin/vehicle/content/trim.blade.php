@extends(backpack_view('blank'))

@section('title', $title)

@php($tab = request('tab', 'features'))

@section('content')
{{-- Trim content (DEC-092): features (shared by all colours) and the gallery (images bound to the trim or one colour). --}}
<div class="xl-page-head">
    <div>
        <div class="text-body-secondary small text-uppercase fw-semibold">Vehicle content · {{ $sheet['model']?->name ?? $trim->model_code }}</div>
        <h2 class="mb-0 fw-bold">{{ $sheet['name'] }} <code class="small fw-normal">{{ $trim->variant_code }}</code></h2>
    </div>
    <div class="xl-toolbar">
        <a href="{{ route('vehicle.content.model', [$trim->model_code, 'tab' => 'trims']) }}" class="btn btn-outline-secondary btn-sm"><i class="la la-arrow-left me-1"></i> Model</a>
    </div>
</div>

<ul class="nav nav-tabs xl-tabs-scroll mb-3" role="tablist">
    @foreach (['features' => ['Features', 'la-check-square'], 'gallery' => ['Gallery', 'la-images']] as $key => [$label, $icon])
        <li class="nav-item" role="presentation">
            <a href="#tab-{{ $key }}" class="nav-link @if ($tab === $key) active @endif" data-bs-toggle="tab" role="tab"><i class="la {{ $icon }} me-1"></i>{{ $label }}</a>
        </li>
    @endforeach
</ul>

<div class="tab-content">
    {{-- Features --}}
    <div class="tab-pane fade @if ($tab === 'features') show active @endif" id="tab-features" role="tabpanel">
        <p class="small text-body-secondary">Features apply to every colour of this trim. Choose Yes / No, or type a short value (e.g. "Optional", "6 airbags").</p>
        <form method="POST" action="{{ route('vehicle.content.trim.features', $trim->variant_code) }}">
            @csrf @method('PUT')
            <datalist id="feature-values"><option value="Yes"><option value="No"><option value="Optional"></datalist>
            @forelse ($sheet['groups'] as $group => $items)
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">{{ $group }}</h3></div>
                    <div class="card-body">
                        @foreach ($items as $item)
                            <div class="row g-2 align-items-center mb-2">
                                <label for="feat-{{ $item['code'] }}" class="col-12 col-md-7 col-form-label py-0">{{ $item['name'] }}</label>
                                <div class="col-12 col-md-5">
                                    <input type="text" id="feat-{{ $item['code'] }}" name="features[{{ $item['code'] }}]" maxlength="255" list="feature-values"
                                           value="{{ old('features.'.$item['code'], $item['value']) }}" class="form-control form-control-sm" @disabled(! $canEdit) placeholder="—">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card mb-3"><div class="xl-empty"><i class="la la-check-square" aria-hidden="true"></i>No feature items yet — add the first one below, or import a workbook.</div></div>
            @endforelse
            @if ($canEdit && $sheet['groups'] !== [])
                <div class="d-flex justify-content-end mb-3"><button type="submit" class="btn btn-primary btn-sm"><i class="la la-save me-1"></i> Save features</button></div>
            @endif
        </form>

        @if ($canEdit)
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Add a feature item</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vehicle.content.trim.feature-item', $trim->variant_code) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-12 col-md-5">
                            <label for="new-group" class="form-label">Feature group</label>
                            <input type="text" id="new-group" name="feature_group" required maxlength="100" list="feature-groups" class="form-control form-control-sm" placeholder="e.g. Safety & Trust">
                            <datalist id="feature-groups">@foreach (array_keys($sheet['groups']) as $g)<option value="{{ $g }}">@endforeach</datalist>
                        </div>
                        <div class="col-12 col-md-5">
                            <label for="new-feature" class="form-label">Feature</label>
                            <input type="text" id="new-feature" name="name" required maxlength="255" class="form-control form-control-sm" placeholder="e.g. Dual airbags">
                        </div>
                        <div class="col-12 col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Add</button></div>
                    </form>
                    <p class="small text-body-secondary mb-0 mt-2">Items are shared by all trims; each trim fills its own value.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Gallery --}}
    <div class="tab-pane fade @if ($tab === 'gallery') show active @endif" id="tab-gallery" role="tabpanel">
        @if ($canEdit)
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Add images</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vehicle.content.trim.gallery', $trim->variant_code) }}" enctype="multipart/form-data" class="row g-2">
                        @csrf
                        <div class="col-12 col-md-4">
                            <label for="gallery-level" class="form-label">Show these images for</label>
                            <select id="gallery-level" name="level" class="form-select form-select-sm">
                                <option value="trim">All colours of this trim</option>
                                @foreach ($sheet['colours'] as $c)
                                    <option value="{{ $c['code'] }}">Only {{ $c['name'] }} ({{ $c['code'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label for="gallery-images" class="form-label">Images (JPG, PNG, WebP; up to {{ round($maxKb / 1024) }} MB each)</label>
                            <x-ui.upload name="images[]" id="gallery-images" accept="image/jpeg,image/png,image/webp" multiple required />
                            @error('images') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12"><button class="btn btn-primary btn-sm"><i class="la la-upload me-1"></i> Upload</button></div>
                    </form>
                </div>
            </div>
        @endif

        @php($levels = ['trim' => ['All colours', $sheet['gallery']['trim']]] + collect($sheet['colours'])->mapWithKeys(fn ($c) => [$c['code'] => [$c['name'].' only', $sheet['gallery']['colours'][$c['code']] ?? []]])->all())
        @foreach ($levels as $levelKey => [$levelLabel, $images])
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">{{ $levelLabel }} <span class="badge bg-secondary-lt ms-1">{{ count($images) }}</span></h3></div>
                <div class="card-body">
                    <div class="row g-2">
                        @forelse ($images as $img)
                            <div class="col-6 col-md-3 col-xl-2">
                                <div class="border rounded p-1 text-center">
                                    <a href="{{ $img['url'] }}" target="_blank" rel="noopener"><img src="{{ $img['thumb'] }}" alt="{{ $img['name'] }}" class="img-fluid rounded" loading="lazy"></a>
                                    @if ($canEdit)
                                        <form method="POST" action="{{ route('vehicle.content.trim.gallery.remove', [$trim->variant_code, $img['id']]) }}" class="mt-1" onsubmit="return confirm('Remove this image?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger w-100"><i class="la la-trash me-1"></i>Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-body-secondary small">No images at this level.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
