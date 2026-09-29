/*!
 * Xceler8 UI layer (DEC-066, .ai/rules/ui.md) — loaded on every admin page.
 *
 * Progressive enhancement, so every screen follows the UI standards without per-view code:
 *  - dates:   <input type="date|datetime-local"> and [data-xl-date] → flatpickr showing the SITE format
 *             (meta xl-date-format), while the submitted value stays ISO (Y-m-d / Y-m-d H:i).
 *  - selects: <select multiple> and [data-xl="select2"] → Select2 (searchable, clearable, chips).
 *  - uploads: <input type="file"> → drop-zone with previews, remove-before-upload, type/size checks
 *             from Settings, and (with data-xl-upload-url) AJAX upload with per-file progress + errors.
 *  - tables:  bare <table class="table"> gets a .table-responsive wrapper (phones scroll, page doesn't).
 *  - cards:   .card with a .card-header inside a <form> → collapsible, draggable (order remembered per screen) with a
 *             required filled / total badge (to-do U1).
 *  - images:  <img> without a loading attribute → loading="lazy" decoding="async" (to-do U4).
 * Opt out per element or container with data-xl="off". Everything is idempotent; content added later
 * (AJAX, modals) is enhanced by a MutationObserver.
 *
 * Helpers for page scripts: XL.formatDate(value, withTime), XL.enhance(root).
 */
