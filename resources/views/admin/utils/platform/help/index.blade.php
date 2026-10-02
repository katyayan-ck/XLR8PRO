@extends(backpack_view('blank'))

@section('title', $title)

{{-- Help centre (DEC-094, W16b): search + the articles this user may open, by module; coverage for settings managers. --}}
@section('content')
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="page-title">{{ $title }}</h2>
                <div class="text-body-secondary small">{{ __('utils.help.shortcut') }}</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label" for="xl-help-centre-q">{{ __('utils.help.search') }}</label>
            <input type="search" id="xl-help-centre-q" class="form-control" data-xl-help-search
                data-url="{{ route('utils.help.search') }}" data-empty="{{ __('utils.help.no_results') }}" autocomplete="off">
            <div class="list-group list-group-flush mt-2" data-xl-help-results></div>
        </div>
    </div>

    @if ($coverage)
        <div class="alert alert-info">{{ __('utils.help.coverage', ['covered' => $coverage['covered'], 'total' => $coverage['total']]) }}</div>
    @endif

    @forelse ($articles as $module => $list)
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">{{ $module }}</h3></div>
            <div class="list-group list-group-flush">
                @foreach ($list as $article)
                    <a href="{{ route('utils.help.show', ['key' => $article['key']]) }}" class="list-group-item list-group-item-action">{{ $article['title'] }}</a>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card"><div class="xl-empty"><i class="la la-life-ring" aria-hidden="true"></i>{{ __('utils.help.none_yet') }}</div></div>
    @endforelse

    @if ($usage)
        {{-- W16f: help usage, last 30 days (settings managers) --}}
        @php
            $labels = ['OPEN' => 'Articles opened', 'MISSING' => 'Screens without help', 'SEARCH' => 'Searches', 'SEARCH_EMPTY' => 'Searches with no result', 'TOUR_DONE' => 'Tours finished', 'SUPPORT' => 'Support requests'];
            $lists = ['articles' => 'Most opened articles', 'missing' => 'Screens without help (asked for)', 'empty_searches' => 'Searches with no result'];
        @endphp
        <div class="card mt-3" data-xl-help-usage>
            <div class="card-header"><h3 class="card-title">{{ __('utils.help.usage_title', ['days' => $usage['days']]) }}</h3></div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    @foreach ($labels as $event => $label)
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-body-secondary">{{ $label }}</div>
                                <div class="h3 m-0">{{ $usage['totals'][$event] ?? 0 }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="row g-3">
                    @foreach ($lists as $key => $label)
                        <div class="col-12 col-lg-4">
                            <div class="fw-bold small mb-1">{{ $label }}</div>
                            @forelse ($usage[$key] as $ref => $n)
                                <div class="d-flex justify-content-between small border-bottom py-1"><span class="text-break">{{ $ref }}</span><span class="badge bg-secondary-lt">{{ $n }}</span></div>
                            @empty
                                <div class="small text-body-secondary">—</div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($coverage && $coverage['missing'] !== [])
        <details class="card mt-3">
            <summary class="card-header">{{ count($coverage['missing']) }} screen route(s) without an article</summary>
            <div class="card-body small"><code>{{ implode(', ', $coverage['missing']) }}</code></div>
        </details>
    @endif
</div>
@endsection
