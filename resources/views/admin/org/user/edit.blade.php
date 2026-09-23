@extends(backpack_view('blank'))

@section('title', 'Edit User - ' . $user->username)

@push('after_styles')
<style>
    :root { --rbac-inherited: #0d6efd; --rbac-added: #198754; --rbac-removed: #dc3545; }
    .card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,.08); }
    .form-control:focus, .form-select:focus { border-color: #80bdff; box-shadow: 0 0 0 .2rem rgba(0,123,255,.25); }
    .hidden-card { display: none; }
    #orgChangeBlock { display: none; border: 1px dashed #fd7e14; border-radius: 8px; padding: 1rem; background: #fff8f0; }

    .rbac-tree { background: #fff; border: 1px solid #dee2e6; border-radius: .5rem; overflow: hidden; }
    .rbac-module { border-bottom: 1px solid #eee; }
    .rbac-module:last-child { border-bottom: none; }
    .rbac-row { display: flex; align-items: center; gap: .5rem; padding: .5rem .9rem; }
    .rbac-row-module { background: #f8f9fb; font-size: .95rem; }
    .rbac-row-process { background: #fcfcfd; padding-left: 2.2rem; font-size: .875rem; border-top: 1px solid #f1f1f1; }
    .rbac-row-perm { padding-left: 4.4rem; font-size: .825rem; border-top: 1px dashed #f3f3f3; }
    .rbac-row-perm:hover { background: #fafbff; }
    .rbac-caret { border: none; background: none; padding: 0 .25rem; color: #6c757d; cursor: pointer; width: 1.2rem; }
    .rbac-caret i { transition: transform .15s ease; display: inline-block; }
    .rbac-module.collapsed > .rbac-module-body { display: none; }
    .rbac-module.collapsed > .rbac-row-module .rbac-caret i { transform: rotate(-90deg); }
    .rbac-process.collapsed > .rbac-process-body { display: none; }
    .rbac-process.collapsed > .rbac-row-process .rbac-caret i { transform: rotate(-90deg); }
    .rbac-label { flex: 1; margin: 0; cursor: pointer; }
    .rbac-label-module { font-weight: 600; }
    .rbac-label-process { font-weight: 500; color: #333; }
    .rbac-perm-code { margin-left: .5rem; font-size: .7rem; color: #adb5bd; }
    .rbac-count { font-weight: 500; }
    .rbac-row-actions { display: flex; gap: .25rem; }
    .rbac-row-actions .btn-link { text-decoration: none; padding: 0 .35rem; font-size: .75rem; }
    .rbac-check { width: 1.05rem; height: 1.05rem; cursor: pointer; flex-shrink: 0; }
    .rbac-check:disabled { cursor: not-allowed; opacity: .35; }
    .rbac-state-badge { font-size: .68rem; font-weight: 600; padding: .05rem .4rem; border-radius: .75rem; display: none; }
    .ov-added .rbac-label-perm { color: var(--rbac-added); }
    .ov-added .rbac-state-badge { display: inline-block; background: #d1e7dd; color: var(--rbac-added); }
    .ov-removed .rbac-label-perm { color: var(--rbac-removed); text-decoration: line-through; }
    .ov-removed .rbac-state-badge { display: inline-block; background: #f8d7da; color: var(--rbac-removed); }
    .ov-inherited .rbac-state-badge { display: inline-block; background: #cfe2ff; color: var(--rbac-inherited); }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header text-black d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Edit User — {{ $user->username }}</h2>
                    <div class="d-flex gap-2">
                        <a href="{{ backpack_url('org/user/'.$user->id.'/show') }}" class="btn btn-outline-primary btn-sm">
                            <i class="la la-eye"></i> View
                        </a>
                        <a href="{{ backpack_url('org/user') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="la la-arrow-left"></i> Back to list
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ backpack_url('org/user/'.$user->id) }}" id="userForm">
                        @csrf
                        @method('PUT')

                        <h5 class="mb-3">Person</h5>
                        <div class="mb-3 p-3 border rounded">
                            <div class="fw-bold">{{ $person?->display_name ?: $person?->full_name ?: '—' }}</div>
                            <div class="small text-muted">
                                {{ $person?->person_code }} · {{ $person?->primary_mobile }} · {{ $person?->primary_email }}
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3">Account</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label>User Type <span class="text-danger">*</span></label>
                                <select name="user_type_code" id="user_type_code" class="form-select" required>
                                    <option value="">Select</option>
                                    @foreach ($userTypes as $type)
                                        <option value="{{ $type->code }}" @selected(old('user_type_code', $employee ? 'emp' : '') === $type->code)>{{ $type->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Date of Joining</label>
                                <input type="date" name="date_of_joining" class="form-control" value="{{ old('date_of_joining', $employee?->joining_date?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Username / Display Name <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" value="{{ old('username', $user->username) }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" minlength="8" placeholder="Leave blank to keep current">
                            </div>
                        </div>

                        <div class="row" id="nonEmployeeRoleRow">
                            <div class="col-md-4 mb-3">
                                <label>Role</label>
                                <select name="role_id" class="form-select">
                                    <option value="">— no role —</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected((string) old('role_id', $user->roles->first()?->id) === (string) $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>

                        <div id="orgCard" class="hidden-card">
                            <hr class="my-4">
                            <h5 class="mb-1">Organisation</h5>
                            <p class="text-muted small">Changing any of these requires a reason and effective date — recorded in Employment History.</p>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Designation (Role) <span class="text-danger">*</span></label>
                                    <select name="designation_code" id="designation_code" class="form-select org-field" data-original="{{ $employee?->designation_code }}">
                                        <option value="">Select</option>
                                        @foreach ($designations as $d)
                                            <option value="{{ $d->code }}" @selected(old('designation_code', $employee?->designation_code) === $d->code)>{{ $d->name }} ({{ $d->rank_label }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Vertical</label>
                                    <select name="vertical_code" class="form-select org-field" data-original="{{ $employee?->vertical_code }}">
                                        <option value="">Select</option>
                                        @foreach ($verticals as $v)
                                            <option value="{{ $v->code }}" @selected(old('vertical_code', $employee?->vertical_code) === $v->code)>{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Branch <span class="text-danger">*</span></label>
                                    <select name="primary_branch_code" id="primary_branch_code" class="form-select org-field" data-original="{{ $employee?->primary_branch_code }}">
                                        <option value="">Select</option>
                                        @foreach ($branches as $b)
                                            <option value="{{ $b->code }}" @selected(old('primary_branch_code', $employee?->primary_branch_code) === $b->code)>{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Branches</label>
                                    <select name="addon_branch_codes[]" id="addon_branch_codes" class="form-select org-field-multi" multiple size="4">
                                        @foreach ($branches as $b)
                                            <option value="{{ $b->code }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Location <span class="text-danger">*</span></label>
                                    <select name="primary_loc_code" id="primary_loc_code" class="form-select org-field"></select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Locations</label>
                                    <select name="addon_loc_codes[]" id="addon_loc_codes" class="form-select org-field-multi" multiple size="4"></select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Department <span class="text-danger">*</span></label>
                                    <select name="primary_dept_code" id="primary_dept_code" class="form-select org-field" data-original="{{ $employee?->primary_dept_code }}">
                                        <option value="">Select</option>
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->code }}" @selected(old('primary_dept_code', $employee?->primary_dept_code) === $dept->code)>{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Departments</label>
                                    <select name="addon_dept_codes[]" id="addon_dept_codes" class="form-select org-field-multi" multiple size="4">
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->code }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Division <span class="text-danger">*</span></label>
                                    <select name="primary_div_code" id="primary_div_code" class="form-select org-field"></select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Divisions</label>
                                    <select name="addon_div_codes[]" id="addon_div_codes" class="form-select org-field-multi" multiple size="4"></select>
                                </div>
                            </div>
                        </div>

                        <div id="vehicleCard" class="hidden-card">
                            <hr class="my-4">
                            <h5 class="mb-3">Vehicle Scope</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Segment</label>
                                    <select name="primary_segment_code" id="primary_segment_code" class="form-select org-field" data-original="{{ $employee?->segment_code }}">
                                        <option value="">Select</option>
                                        @foreach ($segments as $s)
                                            <option value="{{ $s->code }}" @selected(old('primary_segment_code', $employee?->segment_code) === $s->code)>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Segments</label>
                                    <select name="addon_segment_codes[]" id="addon_segment_codes" class="form-select org-field-multi" multiple size="4">
                                        @foreach ($segments as $s)
                                            <option value="{{ $s->code }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Sub Segment</label>
                                    <select name="primary_sub_segment_code" id="primary_sub_segment_code" class="form-select org-field" data-original="{{ $employee?->sub_segment_code }}"></select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Sub Segments</label>
                                    <select name="addon_sub_segment_codes[]" id="addon_sub_segment_codes" class="form-select org-field-multi" multiple size="4"></select>
                                </div>
                            </div>
                        </div>

                        <div id="orgChangeBlock" class="mt-2 mb-3">
                            <div class="fw-semibold text-warning mb-2"><i class="la la-exclamation-triangle"></i> Org/vehicle info changed — this will be recorded in Employment History</div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Reason <span class="text-danger">*</span></label>
                                    <select name="change_reason" class="form-select">
                                        <option value="">— select —</option>
                                        <option value="transfer" @selected(old('change_reason') === 'transfer')>Transfer</option>
                                        <option value="promotion" @selected(old('change_reason') === 'promotion')>Promotion</option>
                                        <option value="demotion" @selected(old('change_reason') === 'demotion')>Demotion</option>
                                        <option value="additional_charge" @selected(old('change_reason') === 'additional_charge')>Additional Charge</option>
                                        <option value="scope_change" @selected(old('change_reason') === 'scope_change')>Scope Change</option>
                                        <option value="designation_change" @selected(old('change_reason') === 'designation_change')>Designation Change</option>
                                        <option value="other" @selected(old('change_reason') === 'other')>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Effective Date <span class="text-danger">*</span></label>
                                    <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date', now()->toDateString()) }}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                            </div>
                        </div>

                        <div id="permissionCard" class="hidden-card">
                            <hr class="my-4">
                            <h5 class="mb-3 d-flex justify-content-between align-items-center">
                                <span>Permissions</span>
                                <span class="badge text-bg-primary fs-6" id="totalCountBadge">0 / 0 permissions</span>
                            </h5>
                            <p class="text-muted small">Every permission the selected designation grants is pre-checked, plus this
                                user's own existing overrides. Add or remove individual permissions to change this user's overrides —
                                the designation's own permission set is never changed.</p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="expandAllBtn">Expand all</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAllBtn">Collapse all</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="resetPermsBtn">Reset to designation defaults</button>
                            </div>
                            @include('demo.partials.tree', ['tree' => $permissionTree])
                            <div id="permissionOverrideInputs"></div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ backpack_url('org/user') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="{{ asset('js/demo-rbac.js') }}"></script>
<script>
const BRANCHES = @json($branches);
const LOCATIONS = @json($locations);
const DEPARTMENTS = @json($departments);
const DIVISIONS = @json($divisions);
const SEGMENTS = @json($segments);
const SUB_SEGMENTS = @json($subSegments);
const ROLE_PERMISSIONS = @json($rolePermissions);
const CURRENT_ADDONS = @json($currentAddonScopes);
const CURRENT_OVERRIDES = @json($currentOverrides);

const userTypeSelect = document.getElementById('user_type_code');
const orgCard = document.getElementById('orgCard');
const vehicleCard = document.getElementById('vehicleCard');
const permissionCard = document.getElementById('permissionCard');
const nonEmployeeRoleRow = document.getElementById('nonEmployeeRoleRow');

function isEmployeeType() {
    const opt = userTypeSelect.options[userTypeSelect.selectedIndex];
    return opt && opt.value.toUpperCase() === 'EMP';
}

function toggleCards() {
    const emp = isEmployeeType();
    orgCard.classList.toggle('hidden-card', !emp);
    vehicleCard.classList.toggle('hidden-card', !emp);
    permissionCard.classList.toggle('hidden-card', !userTypeSelect.value);
    nonEmployeeRoleRow.classList.toggle('hidden-card', emp);

    ['designation_code', 'primary_branch_code', 'primary_loc_code', 'primary_dept_code', 'primary_div_code'].forEach(name => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el) emp ? el.setAttribute('required', 'required') : el.removeAttribute('required');
    });
}
userTypeSelect.addEventListener('change', toggleCards);

function populateSelect(select, items, valueKey, labelKey, placeholder, selectedValue) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item[valueKey];
        opt.textContent = item[labelKey];
        select.appendChild(opt);
    });
    if (selectedValue && items.some(i => i[valueKey] === selectedValue)) select.value = selectedValue;
}

function hideOptions(select, codesToHide) {
    Array.from(select.options).forEach(opt => {
        if (!opt.value) return;
        const hide = codesToHide.includes(opt.value);
        opt.hidden = hide;
        opt.disabled = hide;
        if (hide) opt.selected = false;
    });
}

function selectValues(select, values) {
    Array.from(select.options).forEach(opt => { opt.selected = values.includes(opt.value); });
}

function refreshLocations(preselectPrimary, preselectAddons) {
    const primaryBranch = document.getElementById('primary_branch_code').value;
    const addonBranches = Array.from(document.getElementById('addon_branch_codes').selectedOptions).map(o => o.value);
    const allowedBranches = [primaryBranch, ...addonBranches].filter(Boolean);

    const primaryLocSelect = document.getElementById('primary_loc_code');
    const addonLocSelect = document.getElementById('addon_loc_codes');

    populateSelect(primaryLocSelect, primaryBranch ? LOCATIONS.filter(l => l.branch_code === primaryBranch) : [], 'code', 'name', primaryBranch ? 'Select' : 'Select a branch first', preselectPrimary);
    populateSelect(addonLocSelect, LOCATIONS.filter(l => allowedBranches.includes(l.branch_code)), 'code', 'name', '');
    if (preselectAddons) selectValues(addonLocSelect, preselectAddons);
    hideOptions(document.getElementById('addon_branch_codes'), primaryBranch ? [primaryBranch] : []);
    hideOptions(addonLocSelect, primaryLocSelect.value ? [primaryLocSelect.value] : []);
}

function refreshDivisions(preselectPrimary, preselectAddons) {
    const primaryDept = document.getElementById('primary_dept_code').value;
    const addonDepts = Array.from(document.getElementById('addon_dept_codes').selectedOptions).map(o => o.value);
    const allowedDepts = [primaryDept, ...addonDepts].filter(Boolean);

    const primaryDivSelect = document.getElementById('primary_div_code');
    const addonDivSelect = document.getElementById('addon_div_codes');

    populateSelect(primaryDivSelect, primaryDept ? DIVISIONS.filter(d => d.dept_code === primaryDept) : [], 'code', 'name', primaryDept ? 'Select' : 'Select a department first', preselectPrimary);
    populateSelect(addonDivSelect, DIVISIONS.filter(d => allowedDepts.includes(d.dept_code)), 'code', 'name', '');
    if (preselectAddons) selectValues(addonDivSelect, preselectAddons);
    hideOptions(document.getElementById('addon_dept_codes'), primaryDept ? [primaryDept] : []);
    hideOptions(addonDivSelect, primaryDivSelect.value ? [primaryDivSelect.value] : []);
}

function refreshSubSegments(preselectPrimary, preselectAddons) {
    const primarySeg = document.getElementById('primary_segment_code').value;
    const addonSegs = Array.from(document.getElementById('addon_segment_codes').selectedOptions).map(o => o.value);
    const allowedSegs = [primarySeg, ...addonSegs].filter(Boolean);

    const primarySubSelect = document.getElementById('primary_sub_segment_code');
    const addonSubSelect = document.getElementById('addon_sub_segment_codes');

    populateSelect(primarySubSelect, primarySeg ? SUB_SEGMENTS.filter(s => s.segment_code === primarySeg) : [], 'code', 'name', primarySeg ? 'Select' : 'Select a segment first', preselectPrimary);
    populateSelect(addonSubSelect, SUB_SEGMENTS.filter(s => allowedSegs.includes(s.segment_code)), 'code', 'name', '');
    if (preselectAddons) selectValues(addonSubSelect, preselectAddons);
    hideOptions(document.getElementById('addon_segment_codes'), primarySeg ? [primarySeg] : []);
    hideOptions(addonSubSelect, primarySubSelect.value ? [primarySubSelect.value] : []);
}

document.getElementById('primary_branch_code').addEventListener('change', () => refreshLocations());
document.getElementById('addon_branch_codes').addEventListener('change', () => refreshLocations());
document.getElementById('primary_loc_code').addEventListener('change', () => refreshLocations());
document.getElementById('primary_dept_code').addEventListener('change', () => refreshDivisions());
document.getElementById('addon_dept_codes').addEventListener('change', () => refreshDivisions());
document.getElementById('primary_div_code').addEventListener('change', () => refreshDivisions());
document.getElementById('primary_segment_code').addEventListener('change', () => refreshSubSegments());
document.getElementById('addon_segment_codes').addEventListener('change', () => refreshSubSegments());
document.getElementById('primary_sub_segment_code').addEventListener('change', () => refreshSubSegments());

// ---- Permission tree ----
const tree = RbacTree.init(document.getElementById('rbacTree'), { mode: 'override' });

function currentRolePermissions() {
    if (!isEmployeeType()) return [];
    const code = document.getElementById('designation_code').value;
    return ROLE_PERMISSIONS[code] || [];
}

function updateTotalBadge() {
    const total = document.querySelectorAll('.rbac-check-perm').length;
    const checked = tree.getCheckedCodes().length;
    document.getElementById('totalCountBadge').textContent = `${checked} / ${total} permissions`;
}

function refreshPermissionTree(keepOverrides) {
    if (keepOverrides) {
        tree.applyOverride(currentRolePermissions(), CURRENT_OVERRIDES.added, CURRENT_OVERRIDES.removed);
    } else {
        tree.applyOverride(currentRolePermissions(), [], []);
    }
    updateTotalBadge();
}

document.getElementById('designation_code').addEventListener('change', () => refreshPermissionTree(false));
document.getElementById('resetPermsBtn').addEventListener('click', () => refreshPermissionTree(false));
document.getElementById('expandAllBtn').addEventListener('click', () => tree.expandAll(true));
document.getElementById('collapseAllBtn').addEventListener('click', () => tree.expandAll(false));
tree.onChange = updateTotalBadge;

// ---- Org-change detection (drives the reason/effective-date block) ----
function checkOrgChanged() {
    const changed = Array.from(document.querySelectorAll('.org-field'))
        .some(el => el.value !== (el.dataset.original || ''));
    document.getElementById('orgChangeBlock').style.display = changed ? 'block' : 'none';
}
document.querySelectorAll('.org-field').forEach(el => el.addEventListener('change', checkOrgChanged));

// ---- Initial state ----
toggleCards();
refreshLocations('{{ $employee?->primary_loc_code }}', CURRENT_ADDONS.location || []);
refreshDivisions('{{ $employee?->primary_div_code }}', CURRENT_ADDONS.division || []);
refreshSubSegments('{{ $employee?->sub_segment_code }}', CURRENT_ADDONS.sub_segment || []);
selectValues(document.getElementById('addon_branch_codes'), CURRENT_ADDONS.branch || []);
hideOptions(document.getElementById('addon_branch_codes'), document.getElementById('primary_branch_code').value ? [document.getElementById('primary_branch_code').value] : []);
selectValues(document.getElementById('addon_dept_codes'), CURRENT_ADDONS.department || []);
hideOptions(document.getElementById('addon_dept_codes'), document.getElementById('primary_dept_code').value ? [document.getElementById('primary_dept_code').value] : []);
selectValues(document.getElementById('addon_segment_codes'), CURRENT_ADDONS.segment || []);
hideOptions(document.getElementById('addon_segment_codes'), document.getElementById('primary_segment_code').value ? [document.getElementById('primary_segment_code').value] : []);
refreshPermissionTree(true);
checkOrgChanged();

// ---- Submit: capture override diff as real array[] inputs ----
document.getElementById('userForm').addEventListener('submit', function () {
    const container = document.getElementById('permissionOverrideInputs');
    container.innerHTML = '';
    if (permissionCard.classList.contains('hidden-card')) return;

    const diff = tree.getOverrideDiff();
    diff.added.forEach(code => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'added_permissions[]';
        input.value = code;
        container.appendChild(input);
    });
    diff.removed.forEach(code => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'removed_permissions[]';
        input.value = code;
        container.appendChild(input);
    });
});
</script>
@endpush
