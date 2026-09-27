@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('utils.tickets.store') }}" enctype="multipart/form-data" class="card">
                @csrf
                @if ($ref['type'])
                    <input type="hidden" name="ref_type" value="{{ $ref['type'] }}">
                    <input type="hidden" name="ref_id" value="{{ $ref['id'] }}">
                @endif
                <div class="card-header"><h3 class="card-title mb-0">{{ $title }}</h3></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label required">What is wrong?</label>
                        <input type="text" name="title" maxlength="250" required class="form-control" value="{{ old('title') }}" placeholder="e.g. Quote PDF gives an error">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Category</label>
                        <select name="category" class="form-select" required>
                            @foreach ($categories as $code => $label)
                                <option value="{{ $code }}" @selected(old('category') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Priority</label>
                        <select name="priority" class="form-select" required>
                            @foreach ($priorities as $code => $label)
                                <option value="{{ $code }}" @selected(old('priority', 'P3') === $code)>{{ $label }} ({{ app(\App\Services\Platform\Ticket\TicketService::class)->slaHours($code) }}h SLA)</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($ref['type'])
                        <div class="col-12 small text-muted">Linked to {{ config('platform.entities.'.strtoupper($ref['type']).'.label', $ref['type']) }} #{{ $ref['id'] }}</div>
                    @endif
                    <div class="col-12">
                        <label class="form-label">Details</label>
                        <textarea name="details" rows="6" maxlength="20000" class="form-control" placeholder="Steps, what you expected, what happened">{{ old('details') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Screenshot / file</label>
                        <x-ui.upload name="file" />
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('utils.tickets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button class="btn btn-primary">Open ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
