@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@include('admin.utils.platform.approvals.admin._nav')
@php
    $levels = old('levels', $rule?->levels->map(fn ($l) => $l->only(['level_no', 'designation_code', 'value_type', 'std_value', 'min_value', 'max_value']))->all() ?? []);
    $levels = array_values(array_pad($levels, max(count($levels) + 2, 4), []));
@endphp
<div class="container-fluid">
    <form method="POST" action="{{ $rule ? route('utils.approvals.admin.rules.update', $rule->id) : route('utils.approvals.admin.rules.store') }}" class="card">
        @csrf
        @if ($rule) @method('PUT') @endif
        <div class="card-header"><h3 class="card-title mb-0">{{ $title }}</h3></div>
        <div class="card-body row g-2">
            <div class="col-md-6">
                <label class="form-label required">Topic</label>
                <select name="topic_id" class="form-select" required>
                    @foreach ($options as $id => $label)
                        <option value="{{ $id }}" @selected((int) old('topic_id', $rule?->topic_id ?? $topicId) === $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Valid from</label><input type="date" name="valid_from" class="form-control" value="{{ old('valid_from', $rule?->valid_from?->format('Y-m-d')) }}"></div>
            <div class="col-md-2"><label class="form-label">Valid to</label><input type="date" name="valid_to" class="form-control" value="{{ old('valid_to', $rule?->valid_to?->format('Y-m-d')) }}"></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $rule?->is_active ?? true))> <span class="form-check-label">Active</span></label></div>

            <div class="col-12 mt-2"><div class="text-muted small">Scope — leave blank for ANY. Precedence: variant › model › segment › permit › desk › branch › zone › company.</div></div>
            @foreach (\App\Models\Approval\ApprovalRule::SCOPES as $dim)
                <div class="col-md-2 col-6">
                    <label class="form-label small">{{ ucfirst($dim) }}</label>
                    <input type="text" name="{{ $dim }}_code" maxlength="50" class="form-control form-control-sm" value="{{ old($dim.'_code', $rule?->{$dim.'_code'}) }}">
                </div>
            @endforeach
            <div class="col-12"><label class="form-label">Note</label><input type="text" name="note" maxlength="250" class="form-control" value="{{ old('note', $rule?->note) }}"></div>

            <div class="col-12 mt-3">
                <table class="table table-sm">
                    <thead><tr><th style="width: 80px;">Level</th><th>Designation</th><th>Value type</th><th>Std</th><th>Min</th><th>Max</th></tr></thead>
                    <tbody>
                        @foreach ($levels as $i => $l)
                            <tr>
                                <td><input type="number" min="1" name="levels[{{ $i }}][level_no]" class="form-control form-control-sm" value="{{ $l['level_no'] ?? '' }}"></td>
                                <td>
                                    <select name="levels[{{ $i }}][designation_code]" class="form-select form-select-sm">
                                        <option value=""></option>
                                        @foreach ($designations as $code => $name)
                                            <option value="{{ $code }}" @selected(($l['designation_code'] ?? '') === $code)>{{ $name }} ({{ $code }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="levels[{{ $i }}][value_type]" class="form-select form-select-sm">
                                        @foreach (\App\Services\Platform\Approval\Entities\ApprovalTopicService::VALUE_TYPES as $type)
                                            <option value="{{ $type }}" @selected(($l['value_type'] ?? 'AMOUNT') === $type)>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                @foreach (['std_value', 'min_value', 'max_value'] as $col)
                                    <td><input type="number" step="0.01" min="0" name="levels[{{ $i }}][{{ $col }}]" class="form-control form-control-sm" value="{{ isset($l[$col]) ? (float) $l[$col] : '' }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="form-text">Rows without a level number are ignored. Save and edit again for more rows.</div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('utils.approvals.admin.rules', ['topic' => $rule?->topic_id ?? $topicId]) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary">Save rule</button>
        </div>
    </form>
</div>
@endsection
