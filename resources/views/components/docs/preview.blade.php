@props(['doc', 'compact' => false, 'actions' => false])
{{-- $doc is the DocsService DTO (id, name, kind, mime, size, url, is_image, info, path, fy) --}}
@php
    $icon = match (true) {
        ($doc['kind'] ?? null) === 'INFORMATION' => 'la-info-circle text-info',
        (bool) ($doc['is_image'] ?? false) => 'la-image text-success',
        str_contains((string) ($doc['mime'] ?? ''), 'pdf') => 'la-file-pdf text-danger',
        default => 'la-file-alt text-primary',
    };
    $download = ($doc['kind'] ?? null) !== 'INFORMATION' ? route('utils.docs.download', $doc['id']) : null;
@endphp
<div class="d-flex align-items-start gap-2 {{ $compact ? 'mt-1' : 'border rounded p-2 mb-2' }}">
    @if (! $compact && ($doc['is_image'] ?? false) && ($doc['url'] ?? null))
        <img src="{{ $doc['url'] }}" alt="" class="rounded xl-thumb">
    @else
        <i class="la {{ $icon }} fs-2"></i>
    @endif
    <div class="flex-grow-1 min-w-0">
        @if ($download)
            <a href="{{ $download }}" class="fw-medium text-truncate d-block">{{ $doc['name'] }}</a>
        @else
            <span class="fw-medium">{{ $doc['name'] }}</span>
        @endif
        @unless ($compact)
            <div class="small text-muted">
                {{ $doc['kind'] ?? '' }}
                @if ($doc['size'] ?? null) · {{ number_format($doc['size'] / 1024, 0) }} KB @endif
                @if ($doc['fy'] ?? null) · FY {{ $doc['fy'] }} @endif
            </div>
            @if ($doc['path'] ?? null)
                <x-docs.library-path :path="$doc['path']" />
            @endif
            @if ($doc['info'] ?? null)
                <div class="small mt-1">{!! $doc['info'] !!}</div>
            @endif
        @endunless
    </div>
    @if ($actions)
        {{ $actions }}
    @endif
</div>
