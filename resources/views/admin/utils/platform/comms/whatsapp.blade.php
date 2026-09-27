@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-4">
            <x-whatsapp.inbox :threads="$threads" :box="$box" :active="$thread?->id" />
        </div>
        <div class="col-lg-8">
            @if ($thread)
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h3 class="card-title mb-0">{{ $thread->title ?? ($thread->person_code ?? $contacts->mask('+'.$thread->wa_id)) }}</h3>
                            <div class="small text-muted">{{ $contacts->mask('+'.$thread->wa_id) }}{{ $thread->assignee ? ' · assigned to '.$thread->assignee->display_name : ' · unassigned' }}{{ $thread->ref_type ? ' · linked to '.$thread->ref_type.' #'.$thread->ref_id : '' }}</div>
                        </div>
                        <form method="POST" action="{{ route('utils.whatsapp.manage', $thread->id) }}" class="d-flex flex-wrap gap-1">
                            @csrf
                            <select name="assigned_to" class="form-select form-select-sm" style="max-width: 200px;">
                                <option value="">Unassigned</option>
                                <option value="{{ backpack_user()->id }}">Me</option>
                                @foreach ($team as $id => $label) <option value="{{ $id }}" @selected($thread->assigned_to === $id)>{{ $label }}</option> @endforeach
                            </select>
                            <select name="label" class="form-select form-select-sm" style="max-width: 110px;">
                                @foreach (['OPEN', 'PENDING', 'DONE'] as $l) <option value="{{ $l }}" @selected($thread->label === $l)>{{ $l }}</option> @endforeach
                            </select>
                            <input type="text" name="ref_type" class="form-control form-control-sm" style="max-width: 100px;" placeholder="Link: type">
                            <input type="number" name="ref_id" class="form-control form-control-sm" style="max-width: 90px;" placeholder="id">
                            <button class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                    </div>
                    <x-whatsapp.thread :thread="$thread" :messages="$messages" :docs="$docs" />
                    <x-whatsapp.composer :thread="$thread" />
                </div>
            @else
                <div class="card"><div class="card-body text-muted text-center py-5">Pick a conversation.</div></div>
            @endif
        </div>
    </div>
</div>
@endsection
