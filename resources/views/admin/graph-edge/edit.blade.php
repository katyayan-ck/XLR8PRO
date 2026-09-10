@extends(backpack_view('blank'))

@section('title', 'Edit Graph Edge')

@push('after_styles')
<style>
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
    }
    .readonly-value {
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 10px 15px;
        min-height: 42px;
        display: flex;
        align-items: center;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">Edit Graph Edge</h2>
                </div>
                <div class="card-body">

                    <form method="POST" action="{{ backpack_url('graph-edge/' . $edge->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Edge ID</label>
                                        <div class="readonly-value">{{ $edge->id }}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Created At</label>
                                        <div class="readonly-value">
                                            {{ $edge->created_at?->format('d-m-Y H:i') ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>From Node <span class="text-danger">*</span></label>
                                <select name="from_node_id" class="form-control form-select" required>
                                    <option value="">Select From Node</option>
                                    @foreach(\App\Models\Core\GraphNode::with('user')->get() as $node)
                                        <option value="{{ $node->id }}" {{ old('from_node_id', $edge->from_node_id) == $node->id ? 'selected' : '' }}>
                                            {{ $node->user?->name ?? 'Node #'.$node->id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>To Node <span class="text-danger">*</span></label>
                                <select name="to_node_id" class="form-control form-select" required>
                                    <option value="">Select To Node</option>
                                    @foreach(\App\Models\Core\GraphNode::with('user')->get() as $node)
                                        <option value="{{ $node->id }}" {{ old('to_node_id', $edge->to_node_id) == $node->id ? 'selected' : '' }}>
                                            {{ $node->user?->name ?? 'Node #'.$node->id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Type <span class="text-danger">*</span></label>
                                <select name="type" class="form-control form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="reports_to" {{ old('type', $edge->type) == 'reports_to' ? 'selected' : '' }}>Reports To</option>
                                    <option value="approves" {{ old('type', $edge->type) == 'approves' ? 'selected' : '' }}>Approves</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Level</label>
                                <input type="number" name="level" class="form-control" value="{{ old('level', $edge->level) }}">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label>Powers (JSON)</label>
                                <textarea name="powers" class="form-control" rows="5">{{ old('powers', $edge->powers) }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Update Edge
                            </button>
                            <a href="{{ backpack_url('graph-edge') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
