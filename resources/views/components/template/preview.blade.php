@props(['version' => null, 'code' => null, 'channel' => 'EMAIL', 'vars' => []])
{{-- Template preview (FRS TPL-10, admin only): sample vars merged with $vars; unknown / missing placeholders highlighted. --}}
@php
    $engine = app(\App\Services\Platform\Templates\TemplateService::class);
    if (! $version && $code) {
        $found = $engine->get($code, $channel);
        $version = $found->ok ? $found->get('version') : null;
    }
    $render = $version ? $engine->renderVersion($version->loadMissing('template'), (array) $vars, true) : null;
@endphp
<div class="card template-preview">
    <div class="card-header"><h3 class="card-title mb-0"><i class="la la-eye me-1"></i>Preview{{ $version ? ' · v'.$version->version : '' }}</h3></div>
    <div class="card-body">
        @if (! $render)
            <div class="text-muted">No version to preview.</div>
        @else
            @if ($render->get('unknown'))
                <div class="alert alert-danger py-2 small">Unknown placeholder(s): {{ implode(', ', $render->get('unknown')) }} — sending would fail.</div>
            @endif
            @if ($render->get('missing'))
                <div class="alert alert-warning py-2 small">Required but empty in the sample: {{ implode(', ', $render->get('missing')) }}</div>
            @endif
            @if ($render->get('subject'))
                <div class="mb-2"><span class="text-muted small">Subject</span><div class="fw-medium">{{ $render->get('subject') }}</div></div>
            @endif
            @if ($render->get('html'))
                <div class="border rounded p-2 mb-2 bg-white text-dark" style="max-height: 360px; overflow: auto;">{!! $render->get('html') !!}</div>
            @endif
            @if ($render->get('text'))
                <pre class="border rounded p-2 small mb-0" style="white-space: pre-wrap;">{{ $render->get('text') }}</pre>
            @endif
            @if ($version->template->channel === 'SMS' && $render->get('text'))
                @php $len = mb_strlen($render->get('text')); $unicode = preg_match('/[^\x00-\x7F]/', $render->get('text')); @endphp
                <div class="small text-muted mt-1">{{ $len }} chars · {{ max(1, (int) ceil($len / ($unicode ? 70 : 160))) }} unit(s){{ $unicode ? ' · Unicode' : '' }}</div>
            @endif
        @endif
    </div>
</div>
