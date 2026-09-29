{{--
    Admin-panel error pages (go-live to-do U8) — customised Backpack theme layout. Signed-in users keep the admin shell
    (menu, header) around a clear message and a way back; 500s show the reference id that is also in the log
    (App\Support\ErrorRef). Public / signed-out errors use resources/views/errors/xl.blade.php.
--}}
@extends(backpack_view(backpack_user() && backpack_theme_config('layout') ? 'layouts.'.backpack_theme_config('layout') : 'errors.blank'))

@section('content')
<div class="empty py-5">
    <div class="empty-header text-body-secondary">{{ $error_number }}</div>
    <p class="empty-title">@yield('title')</p>
    <p class="empty-subtitle text-secondary">@yield('description')</p>
    @if ((int) $error_number >= 500)
        <p class="small text-secondary mb-3">Reference <code>{{ \App\Support\ErrorRef::get() }}</code> — quote it to IT support.</p>
    @endif
    <div class="empty-action d-flex flex-wrap gap-2 justify-content-center">
        <a href="{{ backpack_user() ? backpack_url('dashboard') : backpack_url('login') }}" class="btn btn-primary">
            <i class="la la-home me-1" aria-hidden="true"></i>{{ backpack_user() ? 'Go to dashboard' : 'Sign in' }}
        </a>
        <button type="button" class="btn btn-outline-secondary" onclick="history.back()"><i class="la la-arrow-left me-1" aria-hidden="true"></i>Go back</button>
    </div>
</div>
@endsection
