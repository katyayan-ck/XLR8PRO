/**
 * RbacTree — cascading Module -> Process -> Permission checkbox tree.
 *
 * Rules implemented (per spec):
 *  - Checking a Module auto-checks every Process and Permission under it.
 *  - Unchecking a Module auto-unchecks AND disables every Process/Permission under it.
 *  - Same rule one level down: checking/unchecking a Process cascades to its Permissions.
 *  - Individual modules/processes/permissions can still be checked/unchecked independently
 *    when their ancestors are checked — parents show an indeterminate (dash) state when only
 *    some of their children are selected.
 *  - "Select all / Select none" buttons exist at the Module and Process level.
 *  - In "override" mode (user-level screen), each permission's visual state is derived purely
 *    from (checked vs. whether it's in the user's base role) — no separate state machine needed:
 *      checked && inRole   -> inherited (blue)
 *      checked && !inRole  -> added (green)
 *      !checked && inRole  -> removed (red, struck through)
 *      !checked && !inRole -> not granted (default)
 */
(function (window, document) {
    'use strict';

    function RbacTreeController(root, opts) {
        this.root = root;
        this.mode = (opts && opts.mode) || 'role'; // 'role' | 'override'
        this.bind();
    }

    RbacTreeController.prototype.bind = function () {
        var self = this;

        this.root.addEventListener('change', function (e) {
            var cb = e.target;
            if (!cb.classList || !cb.classList.contains('rbac-check')) return;

            if (cb.classList.contains('rbac-check-module')) {
                self.cascadeModule(self.moduleEl(cb.dataset.code), cb.checked);
            } else if (cb.classList.contains('rbac-check-process')) {
                self.cascadeProcess(self.processEl(cb.dataset.module, cb.dataset.code), cb.checked);
                self.refreshModuleState(self.moduleEl(cb.dataset.module));
            } else if (cb.classList.contains('rbac-check-perm')) {
                var processEl = self.processEl(cb.dataset.module, cb.dataset.process);
                self.refreshProcessState(processEl);
                self.refreshModuleState(self.moduleEl(cb.dataset.module));
            }

            self.updateCounts();
            if (self.mode === 'override') self.refreshOverrideVisuals();
            self.onChange && self.onChange();
        });

        this.root.addEventListener('click', function (e) {
            var caret = e.target.closest('.rbac-caret');
            if (caret) {
                var row = caret.closest('.rbac-module, .rbac-process');
                row.classList.toggle('collapsed');
                return;
            }

            var selectAllBtn = e.target.closest('.rbac-select-all');
            if (selectAllBtn) {
                self.handleSelectButton(selectAllBtn, true);
                return;
            }

            var selectNoneBtn = e.target.closest('.rbac-select-none');
            if (selectNoneBtn) {
                self.handleSelectButton(selectNoneBtn, false);
            }
        });
    };

    RbacTreeController.prototype.handleSelectButton = function (btn, checked) {
        var scope = btn.dataset.scope;
        var el = scope === 'module'
            ? this.moduleEl(btn.dataset.code)
            : this.processEl(btn.dataset.module, btn.dataset.code);

        if (scope === 'module') {
            this.cascadeModule(el, checked);
        } else {
            this.cascadeProcess(el, checked);
            this.refreshModuleState(this.moduleEl(btn.dataset.module));
        }

        this.updateCounts();
        if (this.mode === 'override') this.refreshOverrideVisuals();
        this.onChange && this.onChange();
    };

    RbacTreeController.prototype.moduleEl = function (code) {
        return this.root.querySelector('.rbac-module[data-code="' + cssEscape(code) + '"]');
    };

    RbacTreeController.prototype.processEl = function (moduleCode, processCode) {
        return this.root.querySelector(
            '.rbac-process[data-module="' + cssEscape(moduleCode) + '"][data-code="' + cssEscape(processCode) + '"]'
        );
    };

    // ---- Cascade (direct user action on a parent checkbox / select-all button) ----

    RbacTreeController.prototype.cascadeModule = function (moduleEl, checked) {
        var moduleCheckbox = moduleEl.querySelector(':scope > .rbac-row-module .rbac-check-module');
        moduleCheckbox.checked = checked;
        moduleCheckbox.indeterminate = false;

        moduleEl.querySelectorAll('.rbac-check-process').forEach(function (cb) {
            cb.disabled = !checked;
            cb.checked = checked;
            cb.indeterminate = false;
        });
        moduleEl.querySelectorAll('.rbac-check-perm').forEach(function (cb) {
            cb.disabled = !checked;
            cb.checked = checked;
        });
    };

    RbacTreeController.prototype.cascadeProcess = function (processEl, checked) {
        var processCheckbox = processEl.querySelector(':scope > .rbac-row-process .rbac-check-process');
        processCheckbox.checked = checked;
        processCheckbox.indeterminate = false;

        processEl.querySelectorAll('.rbac-check-perm').forEach(function (cb) {
            cb.disabled = !checked;
            cb.checked = checked;
        });
    };

    // ---- Derived state (children changed individually -> reflect on parent, never disables) ----

    RbacTreeController.prototype.refreshProcessState = function (processEl) {
        var processCheckbox = processEl.querySelector(':scope > .rbac-row-process .rbac-check-process');
        if (processCheckbox.disabled) return; // parent module is off; nothing to derive

        var perms = Array.prototype.slice.call(processEl.querySelectorAll('.rbac-check-perm'));
        var checkedCount = perms.filter(function (cb) { return cb.checked; }).length;

        if (checkedCount === 0) {
            processCheckbox.checked = false;
            processCheckbox.indeterminate = false;
        } else if (checkedCount === perms.length) {
            processCheckbox.checked = true;
            processCheckbox.indeterminate = false;
        } else {
            processCheckbox.checked = false;
            processCheckbox.indeterminate = true;
        }
    };

    RbacTreeController.prototype.refreshModuleState = function (moduleEl) {
        var moduleCheckbox = moduleEl.querySelector(':scope > .rbac-row-module .rbac-check-module');
        if (moduleCheckbox.disabled) return;

        var processes = Array.prototype.slice.call(moduleEl.querySelectorAll('.rbac-check-process'));
        var fullyChecked = processes.filter(function (cb) { return cb.checked && !cb.indeterminate; }).length;
        var anySelected = processes.some(function (cb) { return cb.checked || cb.indeterminate; });

        if (fullyChecked === processes.length) {
            moduleCheckbox.checked = true;
            moduleCheckbox.indeterminate = false;
        } else if (!anySelected) {
            moduleCheckbox.checked = false;
            moduleCheckbox.indeterminate = false;
        } else {
            moduleCheckbox.checked = false;
            moduleCheckbox.indeterminate = true;
        }
    };

    // ---- Counts ----

    RbacTreeController.prototype.updateCounts = function () {
        var self = this;
        this.root.querySelectorAll('.rbac-process').forEach(function (processEl) {
            var perms = processEl.querySelectorAll('.rbac-check-perm');
            var checked = Array.prototype.filter.call(perms, function (cb) { return cb.checked; }).length;
            var badge = self.root.querySelector('[data-count-for="' + cssEscape(processEl.dataset.module) + '.' + cssEscape(processEl.dataset.code) + '"]');
            if (badge) badge.textContent = checked + '/' + perms.length;
        });
        this.root.querySelectorAll('.rbac-module').forEach(function (moduleEl) {
            var perms = moduleEl.querySelectorAll('.rbac-check-perm');
            var checked = Array.prototype.filter.call(perms, function (cb) { return cb.checked; }).length;
            var badge = self.root.querySelector('[data-count-for="' + cssEscape(moduleEl.dataset.code) + '"]');
            if (badge) badge.textContent = checked + '/' + perms.length;
        });
    };

    // ---- Public: apply a flat permission-code set (role mode) ----

    RbacTreeController.prototype.applyPermissionSet = function (codes) {
        var set = new Set(codes);
        this.root.querySelectorAll('.rbac-check').forEach(function (cb) { cb.disabled = false; });
        this.root.querySelectorAll('.rbac-check-perm').forEach(function (cb) {
            cb.checked = set.has(cb.dataset.code);
        });
        this.deriveAllParents();
        this.updateCounts();
    };

    // ---- Public: apply role-base + overrides (user-override mode) ----

    RbacTreeController.prototype.applyOverride = function (baseCodes, added, removed) {
        var base = new Set(baseCodes);
        var addedSet = new Set(added || []);
        var removedSet = new Set(removed || []);

        this.root.querySelectorAll('.rbac-check').forEach(function (cb) { cb.disabled = false; });
        this.root.querySelectorAll('.rbac-check-perm').forEach(function (cb) {
            var code = cb.dataset.code;
            var inRole = base.has(code);
            cb.dataset.inRole = inRole ? '1' : '0';
            cb.checked = (inRole && !removedSet.has(code)) || addedSet.has(code);
        });

        this.deriveAllParents();
        this.updateCounts();
        this.refreshOverrideVisuals();
    };

    RbacTreeController.prototype.resetToBase = function () {
        this.root.querySelectorAll('.rbac-check-perm').forEach(function (cb) {
            cb.checked = cb.dataset.inRole === '1';
        });
        this.deriveAllParents();
        this.updateCounts();
        this.refreshOverrideVisuals();
        this.onChange && this.onChange();
    };

    RbacTreeController.prototype.deriveAllParents = function () {
        var self = this;
        this.root.querySelectorAll('.rbac-process').forEach(function (p) { self.refreshProcessState(p); });
        this.root.querySelectorAll('.rbac-module').forEach(function (m) { self.refreshModuleState(m); });
    };

    RbacTreeController.prototype.refreshOverrideVisuals = function () {
        this.root.querySelectorAll('.rbac-row-perm').forEach(function (row) {
            var cb = row.querySelector('.rbac-check-perm');
            var inRole = cb.dataset.inRole === '1';
            var checked = cb.checked;
            var badge = row.querySelector('.rbac-state-badge');

            row.classList.remove('ov-inherited', 'ov-added', 'ov-removed');

            if (checked && inRole) {
                row.classList.add('ov-inherited');
                if (badge) badge.textContent = 'Inherited';
            } else if (checked && !inRole) {
                row.classList.add('ov-added');
                if (badge) badge.textContent = '+ Added';
            } else if (!checked && inRole) {
                row.classList.add('ov-removed');
                if (badge) badge.textContent = '\u2212 Removed';
            } else if (badge) {
                badge.textContent = '';
            }
        });
    };

    // ---- Public: read back current state ----

    RbacTreeController.prototype.getCheckedCodes = function () {
        return Array.prototype.map.call(
            this.root.querySelectorAll('.rbac-check-perm:checked'),
            function (cb) { return cb.dataset.code; }
        );
    };

    RbacTreeController.prototype.getOverrideDiff = function () {
        var added = [];
        var removed = [];
        this.root.querySelectorAll('.rbac-check-perm').forEach(function (cb) {
            var inRole = cb.dataset.inRole === '1';
            if (cb.checked && !inRole) added.push(cb.dataset.code);
            if (!cb.checked && inRole) removed.push(cb.dataset.code);
        });
        return { added: added, removed: removed };
    };

    RbacTreeController.prototype.expandAll = function (expand) {
        this.root.querySelectorAll('.rbac-module, .rbac-process').forEach(function (el) {
            el.classList.toggle('collapsed', !expand);
        });
    };

    function cssEscape(value) {
        return String(value).replace(/["\\]/g, '\\$&');
    }

    window.RbacTree = {
        init: function (root, opts) {
            return new RbacTreeController(root, opts);
        }
    };
})(window, document);
