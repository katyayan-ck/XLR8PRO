@props(['model' => null, 'collection' => 'docs', 'library' => false, 'title' => 'Documents'])
{{--
    Documents on a record (drop-zone, AJAX upload with per-file progress, then the list refreshes) or
    into the library (`library`: path fields + optional information card, posted as a form).
--}}
@php
    $docs = app(\App\Services\Platform\Docs\DocsService::class);
    $refType = $model ? app(\App\Services\Platform\Chat\ChatService::class)->refType($model) : null;
    $files = $model ? $docs->listFor($model, $collection, backpack_user()?->id) : [];
    $canUpload = backpack_user()?->can('UTL_DOCS_UPLOAD');
@endphp
<div class="card docs-uploader">
    <div class="card-header"><h3 class="card-title mb-0"><i class="la la-paperclip me-1"></i>{{ $title }} @if ($model)<span class="text-muted small ms-1">{{ count($files) }}</span>@endif</h3></div>
    @if ($model)
        <div class="card-body">
            @forelse ($files as $file)
                <x-docs.preview :doc="$file" />
            @empty
                <div class="text-muted small">No documents yet.</div>
            @endforelse
            @if ($canUpload && $refType)
                <div class="mt-3">
                    <x-ui.upload name="file" multiple reload :url="route('utils.docs.store')"
                                 :fields="['ref_type' => $refType, 'ref_id' => $model->getKey(), 'collection' => $collection]" />
                </div>
            @endif
        </div>
    @elseif ($canUpload && $library)
        <form method="POST" action="{{ route('utils.docs.store') }}" enctype="multipart/form-data" class="card-body">
            @csrf
            <input type="hidden" name="collection" value="{{ $collection }}">
            <div class="row g-2">
                <div class="col-12"><label class="form-label" for="lib-title">Title</label><input type="text" id="lib-title" name="title" maxlength="250" class="form-control" placeholder="Defaults to the file name"></div>
                <div class="col-12"><x-ui.upload name="file" /></div>
                @foreach (['path_entity' => 'Entity', 'path_location' => 'Location', 'path_category' => 'Category', 'path_sub' => 'Sub-category', 'path_item' => 'Item', 'fy' => 'FY (26-27)'] as $field => $label)
                    <div class="col-6 col-md-4 col-xl-2"><label class="form-label small" for="lib-{{ $field }}">{{ $label }}</label><input type="text" id="lib-{{ $field }}" name="{{ $field }}" maxlength="100" class="form-control form-control-sm"></div>
                @endforeach
                <div class="col-12"><label class="form-label small" for="lib-info">…or an information card instead of a file</label><textarea id="lib-info" name="info_body" rows="2" class="form-control form-control-sm"></textarea></div>
                <div class="col-12 text-end"><button class="btn btn-primary"><i class="la la-upload me-1"></i>Save to library</button></div>
            </div>
        </form>
    @endif
</div>
