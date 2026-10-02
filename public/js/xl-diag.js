/*!
 * Xceler8 diagnostics collector (DEC-094, W16d; FRS help-and-support §5.2). Keeps, per browser tab (sessionStorage), the
 * last 50 actions (page loads, clicks with their label and selector, form submits with field NAMES only, alerts shown),
 * the last 50 AJAX / fetch calls (method, URL without secrets, status, time, size, a masked error body for 4xx / 5xx)
 * and JavaScript errors. It never records what a user types. XL.diag.snapshot() returns it all plus page facts;
 * XL.diag.screenshot() captures the page with html2canvas (loaded on demand) after blanking password / OTP fields and
 * fields marked `data-xl-sensitive`. The support request (W16e) sends both to the server.
 */
(function () {
    'use strict';
    var meta = document.querySelector('meta[name="xl-diag"]');
    var cfg = {};
    try { cfg = meta ? JSON.parse(meta.getAttribute('content')) : {}; } catch (e) { cfg = {}; }
    var KEY = 'xl.diag';
    var MAX = 50;
    var SECRET = /^(_?token|password|otp|signature|code|key|secret|api_key|access_token)$/i;

    /** Masks personal data the same way as the server (DiagnosticsService::mask). */
    function mask(text) {
        return String(text == null ? '' : text)
            .replace(/(^|[^\w-])(\d{4})[\s-]?(\d{4})[\s-]?(\d{4})(?![\w-])/g, function (m, pre, a, b, c) { return pre + 'XXXXXXXX' + c; })
            .replace(/\b[A-Z]{5}\d{4}[A-Z]\b/g, function (m) { return 'XXXXXX' + m.slice(-4); })
            .replace(/(^|[^\w])((?:\+?91[\s-]?)?[6-9]\d{9})(?![\w])/g, function (m, pre, n) { return pre + 'XXXXXX' + n.slice(-4); })
            .replace(/\b([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/g, '$1***@$2')
            .replace(/\b[A-Za-z0-9_\-]{32,}\b/g, '[token]');
    }

    function cleanUrl(url) {
        try {
            var u = new URL(url, window.location.href);
            u.searchParams.forEach(function (v, k) { if (SECRET.test(k)) { u.searchParams.set(k, '[removed]'); } });
            return mask(u.pathname + (u.search ? decodeURIComponent(u.search) : ''));
        } catch (e) { return mask(url); }
    }

    function read() {
        try { return JSON.parse(sessionStorage.getItem(KEY)) || { actions: [], network: [], errors: [] }; } catch (e) { return { actions: [], network: [], errors: [] }; }
    }

    function push(kind, entry) {
        var data = read();
        entry.at = new Date().toISOString();
        data[kind] = (data[kind] || []).concat([entry]).slice(-MAX);
        try { sessionStorage.setItem(KEY, JSON.stringify(data)); } catch (e) { /* storage full or blocked */ }
    }

    function label(el) {
        var text = (el.getAttribute('aria-label') || el.getAttribute('title') || el.innerText || el.value || '').trim();
        return mask(text.replace(/\s+/g, ' ').slice(0, 80));
    }

    function selector(el) {
        if (el.id) { return '#' + el.id; }
        if (el.getAttribute('data-xl-tour')) { return '[data-xl-tour=' + el.getAttribute('data-xl-tour') + ']'; }
        if (el.getAttribute('name')) { return el.tagName.toLowerCase() + '[name=' + el.getAttribute('name') + ']'; }
        var cls = (el.className && typeof el.className === 'string') ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
        return el.tagName.toLowerCase() + cls;
    }

    // -------- actions
    push('actions', { type: 'load', url: cleanUrl(window.location.href), title: document.title });
    document.addEventListener('click', function (e) {
        var el = e.target.closest && e.target.closest('a, button, [role=button], input[type=submit], input[type=button], .nav-link, .dropdown-item');
        if (el) { push('actions', { type: 'click', label: label(el), target: selector(el) }); }
    }, true);
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var names = Array.prototype.map.call(form.elements || [], function (f) { return f.name; })
            .filter(function (n) { return n && n !== '_token'; });
        push('actions', { type: 'submit', form: selector(form), action: cleanUrl(form.getAttribute('action') || ''), fields: names.filter(function (n, i) { return names.indexOf(n) === i; }) });
    }, true);
    if (window.MutationObserver) {
        new MutationObserver(function (records) {
            records.forEach(function (r) {
                Array.prototype.forEach.call(r.addedNodes, function (n) {
                    if (n.nodeType === 1 && n.matches && n.matches('.alert, .invalid-feedback, .noty_body, .toast')) {
                        var text = (n.innerText || '').trim();
                        if (text) { push('actions', { type: 'message', text: mask(text.slice(0, 200)) }); }
                    }
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    // -------- errors
    window.addEventListener('error', function (e) {
        push('errors', { message: mask(e.message || 'error'), source: cleanUrl(e.filename || ''), line: e.lineno || null });
    });
    window.addEventListener('unhandledrejection', function (e) {
        push('errors', { message: mask(String(e.reason && e.reason.message || e.reason || 'unhandled rejection')).slice(0, 300) });
    });
    var consoleError = window.console && window.console.error;
    if (consoleError) {
        window.console.error = function () {
            try { push('errors', { message: mask(Array.prototype.map.call(arguments, String).join(' ')).slice(0, 300), source: 'console' }); } catch (e) { /* ignore */ }
            return consoleError.apply(this, arguments);
        };
    }

    // -------- network (fetch + XHR; jQuery uses XHR)
    function net(method, url, status, started, size, body) {
        var entry = { method: (method || 'GET').toUpperCase(), url: cleanUrl(url), status: status, ms: Math.round(performance.now() - started), size: size };
        if (status >= 400 && body) { entry.error = mask(String(body).slice(0, 2048)); }
        push('network', entry);
    }
    if (window.fetch) {
        var origFetch = window.fetch;
        window.fetch = function (input, init) {
            var started = performance.now();
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            var method = (init && init.method) || (input && input.method) || 'GET';
            return origFetch.apply(this, arguments).then(function (res) {
                if (res.status >= 400) {
                    res.clone().text().then(function (b) { net(method, url, res.status, started, b.length, b); }).catch(function () { net(method, url, res.status, started, null); });
                } else {
                    net(method, url, res.status, started, Number(res.headers.get('content-length')) || null);
                }
                return res;
            }, function (err) { net(method, url, 0, started, null, String(err)); throw err; });
        };
    }
    var open = XMLHttpRequest.prototype.open;
    var send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) { this._xlDiag = { method: method, url: url }; return open.apply(this, arguments); };
    XMLHttpRequest.prototype.send = function () {
        var xhr = this;
        var started = performance.now();
        xhr.addEventListener('loadend', function () {
            if (!xhr._xlDiag) { return; }
            var body = null;
            try { body = xhr.status >= 400 && (xhr.responseType === '' || xhr.responseType === 'text') ? xhr.responseText : null; } catch (e) { body = null; }
            net(xhr._xlDiag.method, xhr._xlDiag.url, xhr.status, started, (xhr.responseText || '').length || null, body);
        });
        return send.apply(this, arguments);
    };

    // -------- snapshot + screenshot
    function snapshot() {
        var data = read();
        var html = document.documentElement;
        return {
            page: {
                url: cleanUrl(window.location.href), route: cfg.route || null, title: document.title,
                screen: window.screen.width + 'x' + window.screen.height, viewport: window.innerWidth + 'x' + window.innerHeight,
                browser: navigator.userAgent, time: new Date().toISOString(), version: cfg.version || null,
                theme: html.getAttribute('data-bs-theme') || null,
            },
            actions: data.actions || [], network: data.network || [], errors: data.errors || [],
        };
    }

    function loadHtml2canvas() {
        if (window.html2canvas) { return Promise.resolve(window.html2canvas); }
        if (!cfg.html2canvas) { return Promise.reject(new Error('html2canvas unavailable')); }
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = cfg.html2canvas;
            s.onload = function () { return window.html2canvas ? resolve(window.html2canvas) : reject(new Error('html2canvas missing')); };
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    /** Overlays that must never be in a support screenshot: the help pane and any backdrop / lightbox. */
    var OVERLAYS = '.xl-help-pane, .modal-backdrop, .offcanvas-backdrop, .dropdown-menu.show, .tooltip, .popover, [data-xl-capture-hide]';

    /**
     * JPEG data URL of the base page as it looks without the help pane (owner 03-10). html2canvas copies the page
     * synchronously when it is called, so the overlays are hidden only for that call and restored straight after — the
     * user never sees a flicker, and the copy holds neither the pane nor a backdrop. In the copy only, password / OTP
     * inputs and `data-xl-sensitive` fields are blanked.
     */
    function capture(h2c) {
        var root = document.documentElement;
        var hidden = Array.prototype.map.call(document.querySelectorAll(OVERLAYS), function (el) {
            var before = el.style.getPropertyValue('display');
            var priority = el.style.getPropertyPriority('display');
            el.style.setProperty('display', 'none', 'important');
            return [el, before, priority];
        });
        var hadOpen = root.classList.contains('xl-help-open');
        root.classList.remove('xl-help-open');
        var job;
        try {
            job = h2c(document.body, {
                logging: false, useCORS: true, scale: Math.min(window.devicePixelRatio || 1, 1.5),
                x: window.scrollX, y: window.scrollY, width: window.innerWidth, height: window.innerHeight,
                ignoreElements: function (el) { return !!(el.matches && el.matches(OVERLAYS)); },
                onclone: function (doc) {
                    // The copy re-runs the theme's fade-in animations from near-transparent (a washed-out page in the
                    // owner's Firefox capture, 03-10): show everything at its final state, then wait for the fonts.
                    var still = doc.createElement('style');
                    still.textContent = '*, *::before, *::after { animation: none !important; transition: none !important; }';
                    doc.head.appendChild(still);
                    doc.documentElement.classList.remove('xl-help-open');
                    Array.prototype.forEach.call(doc.querySelectorAll(OVERLAYS), function (el) { el.remove(); });
                    Array.prototype.forEach.call(doc.querySelectorAll('input[type=password], input[name*=otp i], input[autocomplete=one-time-code], [data-xl-sensitive], [data-xl-sensitive] input'), function (el) {
                        if ('value' in el) { el.value = ''; el.setAttribute('value', ''); }
                        el.style.background = '#999';
                        el.style.color = 'transparent';
                    });
                    return doc.fonts && doc.fonts.ready ? doc.fonts.ready.then(function () { return undefined; }) : undefined;   // html2canvas awaits this
                },
            });
        } finally {
            hidden.forEach(function (h) {
                if (h[1]) { h[0].style.setProperty('display', h[1], h[2]); } else { h[0].style.removeProperty('display'); }
            });
            if (hadOpen) { root.classList.add('xl-help-open'); }
        }
        return job.then(function (canvas) { return canvas.toDataURL('image/jpeg', 0.8); });   // JPEG keeps the upload small
    }

    function screenshot() {
        return loadHtml2canvas().then(capture);
    }

    window.XL = window.XL || {};
    window.XL.diag = { snapshot: snapshot, screenshot: screenshot, mask: mask, cleanUrl: cleanUrl };
})();
