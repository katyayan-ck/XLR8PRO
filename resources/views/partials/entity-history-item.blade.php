<div class="history-item border-start border-3 border-primary ps-3 mb-4">
    <div class="d-flex justify-content-between">
        <div>
            <strong>{{ $thread->actor?->name ?? $thread->actor?->username ?? 'System' }}</strong>
            <span class="badge bg-info ms-2">{{ $thread->action?->value ?? $thread->title }}</span>
        </div>
        <small class="text-muted">{{ $thread->created_at->format('d M, Y • h:i A') }}</small>
    </div>

    <h6 class="mt-1 mb-2">{{ $thread->title }}</h6>
    @if($thread->body)
    <p class="mb-2">{{ $thread->body }}</p>
    @endif

    @if($thread->media->isNotEmpty())
    <div class="mt-2 d-flex flex-wrap gap-2">

        @foreach($thread->media as $media)

        @php
        $ext = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));
        $url = $media->getFullUrl();
        @endphp

        @if(in_array($ext,['jpg','jpeg','png','gif','webp']))

        <img src="{{ $url }}" class="history-media-thumb" data-url="{{ $url }}" data-type="image" style="
                    width:60px;
                    height:60px;
                    object-fit:cover;
                    border-radius:6px;
                    cursor:pointer;
                    border:1px solid #ddd;
                 ">

        @elseif($ext == 'pdf')

        <div class="pdf-preview-btn" data-url="{{ $url }}" data-type="pdf" style="
                    width:60px;
                    height:60px;
                    cursor:pointer;
                    border:1px solid #ddd;
                    border-radius:6px;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:#f8f9fa;
                 ">
            <i class="la la-file-pdf-o text-danger" style="font-size:30px"></i>
        </div>

        @endif

        @endforeach

    </div>
    @endif


    @if($thread->children->isNotEmpty())
    <div class="mt-3 ms-4 border-start border-2 ps-3">
        @foreach($thread->children as $child)
        @include('partials.entity-history-item', ['thread' => $child])
        @endforeach
    </div>
    @endif
</div>