(function () {
    'use strict';

    const meta = (name, fallback) => document.querySelector(`meta[name="${name}"]`)?.content || fallback;
    const XL = (window.XL = window.XL || {});
    XL.dateFormat = meta('xl-date-format', 'd-M-Y');
    XL.dateTimeFormat = meta('xl-datetime-format', XL.dateFormat + ' H:i');
    XL.uploadMaxKb = parseInt(meta('xl-upload-max-kb', '10240'), 10) || 10240;
    XL.uploadTypes = meta('xl-upload-types', '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);

    // ── Date formatting (PHP tokens, same as the server) ─────────────────────────────────────────
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const pad = (n) => String(n).padStart(2, '0');

    /** Parse ISO-ish strings as LOCAL time (new Date('2026-09-28') would be UTC midnight). */
    XL.parseDate = function (value) {
        if (value instanceof Date) return isNaN(value) ? null : value;
        if (value === null || value === undefined || value === '') return null;
        const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (m) return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0));
        const d = new Date(value);
        return isNaN(d) ? null : d;
    };

    XL.formatDate = function (value, withTime, fallback) {
        const d = XL.parseDate(value);
        if (!d) return fallback !== undefined ? fallback : value || '';
        const h = d.getHours();
        const tokens = {
            d: pad(d.getDate()), j: d.getDate(), D: DAYS[d.getDay()].slice(0, 3), l: DAYS[d.getDay()],
            m: pad(d.getMonth() + 1), n: d.getMonth() + 1, M: MONTHS[d.getMonth()].slice(0, 3), F: MONTHS[d.getMonth()],
            Y: d.getFullYear(), y: String(d.getFullYear()).slice(2), H: pad(h), G: h, h: pad(h % 12 || 12), g: h % 12 || 12,
            i: pad(d.getMinutes()), s: pad(d.getSeconds()), A: h < 12 ? 'AM' : 'PM', a: h < 12 ? 'am' : 'pm',
        };
        return (withTime ? XL.dateTimeFormat : XL.dateFormat).replace(/\\?([a-zA-Z])/g, (all, t) => (all[0] === '\\' ? t : tokens[t] !== undefined ? tokens[t] : t));
    };

    const isOff = (el) => el.dataset.xl === 'off' || !!el.closest('[data-xl="off"]');
    const inGrid = (el) => !!el.closest('.ag-root-wrapper, .ag-theme-quartz, .ag-theme-alpine, .ag-theme-balham');

    // ── Dates ───────────────────────────────────────────────────────────────────────────────────
    function enhanceDates(root) {
        if (!window.flatpickr) return;
        root.querySelectorAll('input[type="date"], input[type="datetime-local"], input[data-xl-date]').forEach((el) => {
            if (el._flatpickr || isOff(el) || inGrid(el)) return;
            const native = el.getAttribute('type');
            const time = native === 'datetime-local' || el.dataset.xlDate === 'datetime';
            const valueFormat = time ? (native === 'datetime-local' ? 'Y-m-d\\TH:i' : 'Y-m-d H:i') : 'Y-m-d';
            el.setAttribute('type', 'text');
            el.setAttribute('autocomplete', 'off');
            window.flatpickr(el, {
                allowInput: true,
                altInput: true,
                altFormat: time ? XL.dateTimeFormat : XL.dateFormat,
                altInputClass: el.className + ' xl-date',
                dateFormat: valueFormat,
                enableTime: time,
                time_24hr: true,
                minDate: el.getAttribute('min') || el.dataset.min || null,
                maxDate: el.getAttribute('max') || el.dataset.max || null,
                disableMobile: true, // native mobile pickers ignore the site format
                onReady: (d, s, fp) => {
                    fp.altInput.placeholder = el.getAttribute('placeholder') || (time ? XL.formatDate(new Date(2026, 8, 28, 14, 30), true) : XL.formatDate(new Date(2026, 8, 28)));
                    fp.altInput.required = el.required;
                    fp.altInput.disabled = el.disabled;
                    fp.altInput.readOnly = el.readOnly;
                    if (el.id) {
                        document.querySelectorAll(`label[for="${CSS.escape(el.id)}"]`).forEach((label) => {
                            fp.altInput.id = el.id + '_display';
                            label.setAttribute('for', fp.altInput.id);
                        });
                    }
                },
            });
        });
    }

    // ── Select2 ─────────────────────────────────────────────────────────────────────────────────
    function enhanceSelects(root) {
        const $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2) return;
        root.querySelectorAll('select[multiple], select[data-xl="select2"]').forEach((el) => {
            if (isOff(el) || inGrid(el) || el.classList.contains('select2-hidden-accessible')) return;
            // a select the page hides on purpose (driven by its own custom UI) must stay hidden (DEC-068)
            if (el.hidden || el.style.display === 'none') return;
            const modal = el.closest('.modal');
            const options = {
                width: '100%',
                placeholder: el.dataset.placeholder || el.getAttribute('placeholder') || (el.multiple ? 'Select one or more…' : 'Select…'),
                allowClear: !el.required,
                closeOnSelect: !el.multiple,
                dropdownParent: modal ? $(modal) : $(document.body),
                dropdownCssClass: 'xl-select2-dropdown',
                selectionCssClass: 'xl-select2-selection' + (el.classList.contains('form-select-sm') ? ' xl-select2-sm' : ''),
            };
            if (el.dataset.xlSource) {
                options.ajax = { url: el.dataset.xlSource, dataType: 'json', delay: 250, data: (p) => ({ q: p.term, page: p.page || 1 }) };
                options.minimumInputLength = 1;
            }
            $(el).select2(options);
        });
    }

    // ── Drop-zone uploads ───────────────────────────────────────────────────────────────────────
    const human = (bytes) => (bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB');
    const extOf = (name) => (name.includes('.') ? name.split('.').pop().toLowerCase() : '');
    const ICONS = { pdf: 'la-file-pdf', doc: 'la-file-word', docx: 'la-file-word', xls: 'la-file-excel', xlsx: 'la-file-excel', csv: 'la-file-csv', mp3: 'la-file-audio', wav: 'la-file-audio', mp4: 'la-file-video', zip: 'la-file-archive', json: 'la-file-code' };
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    function acceptRules(input) {
        const accept = (input.getAttribute('accept') || '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
        return accept.length ? accept : XL.uploadTypes.map((t) => '.' + t.replace(/^\./, ''));
    }

    function check(file, input) {
        const maxKb = parseInt(input.dataset.maxKb || XL.uploadMaxKb, 10);
        if (file.size > maxKb * 1024) return `larger than ${human(maxKb * 1024)}`;
        const rules = acceptRules(input);
        if (!rules.length) return null;
        const ext = '.' + extOf(file.name);
        const type = (file.type || '').toLowerCase();
        const ok = rules.some((r) => (r.startsWith('.') ? r === ext : r.endsWith('/*') ? type.startsWith(r.slice(0, -1)) : r === type));
        return ok ? null : 'this file type is not allowed';
    }

    function buildDropzone(input) {
        input.dataset.xlDone = '1';
        const multiple = input.multiple;
        const ajaxUrl = input.dataset.xlUploadUrl || '';
        const zone = document.createElement('div');
        zone.className = 'xl-dropzone';
        const rules = acceptRules(input);
        const hint = [rules.length ? rules.map((r) => r.replace(/^\./, '')).join(', ') : null, 'max ' + human(parseInt(input.dataset.maxKb || XL.uploadMaxKb, 10) * 1024)].filter(Boolean).join(' · ');
        zone.innerHTML =
            `<div class="xl-dropzone-target" tabindex="0" role="button" aria-label="${multiple ? 'Choose files' : 'Choose a file'}">
                <span class="xl-dropzone-icon"><i class="la la-cloud-upload-alt"></i></span>
                <span class="xl-dropzone-text"><strong>Drop ${multiple ? 'files' : 'a file'} here</strong> or <span class="xl-link">browse</span></span>
                <span class="xl-dropzone-hint">${esc(hint)}</span>
            </div>
            <ul class="xl-dropzone-list" aria-live="polite"></ul>
            ${ajaxUrl ? '<div class="xl-dropzone-actions"><button type="button" class="btn btn-primary btn-sm xl-dropzone-send" disabled><i class="la la-upload me-1"></i>Upload</button></div>' : ''}`;
        input.parentNode.insertBefore(zone, input);
        zone.appendChild(input);
        input.classList.add('xl-dropzone-input');
        input.tabIndex = -1;

        const target = zone.querySelector('.xl-dropzone-target');
        const list = zone.querySelector('.xl-dropzone-list');
        const send = zone.querySelector('.xl-dropzone-send');
        let store = new DataTransfer();
        let messages = [];

        const sync = () => {
            try { input.files = store.files; } catch (e) { /* very old browsers: keep the native selection */ }
            render();
            input.dispatchEvent(new CustomEvent('xl:files', { bubbles: true, detail: { files: store.files } }));
        };
        const add = (files) => {
            messages = [];
            if (!multiple) store = new DataTransfer();
            Array.from(files).forEach((file, index) => {
                if (!multiple && index > 0) { messages.push(`${file.name}: only one file can be attached here`); return; }
                const problem = check(file, input);
                if (problem) { messages.push(`${file.name}: ${problem}`); return; }
                const duplicate = Array.from(store.files).some((f) => f.name === file.name && f.size === file.size);
                if (!duplicate) store.items.add(file);
            });
            sync();
        };
        const remove = (index) => {
            const next = new DataTransfer();
            Array.from(store.files).forEach((f, i) => { if (i !== index) next.items.add(f); });
            store = next;
            messages = [];
            sync();
        };
        function render() {
            list.innerHTML = '';
            Array.from(store.files).forEach((file, i) => {
                const li = document.createElement('li');
                li.className = 'xl-dropzone-item';
                const isImage = (file.type || '').startsWith('image/');
                const thumb = isImage ? `<img src="${URL.createObjectURL(file)}" alt="">` : `<i class="la ${ICONS[extOf(file.name)] || 'la-file-alt'}"></i>`;
                li.innerHTML = `<span class="xl-dropzone-thumb">${thumb}</span>
                    <span class="xl-dropzone-meta"><span class="xl-dropzone-name" title="${esc(file.name)}">${esc(file.name)}</span><span class="xl-dropzone-size">${human(file.size)}</span>
                    <span class="xl-dropzone-progress" hidden><span></span></span><span class="xl-dropzone-error"></span></span>
                    <button type="button" class="btn btn-icon btn-ghost-danger xl-dropzone-remove" aria-label="Remove ${esc(file.name)}"><i class="la la-times"></i></button>`;
                li.querySelector('.xl-dropzone-remove').addEventListener('click', () => remove(i));
                list.appendChild(li);
            });
            messages.forEach((m) => {
                const li = document.createElement('li');
                li.className = 'xl-dropzone-item is-rejected';
                li.innerHTML = `<i class="la la-exclamation-circle"></i><span>${esc(m)}</span>`;
                list.appendChild(li);
            });
            zone.classList.toggle('has-files', store.files.length > 0);
            if (send) send.disabled = store.files.length === 0;
        }

        target.addEventListener('click', () => input.click());
        target.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('is-dragover'); }));
        ['dragleave', 'dragend'].forEach((t) => zone.addEventListener(t, (e) => { if (!zone.contains(e.relatedTarget)) zone.classList.remove('is-dragover'); }));
        zone.addEventListener('drop', (e) => { e.preventDefault(); zone.classList.remove('is-dragover'); if (e.dataTransfer?.files?.length) add(e.dataTransfer.files); });
        input.addEventListener('change', () => {
            if (input._xlSyncing) return;
            const picked = Array.from(input.files || []);
            if (multiple) { const keep = store; store = new DataTransfer(); Array.from(keep.files).forEach((f) => store.items.add(f)); }
            add(picked);
        });
        const form = input.form;
        if (form && !ajaxUrl) {
            form.addEventListener('submit', () => { if (store.files.length) zone.classList.add('is-uploading'); });
        }
        if (send) send.addEventListener('click', () => ajaxUpload(zone, input, store, ajaxUrl, list, () => { store = new DataTransfer(); sync(); }));
    }

    /** AJAX mode: one request per file, per-file progress and server errors; fields from data-xl-upload-fields (JSON). */
    function ajaxUpload(zone, input, store, url, list, done) {
        const files = Array.from(store.files);
        const token = meta('csrf-token', '');
        const extra = input.dataset.xlUploadFields ? JSON.parse(input.dataset.xlUploadFields) : {};
        const items = list.querySelectorAll('.xl-dropzone-item:not(.is-rejected)');
        zone.classList.add('is-uploading');
        let failed = 0;
        const one = (file, i) => new Promise((resolve) => {
            const item = items[i];
            const bar = item?.querySelector('.xl-dropzone-progress');
            const error = item?.querySelector('.xl-dropzone-error');
            if (bar) bar.hidden = false;
            const body = new FormData();
            Object.entries(extra).forEach(([k, v]) => body.append(k, v));
            body.append(input.name || 'file', file);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.setRequestHeader('X-CSRF-TOKEN', token);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => { if (bar && e.lengthComputable) bar.firstElementChild.style.width = Math.round((e.loaded / e.total) * 100) + '%'; };
            xhr.onload = () => {
                let res = {};
                try { res = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
                const ok = xhr.status < 300 && res.ok !== false;
                if (!ok) {
                    failed++;
                    item?.classList.add('is-failed');
                    if (error) error.textContent = res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : 'Upload failed');
                } else {
                    item?.classList.add('is-done');
                }
                resolve();
            };
            xhr.onerror = () => { failed++; item?.classList.add('is-failed'); if (error) error.textContent = 'Network error'; resolve(); };
            xhr.send(body);
        });
        files.reduce((p, f, i) => p.then(() => one(f, i)), Promise.resolve()).then(() => {
            zone.classList.remove('is-uploading');
            zone.dispatchEvent(new CustomEvent('xl:uploaded', { bubbles: true, detail: { failed, total: files.length } }));
            if (failed === 0) {
                if (input.dataset.xlReload !== undefined) window.location.reload();
                else setTimeout(done, 800);
            }
        });
    }

    function enhanceUploads(root) {
        if (typeof DataTransfer === 'undefined') return;
        root.querySelectorAll('input[type="file"]').forEach((input) => {
            if (input.dataset.xlDone || isOff(input) || inGrid(input) || input.closest('.xl-dropzone')) return;
            buildDropzone(input);
        });
    }

    // ── Responsive tables ───────────────────────────────────────────────────────────────────────
    function wrapTables(root) {
        root.querySelectorAll('table.table').forEach((table) => {
            if (isOff(table) || table.id === 'crudTable' || table.closest('.table-responsive, .dataTables_wrapper, .dt-container, .ag-root-wrapper, .dropdown-menu')) return;
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            table.parentNode.insertBefore(wrap, table);
            wrap.appendChild(table);
        });
    }

    // ── Form cards (to-do U1): collapse, drag to reorder, required filled / total ─────────────────
    // Every .card with a .card-header inside a <form> gets a grip (drag, or Alt+↑/↓ on it), a collapse chevron and a
    // "filled / total" badge for its required fields. Order and collapsed cards are remembered per screen (record ids
    // in the URL are ignored, so every edit page of an entity shares one layout) in localStorage "xl.cards:{path}".
    const CARD_STORE = 'xl.cards:' + location.pathname.replace(/\/\d+(?=\/|$)/g, '/#');
    const readCards = () => { try { return JSON.parse(localStorage.getItem(CARD_STORE) || '{}') || {}; } catch (e) { return {}; } };
    const writeCards = (state) => { try { localStorage.setItem(CARD_STORE, JSON.stringify(state)); } catch (e) { /* private mode */ } };
    const cardsOf = (parent) => Array.from(parent.children).filter((el) => el.classList.contains('xl-card'));
    const groupKey = (parent) => parent.id || 'g' + Array.from(document.querySelectorAll('.xl-card-group')).indexOf(parent);

    function cardKey(card, used) {
        const header = card.querySelector(':scope > .card-header');
        const title = (header.querySelector('.card-title') || header).textContent || 'card';
        let key = title.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40) || 'card';
        let n = 1;
        while (used.has(key)) key = key.replace(/~\d+$/, '') + '~' + ++n;
        used.add(key);
        return key;
    }

    /** Required fields of a card: Backpack's `.form-group.required` wrappers plus any [required] control outside one. */
    function requiredCount(card) {
        const controls = (scope) => Array.from(scope.querySelectorAll('input, select, textarea'))
            .filter((c) => !c.disabled && c.name !== '_token' && c.name !== '_method');
        const filled = (list) => list.some((c) => (c.type === 'checkbox' || c.type === 'radio') ? c.checked
            : (c.multiple ? c.selectedOptions.length > 0 : String(c.value || '').trim() !== ''));
        let total = 0, done = 0;
        const wrappers = Array.from(card.querySelectorAll('.form-group.required, [bp-field-wrapper].required'));
        wrappers.forEach((w) => { const list = controls(w); if (!list.length) return; total++; if (filled(list)) done++; });
        const seen = new Set();
        card.querySelectorAll('[required]').forEach((c) => {
            if (c.disabled || wrappers.some((w) => w.contains(c))) return;
            const key = (c.type === 'radio' || c.type === 'checkbox') && c.name ? c.name : c;
            if (seen.has(key)) return;
            seen.add(key);
            total++;
            const group = typeof key === 'string' ? Array.from(card.querySelectorAll(`[name="${CSS.escape(key)}"]`)) : [c];
            if (filled(group)) done++;
        });
        return { total, done };
    }

    function refreshCount(card) {
        const badge = card.querySelector(':scope > .card-header .xl-req');
        if (!badge) return;
        const { total, done } = requiredCount(card);
        badge.hidden = total === 0;
        badge.textContent = done + '/' + total;
        badge.className = 'badge xl-req ' + (done >= total ? 'bg-success-lt' : 'bg-warning-lt');
        badge.title = `Required fields filled: ${done} of ${total}`;
    }

    function saveOrder(parent) {
        const state = readCards();
        state.order = state.order || {};
        state.order[groupKey(parent)] = cardsOf(parent).map((c) => c.dataset.xlCard);
        writeCards(state);
    }

    function setCollapsed(card, collapsed, save) {
        card.classList.toggle('xl-collapsed', collapsed);
        card.querySelector(':scope > .card-header .xl-card-toggle')?.setAttribute('aria-expanded', String(!collapsed));
        if (!save) return;
        const state = readCards();
        const list = new Set(state.collapsed || []);
        collapsed ? list.add(card.dataset.xlCard) : list.delete(card.dataset.xlCard);
        state.collapsed = Array.from(list);
        writeCards(state);
    }

    /** Put the cards of one parent in the saved order; other children (buttons, hidden inputs) keep their places. */
    function restoreOrder(parent, saved) {
        const cards = cardsOf(parent);
        if (!saved || cards.length < 2) return;
        const rank = (c) => { const i = saved.indexOf(c.dataset.xlCard); return i < 0 ? saved.length + cards.indexOf(c) : i; };
        const sorted = cards.slice().sort((a, b) => rank(a) - rank(b));
        if (sorted.every((c, i) => c === cards[i])) return;
        const slots = cards.map((c) => { const mark = document.createComment('xl'); parent.insertBefore(mark, c); return mark; });
        slots.forEach((mark, i) => { parent.insertBefore(sorted[i], mark); mark.remove(); });
    }

    let dragged = null;
    function wireDrag(card, handle) {
        handle.addEventListener('pointerdown', () => { card.draggable = true; });
        handle.addEventListener('pointerup', () => { card.draggable = false; });
        card.addEventListener('dragstart', (e) => {
            if (!card.draggable) return;
            e.stopPropagation();
            dragged = card;
            card.classList.add('xl-dragging');
            e.dataTransfer.effectAllowed = 'move';
            // a private type: Firefox needs data to start a drag, and inputs never accept it as dropped text
            try { e.dataTransfer.setData('application/x-xl-card', card.dataset.xlCard); } catch (err) { /* old browsers */ }
        });
        card.addEventListener('dragover', (e) => {
            if (!dragged || dragged === card || dragged.parentNode !== card.parentNode) return;
            e.preventDefault();
            const box = card.getBoundingClientRect();
            card.parentNode.insertBefore(dragged, e.clientY > box.top + box.height / 2 ? card.nextSibling : card);
        });
        card.addEventListener('drop', (e) => { if (dragged) e.preventDefault(); });
        card.addEventListener('dragend', () => {
            card.draggable = false;
            card.classList.remove('xl-dragging');
            if (dragged) saveOrder(dragged.parentNode);
            dragged = null;
        });
        handle.addEventListener('keydown', (e) => {
            if (!e.altKey || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
            e.preventDefault();
            const siblings = cardsOf(card.parentNode);
            const target = siblings[siblings.indexOf(card) + (e.key === 'ArrowUp' ? -1 : 1)];
            if (!target) return;
            card.parentNode.insertBefore(card, e.key === 'ArrowUp' ? target : target.nextSibling);
            saveOrder(card.parentNode);
            handle.focus();
        });
    }

    function enhanceCards(root) {
        const scope = (root.matches && root.matches('.card') ? [root] : []).concat(Array.from(root.querySelectorAll('form .card')));
        const fresh = scope.filter((card) => !card.dataset.xlCard && card.closest('form') && !isOff(card)
            && card.querySelector(':scope > .card-header') && !inGrid(card));
        if (fresh.length) {
            const state = readCards();
            const collapsed = new Set(state.collapsed || []);
            const used = new Set(Array.from(document.querySelectorAll('[data-xl-card]')).map((c) => c.dataset.xlCard));
            const parents = new Set();
            fresh.forEach((card) => {
                card.dataset.xlCard = cardKey(card, used);
                card.classList.add('xl-card');
                card.parentNode.classList.add('xl-card-group');
                parents.add(card.parentNode);
                const header = card.querySelector(':scope > .card-header');
                const tools = document.createElement('div');
                tools.className = 'xl-card-tools';
                tools.innerHTML = '<span class="badge xl-req" hidden></span>'
                    + '<button type="button" class="btn btn-ghost-secondary btn-sm xl-card-toggle" aria-expanded="true" title="Collapse / expand"><i class="la la-angle-down"></i></button>';
                const actions = header.querySelector(':scope > .card-actions');
                actions ? actions.prepend(tools) : header.appendChild(tools);
                tools.querySelector('.xl-card-toggle').addEventListener('click', () => setCollapsed(card, !card.classList.contains('xl-collapsed'), true));
                if (collapsed.has(card.dataset.xlCard)) setCollapsed(card, true, false);
                refreshCount(card);
            });
            parents.forEach((parent) => {
                const siblings = cardsOf(parent);
                if (siblings.length < 2) return;
                restoreOrder(parent, (state.order || {})[groupKey(parent)]);
                siblings.forEach((card) => {
                    const header = card.querySelector(':scope > .card-header');
                    if (header.querySelector(':scope > .xl-card-handle')) return;
                    const handle = document.createElement('button');
                    handle.type = 'button';
                    handle.className = 'btn btn-ghost-secondary btn-sm xl-card-handle';
                    handle.title = 'Drag to reorder (or Alt + ↑ / ↓)';
                    handle.setAttribute('aria-label', 'Move card');
                    handle.innerHTML = '<i class="la la-grip-vertical"></i>';
                    header.prepend(handle);
                    wireDrag(card, handle);
                });
            });
        }
        // content added inside an existing card (repeatable rows, AJAX) → recount it
        const host = root.closest && root.closest('.xl-card');
        if (host) refreshCount(host);
    }

    // one listener for every card: recount on edits; open a collapsed card when the browser flags an invalid field
    // in it (a hidden invalid field would otherwise block the submit silently)
    let recount = null;
    const queueRecount = (e) => {
        const card = e.target.closest && e.target.closest('.xl-card');
        if (!card) return;
        recount = recount || new Set();
        recount.add(card);
        requestAnimationFrame(() => { if (recount) { recount.forEach(refreshCount); recount = null; } });
    };
    document.addEventListener('input', queueRecount, true);
    document.addEventListener('change', queueRecount, true);
    document.addEventListener('invalid', (e) => {
        let card = e.target.closest && e.target.closest('.xl-collapsed');
        while (card) { setCollapsed(card, false, false); card = card.parentElement && card.parentElement.closest('.xl-collapsed'); }
    }, true);

    // ── Images (to-do U4): lazy + async decode unless the markup says otherwise ────────────────────
    function enhanceImages(root) {
        root.querySelectorAll('img:not([loading])').forEach((img) => {
            img.loading = 'lazy';
            if (!img.hasAttribute('decoding')) img.decoding = 'async';
        });
    }

    XL.enhance = function (root) {
        root = root || document;
        [enhanceDates, enhanceSelects, enhanceUploads, wrapTables, enhanceCards, enhanceImages].forEach((fn) => {
            try { fn(root); } catch (e) { /* one broken widget must not stop the others */ }
        });
    };

    function start() {
        // grids created before this file loaded: wrap late AG-Grid loads and re-render date cells
        if (window.agGrid && XL.wrapAgGrid && !window.agGrid.__xl) window.agGrid = XL.wrapAgGrid(window.agGrid);
        // Select2 announces changes through jQuery only, which native listeners never see
        if (window.jQuery) window.jQuery(document).on('change', 'select', queueRecount);
        (XL.grids || []).forEach((api) => { try { api && api.refreshCells && api.refreshCells({ force: true }); } catch (e) { /* destroyed grid */ } });
        XL.enhance(document);
        let queued = [];
        let timer = null;
        new MutationObserver((mutations) => {
            mutations.forEach((m) => m.addedNodes.forEach((n) => {
                if (n.nodeType === 1 && !inGrid(n) && !n.closest('.select2-container, .flatpickr-calendar, .xl-dropzone')) queued.push(n);
            }));
            if (queued.length && !timer) {
                timer = setTimeout(() => {
                    const nodes = queued; queued = []; timer = null;
                    nodes.forEach((n) => n.isConnected && XL.enhance(n.parentNode || n));
                }, 150);
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    // run after page scripts (which usually initialise on DOMContentLoaded / jQuery ready) so we skip
    // anything they already enhanced
    if (document.readyState === 'complete') start();
    else window.addEventListener('load', start);
})();
