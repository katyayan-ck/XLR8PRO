@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid py-4">
    <h2 class="mb-4">OrgService Demo – All Use Cases</h2>

    <div class="row g-4">

        <!-- 1. Master Entities -->
        <div class="col-12"><h5 class="text-primary">1. Master Entities</h5></div>
        <div class="col-md-3"><div class="card"><div class="card-header"><code>branches()</code></div><div class="card-body"><pre>{{ json_encode($branches, JSON_PRETTY_PRINT) }}</pre></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-header"><code>locations('CHR')</code></div><div class="card-body"><pre>{{ json_encode($churuLocations, JSON_PRETTY_PRINT) }}</pre></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-header"><code>departments()</code></div><div class="card-body"><pre>{{ json_encode($departments, JSON_PRETTY_PRINT) }}</pre></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-header"><code>divisions('SLS')</code></div><div class="card-body"><pre>{{ json_encode($salesDivisions, JSON_PRETTY_PRINT) }}</pre></div></div></div>

        <!-- 2. Complex User Queries -->
        <div class="col-12 mt-4"><h5 class="text-primary">2. Complex User Queries</h5></div>
        <div class="col-md-6"><div class="card"><div class="card-header"><code>usersByPost('SLS_CNS_CHR_003', 'CHR')</code></div><div class="card-body"><pre>{{ json_encode($usersByPost, JSON_PRETTY_PRINT) }}</pre></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-header"><code>usersByDesignation('CNS', 'CHR')</code></div><div class="card-body"><pre>{{ json_encode($usersByDesig, JSON_PRETTY_PRINT) }}</pre></div></div></div>

        <!-- 3. Single lookups -->
        <div class="col-12 mt-4"><h5 class="text-primary">3. Single Lookups</h5></div>
        <div class="col-md-3"><div class="card"><div class="card-header">Branch Name</div><div class="card-body"><code>branchName('CHR')</code> → <strong>{{ \App\Services\OrgService::branchName('CHR') }}</strong></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-header">Department Name</div><div class="card-body"><code>departmentName('SLS')</code> → <strong>{{ \App\Services\OrgService::departmentName('SLS') }}</strong></div></div></div>

        <!-- 4. More Complex Examples -->
        <div class="col-12 mt-4"><h5 class="text-primary">4. More Complex Examples</h5></div>
        <div class="col-md-4"><div class="card"><div class="card-header">Users in ALL branches with Post</div><div class="card-body"><code>usersByPost('SLS_CNS_CHR_003')</code><pre>{{ json_encode(\App\Services\OrgService::usersByPost('SLS_CNS_CHR_003'), JSON_PRETTY_PRINT) }}</pre></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-header">All Models in Segment PRSNL</div><div class="card-body"><code>models('PRSNL')</code><pre>{{ json_encode(\App\Services\OrgService::models('PRSNL'), JSON_PRETTY_PRINT) }}</pre></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-header">Variants of Model XUV700</div><div class="card-body"><code>variants('XUV700')</code><pre>{{ json_encode(\App\Services\OrgService::variants('XUV700'), JSON_PRETTY_PRINT) }}</pre></div></div></div>

    </div>
</div>
@endsection