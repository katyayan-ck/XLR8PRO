@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@include('admin.utils.platform.approvals.admin._nav')
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0"><i class="la la-sitemap me-1"></i>Topic tree</h3></div>
                <x-approval.topic-tree :tree="$tree" :editable="true" />
            </div>
        </div>
        <div class="col-lg-5">
            <form method="POST" action="{{ route('utils.approvals.admin.topics.save') }}" class="card">
                @csrf
                <input type="hidden" name="id" value="{{ $editing?->id }}">
                <div class="card-header d-flex justify-content-between">
                    <h3 class="card-title mb-0">{{ $editing ? 'Edit '.$editing->code : 'New topic' }}</h3>
                    @if ($editing) <a href="{{ route('utils.approvals.admin.topics') }}" class="small">+ new instead</a> @endif
                </div>
                <div class="card-body row g-2">
                    <div class="col-12">
                        <label class="form-label required">Code</label>
                        <input type="text" name="code" maxlength="100" class="form-control" value="{{ old('code', $editing?->code) }}" @readonly($editing) placeholder="DISCOUNT.EXTRA" required>
                        <div class="form-text">Stable; cannot change once saved.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label required">Title</label>
                        <input type="text" name="title" maxlength="150" class="form-control" value="{{ old('title', $editing?->title) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Parent</label>
                        <select name="parent_id" class="form-select">
                            <option value="">— Main topic —</option>
                            @foreach ($options as $id => $label)
                                @continue($editing && $id === $editing->id)
                                <option value="{{ $id }}" @selected((int) old('parent_id', $editing?->parent_id) === $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Item key</label>
                        <input type="text" name="item_key" maxlength="60" class="form-control" value="{{ old('item_key', $editing?->item_key) }}" placeholder="extra_disc">
                        <div class="form-text">Only on items (requests attach here).</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Mode</label>
                        <select name="mode" class="form-select">
                            <option value="">Inherit (default OPEN_TO_ALL)</option>
                            @foreach (\App\Services\Platform\Approval\Entities\ApprovalTopicService::MODES as $mode)
                                <option value="{{ $mode }}" @selected(old('mode', $editing?->mode) === $mode)>{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Value type</label>
                        <select name="value_type" class="form-select">
                            <option value="">Inherit</option>
                            @foreach (\App\Services\Platform\Approval\Entities\ApprovalTopicService::VALUE_TYPES as $type)
                                <option value="{{ $type }}" @selected(old('value_type', $editing?->value_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 d-flex flex-column justify-content-end">
                        <label class="form-check"><input type="checkbox" name="is_mandatory" value="1" class="form-check-input" @checked(old('is_mandatory', $editing?->is_mandatory))> <span class="form-check-label">Mandatory</span></label>
                        <label class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $editing?->is_active ?? true))> <span class="form-check-label">Active</span></label>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $editing?->description) }}</textarea>
                    </div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary">Save topic</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
