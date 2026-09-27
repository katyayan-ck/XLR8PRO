@props(['box' => 'ASSIGNED', 'rows' => null, 'limit' => 10])
{{-- A task inbox (FRS TSK-04). Pass :rows from the controller, or let the component load the user's box. --}}
@php
    $rows ??= app(\App\Services\Platform\Task\TaskService::class)->inbox(backpack_user()->id, $box, [], $limit);
    $statusColor = ['FRESH' => 'blue', 'INPROGRESS' => 'cyan', 'HOLD' => 'orange', 'SUBMITTED' => 'purple', 'CLOSED' => 'secondary', 'REOPENED' => 'red'];
@endphp
<div class="table-responsive">
    <table class="table table-vcenter card-table">
        <thead>
            <tr><th>Task</th><th>Status</th><th>Priority</th><th>Owner</th><th>Deadline</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $task)
                @php $overdue = $task->deadline && $task->status !== 'CLOSED' && $task->deadline->isPast(); @endphp
                <tr>
                    <td>
                        <a href="{{ route('utils.tasks.show', $task->id) }}" class="fw-medium">{{ $task->title }}</a>
                        <div class="small text-muted">
                            #{{ $task->id }}
                            @if ($task->is_group) · <span class="badge bg-azure-lt">group</span> @endif
                            @if ($task->ref_type) · {{ config('platform.entities.'.$task->ref_type.'.label', $task->ref_type) }} #{{ $task->ref_id }} @endif
                        </div>
                    </td>
                    <td><span class="badge bg-{{ $statusColor[$task->status] ?? 'secondary' }}-lt">{{ $task->status }}</span></td>
                    <td>{{ $task->priority }}</td>
                    <td class="small">{{ $task->owner?->display_name }}</td>
                    <td class="small {{ $overdue ? 'text-danger fw-bold' : '' }}">
                        {{ site_datetime($task->deadline, '—') }}
                        @if ($overdue) <div>overdue</div> @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No tasks here.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
