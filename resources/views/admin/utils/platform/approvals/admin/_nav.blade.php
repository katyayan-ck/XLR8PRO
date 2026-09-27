<div class="container-fluid mb-3">
    <div class="btn-group">
        <a href="{{ route('utils.approvals.admin.topics') }}" class="btn btn-sm {{ request()->routeIs('utils.approvals.admin.topics') ? 'btn-primary' : 'btn-outline-primary' }}">Topics</a>
        <a href="{{ route('utils.approvals.admin.rules') }}" class="btn btn-sm {{ request()->routeIs('utils.approvals.admin.rules*') ? 'btn-primary' : 'btn-outline-primary' }}">Rules</a>
        <a href="{{ route('utils.approvals.admin.import') }}" class="btn btn-sm {{ request()->routeIs('utils.approvals.admin.import*') ? 'btn-primary' : 'btn-outline-primary' }}">Import power sheet</a>
        <a href="{{ route('utils.approvals.admin.simulate') }}" class="btn btn-sm {{ request()->routeIs('utils.approvals.admin.simulate') ? 'btn-primary' : 'btn-outline-primary' }}">Simulation</a>
        <a href="{{ route('utils.approvals.index') }}" class="btn btn-sm btn-outline-secondary">Inbox</a>
    </div>
</div>
