@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('utils.approvals.store') }}" class="card">
                @csrf
                @if ($ref['type'])
                    <input type="hidden" name="ref_type" value="{{ $ref['type'] }}">
                    <input type="hidden" name="ref_id" value="{{ $ref['id'] }}">
                @endif
                <div class="card-header"><h3 class="card-title mb-0">{{ $title }}</h3></div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label required">What are you asking for?</label>
                        <select name="item_key" class="form-select" required>
                            @foreach ($items as $item)
                                <option value="{{ $item->item_key }}" @selected(old('item_key') === $item->item_key)>{{ $item->title }} ({{ $item->code }})</option>
                            @endforeach
                        </select>
                        @if ($items->isEmpty()) <div class="form-text text-danger">No approval items are set up yet.</div> @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Ask</label>
                        <input type="number" step="0.01" min="0" name="asked" required class="form-control" value="{{ old('asked') }}">
                    </div>
                    @foreach (['model' => 'Model', 'variant' => 'Variant', 'segment' => 'Segment', 'branch' => 'Branch (blank = yours)', 'channel' => 'Channel'] as $dim => $label)
                        <div class="col-md-4">
                            <label class="form-label">{{ $label }}</label>
                            <input type="text" name="scope[{{ $dim }}]" maxlength="50" class="form-control" value="{{ old('scope.'.$dim) }}">
                        </div>
                    @endforeach
                    @if ($ref['type'])
                        <div class="col-12 small text-muted">For {{ config('platform.entities.'.strtoupper($ref['type']).'.label', $ref['type']) }} #{{ $ref['id'] }}</div>
                    @endif
                    <div class="col-12">
                        <label class="form-label">Why</label>
                        <textarea name="remark" rows="3" maxlength="1000" class="form-control">{{ old('remark') }}</textarea>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('utils.approvals.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button class="btn btn-primary">Raise request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
