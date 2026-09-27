@props(['model' => null, 'collection' => 'docs', 'library' => false, 'title' => 'Documents'])
{{-- Upload onto a record (`:model`) or into the library (`library`: shows the path fields); lists the record's files. --}}
@php
    $docs = app(\App\Services\Platform\Docs\DocsService::class);
    $refType = $model ? app(\App\Services\Platform\Chat\ChatService::class)->refType($model) : null;
    $files = $model ? $docs->listFor($model, $collection, backpack_user()?->id) : [];
    $canUpload = backpack_user()?->can('UTL_DOCS_UPLOAD');
@endphp
<div class="card docs-uploader">
    <div class="card-header"><h3 class="card-title mb-0"><i class="la la-paperclip me-1"></i>{{ $title }}</h3></div>
    @if ($model)
        <div class="card-body">
            @forelse ($files as $file)
                <x-docs.preview :doc="$file" />
            @empty
                <div class="text-muted small">No documents yet.</div>
            @endforelse
        </div>
    @endif
    @if ($canUpload && ($refType || $library))
        <div class="card-footer">
            <form method="POST" action="{{ route('utils.docs.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="collection" value="{{ $collection }}">
                @if ($refType)
                    <input type="hidden" name="ref_type" value="{{ $refType }}">
                    <input type="hidden" name="ref_id" value="{{ $model->getKey() }}">
                @endif
                <div class="row g-2">
                    <div class="col-md-6"><input type="text" name="title" maxlength="250" class="form-control form-control-sm" placeholder="Title (defaults to the file name)"></div>
                    <div class="col-md-6"><input type="file" name="file" class="form-control form-control-sm"></div>
                    @if ($library)
                        @foreach (['path_entity' => 'Entity', 'path_location' => 'Location', 'path_category' => 'Category', 'path_sub' => 'Sub-category', 'path_item' => 'Item', 'fy' => 'FY (2026-27)'] as $field => $label)
                            <div class="col-md-2"><input type="text" name="{{ $field }}" maxlength="100" class="form-control form-control-sm" placeholder="{{ $label }}"></div>
                        @endforeach
                        <div class="col-12"><textarea name="info_body" rows="2" class="form-control form-control-sm" placeholder="…or write an information card instead of a file"></textarea></div>
                    @endif
                    <div class="col-12 text-end"><button class="btn btn-sm btn-primary"><i class="la la-upload me-1"></i>Upload</button></div>
                </div>
            </form>
        </div>
    @endif
</div>
