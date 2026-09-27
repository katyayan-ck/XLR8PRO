@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@php
    $selected = fn (string $key) => collect(old($key, collect($task['people'][$key] ?? [])->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
    $assigneeIds = $selected('assignees');
    $followerIds = $selected('followers');
    $snooperIds = $selected('snoopers');
@endphp
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <form method="POST" action="{{ $task ? route('utils.tasks.update', $task['id']) : route('utils.tasks.store') }}" class="card">
                @csrf
                @if ($task) @method('PUT') @endif
                @if ($ref && $ref['type'])
                    <input type="hidden" name="ref_type" value="{{ $ref['type'] }}">
                    <input type="hidden" name="ref_id" value="{{ $ref['id'] }}">
                @endif
                <div class="card-header"><h3 class="card-title mb-0">{{ $title }}</h3></div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label required">Title</label>
                        <input type="text" name="title" maxlength="250" required class="form-control" value="{{ old('title', $task['title'] ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Type</label>
                        <select name="type" id="task-type" class="form-select" required>
                            @foreach ($types as $code => $label)
                                <option value="{{ $code }}" @selected(old('type', $task['type'] ?? 'ASSIGNED_TASK') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach ($priorities as $code => $label)
                                <option value="{{ $code }}" @selected(old('priority', $task['priority'] ?? 'NORMAL') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Deadline</label>
                        <x-ui.date name="deadline" :value="$task['deadline'] ?? null" time />
                    </div>
                    @if ($ref && $ref['type'])
                        <div class="col-md-4">
                            <label class="form-label">Linked to</label>
                            <div class="form-control-plaintext">{{ config('platform.entities.'.strtoupper($ref['type']).'.label', $ref['type']) }} #{{ $ref['id'] }}</div>
                        </div>
                    @endif
                    <div class="col-md-4" id="assignees-box">
                        <label class="form-label">Assignees</label>
                        <x-ui.select name="assignees[]" :options="$team" :selected="$assigneeIds" multiple placeholder="Pick assignees" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Followers <span class="text-muted small">(may remark)</span></label>
                        <x-ui.select name="followers[]" :options="$team" :selected="$followerIds" multiple placeholder="Pick followers" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Snoopers <span class="text-muted small">(read only)</span></label>
                        <x-ui.select name="snoopers[]" :options="$team" :selected="$snooperIds" multiple placeholder="Pick snoopers" />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Details</label>
                        <textarea name="details" rows="5" maxlength="20000" class="form-control">{{ old('details', strip_tags($task['details'] ?? '')) }}</textarea>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ $task ? route('utils.tasks.show', $task['id']) : route('utils.tasks.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button class="btn btn-primary">{{ $task ? 'Save changes' : 'Create task' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
    (function () {
        const type = document.getElementById('task-type');
        const box = document.getElementById('assignees-box');
        const sync = () => { box.style.display = /SELF/.test(type.value) ? 'none' : ''; };
        type.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
