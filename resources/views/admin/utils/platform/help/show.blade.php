@extends(backpack_view('blank'))

@section('title', $title)

{{-- One help article as a page (DEC-094, W16b); `$html` is CommonMark output with raw HTML escaped (HelpService). --}}
@section('content')
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="page-pretitle"><a href="{{ route('utils.help.index') }}">{{ __('utils.help.centre') }}</a></div>
        <h2 class="page-title">{{ $article['title'] }}</h2>
        @if ($article['updated'])
            <div class="text-body-secondary small">{{ __('utils.help.updated', ['date' => site_date($article['updated'])]) }}</div>
        @endif
    </div>
    <div class="card">
        <div class="card-body xl-help-article">{!! $html !!}</div>
    </div>
</div>
@endsection
