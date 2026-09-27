@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@include('admin.utils.platform.approvals.admin._nav')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <form method="GET" action="{{ route('utils.approvals.admin.rules') }}" class="d-flex gap-2">
                <select name="topic" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All topics</option>
                    @foreach ($options as $id => $label)
                        <option value="{{ $id }}" @selected($topicId === $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('utils.approvals.admin.rules.create', ['topic' => $topicId]) }}" class="btn btn-sm btn-primary"><i class="la la-plus me-1"></i>New rule</a>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>#</th><th>Topic</th><th>Scope</th><th>Levels</th><th>Valid</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr class="{{ $rule->is_active ? '' : 'text-muted' }}">
                            <td>{{ $rule->id }}</td>
                            <td><code>{{ $rule->topic?->code }}</code></td>
                            <td class="small">
                                @forelse (array_filter($rule->scopeTuple()) as $dim => $value)
                                    <span class="badge bg-secondary-lt">{{ $dim }}={{ $value }}</span>
                                @empty
                                    <span class="text-muted">any</span>
                                @endforelse
                            </td>
                            <td class="small">
                                @foreach ($rule->levels as $l)
                                    <div>L{{ $l->level_no }} {{ $l->designation_code ?? 'users' }} · {{ $l->value_type }} max {{ $l->max_value !== null ? (float) $l->max_value : '∞' }}</div>
                                @endforeach
                            </td>
                            <td class="small">{{ $rule->valid_from ? site_date($rule->valid_from) : '…' }} → {{ $rule->valid_to ? site_date($rule->valid_to) : '…' }}</td>
                            <td class="text-end">
                                <a href="{{ route('utils.approvals.admin.rules.edit', $rule->id) }}" class="btn btn-sm btn-ghost-primary"><i class="la la-edit"></i></a>
                                <form method="POST" action="{{ route('utils.approvals.admin.rules.destroy', $rule->id) }}" class="d-inline" onsubmit="return confirm('Remove rule #{{ $rule->id }}? Open requests keep their snapshot.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ghost-danger"><i class="la la-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No rules.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rules->hasPages())
            <div class="card-footer">{{ $rules->links() }}</div>
        @endif
    </div>
</div>
@endsection
