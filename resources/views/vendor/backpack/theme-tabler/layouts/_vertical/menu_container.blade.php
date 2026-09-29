{{-- DEC-067: Tabler-style sidebar. A dark sidebar ($theme = 'dark') carries data-bs-theme="dark" so every Tabler token inside it
     (links, dropdowns, active states) switches with it; the user block lives in the top header (layouts/vertical). --}}
@if (backpack_auth()->check())
    <aside data-menu-theme="{{ $theme ?? 'light' }}" @if(($theme ?? 'light') === 'dark') data-bs-theme="dark" @endif
        class="{{ backpack_theme_config('classes.sidebar') ?? 'navbar navbar-vertical navbar-expand-lg' }} xl-sidebar @if(backpack_theme_config('options.sidebarFixed')) navbar-fixed @endif">
        <div class="container-fluid">
            <ul class="nav navbar-nav d-flex flex-row align-items-center justify-content-between w-100 d-lg-none">
                @include(backpack_view('layouts.partials.mobile_toggle_btn'), ['forceWhiteLabelText' => true])
                <div class="d-flex flex-row align-items-center">
                    <li class="nav-item">
                        @includeWhen(backpack_theme_config('options.showColorModeSwitcher'), backpack_view('layouts.partials.switch_theme'))
                    </li>
                    @include(backpack_view('inc.topbar_right_content'))
                    @include(backpack_view('inc.menu_user_dropdown'))
                </div>
            </ul>
            <h1 class="navbar-brand d-none d-lg-flex align-self-stretch justify-content-center py-3 mb-0">
                {{-- DEC-083: the configurable site logo always opens the dashboard --}}
                <a class="text-decoration-none" href="{{ backpack_url('dashboard') }}" title="{{ backpack_theme_config('project_name') }} — Dashboard">
                    <img src="{{ site_logo_url() }}" alt="{{ backpack_theme_config('project_name') }}" class="xl-site-logo">
                </a>
            </h1>
            <div class="collapse navbar-collapse" id="mobile-menu">
                <ul class="navbar-nav pt-lg-2">
                    @include(backpack_view('inc.sidebar_content'))
                </ul>
            </div>
        </div>
    </aside>
@endif
