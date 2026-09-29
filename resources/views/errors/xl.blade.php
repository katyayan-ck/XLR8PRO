{{--
    Branded error page layout (go-live to-do U8). Every errors/{code}.blade.php extends it. Nothing here may depend on a
    working database or session beyond a rescue() fallback: a 500 can mean the database is down. Never shows a stack
    trace; 500s show a reference id that is also in the log (App\Support\ErrorRef).
--}}
@php
    $logo = rescue(fn () => site_logo_url(), asset('images/Logo-108x75.png'), false);
    $home = rescue(fn () => backpack_user() ? backpack_url('dashboard') : backpack_url('login'), url('/'), false);
    $signedIn = rescue(fn () => (bool) backpack_user(), false, false);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/xl-errors.css') }}">
</head>
<body>
    <main class="xe-card" role="main">
        <img class="xe-logo" src="{{ $logo }}" alt="{{ config('app.name') }}">
        <div class="xe-code" aria-hidden="true">@yield('code')</div>
        <h1 class="xe-title">@yield('title')</h1>
        <p class="xe-message">@yield('message')</p>
        @hasSection('reference')
            <p class="xe-ref">Reference <code>@yield('reference')</code> — quote it to IT support.</p>
        @endif
        <div class="xe-actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <a class="xe-btn xe-btn-primary" href="{{ $home }}">{{ $signedIn ? 'Go to dashboard' : 'Sign in' }}</a>
                <button type="button" class="xe-btn" onclick="history.back()">Go back</button>
            @endif
        </div>
    </main>
</body>
</html>
