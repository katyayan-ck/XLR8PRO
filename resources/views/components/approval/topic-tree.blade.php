@props(['tree' => null, 'editable' => false])
{{-- Approval topic tree (FRS TOP-07). --}}
@php $tree ??= app(\App\Services\Platform\Approval\TopicService::class)->tree(); @endphp
<div class="list-group list-group-flush">
    @forelse ($tree as $row)
        @php $t = $row['topic']; @endphp
        <div class="list-group-item d-flex align-items-center gap-2 py-2" style="padding-left: {{ 1 + $row['depth'] * 1.5 }}rem;">
            <i class="la {{ $t->item_key ? 'la-dot-circle text-primary' : 'la-folder text-warning' }}"></i>
            <div class="flex-grow-1">
                <span class="fw-medium {{ $t->is_active ? '' : 'text-muted text-decoration-line-through' }}">{{ $t->title }}</span>
                <code class="small ms-1">{{ $t->code }}</code>
                @if ($t->item_key) <span class="badge bg-blue-lt ms-1">{{ $t->item_key }}</span> @endif
                @if ($t->mode) <span class="badge bg-purple-lt ms-1">{{ $t->mode }}</span> @endif
                @if ($t->is_mandatory) <span class="badge bg-red-lt ms-1">mandatory</span> @endif
            </div>
            <a href="{{ route('utils.approvals.admin.rules', ['topic' => $t->id]) }}" class="small">{{ $row['rules'] }} rule(s)</a>
            @if ($editable)
                <a href="{{ route('utils.approvals.admin.topics', ['edit' => $t->id]) }}" class="btn btn-sm btn-ghost-primary"><i class="la la-edit"></i></a>
            @endif
        </div>
    @empty
        <div class="list-group-item text-muted">No topics yet.</div>
    @endforelse
</div>
