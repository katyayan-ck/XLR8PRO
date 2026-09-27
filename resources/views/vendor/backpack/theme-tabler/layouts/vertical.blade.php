<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}" dir="{{ backpack_theme_config('html_direction') }}">

<head>
    @include(backpack_view('inc.head'))
</head>

{{-- DEC-067: Tabler vertical layout — sidebar menu plus a top header carrying mode, appearance, notifications and the user
     block. layouts/vertical_dark reuses this with a dark sidebar. --}}
<body class="{{ backpack_theme_config('classes.body') }}" bp-layout="{{ ($xlSidebarTheme ?? 'light') === 'dark' ? 'vertical-dark' : 'vertical' }}">

@include(backpack_view('layouts.partials.light_dark_mode_logic'))

<div class="page">

    @include(backpack_view('layouts._vertical.menu_container'), ['theme' => $xlSidebarTheme ?? 'light'])

    <div class="page-wrapper">

        <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none xl-topbar @if(backpack_theme_config('options.useStickyHeader')) sticky-top @endif">
            <div class="{{ backpack_theme_config('options.useFluidContainers') ? 'container-fluid' : 'container-xxl' }}">
                <div class="navbar-nav flex-row order-md-last align-items-center">
                    @include(backpack_view('inc.menu'))
                </div>
                <div class="me-auto text-secondary small d-none d-xl-block">{{ backpack_theme_config('project_name') }}</div>
            </div>
        </header>

        <div class="page-body">
            <main class="{{ backpack_theme_config('options.useFluidContainers') ? 'container-fluid' : 'container-xxl' }}">

                @yield('before_breadcrumbs_widgets')
                @includeWhen(isset($breadcrumbs), backpack_view('inc.breadcrumbs'))
                @yield('after_breadcrumbs_widgets')
                @yield('header')

                <div class="container-fluid animated fadeIn">
                    @yield('before_content_widgets')
                    @yield('content')
                    @yield('after_content_widgets')
                </div>
            </main>
        </div>

        @include(backpack_view('inc.footer'))
    </div>
</div>

@include(backpack_view('inc.theme_settings'))

@yield('before_scripts')
@stack('before_scripts')

@include(backpack_view('inc.scripts'))
@include(backpack_view('inc.theme_scripts'))

@yield('after_scripts')
@stack('after_scripts')
</body>
</html>
