@extends(backpack_view('blank'))

@section('title', 'New User')

@push('after_styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
    :root { --rbac-inherited: #0d6efd; --rbac-added: #198754; --rbac-removed: #dc3545; }
    .card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,.08); }
    .form-control:focus, .form-select:focus { border-color: #80bdff; box-shadow: 0 0 0 .2rem rgba(0,123,255,.25); }
    .hidden-card { display: none; }
    #personResults { position: relative; }
    #personResultsList {
        position: absolute; z-index: 20; top: 100%; left: 0; right: 0;
        background: var(--tblr-card-bg); border: 1px solid #dee2e6; border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0,0,0,.15); max-height: 320px; overflow-y: auto; display: none;
    }
    .person-result { display: flex; align-items: center; gap: .6rem; padding: .5rem .75rem; cursor: pointer; }
    .person-result:hover { background: var(--tblr-bg-surface-secondary); }
    .person-avatar {
        width: 32px; height: 32px; border-radius: 50%; background: #6c757d; color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: .7rem; flex-shrink: 0; overflow: hidden;
    }
    .person-avatar img { width: 100%; height: 100%; object-fit: cover; }
    #selectedPersonCard { display: none; }

    .rbac-tree { background: var(--tblr-card-bg); border: 1px solid #dee2e6; border-radius: .5rem; overflow: hidden; }
    .rbac-module { border-bottom: 1px solid #eee; }
    .rbac-module:last-child { border-bottom: none; }
    .rbac-row { display: flex; align-items: center; gap: .5rem; padding: .5rem .9rem; }
    .rbac-row-module { background: #f8f9fb; font-size: .95rem; }
    .rbac-row-process { background: #fcfcfd; padding-left: 2.2rem; font-size: .875rem; border-top: 1px solid #f1f1f1; }
    .rbac-row-perm { padding-left: 4.4rem; font-size: .825rem; border-top: 1px dashed #f3f3f3; }
    .rbac-row-perm:hover { background: #fafbff; }
    .rbac-caret { border: none; background: none; padding: 0 .25rem; color: var(--tblr-muted); cursor: pointer; width: 1.2rem; }
    .rbac-caret i { transition: transform .15s ease; display: inline-block; }
    .rbac-module.collapsed > .rbac-module-body { display: none; }
    .rbac-module.collapsed > .rbac-row-module .rbac-caret i { transform: rotate(-90deg); }
    .rbac-process.collapsed > .rbac-process-body { display: none; }
    .rbac-process.collapsed > .rbac-row-process .rbac-caret i { transform: rotate(-90deg); }
    .rbac-label { flex: 1; margin: 0; cursor: pointer; }
    .rbac-label-module { font-weight: 600; }
    .rbac-label-process { font-weight: 500; color: var(--tblr-muted); }
    .rbac-perm-code { margin-left: .5rem; font-size: .7rem; color: var(--tblr-muted); }
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
                    <h2 class="mb-0">New User</h2>
                    <a href="{{ backpack_url('org/user') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="la la-arrow-left"></i> Back to list
                    </a>
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

                    <form method="POST" action="{{ backpack_url('org/user') }}" id="userForm">
                        @csrf

                        {{-- ═══ 1. PERSON ═══ --}}
                        <h5 class="mb-3">1. Person</h5>
                        <div class="mb-3" id="personResults">
                            <label>Search for an existing person <span class="text-danger">*</span></label>
                            <input type="text" id="personSearchInput" class="form-control" placeholder="Search by name, code, or mobile..." autocomplete="off">
                            <div id="personResultsList"></div>
                            <input type="hidden" name="person_code" id="person_code" value="{{ old('person_code') }}">
                        </div>

                        <div id="selectedPersonCard" class="mb-3 p-3 border rounded d-flex align-items-center gap-3">
                            <div class="person-avatar" style="width:56px;height:56px;font-size:1.1rem;" id="selectedPersonAvatar"></div>
                            <div>
                                <div class="fw-bold" id="selectedPersonName"></div>
                                <div class="small text-muted" id="selectedPersonMeta"></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="changePersonBtn">Change</button>
                        </div>

                        <hr class="my-4">

                        {{-- ═══ 2. ACCOUNT ═══ --}}
                        <h5 class="mb-3">2. Account</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label>User Type <span class="text-danger">*</span></label>
                                <select name="user_type_code" id="user_type_code" class="form-select" required>
                                    <option value="">Select</option>
                                    @foreach ($userTypes as $type)
                                        <option value="{{ $type->code }}" @selected(old('user_type_code') === $type->code)>{{ $type->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Date of Joining</label>
                                <input type="date" name="date_of_joining" class="form-control" value="{{ old('date_of_joining') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Username / Display Name <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" value="{{ old('username') }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>
                        </div>

                        <div class="row" id="nonEmployeeRoleRow">
                            <div class="col-md-4 mb-3">
                                <label>Role</label>
                                <select name="role_id" class="form-select">
                                    <option value="">— no role —</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" {{ old('is_active', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>

                        {{-- ═══ 3. ORG CARD (Employee only) ═══ --}}
                        <div id="orgCard" class="hidden-card">
                            <hr class="my-4">
                            <h5 class="mb-3">3. Organisation</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Designation (Role) <span class="text-danger">*</span></label>
                                    <select name="designation_code" id="designation_code" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($designations as $d)
                                            <option value="{{ $d->code }}" @selected(old('designation_code') === $d->code)>{{ $d->name }} ({{ $d->rank_label }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Vertical</label>
                                    <select name="vertical_code" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($verticals as $v)
                                            <option value="{{ $v->code }}" @selected(old('vertical_code') === $v->code)>{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Branch <span class="text-danger">*</span></label>
                                    <select name="primary_branch_code" id="primary_branch_code" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($branches as $b)
                                            <option value="{{ $b->code }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Branches</label>
                                    <select name="addon_branch_codes[]" id="addon_branch_codes" class="form-select" multiple size="4">
                                        @foreach ($branches as $b)
                                            <option value="{{ $b->code }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Ctrl/Cmd+click to select multiple. The primary branch is hidden here automatically.</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Location <span class="text-danger">*</span></label>
                                    <select name="primary_loc_code" id="primary_loc_code" class="form-select">
                                        <option value="">Select a branch first</option>
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Locations</label>
                                    <select name="addon_loc_codes[]" id="addon_loc_codes" class="form-select" multiple size="4"></select>
                                    <div class="form-text">Children of the primary + addon branches.</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Department <span class="text-danger">*</span></label>
                                    <select name="primary_dept_code" id="primary_dept_code" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->code }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Departments</label>
                                    <select name="addon_dept_codes[]" id="addon_dept_codes" class="form-select" multiple size="4">
                                        @foreach ($departments as $dept)
                                            <option value="{{ $dept->code }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Division <span class="text-danger">*</span></label>
                                    <select name="primary_div_code" id="primary_div_code" class="form-select">
                                        <option value="">Select a department first</option>
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Divisions</label>
                                    <select name="addon_div_codes[]" id="addon_div_codes" class="form-select" multiple size="4"></select>
                                    <div class="form-text">Children of the primary + addon departments.</div>
                                </div>
                            </div>
                        </div>

                        {{-- ═══ 4. VEHICLE CARD (Employee only) ═══ --}}
                        <div id="vehicleCard" class="hidden-card">
                            <hr class="my-4">
                            <h5 class="mb-3">4. Vehicle Scope</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Segment</label>
                                    <select name="primary_segment_code" id="primary_segment_code" class="form-select">
                                        <option value="">Select</option>
                                        @foreach ($segments as $s)
                                            <option value="{{ $s->code }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Segments</label>
                                    <select name="addon_segment_codes[]" id="addon_segment_codes" class="form-select" multiple size="4">
                                        @foreach ($segments as $s)
                                            <option value="{{ $s->code }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Primary Sub Segment</label>
                                    <select name="primary_sub_segment_code" id="primary_sub_segment_code" class="form-select">
                                        <option value="">Select a segment first</option>
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label>Addon Sub Segments</label>
                                    <select name="addon_sub_segment_codes[]" id="addon_sub_segment_codes" class="form-select" multiple size="4"></select>
                                    <div class="form-text">Children of the primary + addon segments.</div>
                                </div>
                            </div>
                        </div>

                        {{-- ═══ 5. PERMISSIONS CARD ═══ --}}
                        <div id="permissionCard" class="hidden-card">
                            <hr class="my-4">
                            <h5 class="mb-3 d-flex justify-content-between align-items-center">
                                <span>5. Permissions</span>
                                <span class="badge text-bg-primary fs-6" id="totalCountBadge">0 / 0 permissions</span>
                            </h5>
                            <p class="text-muted small">Every permission the selected designation grants is pre-checked. Add or remove
                                individual permissions here to override them for this user only — the designation's own permission set
                                is never changed.</p>
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
                            <button type="submit" class="btn btn-primary">Create User</button>
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
const SEARCH_URL = @json(backpack_url('org/user/search-persons'));
const BRANCHES = @json($branches);
const LOCATIONS = @json($locations);
const DEPARTMENTS = @json($departments);
const DIVISIONS = @json($divisions);
const SEGMENTS = @json($segments);
const SUB_SEGMENTS = @json($subSegments);
const ROLE_PERMISSIONS = @json($rolePermissions);

// ---- 1. Person typeahead ----
const personInput = document.getElementById('personSearchInput');
const personResultsList = document.getElementById('personResultsList');
let searchTimer = null;

personInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { personResultsList.style.display = 'none'; return; }

    searchTimer = setTimeout(() => {
        fetch(SEARCH_URL + '?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(persons => {
                personResultsList.innerHTML = '';
                if (!persons.length) {
                    personResultsList.innerHTML = '<div class="p-2 text-muted small">No matching persons found.</div>';
                } else {
                    persons.forEach(p => {
                        const row = document.createElement('div');
                        row.className = 'person-result';
                        row.innerHTML = `
                            <div class="person-avatar">${p.photo ? `<img src="${p.photo}">` : (p.display_name || '?').substring(0,2).toUpperCase()}</div>
                            <div>
                                <div class="fw-bold">${p.display_name}</div>
                                <div class="small text-muted">${p.person_code} ${p.mobile ? '· ' + p.mobile : ''} ${p.email ? '· ' + p.email : ''}</div>
                            </div>`;
                        row.addEventListener('click', () => selectPerson(p));
                        personResultsList.appendChild(row);
                    });
                }
                personResultsList.style.display = 'block';
            });
    }, 300);
});

function selectPerson(p) {
    document.getElementById('person_code').value = p.person_code;
    document.getElementById('selectedPersonAvatar').innerHTML = p.photo ? `<img src="${p.photo}">` : (p.display_name || '?').substring(0,2).toUpperCase();
    document.getElementById('selectedPersonName').textContent = p.display_name;
    document.getElementById('selectedPersonMeta').textContent = [p.person_code, p.mobile, p.email].filter(Boolean).join(' · ');
    document.getElementById('selectedPersonCard').style.display = 'flex';
    document.getElementById('personResults').style.display = 'none';
    personResultsList.style.display = 'none';
}

document.getElementById('changePersonBtn').addEventListener('click', () => {
    document.getElementById('person_code').value = '';
    document.getElementById('selectedPersonCard').style.display = 'none';
    document.getElementById('personResults').style.display = 'block';
    personInput.value = '';
    personInput.focus();
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('#personResults')) personResultsList.style.display = 'none';
});

// ---- 2. User type toggles Org/Vehicle/Permission cards ----
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

    refreshPermissionTree();
}
userTypeSelect.addEventListener('change', toggleCards);
toggleCards();

// ---- 3. Cascading org selects ----
function populateSelect(select, items, valueKey, labelKey, placeholder) {
    const current = select.value;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item[valueKey];
        opt.textContent = item[labelKey];
        select.appendChild(opt);
    });
    if (items.some(i => i[valueKey] === current)) select.value = current;
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

function refreshBranchAddon() {
    const primary = document.getElementById('primary_branch_code').value;
    hideOptions(document.getElementById('addon_branch_codes'), primary ? [primary] : []);
}

function refreshLocations() {
    const primaryBranch = document.getElementById('primary_branch_code').value;
    const addonBranches = Array.from(document.getElementById('addon_branch_codes').selectedOptions).map(o => o.value);
    const allowedBranches = [primaryBranch, ...addonBranches].filter(Boolean);

    const primaryLocSelect = document.getElementById('primary_loc_code');
    const addonLocSelect = document.getElementById('addon_loc_codes');

    const primaryLocOptions = primaryBranch ? LOCATIONS.filter(l => l.branch_code === primaryBranch) : [];
    populateSelect(primaryLocSelect, primaryLocOptions, 'code', 'name', primaryBranch ? 'Select' : 'Select a branch first');

    const addonLocOptions = LOCATIONS.filter(l => allowedBranches.includes(l.branch_code));
    populateSelect(addonLocSelect, addonLocOptions, 'code', 'name', '');
    hideOptions(addonLocSelect, primaryLocSelect.value ? [primaryLocSelect.value] : []);
}

function refreshDeptAddon() {
    const primary = document.getElementById('primary_dept_code').value;
    hideOptions(document.getElementById('addon_dept_codes'), primary ? [primary] : []);
}

function refreshDivisions() {
    const primaryDept = document.getElementById('primary_dept_code').value;
    const addonDepts = Array.from(document.getElementById('addon_dept_codes').selectedOptions).map(o => o.value);
    const allowedDepts = [primaryDept, ...addonDepts].filter(Boolean);

    const primaryDivSelect = document.getElementById('primary_div_code');
    const addonDivSelect = document.getElementById('addon_div_codes');

    const primaryDivOptions = primaryDept ? DIVISIONS.filter(d => d.dept_code === primaryDept) : [];
    populateSelect(primaryDivSelect, primaryDivOptions, 'code', 'name', primaryDept ? 'Select' : 'Select a department first');

    const addonDivOptions = DIVISIONS.filter(d => allowedDepts.includes(d.dept_code));
    populateSelect(addonDivSelect, addonDivOptions, 'code', 'name', '');
    hideOptions(addonDivSelect, primaryDivSelect.value ? [primaryDivSelect.value] : []);
}

document.getElementById('primary_branch_code').addEventListener('change', () => { refreshBranchAddon(); refreshLocations(); });
document.getElementById('addon_branch_codes').addEventListener('change', refreshLocations);
document.getElementById('primary_loc_code').addEventListener('change', refreshLocations);
document.getElementById('primary_dept_code').addEventListener('change', () => { refreshDeptAddon(); refreshDivisions(); });
document.getElementById('addon_dept_codes').addEventListener('change', refreshDivisions);
document.getElementById('primary_div_code').addEventListener('change', refreshDivisions);

// ---- 4. Cascading vehicle selects ----
function refreshSegmentAddon() {
    const primary = document.getElementById('primary_segment_code').value;
    hideOptions(document.getElementById('addon_segment_codes'), primary ? [primary] : []);
}

function refreshSubSegments() {
    const primarySeg = document.getElementById('primary_segment_code').value;
    const addonSegs = Array.from(document.getElementById('addon_segment_codes').selectedOptions).map(o => o.value);
    const allowedSegs = [primarySeg, ...addonSegs].filter(Boolean);

    const primarySubSelect = document.getElementById('primary_sub_segment_code');
    const addonSubSelect = document.getElementById('addon_sub_segment_codes');

    const primarySubOptions = primarySeg ? SUB_SEGMENTS.filter(s => s.segment_code === primarySeg) : [];
    populateSelect(primarySubSelect, primarySubOptions, 'code', 'name', primarySeg ? 'Select' : 'Select a segment first');

    const addonSubOptions = SUB_SEGMENTS.filter(s => allowedSegs.includes(s.segment_code));
    populateSelect(addonSubSelect, addonSubOptions, 'code', 'name', '');
    hideOptions(addonSubSelect, primarySubSelect.value ? [primarySubSelect.value] : []);
}

document.getElementById('primary_segment_code').addEventListener('change', () => { refreshSegmentAddon(); refreshSubSegments(); });
document.getElementById('addon_segment_codes').addEventListener('change', refreshSubSegments);
document.getElementById('primary_sub_segment_code').addEventListener('change', refreshSubSegments);

// ---- 5. Permission tree ----
const tree = RbacTree.init(document.getElementById('rbacTree'), { mode: 'override' });

function currentRolePermissions() {
    // Non-employee roles aren't keyed by designation code client-side; start blank, still overridable.
    if (!isEmployeeType()) return [];

    const code = document.getElementById('designation_code').value;
    return ROLE_PERMISSIONS[code] || [];
}

function updateTotalBadge() {
    const total = document.querySelectorAll('.rbac-check-perm').length;
    const checked = tree.getCheckedCodes().length;
    document.getElementById('totalCountBadge').textContent = `${checked} / ${total} permissions`;
}

function refreshPermissionTree() {
    tree.applyOverride(currentRolePermissions(), [], []);
    updateTotalBadge();
}

document.getElementById('designation_code').addEventListener('change', refreshPermissionTree);
document.getElementById('resetPermsBtn').addEventListener('click', refreshPermissionTree);
document.getElementById('expandAllBtn').addEventListener('click', () => tree.expandAll(true));
document.getElementById('collapseAllBtn').addEventListener('click', () => tree.expandAll(false));
tree.onChange = updateTotalBadge;

// ---- Submit: capture override diff as real array[] inputs (not a JSON string — Laravel needs name[] repeats) ----
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
