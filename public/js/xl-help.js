/*!
 * Xceler8 F1 help (DEC-094, W16b): F1, Shift+? or the "?" top-bar button opens a right-side pane with the help article
 * for the current screen (route name from <meta name="xl-help">). Esc, the close button or F1 again closes it; focus moves
 * into the pane and back. Nothing is loaded until the first open. Also drives the Help centre search box.
 */
(function () {
    'use strict';
    var meta = document.querySelector('meta[name="xl-help"]');
    var cfg = {};
    try { cfg = meta ? JSON.parse(meta.getAttribute('content')) : {}; } catch (e) { cfg = {}; }
    var t = cfg.labels || {};
    var pane = null;
    var loaded = false;
    var opener = null;

    function getJson(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); });
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function results(box, url, query, empty) {
        if (query.trim().length < 2) { box.innerHTML = ''; return; }
        getJson(url + '?q=' + encodeURIComponent(query)).then(function (data) {
            box.innerHTML = (data.results || []).length
                ? data.results.map(function (r) {
                    return '<a class="list-group-item list-group-item-action" href="' + esc(r.url) + '"><div class="fw-bold">' + esc(r.title) + '</div><div class="small text-body-secondary">' + esc(r.snippet) + '</div></a>';
                }).join('')
                : '<div class="list-group-item text-body-secondary">' + esc(empty) + '</div>';
        }).catch(function () { box.innerHTML = ''; });
    }

    function bindSearch(input) {
        var box = input.parentNode.querySelector('[data-xl-help-results]');
        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { results(box, input.getAttribute('data-url'), input.value, input.getAttribute('data-empty')); }, 250);
        });
    }

    function build() {
        pane = document.createElement('aside');
        pane.className = 'xl-help-pane';
        pane.setAttribute('role', 'dialog');
        pane.setAttribute('aria-modal', 'false');
        pane.setAttribute('aria-labelledby', 'xl-help-title');
        pane.setAttribute('tabindex', '-1');
        pane.innerHTML =
            '<div class="xl-help-head"><h2 id="xl-help-title" class="h3 m-0">' + esc(t.title) + '</h2>' +
            '<button type="button" class="btn-close" data-xl-help-close aria-label="' + esc(t.close) + '"></button></div>' +
            '<div class="xl-help-search"><input type="search" class="form-control form-control-sm" placeholder="' + esc(t.search) + '"' +
            ' data-url="' + esc(cfg.search) + '" data-empty="' + esc(t.no_results) + '" aria-label="' + esc(t.search) + '">' +
            '<div class="list-group list-group-flush" data-xl-help-results></div></div>' +
            '<div class="xl-help-body" aria-live="polite"><div class="text-body-secondary">…</div></div>' +
            '<div class="xl-help-foot"><a href="' + esc(cfg.centre) + '">' + esc(t.centre) + '</a></div>';
        document.body.appendChild(pane);
        pane.querySelector('[data-xl-help-close]').addEventListener('click', close);
        bindSearch(pane.querySelector('.xl-help-search input'));
    }

    function load() {
        if (loaded) { return; }
        loaded = true;
        var body = pane.querySelector('.xl-help-body');
        getJson(cfg.pane + '?route=' + encodeURIComponent(cfg.route || '')).then(function (data) {
            pane.querySelector('#xl-help-title').textContent = data.title || t.title;
            if (data.missing) {
                body.innerHTML = '<div class="alert alert-info mb-0">' + esc(data.message) + '</div>';
                return;
            }
            body.innerHTML = '<div class="xl-help-article">' + data.html + '</div>' +   // server-rendered, raw HTML escaped
                '<div class="small text-body-secondary mt-3">' + esc(data.updated || '') + ' · <a href="' + esc(data.url) + '">' + esc(t.open_full) + '</a></div>';
            document.dispatchEvent(new CustomEvent('xl:help-loaded', { detail: data }));   // tours hook in here (W16c)
        }).catch(function () {
            loaded = false;
            body.innerHTML = '<div class="alert alert-warning mb-0">' + esc(t.missing) + '</div>';
        });
    }

    function open() {
        if (!cfg.pane) { return; }
        if (!pane) { build(); }
        opener = document.activeElement;
        pane.classList.add('show');
        document.documentElement.classList.add('xl-help-open');
        load();
        pane.focus();
    }

    function close() {
        if (!pane || !pane.classList.contains('show')) { return; }
        pane.classList.remove('show');
        document.documentElement.classList.remove('xl-help-open');
        if (opener && opener.focus) { opener.focus(); }
    }

    function toggle() { if (pane && pane.classList.contains('show')) { close(); } else { open(); } }

    function typing(el) {
        return el && (el.isContentEditable || /^(input|textarea|select)$/i.test(el.tagName));
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'F1') { e.preventDefault(); toggle(); return; }
        if (e.key === 'Escape' && pane && pane.classList.contains('show')) { close(); return; }
        if (e.key === '?' && e.shiftKey && !typing(e.target)) { e.preventDefault(); toggle(); }
    });
    window.addEventListener('help', function (e) { e.preventDefault(); });   // old IE / Edge F1 help

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-xl-help-open]');
        if (btn) { e.preventDefault(); toggle(); }
    });

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-xl-help-search]'), bindSearch);
    });

    window.XL = window.XL || {};
    window.XL.help = { open: open, close: close };
})();
