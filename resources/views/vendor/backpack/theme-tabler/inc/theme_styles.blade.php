{{--
    DEC-067: render-blocking theme bootstrap. Applies the saved colour mode (resolving "system" here, so dark-mode users
    never see a white flash) and the Tabler 1.4 theme attributes chosen in the Appearance panel
    (primary colour, base palette, font, radius — see public/js/xl-theme.js) before the first paint.
--}}
<script>
(function () {
    var root = document.documentElement, mode = null, saved = {};
    try { mode = localStorage.getItem('colorMode'); saved = JSON.parse(localStorage.getItem('xl.theme') || '{}') || {}; } catch (e) {}
    if (!mode || mode === 'system') {
        mode = @json(backpack_theme_config('options.defaultColorMode') ?? 'system');
        if (mode === 'system') { mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
    }
    root.setAttribute('data-bs-theme', mode);
    ['primary', 'base', 'font', 'radius'].forEach(function (key) {
        if (saved[key] !== undefined && saved[key] !== null && saved[key] !== '') { root.setAttribute('data-bs-theme-' + key, saved[key]); }
    });
})();
</script>

@basset('https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css', true, ['integrity' => 'sha256-fvdQvRBUamldCxJ2etgEi9jz7F3n2u+xBn+dDao9HJo=', 'crossorigin' => 'anonymous'])
@basset('https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler-themes.min.css', true, ['integrity' => 'sha256-ynp4dAgQ+boRy4UONOoEXmBVQw8LZJ1vxhbqyru4dEU=', 'crossorigin' => 'anonymous'])
@basset(base_path('vendor/backpack/theme-tabler/resources/assets/css/style.css'))
@basset(base_path('vendor/backpack/theme-tabler/resources/assets/css/color-adjustments.css'))
<link rel="stylesheet" href="{{ asset('css/xl-theme.css') }}?v={{ @filemtime(public_path('css/xl-theme.css')) }}">
