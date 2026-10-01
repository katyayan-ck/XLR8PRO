@extends(backpack_view('blank'))

@section('title', __('utils.coming_soon.title'))

{{--
    "Coming soon" page (DEC-095 #8, D13 — BUG-056 / BUG-062): menu items whose screen is not built yet link here with
    ?feature=<menu label> instead of a 404. Replace the menu link with the real route when the screen ships.
--}}
@section('content')
@php
    $feature = \Illuminate\Support\Str::limit(trim((string) request()->query('feature', '')), 80) ?: __('utils.coming_soon.this_screen');
@endphp
<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="page-pretitle">{{ __('utils.coming_soon.title') }}</div>
        <h2 class="page-title">{{ $feature }}</h2>
    </div>
    <div class="card">
        <div class="xl-empty">
            <i class="la la-tools" aria-hidden="true"></i>
            <p class="mb-3">{{ __('utils.coming_soon.text', ['feature' => $feature]) }}</p>
            <a href="{{ backpack_url('dashboard') }}" class="btn btn-primary">{{ __('utils.coming_soon.back') }}</a>
        </div>
    </div>
</div>
@endsection
