{{-- Shared image + documents upload block for org reference-data entities.
     Expects: $imageCollection (string, e.g. 'branch_image'), $model (nullable — null on create). --}}
<div class="row">
    <div class="col-md-6 mb-3">
        <label>Image</label>
        @if ($model && $model->getFirstMediaUrl($imageCollection))
            <div class="mb-2 d-flex align-items-center gap-3">
                <img src="{{ $model->getFirstMediaUrl($imageCollection) }}" alt="" style="height:70px; width:70px; object-fit:cover; border-radius:8px; border:1px solid #dee2e6;">
                <div class="form-check">
                    <input type="checkbox" name="remove_image" value="1" class="form-check-input" id="remove_image">
                    <label class="form-check-label text-danger" for="remove_image">Remove current image</label>
                </div>
            </div>
        @endif
        <input type="file" name="{{ $imageCollection }}" class="form-control" accept="image/jpeg,image/png,image/webp">
        <div class="form-text">JPEG, PNG, or WEBP.</div>
    </div>

    <div class="col-md-6 mb-3">
        <label>Documents</label>
        @if ($model && $model->getMedia('documents')->isNotEmpty())
            <div class="mb-2">
                @foreach ($model->getMedia('documents') as $doc)
                    <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1">
                        <a href="{{ $doc->getUrl() }}" target="_blank" class="text-truncate" style="max-width: 70%;">
                            <i class="la la-file"></i> {{ $doc->file_name }}
                        </a>
                        <div class="form-check mb-0">
                            <input type="checkbox" name="remove_documents[]" value="{{ $doc->id }}" class="form-check-input" id="remove_doc_{{ $doc->id }}">
                            <label class="form-check-label text-danger small" for="remove_doc_{{ $doc->id }}">Remove</label>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <input type="file" name="documents[]" class="form-control" multiple accept=".pdf,.doc,.docx,image/jpeg,image/png">
        <div class="form-text">PDF, Word, or image files. You can select multiple.</div>
    </div>
</div>
