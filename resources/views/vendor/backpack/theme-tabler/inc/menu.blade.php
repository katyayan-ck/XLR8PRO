{{-- Top menu items (ordered left) --}}
@if (backpack_auth()->check())
<ul class="nav navbar-nav d-md-down-none">
    @include(backpack_view('inc.topbar_left_content'))
</ul>
@endif

{{-- Top menu right items: colour mode, appearance, notifications, user block (DEC-067) --}}
<ul class="nav navbar-nav d-flex flex-row flex-shrink-0 align-items-center @if(backpack_theme_config('html_direction') == 'rtl') me-0 @endif">

    @if (backpack_auth()->guest())
    <li class="nav-item">
        <a class="nav-link" href="{{ route('backpack.auth.login') }}">{{ trans('backpack::base.login') }}</a>
    </li>
    @if (config('backpack.base.registration_open'))
    <li class="nav-item">
        <a class="nav-link" href="{{ route('backpack.auth.register') }}">{{ trans('backpack::base.register') }}</a>
    </li>
    @endif
    @else

    <li class="nav-item d-flex align-items-center">
        @includeWhen(backpack_theme_config('options.showColorModeSwitcher'), backpack_view('layouts.partials.switch_theme'))
    </li>

    @include(backpack_view('inc.appearance_button'))

    @include(backpack_view('inc.topbar_right_content'))

    @include(backpack_view('inc.menu_user_dropdown'))
    @endif
</ul>
