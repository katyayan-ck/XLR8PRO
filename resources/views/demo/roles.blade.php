@extends('demo.layout')

@section('title', 'Role Permissions')

@section('sidebar')
    <div class="sidebar">
        <div class="sidebar-search">
            <input type="search" id="roleSearch" class="form-control form-control-sm" placeholder="Search roles...">
        </div>
        <div id="roleList">
            @foreach ($roles as $role)
                <div class="sidebar-item" data-role-id="{{ $role['id'] }}" data-role-name="{{ strtolower($role['name']) }}">
                    <div class="avatar">{{ strtoupper(substr($role['name'], 0, 2)) }}</div>
                    <div class="meta">
                        <span>{{ $role['name'] }}</span>
                        <small>{{ $role['code'] }} &middot; <span class="role-perm-count">{{ count($role['permissions']) }}</span> permissions</small>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@section('content')
    <div class="toolbar-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div>
                <h5 class="mb-0" id="selectedRoleName">—</h5>
                <small class="text-muted" id="selectedRoleMeta"></small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-primary fs-6" id="totalCountBadge">0 / 0 permissions</span>
                <button class="btn btn-success btn-sm" id="saveBtn"><i class="bi bi-check2-circle"></i> Save role permissions</button>
            </div>
        </div>
        <hr class="my-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="max-width: 340px;">
                <span class="input-group-text"><i class="bi bi-files"></i></span>
                <select class="form-select" id="copyFromSelect">
                    <option value="">Copy permissions from role...</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role['id'] }}">{{ $role['name'] }} ({{ count($role['permissions']) }})</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary" id="copyFromBtn">Copy</button>
            </div>
            <div class="vr d-none d-md-block"></div>
            <button class="btn btn-outline-secondary btn-sm" id="expandAllBtn"><i class="bi bi-arrows-expand"></i> Expand all</button>
            <button class="btn btn-outline-secondary btn-sm" id="collapseAllBtn"><i class="bi bi-arrows-collapse"></i> Collapse all</button>
            <div class="vr d-none d-md-block"></div>
            <button class="btn btn-outline-success btn-sm" id="checkAllBtn"><i class="bi bi-check-all"></i> Select all permissions</button>
            <button class="btn btn-outline-danger btn-sm" id="uncheckAllBtn"><i class="bi bi-x-circle"></i> Clear all permissions</button>
        </div>
    </div>

    @include('demo.partials.tree', ['tree' => $tree])
@endsection

@push('scripts')
<script>
    const DEMO_ROLES = @json($roles);
    const DEFAULT_ROLE_ID = @json($defaultRoleId);

    const rolesById = Object.fromEntries(DEMO_ROLES.map(r => [r.id, r]));
    let currentRoleId = null;
    let dirty = false;

    const tree = RbacTree.init(document.getElementById('rbacTree'), { mode: 'role' });
    tree.onChange = () => { dirty = true; updateTotalBadge(); syncSidebarCount(currentRoleId); };

    function updateTotalBadge() {
        const total = document.querySelectorAll('.rbac-check-perm').length;
        const checked = tree.getCheckedCodes().length;
        document.getElementById('totalCountBadge').textContent = `${checked} / ${total} permissions`;
    }

    function syncSidebarCount(roleId) {
        const item = document.querySelector(`.sidebar-item[data-role-id="${roleId}"] .role-perm-count`);
        if (item) item.textContent = tree.getCheckedCodes().length;
    }

    function selectRole(roleId, opts) {
        opts = opts || {};
        if (dirty && !opts.force) {
            if (!confirm('You have unsaved changes to this role. Switch roles anyway and discard them?')) {
                return;
            }
        }

        const role = rolesById[roleId];
        if (!role) return;

        currentRoleId = roleId;
        dirty = false;

        document.querySelectorAll('.sidebar-item').forEach(el => el.classList.toggle('active', el.dataset.roleId == roleId));
        document.getElementById('selectedRoleName').textContent = role.name;
        document.getElementById('selectedRoleMeta').textContent = `Designation code: ${role.code}`;

        tree.applyPermissionSet(role.permissions);
        updateTotalBadge();
    }

    document.getElementById('roleList').addEventListener('click', (e) => {
        const item = e.target.closest('.sidebar-item');
        if (item) selectRole(Number(item.dataset.roleId));
    });

    document.getElementById('roleSearch').addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('.sidebar-item').forEach(el => {
            el.style.display = el.dataset.roleName.includes(q) ? '' : 'none';
        });
    });

    document.getElementById('copyFromBtn').addEventListener('click', () => {
        const sourceId = Number(document.getElementById('copyFromSelect').value);
        if (!sourceId) { alert('Pick a role to copy from first.'); return; }
        const source = rolesById[sourceId];
        const target = rolesById[currentRoleId];
        if (!confirm(`Copy "${source.name}"'s permissions onto "${target.name}"?\n\nThis will RESET all of "${target.name}"'s current permissions to match "${source.name}" exactly (${source.permissions.length} permissions). You can still fine-tune afterwards.`)) {
            return;
        }
        tree.applyPermissionSet(source.permissions);
        dirty = true;
        updateTotalBadge();
        syncSidebarCount(currentRoleId);
    });

    document.getElementById('expandAllBtn').addEventListener('click', () => tree.expandAll(true));
    document.getElementById('collapseAllBtn').addEventListener('click', () => tree.expandAll(false));

    document.getElementById('checkAllBtn').addEventListener('click', () => {
        const allCodes = Array.from(document.querySelectorAll('.rbac-check-perm')).map(cb => cb.dataset.code);
        tree.applyPermissionSet(allCodes);
        dirty = true;
        updateTotalBadge();
        syncSidebarCount(currentRoleId);
    });
    document.getElementById('uncheckAllBtn').addEventListener('click', () => {
        tree.applyPermissionSet([]);
        dirty = true;
        updateTotalBadge();
        syncSidebarCount(currentRoleId);
    });

    document.getElementById('saveBtn').addEventListener('click', () => {
        const payload = { role_id: currentRoleId, permissions: tree.getCheckedCodes() };
        console.log('Would PUT /admin/iam/role/' + currentRoleId + '/permissions with payload:', payload);
        dirty = false;
        rolesById[currentRoleId].permissions = payload.permissions;
        alert(`(Mockup — nothing persisted)\n\nWould save ${payload.permissions.length} permissions for "${rolesById[currentRoleId].name}".\nSee browser console for the exact payload shape.`);
    });

    selectRole(DEFAULT_ROLE_ID, { force: true });
</script>
@endpush
