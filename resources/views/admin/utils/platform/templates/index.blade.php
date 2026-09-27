@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php $statusColor = ['DRAFT' => 'secondary', 'IN_REVIEW' => 'orange', 'APPROVED' => 'cyan', 'ACTIVE' => 'green', 'RETIRED' => 'muted', 'PENDING_PROVIDER' => 'yellow', 'REJECTED' => 'red']; @endphp
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <form method="GET" action="{{ route('utils.templates.index') }}" class="xl-toolbar flex-grow-1">
                <select name="channel" class="form-select form-select-sm"><option value="">Any channel</option>
                    @foreach (\App\Models\Comms\CommTemplate::CHANNELS as $c) <option value="{{ $c }}" @selected(($filters['channel'] ?? '') === $c)>{{ $c }}</option> @endforeach
                </select>
                <select name="category" class="form-select form-select-sm"><option value="">Any category</option>
                    @foreach (\App\Models\Comms\CommTemplate::CATEGORIES as $c) <option value="{{ $c }}" @selected(($filters['category'] ?? '') === $c)>{{ $c }}</option> @endforeach
                </select>
                <select name="status" class="form-select form-select-sm"><option value="">Any status</option>
                    @foreach (\App\Models\Comms\CommTemplateVersion::STATUSES as $s) <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option> @endforeach
                </select>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Code or name">
                <button class="btn btn-sm btn-outline-primary">Filter</button>
            </form>
            <div class="d-flex gap-2">
                <a href="{{ route('utils.templates.export') }}" class="btn btn-sm btn-outline-secondary"><i class="la la-download"></i> Export JSON</a>
                @can('UTL_TPL_EDIT')
                    <form method="POST" action="{{ route('utils.templates.import') }}" enctype="multipart/form-data" class="d-flex flex-column gap-1">
                        @csrf
                        <x-ui.upload name="file" accept=".json" required />
                        <button class="btn btn-sm btn-outline-secondary">Import drafts</button>
                    </form>
                    <a href="{{ route('utils.templates.create') }}" class="btn btn-sm btn-primary"><i class="la la-plus"></i> New template</a>
                @endcan
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Code</th><th>Channel</th><th>Category</th><th>Versions</th><th class="text-end">Sends</th><th>Last used</th></tr></thead>
                <tbody>
                    @forelse ($rows as $t)
                        @php $active = $t->versions->firstWhere('status', 'ACTIVE'); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('utils.templates.edit', $t->id) }}" class="fw-medium">{{ $t->code }}</a>
                                <div class="small text-muted">{{ $t->name }} · {{ $t->locale }} @if ($t->is_system) · <span class="badge bg-secondary-lt">system</span> @endif</div>
                            </td>
                            <td>{{ $t->channel }}</td>
                            <td><span class="badge {{ $t->category === 'PROMOTIONAL' ? 'bg-orange-lt' : 'bg-secondary-lt' }}">{{ $t->category }}</span></td>
                            <td>
                                @foreach ($t->versions->take(4) as $v)
                                    <span class="badge bg-{{ $statusColor[$v->status] ?? 'secondary' }}-lt">v{{ $v->version }} {{ $v->status }}</span>
                                @endforeach
                            </td>
                            <td class="text-end">{{ $t->versions->sum('usage_count') }}</td>
                            <td class="small">{{ site_datetime($active?->last_used_at, '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No templates.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages()) <div class="card-footer">{{ $rows->links() }}</div> @endif
    </div>
</div>
@endsection
