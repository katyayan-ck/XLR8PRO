@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Edit Tree Entry</h2>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('desig-dept-tree/'.$entry->id) }}">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-header"><strong>Edit Details</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Designation <span class="text-danger">*</span></label>
                        <select name="desig_code" class="form-control" required>
                            @foreach($designations as $d)
                                <option value="{{ $d->code }}" {{ $entry->desig_code == $d->code ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Branch</label>
                        <select name="branch_code" class="form-control">
                            <option value="">-- All --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->code }}" {{ $entry->branch_code == $b->code ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Department</label>
                        <select name="dept_code" class="form-control">
                            <option value="">-- All --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->code }}" {{ $entry->dept_code == $d->code ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Division</label>
                        <select name="div_code" class="form-control">
                            <option value="">-- All --</option>
                            @foreach($divisions as $d)
                                <option value="{{ $d->code }}" {{ $entry->div_code == $d->code ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Hierarchy Level <span class="text-danger">*</span></label>
                        <input type="number" name="hierarchy_level" value="{{ old('hierarchy_level', $entry->hierarchy_level) }}" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Rank</label>
                        <input type="number" name="rank" value="{{ old('rank', $entry->rank) }}" class="form-control" min="0">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Reports To (Parent)</label>
                        <select name="parent_id" class="form-control">
                            <option value="">-- None --</option>
                            @foreach($parents as $p)
                                <option value="{{ $p->id }}" {{ $entry->parent_id == $p->id ? 'selected' : '' }}>
                                    L{{ $p->hierarchy_level }} — {{ $p->designation?->name ?? $p->desig_code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end pb-2">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_top_mgmt" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_top_mgmt" name="is_top_mgmt" value="1" {{ $entry->is_top_mgmt ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_top_mgmt">Top Management</label>
                        </div>
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end pb-2">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ $entry->is_active ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <button type="submit" class="btn btn-success"><i class="la la-save"></i> Update</button>
            <a href="{{ backpack_url('desig-dept-tree') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection