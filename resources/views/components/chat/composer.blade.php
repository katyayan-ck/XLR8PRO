@props(['model', 'parentId' => null, 'allowInternal' => false])
@php $refType = app(\App\Services\Platform\Chat\ChatService::class)->refType($model); @endphp
@if ($refType)
    <form method="POST" action="{{ route('utils.chat.remark') }}" enctype="multipart/form-data" class="chat-composer">
        @csrf
        <input type="hidden" name="ref_type" value="{{ $refType }}">
        <input type="hidden" name="ref_id" value="{{ $model->getKey() }}">
        @if ($parentId)
            <input type="hidden" name="parent_id" value="{{ $parentId }}">
        @endif
        <textarea name="body" rows="2" maxlength="5000" class="form-control mb-2" placeholder="{{ $parentId ? 'Write a reply…' : 'Write a remark… (@username to mention)' }}"></textarea>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="flex-grow-1"><x-ui.upload name="file" /></div>
            @if ($allowInternal)
                <label class="form-check mb-0"><input type="checkbox" name="internal" value="1" class="form-check-input"> <span class="form-check-label small">Internal</span></label>
            @endif
            <button type="submit" class="btn btn-sm btn-primary ms-auto"><i class="la la-paper-plane me-1"></i>{{ $parentId ? 'Reply' : 'Post' }}</button>
        </div>
    </form>
@endif
