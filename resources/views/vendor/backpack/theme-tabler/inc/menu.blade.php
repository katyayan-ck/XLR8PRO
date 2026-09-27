{{-- Top menu items (ordered left) --}}
@if (backpack_auth()->check())
<ul class="nav navbar-nav d-md-down-none">
    @include(backpack_view('inc.topbar_left_content'))
</ul>
@endif

{{-- Top menu right items (ordered right) --}}
<ul class="nav navbar-nav d-flex flex-row flex-shrink-0 @if(backpack_theme_config('html_direction') == 'rtl') me-0 @endif">

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

    {{-- Dark Mode Toggle --}}
    <li class="nav-item">
        @includeWhen(backpack_theme_config('options.showColorModeSwitcher'), backpack_view('layouts.partials.switch_theme'))
    </li>

    {{-- Original Topbar Right Content (यहाँ से तीनों आइकॉन्स आएंगे) --}}
    @include(backpack_view('inc.topbar_right_content'))

    {{-- User Dropdown (profile, logout) --}}
    @include(backpack_view('inc.menu_user_dropdown'))
    @endif
</ul>

