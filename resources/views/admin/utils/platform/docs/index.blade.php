@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $levels = ['path_entity' => 'Entity', 'path_location' => 'Location', 'path_category' => 'Category', 'path_sub' => 'Sub-category', 'path_item' => 'Item', 'fy' => 'FY'];
    $inCart = collect($cart)->pluck('id')->all();
@endphp
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="la la-folder-open me-1"></i>Library</h3></div>
                <div class="card-body">
                    <form method="GET" action="{{ route('utils.docs.index') }}" class="row g-2 mb-3">
                        @foreach ($levels as $field => $label)
                            <div class="col-md-2">
                                <select name="{{ $field }}" class="form-select form-select-sm">
                                    <option value="">{{ $label }}</option>
                                    @foreach ($library['facets'][$field] ?? [] as $option)
                                        <option value="{{ $option }}" @selected(($filters[$field] ?? '') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        <div class="col-md-3">
                            <select name="kind" class="form-select form-select-sm">
                                <option value="">Any kind</option>
                                @foreach (config('platform.docs.kinds') as $kindOption)
                                    <option value="{{ $kindOption }}" @selected(($filters['kind'] ?? '') === $kindOption)>{{ ucfirst(strtolower($kindOption)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search title or description"></div>
                        <div class="col-md-3 d-flex gap-2">
                            <button class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                            <a href="{{ route('utils.docs.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        </div>
                    </form>

                    @forelse ($library['items'] as $doc)
                        <x-docs.preview :doc="$doc">
                            <x-slot:actions>
                                @if (in_array($doc['id'], $inCart, true))
                                    <span class="badge bg-green-lt">In cart</span>
                                @else
                                    <form method="POST" action="{{ route('utils.docs.cart.add', $doc['id']) }}">@csrf<button class="btn btn-sm btn-outline-primary"><i class="la la-cart-plus"></i></button></form>
                                @endif
                            </x-slot:actions>
                        </x-docs.preview>
                    @empty
                        <div class="text-muted text-center py-4">No library documents match.</div>
                    @endforelse
                </div>
            </div>

            @can('UTL_DOCS_UPLOAD')
                <x-docs.uploader :library="true" title="Add to the library" />
            @endcan

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="la la-user me-1"></i>My uploads</h3></div>
                <div class="card-body">
                    @forelse ($mine as $doc)
                        <x-docs.preview :doc="$doc">
                            <x-slot:actions>
                                <div class="d-flex gap-1">
                                    @unless (in_array($doc['id'], $inCart, true))
                                        <form method="POST" action="{{ route('utils.docs.cart.add', $doc['id']) }}">@csrf<button class="btn btn-sm btn-outline-primary" title="Add to cart"><i class="la la-cart-plus"></i></button></form>
                                    @endunless
                                    <form method="POST" action="{{ route('utils.docs.destroy', $doc['id']) }}" onsubmit="return confirm('Delete this document?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Delete"><i class="la la-trash"></i></button></form>
                                </div>
                            </x-slot:actions>
                        </x-docs.preview>
                    @empty
                        <div class="text-muted small">You have not uploaded anything yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="la la-shopping-cart me-1"></i>Cart ({{ count($cart) }})</h3></div>
                <div class="card-body">
                    @forelse ($cart as $doc)
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-truncate small">{{ $doc['name'] }}</span>
                            <form method="POST" action="{{ route('utils.docs.cart.remove', $doc['id']) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0">Remove</button></form>
                        </div>
                    @empty
                        <div class="text-muted small">Add documents to build a pack.</div>
                    @endforelse
                </div>
                @if ($cart)
                    <div class="card-footer">
                        <form method="POST" action="{{ route('utils.docs.cart.save') }}" class="d-flex flex-column gap-2">
                            @csrf
                            <input type="text" name="name" required maxlength="100" class="form-control form-control-sm" placeholder="Pack name">
                            <input type="text" name="purpose" maxlength="250" class="form-control form-control-sm" placeholder="Purpose (optional)">
                            <button class="btn btn-sm btn-primary">Save as pack</button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="la la-box me-1"></i>My packs</h3></div>
                <div class="list-group list-group-flush">
                    @forelse ($groups as $group)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-medium">{{ $group['name'] }}</div>
                                    <div class="small text-muted">{{ $group['count'] }} file(s){{ $group['description'] ? ' · '.$group['description'] : '' }}</div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('utils.docs.packs.zip', $group['id']) }}" class="btn btn-sm btn-outline-primary" title="Download zip"><i class="la la-file-archive"></i></a>
                                    <form method="POST" action="{{ route('utils.docs.packs.destroy', $group['id']) }}" onsubmit="return confirm('Delete this pack? The documents stay.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Delete pack"><i class="la la-trash"></i></button></form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted small">No packs yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
