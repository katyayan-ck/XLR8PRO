@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $statusColor = ['FRESH' => 'blue', 'INPROGRESS' => 'cyan', 'HOLD' => 'orange', 'SUBMITTED' => 'purple', 'CLOSED' => 'secondary', 'REOPENED' => 'red'];
    $can = $task['user_can'];
@endphp
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="card-title mb-1">{{ $task['title'] }}</h3>
                        <div class="small text-muted">
                            #{{ $task['id'] }} · {{ $task['type_label'] }} · {{ $task['priority_label'] }}
                            @if ($task['is_group']) · <span class="badge bg-azure-lt">group</span> @endif
                            · your role: <strong>{{ $task['user_role'] }}</strong>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-{{ $statusColor[$task['status']] ?? 'secondary' }} text-white fs-5">{{ $task['status'] }}</span>
                        @if (in_array('edit', $can, true))
                            <a href="{{ route('utils.tasks.edit', $task['id']) }}" class="btn btn-sm btn-outline-primary"><i class="la la-edit"></i> Edit</a>
                        @endif
                        @if (in_array('delete', $can, true))
                            <form method="POST" action="{{ route('utils.tasks.destroy', $task['id']) }}" onsubmit="return confirm('Delete this task? Its history and files are kept.')">
                                @csrf @method('DELETE')
                                <input type="hidden" name="confirm" value="1">
                                <button class="btn btn-sm btn-outline-danger"><i class="la la-trash"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if ($task['details'])
                        <div class="mb-3">{!! $task['details'] !!}</div>
                    @endif
                    <x-task.composer :task="$task" />
                </div>
            </div>

            <x-chat.thread :model="$model" title="Timeline" :composer="false" />
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Deadline</dt>
                        <dd class="col-7 {{ $task['is_overdue'] ? 'text-danger fw-bold' : '' }}">
                            {{ site_datetime($task['deadline'], '—') }}
                            @if ($task['days_left'] !== null && $task['status'] !== 'CLOSED')
                                <div>{{ $task['is_overdue'] ? abs($task['days_left']).' day(s) overdue' : $task['days_left'].' day(s) left' }}</div>
                            @endif
                        </dd>
                        <dt class="col-5">Age</dt><dd class="col-7">{{ $task['age_days'] }} day(s)</dd>
                        <dt class="col-5">Owner</dt><dd class="col-7">{{ $task['owner']['name'] }}</dd>
                        @foreach (['assignees' => 'Assignees', 'followers' => 'Followers', 'snoopers' => 'Snoopers'] as $key => $label)
                            <dt class="col-5">{{ $label }}</dt>
                            <dd class="col-7">{{ collect($task['people'][$key])->pluck('name')->join(', ') ?: '—' }}</dd>
                        @endforeach
                        @if ($task['ref_type'])
                            <dt class="col-5">Linked to</dt>
                            <dd class="col-7">
                                @if ($task['ref_url'])
                                    <a href="{{ $task['ref_url'] }}">{{ config('platform.entities.'.$task['ref_type'].'.label', $task['ref_type']) }} #{{ $task['ref_id'] }}</a>
                                @else
                                    {{ $task['ref_type'] }} #{{ $task['ref_id'] }}
                                @endif
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
            <x-docs.uploader :model="$model" title="Attachments" />
        </div>
    </div>
</div>
@endsection
