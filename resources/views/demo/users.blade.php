@extends('demo.layout')

@section('title', 'User Permission Overrides')

@section('sidebar')
    <div class="sidebar">
        <div class="sidebar-search">
            <input type="search" id="userSearch" class="form-control form-control-sm" placeholder="Search users...">
        </div>
        <div id="userList">
            @foreach ($users as $user)
                <div class="sidebar-item" data-user-id="{{ $user['id'] }}" data-user-name="{{ strtolower($user['display_name'].' '.$user['username']) }}">
                    <div class="avatar">{{ strtoupper(substr($user['display_name'], 0, 2)) }}</div>
                    <div class="meta">
                        <span>{{ $user['display_name'] }}</span>
                        <small>{{ $user['username'] }} &middot; {{ $user['role_name'] }}</small>
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
                <h5 class="mb-0" id="selectedUserName">—</h5>
                <small class="text-muted" id="selectedUserMeta"></small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-primary fs-6" id="totalCountBadge">0 / 0 permissions</span>
                <button class="btn btn-success btn-sm" id="saveBtn"><i class="bi bi-check2-circle"></i> Save user overrides</button>
            </div>
        </div>
        <hr class="my-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="legend">
                <span><span class="dot dot-inherited"></span> Inherited from role</span>
                <span><span class="dot dot-added"></span> Added for this user only</span>
                <span><span class="dot dot-removed"></span> Removed for this user only</span>
                <span><span class="dot dot-none"></span> Not granted</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="expandAllBtn"><i class="bi bi-arrows-expand"></i> Expand all</button>
                <button class="btn btn-outline-secondary btn-sm" id="collapseAllBtn"><i class="bi bi-arrows-collapse"></i> Collapse all</button>
                <button class="btn btn-outline-warning btn-sm" id="resetBtn"><i class="bi bi-arrow-counterclockwise"></i> Reset to role default</button>
            </div>
        </div>
        <div class="mt-2" id="overrideSummary"></div>
    </div>

    @include('demo.partials.tree', ['tree' => $tree])
@endsection

@push('scripts')
<script>
    const DEMO_USERS = @json($users);
    const DEMO_ROLES = @json($roles);
    const rolesById = Object.fromEntries(DEMO_ROLES.map(r => [r.id, r]));
    const usersById = Object.fromEntries(DEMO_USERS.map(u => [u.id, u]));

    let currentUserId = null;
    let dirty = false;

    const tree = RbacTree.init(document.getElementById('rbacTree'), { mode: 'override' });
    tree.onChange = () => { dirty = true; updateTotalBadge(); updateSummary(); };

    function updateTotalBadge() {
        const total = document.querySelectorAll('.rbac-check-perm').length;
        const checked = tree.getCheckedCodes().length;
        document.getElementById('totalCountBadge').textContent = `${checked} / ${total} permissions`;
    }

    function updateSummary() {
        const diff = tree.getOverrideDiff();
        const el = document.getElementById('overrideSummary');
        if (diff.added.length === 0 && diff.removed.length === 0) {
            el.innerHTML = '<small class="text-muted">No overrides — this user has exactly their role\'s permissions.</small>';
            return;
        }
        let html = '<small>';
        if (diff.added.length) html += `<span class="text-success"><i class="bi bi-plus-circle"></i> ${diff.added.length} added</span>&nbsp;&nbsp;`;
        if (diff.removed.length) html += `<span class="text-danger"><i class="bi bi-dash-circle"></i> ${diff.removed.length} removed</span>`;
        html += '</small>';
        el.innerHTML = html;
    }

    function selectUser(userId, opts) {
        opts = opts || {};
        if (dirty && !opts.force) {
            if (!confirm('You have unsaved override changes for this user. Switch users anyway and discard them?')) {
                return;
            }
        }

        const user = usersById[userId];
        if (!user) return;
        const role = rolesById[user.role_id];

        currentUserId = userId;
        dirty = false;

        document.querySelectorAll('#userList .sidebar-item').forEach(el => el.classList.toggle('active', el.dataset.userId == userId));
        document.getElementById('selectedUserName').textContent = user.display_name;
        document.getElementById('selectedUserMeta').innerHTML =
            `${user.username} &nbsp;&middot;&nbsp; Role: <strong>${role ? role.name : '(none)'}</strong> ` +
            `<a href="{{ route('demo.roles') }}" target="_blank" class="ms-1">view role <i class="bi bi-box-arrow-up-right"></i></a>`;

        tree.applyOverride(role ? role.permissions : [], user.overrides.added, user.overrides.removed);
        updateTotalBadge();
        updateSummary();
    }

    document.getElementById('userList').addEventListener('click', (e) => {
        const item = e.target.closest('.sidebar-item');
        if (item) selectUser(Number(item.dataset.userId));
    });

    document.getElementById('userSearch').addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('#userList .sidebar-item').forEach(el => {
            el.style.display = el.dataset.userName.includes(q) ? '' : 'none';
        });
    });

    document.getElementById('expandAllBtn').addEventListener('click', () => tree.expandAll(true));
    document.getElementById('collapseAllBtn').addEventListener('click', () => tree.expandAll(false));

    document.getElementById('resetBtn').addEventListener('click', () => {
        if (!confirm('Reset this user to exactly their role\'s permissions? All user-level overrides will be discarded.')) return;
        tree.resetToBase();
        updateTotalBadge();
        updateSummary();
    });

    document.getElementById('saveBtn').addEventListener('click', () => {
        const diff = tree.getOverrideDiff();
        const payload = { user_id: currentUserId, added: diff.added, removed: diff.removed };
        console.log('Would PUT /admin/org/user/' + currentUserId + '/permission-overrides with payload:', payload);
        dirty = false;
        usersById[currentUserId].overrides = { added: diff.added, removed: diff.removed };
        alert(`(Mockup — nothing persisted)\n\nWould save overrides for "${usersById[currentUserId].display_name}":\n+${diff.added.length} added, −${diff.removed.length} removed.\nSee browser console for the exact payload shape.`);
    });

    if (DEMO_USERS.length) selectUser(DEMO_USERS[0].id, { force: true });
</script>
@endpush
