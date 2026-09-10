@extends(backpack_view('blank'))

@section('title', 'Add New Graph Edge')

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
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-black">
                    <h2 class="mb-0">Add New Graph Edge</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ backpack_url('graph-edge') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>From Node <span class="text-danger">*</span></label>
                                <select name="from_node_id" class="form-control form-select" required>
                                    <option value="">Select From Node</option>
                                    @foreach(\App\Models\Core\GraphNode::with('user')->get() as $node)
                                    <option value="{{ $node->id }}" {{ old('from_node_id')==$node->id ? 'selected' : ''
                                        }}>
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
                                    <option value="{{ $node->id }}" {{ old('to_node_id')==$node->id ? 'selected' : ''
                                        }}>
                                        {{ $node->user?->name ?? 'Node #'.$node->id }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Type <span class="text-danger">*</span></label>
                                <select name="type" class="form-control form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="reports_to" {{ old('type')=='reports_to' ? 'selected' : '' }}>Reports
                                        To</option>
                                    <option value="approves" {{ old('type')=='approves' ? 'selected' : '' }}>Approves
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Level</label>
                                <input type="number" name="level" class="form-control" value="{{ old('level') }}">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label>Powers (JSON)</label>
                                <textarea name="powers" class="form-control" rows="5"
                                    placeholder='{"permission": "manage_users"}'>{{ old('powers') }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> Create Edge
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