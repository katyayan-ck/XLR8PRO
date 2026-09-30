{{-- Menu brand (DEC-083 / owner 30-09): the site logo or the dealership name as text (setting branding.menu_logo); always opens the dashboard. --}}
<a class="{{ $class ?? 'nav-link' }}" href="{{ backpack_url('dashboard') }}" title="{{ site_title() }} — Dashboard">
    @if (setting('branding.menu_logo', 'logo') === 'text')
        <span class="xl-site-brand-text">{{ dealership('name') ?: backpack_theme_config('project_name') }}</span>
    @else
        <img src="{{ site_logo_url() }}" alt="{{ site_title() }}" class="xl-site-logo">
    @endif
</a>
