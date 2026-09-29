/*!
 * Xceler8 theme layer (DEC-067). Loaded on every admin page after Tabler.
 *
 *  XL.theme.get()                      → {mode, primary, base, font, radius, text, space, layout}
 *  XL.theme.set('primary', 'red')      → applies + saves (mode / layout too: 'mode' → light|dark|system)
 *  XL.theme.reset()                    → Tabler defaults, horizontal layout
 *  XL.theme.onChange(fn)               → fn(state) after any change (mode, colour, font, radius, OS scheme)
 *  XL.theme.token('--tblr-primary')    → resolved CSS value, for charts / canvas code
 *
 * Colours, base palette, font and radius are Tabler 1.4 `data-bs-theme-*` attributes on <html>
 * (tabler-themes.min.css). They are applied before first paint by inc/theme_styles.blade.php and stored per browser
 * in localStorage "xl.theme". The menu layout is the `xl_layout` cookie, read on the server by
 * App\Http\Middleware\ApplyUiPreferences, so changing it reloads the page.
 * Density (to-do U3): `text` (xs|sm|md|lg) and `space` (compact|cozy|comfortable) are `data-xl-text` / `data-xl-space` on
 * <html>; '' means the site default (Settings ui.density.*, XL.densityDefaults from the bootstrap). CSS: xl-ui.css.
 * The Appearance panel (inc/theme_settings.blade.php) is plain markup wired by `[data-xl-theme]` inputs.
 */
(function () {
    'use strict';

    var XL = window.XL = window.XL || {};
    if (XL.theme) { return; }

    var STORE = 'xl.theme';
    var ATTRS = ['primary', 'base', 'font', 'radius'];
    var DENSITY = { text: ['xs', 'sm', 'md', 'lg'], space: ['compact', 'cozy', 'comfortable'] };
    var DENSITY_DEFAULTS = XL.densityDefaults || { text: 'md', space: 'comfortable' };
    var LAYOUTS = ['horizontal', 'vertical', 'vertical_dark'];
    var root = document.documentElement;
    var listeners = [];

    function readStore() {
        try { return JSON.parse(window.localStorage.getItem(STORE) || '{}') || {}; } catch (e) { return {}; }
    }

    function writeStore(state) {
        try { window.localStorage.setItem(STORE, JSON.stringify(state)); } catch (e) { /* private mode: session only */ }
    }

    function readCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function currentLayout() {
        var fromBody = document.body ? document.body.getAttribute('bp-layout') : null;
        var fromCookie = readCookie('xl_layout');
        if (LAYOUTS.indexOf(fromCookie) > -1) { return fromCookie; }
        return fromBody ? fromBody.replace('-', '_') : 'horizontal';
    }

    function currentMode() {
        return window.colorMode && window.colorMode.get ? (window.colorMode.get() || 'system') : (root.getAttribute('data-bs-theme') || 'light');
    }

    function get() {
        var saved = readStore();
        var state = { mode: currentMode(), layout: currentLayout() };
        ATTRS.concat(Object.keys(DENSITY)).forEach(function (key) { state[key] = saved[key] || ''; });
        return state;
    }

    function applyAttrs(saved) {
        ATTRS.forEach(function (key) {
            if (saved[key]) { root.setAttribute('data-bs-theme-' + key, saved[key]); } else { root.removeAttribute('data-bs-theme-' + key); }
        });
        Object.keys(DENSITY).forEach(function (key) {
            root.setAttribute('data-xl-' + key, DENSITY[key].indexOf(saved[key]) > -1 ? saved[key] : DENSITY_DEFAULTS[key]);
        });
    }

    var emitQueued = false;
    function emit() {
        if (emitQueued) { return; }
        emitQueued = true;
        window.requestAnimationFrame(function () {
            emitQueued = false;
            var state = get();
            syncPanel(state);
            listeners.forEach(function (fn) { try { fn(state); } catch (e) { /* a listener must not break the others */ } });
        });
    }

    function set(key, value) {
        value = value === null || value === undefined ? '' : String(value);
        if (key === 'mode') {
            if (window.colorMode && window.colorMode.set) { window.colorMode.set(value || 'system'); } else { root.setAttribute('data-bs-theme', value || 'light'); }
            emit();
            return;
        }
        if (key === 'layout') {
            if (LAYOUTS.indexOf(value) < 0) { value = 'horizontal'; }
            document.cookie = 'xl_layout=' + value + '; path=/; max-age=31536000; SameSite=Lax';
            if (value !== currentLayoutFromBody()) { window.location.reload(); }
            return;
        }
        if (ATTRS.indexOf(key) < 0 && !DENSITY[key]) { return; }
        var saved = readStore();
        saved[key] = value;
        writeStore(saved);
        applyAttrs(saved);
        emit();
    }

    function currentLayoutFromBody() {
        var layout = document.body ? document.body.getAttribute('bp-layout') : '';
        return layout ? layout.replace('-', '_') : 'horizontal';
    }

    function reset() {
        writeStore({});
        applyAttrs({});
        set('mode', 'system');
        set('layout', 'horizontal');
    }

    function token(name, fallback) {
        var value = window.getComputedStyle(document.body || root).getPropertyValue(name);
        return value ? value.trim() : (fallback || '');
    }

    function onChange(fn) {
        if (typeof fn === 'function') { listeners.push(fn); }
        return fn;
    }

    /* ---- Appearance panel: inputs carry data-xl-theme="primary|base|font|radius|text|space|mode|layout" ---- */
    function syncPanel(state) {
        state = state || get();
        document.querySelectorAll('[data-xl-theme]').forEach(function (input) {
            var key = input.getAttribute('data-xl-theme');
            if (input.type === 'radio') { input.checked = String(state[key] || '') === input.value; }
        });
    }

    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || !input.matches || !input.matches('[data-xl-theme]')) { return; }
        set(input.getAttribute('data-xl-theme'), input.value);
    });

    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('[data-xl-theme-reset]') : null;
        if (button) { event.preventDefault(); reset(); }
    });

    /* Anything that flips the theme attributes (Backpack's mode button, the OS scheme, another tab) notifies listeners. */
    new MutationObserver(emit).observe(root, {
        attributes: true,
        attributeFilter: ['data-bs-theme', 'data-bs-theme-primary', 'data-bs-theme-base', 'data-bs-theme-font', 'data-bs-theme-radius', 'data-xl-text', 'data-xl-space'],
    });

    window.addEventListener('storage', function (event) {
        if (event.key === STORE) { applyAttrs(readStore()); }
        if (event.key === 'colorMode' && window.colorMode) { window.colorMode.set(event.newValue || 'system'); }
    });

    XL.theme = { get: get, set: set, reset: reset, onChange: onChange, token: token, layouts: LAYOUTS };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { syncPanel(); });
    } else {
        syncPanel();
    }
})();
