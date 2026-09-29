/*!
 * Xceler8 idle guard (go-live to-do S1 / S2): tracks real user activity (keys, clicks, pointer, scroll, touch — shared
 * across tabs), sends a heartbeat while active, locks the screen after the idle-lock time and signs out after the
 * idle-logout time with a warning first. Config comes from <meta name="xl-idle">; the server enforces the same limits
 * (EnforceIdleSession), so closing this script never bypasses them.
 */
(function () {
    'use strict';
    var meta = document.querySelector('meta[name="xl-idle"]');
    if (!meta) { return; }
    var cfg;
    try { cfg = JSON.parse(meta.getAttribute('content')); } catch (e) { return; }
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || cfg.csrf;
    var KEY = 'xl.idle.last';
    var HEARTBEAT_MS = 60 * 1000;
    var lastLocal = Date.now();
    var lastBeat = 0;
    var warned = false;
    var modal = null;

    function lastActive() {
        var shared = 0;
        try { shared = parseInt(localStorage.getItem(KEY) || '0', 10) || 0; } catch (e) { /* storage blocked */ }
        return Math.max(lastLocal, shared);
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-XL-Activity': '1', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {}),
        });
    }

    function activity() {
        lastLocal = Date.now();
        try { localStorage.setItem(KEY, String(lastLocal)); } catch (e) { /* storage blocked */ }
        if (warned) { hideWarning(); }
        if (lastLocal - lastBeat > HEARTBEAT_MS) {
            lastBeat = lastLocal;
            post(cfg.activity).then(function (r) {
                if (r.status === 401) { window.location.href = cfg.login; }
                return r.ok ? r.json() : null;
            }).then(function (s) {
                if (s && s.locked) { window.location.href = cfg.lockScreen + '?to=' + encodeURIComponent(window.location.href); }
            }).catch(function () { /* offline: the server decides at the next request */ });
        }
    }

    var throttled = false;
    ['keydown', 'mousedown', 'click', 'wheel', 'touchstart', 'scroll', 'mousemove'].forEach(function (evt) {
        window.addEventListener(evt, function () {
            if (throttled) { return; }
            throttled = true;
            setTimeout(function () { throttled = false; }, 2000);
            activity();
        }, { passive: true, capture: true });
    });

    function lockNow(idle) {
        post(cfg.lockUrl, { idle: idle ? 1 : 0, to: window.location.href })
            .then(function (r) { return r.json(); })
            .then(function (s) { window.location.href = s.redirect || cfg.lockScreen; })
            .catch(function () { window.location.href = cfg.lockScreen; });
    }

    function showWarning(seconds) {
        warned = true;
        if (!modal) {
            modal = document.createElement('div');
            modal.className = 'modal modal-blur fade show d-block';
            modal.setAttribute('role', 'alertdialog');
            modal.setAttribute('aria-live', 'assertive');
            modal.innerHTML = '<div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center py-4">'
                + '<i class="la la-hourglass-half la-3x text-warning mb-2" aria-hidden="true"></i><h3>Still there?</h3>'
                + '<div class="text-body-secondary">You will be signed out in <strong class="xl-idle-count"></strong> s.</div></div>'
                + '<div class="modal-footer"><button type="button" class="btn btn-primary w-100 xl-idle-stay">Stay signed in</button></div></div></div>';
            modal.querySelector('.xl-idle-stay').addEventListener('click', function () { lastBeat = 0; activity(); });
            document.body.appendChild(modal);
        }
        modal.querySelector('.xl-idle-count').textContent = String(Math.max(0, seconds));
    }

    function hideWarning() {
        warned = false;
        if (modal) { modal.remove(); modal = null; }
    }

    setInterval(function () {
        var idle = (Date.now() - lastActive()) / 1000;
        if (cfg.logout > 0) {
            var left = Math.round(cfg.logout * 60 - idle);
            if (left <= 0) { window.location.href = cfg.logoutUrl; return; }
            if (left <= cfg.warning) { showWarning(left); } else if (warned) { hideWarning(); }
        }
        if (cfg.lock > 0 && idle >= cfg.lock * 60) { lockNow(true); }
    }, 1000);

    document.querySelectorAll('[data-xl-lock]').forEach(function (el) {
        el.addEventListener('click', function (e) { e.preventDefault(); lockNow(false); });
    });

    activity();
})();
