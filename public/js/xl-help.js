/*!
 * Xceler8 F1 help (DEC-094, W16b): F1, Shift+? or the "?" top-bar button opens a right-side pane with the help article
 * for the current screen (route name from <meta name="xl-help">). Esc, the close button or F1 again closes it; focus moves
 * into the pane and back. Nothing is loaded until the first open. Also drives the Help centre search box.
 * Page tours (W16c): the article's `tour` steps run with Driver.js — from the pane's "Take the tour" button or `?tour=1`;
 * steps whose element is not on the page are skipped. A dot on ? marks an article changed since this browser last opened it.
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
            '<div class="xl-help-foot d-flex justify-content-between align-items-center gap-2"><a href="' + esc(cfg.centre) + '">' + esc(t.centre) + '</a>' +
            (cfg.support ? '<button type="button" class="btn btn-sm btn-primary" data-xl-support-open><i class="la la-life-ring me-1" aria-hidden="true"></i>' + esc(cfg.support.labels.still_need_help) + '</button>' : '') +
            '</div>';
        document.body.appendChild(pane);
        pane.querySelector('[data-xl-help-close]').addEventListener('click', close);
        var supportBtn = pane.querySelector('[data-xl-support-open]');
        if (supportBtn) { supportBtn.addEventListener('click', supportForm); }
        bindSearch(pane.querySelector('.xl-help-search input'));
    }

    var panePromise = null;

    function fetchPane() {
        if (!panePromise) {
            panePromise = getJson(cfg.pane + '?route=' + encodeURIComponent(cfg.route || ''))
                .catch(function (err) { panePromise = null; return Promise.reject(err); });
        }
        return panePromise;
    }

    /** Tour steps whose element is on the page and visible, in Driver.js shape (pure; exposed for checks). */
    function tourSteps(tour, root) {
        root = root || document;
        return (tour || []).map(function (step) {
            var el = null;
            try { el = step && step.element ? root.querySelector(step.element) : null; } catch (e) { el = null; }   // bad selector = skipped
            if (!el || !(el.offsetParent !== null || el.getClientRects().length)) { return null; }
            return { element: el, popover: { title: String(step.title || ''), description: String(step.text || '') } };
        }).filter(Boolean);
    }

    function runTour(data) {
        var make = window.driver && window.driver.js && window.driver.js.driver;
        var steps = tourSteps(data && data.tour);
        if (!make || !steps.length) {
            if (pane && pane.classList.contains('show')) {
                var note = pane.querySelector('[data-xl-tour-note]');
                if (note) { note.textContent = t.tour_empty || ''; }
            }
            return;
        }
        close();
        make({ showProgress: true, nextBtnText: t.tour_next, prevBtnText: t.tour_prev, doneBtnText: t.tour_done, steps: steps }).drive();
    }

    function markSeen() {
        if (!cfg.article) { return; }
        try { localStorage.setItem('xl.help.seen.' + cfg.article.key, cfg.article.updated || '1'); } catch (e) { /* storage blocked */ }
        Array.prototype.forEach.call(document.querySelectorAll('[data-xl-help-open]'), function (b) { b.classList.remove('xl-help-new'); });
    }

    function load() {
        if (loaded) { return; }
        loaded = true;
        var body = pane.querySelector('.xl-help-body');
        fetchPane().then(function (data) {
            pane.querySelector('#xl-help-title').textContent = data.title || t.title;
            if (data.missing) {
                body.innerHTML = '<div class="alert alert-info mb-0">' + esc(data.message) + '</div>';
                return;
            }
            var tourBtn = (data.tour || []).length
                ? '<div class="mb-3"><button type="button" class="btn btn-sm btn-outline-primary" data-xl-tour-start><i class="la la-route me-1" aria-hidden="true"></i>' + esc(t.take_tour) + '</button>' +
                  ' <span class="small text-body-secondary ms-2" data-xl-tour-note></span></div>'
                : '';
            body.innerHTML = tourBtn + '<div class="xl-help-article">' + data.html + '</div>' +   // server-rendered, raw HTML escaped
                '<div class="small text-body-secondary mt-3">' + esc(data.updated || '') + ' · <a href="' + esc(data.url) + '">' + esc(t.open_full) + '</a></div>';
            var start = body.querySelector('[data-xl-tour-start]');
            if (start) { start.addEventListener('click', function () { runTour(data); }); }
            markSeen();
            document.dispatchEvent(new CustomEvent('xl:help-loaded', { detail: data }));
        }).catch(function () {
            loaded = false;
            body.innerHTML = '<div class="alert alert-warning mb-0">' + esc(t.missing) + '</div>';
        });
    }

    // ---------------- "Still need help?" support request (W16e)
    var shot = null;

    function supportForm() {
        var L = cfg.support.labels;
        var body = pane.querySelector('.xl-help-body');
        var cats = Object.keys(L.categories || {}).map(function (code) {
            return '<option value="' + esc(code) + '">' + esc(L.categories[code]) + '</option>';
        }).join('');
        body.innerHTML =
            '<form data-xl-support-form novalidate>' +
            '<h3 class="h4">' + esc(L.title) + '</h3>' +
            '<div class="mb-2"><label class="form-label" for="xl-sup-cat">' + esc(L.what) + '</label><select id="xl-sup-cat" name="category" class="form-select" required>' + cats + '</select></div>' +
            '<div class="mb-2"><label class="form-check"><input type="checkbox" class="form-check-input" name="urgent"> <span class="form-check-label">' + esc(L.urgent) + '</span></label></div>' +
            '<div class="mb-2"><label class="form-label" for="xl-sup-subj">' + esc(L.subject) + '</label><input id="xl-sup-subj" name="subject" class="form-control" maxlength="200" required value="' + esc(document.title) + '"></div>' +
            '<div class="mb-2"><label class="form-label" for="xl-sup-desc">' + esc(L.description) + '</label><textarea id="xl-sup-desc" name="description" class="form-control" rows="4" maxlength="5000" required></textarea></div>' +
            '<div class="mb-2"><label class="form-check"><input type="checkbox" class="form-check-input" name="diagnostics" checked> <span class="form-check-label small">' + esc(L.diagnostics) + '</span></label></div>' +
            '<div class="mb-2" data-xl-shot hidden><label class="form-check"><input type="checkbox" class="form-check-input" name="with_screenshot" checked> <span class="form-check-label small">' + esc(L.screenshot) + '</span></label>' +
            '<img alt="" class="img-fluid border rounded mt-1" data-xl-shot-img></div>' +
            '<div class="small mb-2" data-xl-support-msg aria-live="polite"></div>' +
            '<div class="d-flex gap-2"><button type="submit" class="btn btn-primary">' + esc(L.send) + '</button>' +
            '<button type="button" class="btn btn-outline-secondary" data-xl-support-cancel>' + esc(L.cancel) + '</button></div>' +
            '</form>';
        var form = body.querySelector('form');
        form.querySelector('[data-xl-support-cancel]').addEventListener('click', function () { loaded = false; load(); });
        form.addEventListener('submit', function (e) { e.preventDefault(); sendSupport(form); });
        form.querySelector('[name=description]').focus();
        if (window.XL && window.XL.diag && window.XL.diag.screenshot) {
            window.XL.diag.screenshot().then(function (dataUrl) {
                shot = dataUrl;
                var box = form.querySelector('[data-xl-shot]');
                box.querySelector('[data-xl-shot-img]').src = dataUrl;
                box.hidden = false;
            }).catch(function (err) {
                // BUG-232: never fail silently — tell the user, and record why in the diagnostics (errors.json)
                shot = null;
                var box = form.querySelector('[data-xl-shot]');
                box.innerHTML = '<div class="small text-warning">' + esc(L.screenshot_failed) + '</div>';
                box.hidden = false;
                if (window.console && window.console.error) { window.console.error('Support screenshot failed: ' + (err && err.message ? err.message : String(err))); }
            });
        }
    }

    function sendSupport(form) {
        var L = cfg.support.labels;
        var msg = form.querySelector('[data-xl-support-msg]');
        var submit = form.querySelector('[type=submit]');
        if (!form.description.value.trim() || !form.subject.value.trim()) { msg.className = 'small mb-2 text-danger'; msg.textContent = L.description; return; }
        var withDiag = form.diagnostics.checked;
        var payload = {
            category: form.category.value, urgent: form.urgent.checked, subject: form.subject.value, description: form.description.value,
            route: cfg.route || null, diagnostics: withDiag,
            snapshot: withDiag && window.XL && window.XL.diag ? window.XL.diag.snapshot() : null,
            screenshot: withDiag && shot && form.with_screenshot && form.with_screenshot.checked ? shot : null,
        };
        submit.disabled = true;
        msg.className = 'small mb-2 text-body-secondary';
        msg.textContent = L.sending;
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        fetch(cfg.support.store, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); }).then(function (res) {
            if (res.ok && res.body.ok) {
                form.innerHTML = '<div class="alert alert-success">' + esc(res.body.message) + '</div>' +
                    '<a class="btn btn-sm btn-outline-primary" href="' + esc(res.body.data.url) + '">' + esc(res.body.data.number) + '</a> ' +
                    '<a class="btn btn-sm btn-link" href="' + esc(cfg.support.list) + '">' + esc(L.my_requests) + '</a>';
                shot = null;
                return;
            }
            var errors = res.body && res.body.errors ? Object.keys(res.body.errors).map(function (k) { return res.body.errors[k][0]; }) : [];
            msg.className = 'small mb-2 text-danger';
            msg.textContent = errors[0] || (res.body && res.body.message) || L.failed;
            submit.disabled = false;
        }).catch(function () {
            msg.className = 'small mb-2 text-danger';
            msg.textContent = L.failed;
            submit.disabled = false;
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
        if (cfg.article) {
            var seen = null;
            try { seen = localStorage.getItem('xl.help.seen.' + cfg.article.key); } catch (e) { seen = null; }
            if (seen !== (cfg.article.updated || '1')) {
                Array.prototype.forEach.call(document.querySelectorAll('[data-xl-help-open]'), function (b) {
                    b.classList.add('xl-help-new');
                    b.setAttribute('title', (b.getAttribute('title') || '') + ' — ' + (t['new'] || ''));
                });
            }
            if (cfg.article.tour && /[?&]tour=1(&|$)/.test(window.location.search)) {
                fetchPane().then(function (data) { markSeen(); runTour(data); }).catch(function () { /* no tour */ });
            }
        }
    });

    window.XL = window.XL || {};
    window.XL.help = { open: open, close: close, tourSteps: tourSteps, support: function () { open(); supportForm(); } };
})();
