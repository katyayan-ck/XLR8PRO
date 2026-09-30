<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}" dir="{{ backpack_theme_config('html_direction') }}">

<head>
    @include(backpack_view('inc.head'))
</head>

{{-- DEC-067: colours come from Tabler tokens only (no hard-coded header / page backgrounds), so the colour mode and the
     Appearance panel reach every part of the shell. --}}
<body class="{{ backpack_theme_config('classes.body') }}" bp-layout="horizontal">

    @include(backpack_view('layouts.partials.light_dark_mode_logic'))

    <div class="page">
        <div class="page-wrapper">

            <div class="xl-shell-top @if(backpack_theme_config('options.doubleTopBarInHorizontalLayouts')) double-top-bar @else single-top-bar @endif @if(backpack_theme_config('options.useStickyHeader')) sticky-top @endif @if(backpack_theme_config('options.useFluidContainers')) container-fluid @else container-xxl @endif">
                @includeWhen(backpack_theme_config('options.doubleTopBarInHorizontalLayouts'), backpack_view('layouts._horizontal.header_container'))
                @include(backpack_view('layouts._horizontal.menu_container'))
            </div>

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

    @if (setting('ui.appearance_enabled', true))
        @include(backpack_view('inc.theme_settings'))
    @endif

    @yield('before_scripts')
    @stack('before_scripts')

    @include(backpack_view('inc.scripts'))
    @include(backpack_view('inc.theme_scripts'))

    @yield('after_scripts')
    @stack('after_scripts')
</body>

</html>
