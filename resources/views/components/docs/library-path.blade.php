@props(['path'])
{{-- Library path breadcrumb: Entity › Location › Category › Sub › Item (Document::libraryPath()) --}}
@php $parts = array_values(array_filter(is_array($path) ? $path : explode('/', (string) $path), fn ($p) => $p !== null && $p !== '')); @endphp
@if ($parts !== [])
    <ol class="breadcrumb breadcrumb-arrows small mb-0">
        @foreach ($parts as $part)
            <li class="breadcrumb-item">{{ $part }}</li>
        @endforeach
    </ol>
@endif
