<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RBAC Demo') — XLRM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --rbac-inherited: #0d6efd;
            --rbac-added: #198754;
            --rbac-removed: #dc3545;
        }
        body { background: #f4f6f9; }
        .demo-banner {
            background: #fff3cd; border-bottom: 1px solid #ffe69c; color: #664d03;
            font-size: .875rem; padding: .5rem 1rem;
        }
        .app-shell { display: flex; min-height: calc(100vh - 40px); }
        .sidebar {
            width: 300px; flex-shrink: 0; background: #fff; border-right: 1px solid #dee2e6;
            overflow-y: auto; height: calc(100vh - 40px); position: sticky; top: 40px;
        }
        .sidebar-search { padding: .75rem; border-bottom: 1px solid #eee; }
        .sidebar-item {
            display: flex; align-items: center; gap: .5rem; padding: .55rem .9rem; cursor: pointer;
            border-left: 3px solid transparent; font-size: .9rem;
        }
        .sidebar-item:hover { background: #f8f9fa; }
        .sidebar-item.active { background: #eaf1ff; border-left-color: var(--rbac-inherited); font-weight: 600; }
        .sidebar-item .avatar {
            width: 28px; height: 28px; border-radius: 50%; background: #6c757d; color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: .7rem; flex-shrink: 0;
        }
        .sidebar-item .meta { display: flex; flex-direction: column; overflow: hidden; }
        .sidebar-item .meta small { color: #6c757d; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .main { flex: 1; padding: 1.25rem 1.5rem; min-width: 0; }
        .toolbar-card { background: #fff; border: 1px solid #dee2e6; border-radius: .5rem; padding: 1rem 1.25rem; margin-bottom: 1rem; }

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
        .rbac-row-perm:has(.rbac-check:disabled) .rbac-label { color: #adb5bd; cursor: not-allowed; }
        .rbac-state-badge { font-size: .68rem; font-weight: 600; padding: .05rem .4rem; border-radius: .75rem; display: none; }

        /* Override (user-level) visual states — derived purely from checked vs data-in-role */
        .ov-added .rbac-label-perm { color: var(--rbac-added); }
        .ov-added .rbac-state-badge { display: inline-block; background: #d1e7dd; color: var(--rbac-added); }
        .ov-removed .rbac-label-perm { color: var(--rbac-removed); text-decoration: line-through; }
        .ov-removed .rbac-state-badge { display: inline-block; background: #f8d7da; color: var(--rbac-removed); }
        .ov-inherited .rbac-state-badge { display: inline-block; background: #cfe2ff; color: var(--rbac-inherited); }

        .legend { display: flex; gap: 1.25rem; flex-wrap: wrap; font-size: .8rem; }
        .legend span { display: inline-flex; align-items: center; gap: .35rem; }
        .legend .dot { width: .6rem; height: .6rem; border-radius: 50%; display: inline-block; }
        .dot-inherited { background: var(--rbac-inherited); }
        .dot-added { background: var(--rbac-added); }
        .dot-removed { background: var(--rbac-removed); }
        .dot-none { background: #dee2e6; }
    </style>
    @stack('styles')
</head>
<body>
    <div class="demo-banner">
        <i class="bi bi-cone-striped"></i>
        <strong>UI/UX Mockup</strong> — real Module/Process/Permission data from <code>xlr8_iam_permissions</code> (225 rows), real role names from Designations. Role/user assignments shown are spoofed for demonstration. Nothing here saves to the database yet.
        &nbsp;|&nbsp;
        <a href="{{ route('demo.roles') }}">Role permissions</a> &nbsp;·&nbsp;
        <a href="{{ route('demo.users') }}">User overrides</a>
    </div>
    <div class="app-shell">
        @yield('sidebar')
        <div class="main">
            @yield('content')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/demo-rbac.js') }}"></script>
    @stack('scripts')
</body>
</html>
