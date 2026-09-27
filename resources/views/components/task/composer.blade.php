@props(['task'])
{{-- Follow-up form (FRS §5.2: remark and/or status change in one form). $task is the TaskService DTO. --}}
@php
    $can = $task['user_can'];
    $targets = collect($can)->filter(fn ($r) => str_starts_with($r, 'status:'))->map(fn ($r) => substr($r, 7))->values();
    $labels = ['INPROGRESS' => 'In progress', 'HOLD' => 'On hold', 'SUBMITTED' => 'Submit for review', 'CLOSED' => 'Close', 'REOPENED' => 'Reopen'];
@endphp
@if (in_array('remark', $can, true))
    <form method="POST" action="{{ route('utils.tasks.follow-up', $task['id']) }}" enctype="multipart/form-data">
        @csrf
        <textarea name="remark" rows="2" maxlength="5000" class="form-control mb-2" placeholder="Follow-up remark… (@username to mention)"></textarea>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <select name="status" class="form-select form-select-sm">
                <option value="NO_CHANGE">Status: no change ({{ $task['status'] }})</option>
                @foreach ($targets as $to)
                    <option value="{{ $to }}">{{ $labels[$to] ?? $to }}</option>
                @endforeach
            </select>
            <div class="w-100"><x-ui.upload name="file" /></div>
            <button class="btn btn-sm btn-primary ms-auto"><i class="la la-paper-plane me-1"></i>Save follow-up</button>
        </div>
    </form>
@else
    <div class="text-muted small"><i class="la la-eye me-1"></i>You can read this task but not follow it up.</div>
@endif
