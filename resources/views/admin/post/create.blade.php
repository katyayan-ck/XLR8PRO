@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Add Post</h2>
        <p class="ml-2 ml-md-4 mb-0">Create a new IAM Post with org and vehicle scopes.</p>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('post') }}" id="postForm">
        @csrf

        {{-- Core Fields --}}
        <div class="card">
            <div class="card-header"><strong>Post Details</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Designation <span class="text-danger">*</span></label>
                        <select name="desig_code" class="form-control @error('desig_code') is-invalid @enderror" required>
                            <option value="">-- Select Designation --</option>
                            @foreach($designations as $d)
                                <option value="{{ $d->code }}" {{ old('desig_code') == $d->code ? 'selected' : '' }}>
                                    {{ $d->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('desig_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Branch</label>
                        <select name="branch_code" class="form-control">
                            <option value="">-- All Branches --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->code }}" {{ old('branch_code') == $b->code ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Department</label>
                        <select name="dept_code" class="form-control">
                            <option value="">-- All Departments --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->code }}" {{ old('dept_code') == $d->code ? 'selected' : '' }}>
                                    {{ $d->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Division</label>
                        <select name="div_code" class="form-control">
                            <option value="">-- All Divisions --</option>
                            @foreach($divisions as $d)
                                <option value="{{ $d->code }}" {{ old('div_code') == $d->code ? 'selected' : '' }}>
                                    {{ $d->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Location</label>
                        <select name="loc_code" class="form-control">
                            <option value="">-- All Locations --</option>
                            @foreach($locations as $l)
                                <option value="{{ $l->code }}" {{ old('loc_code') == $l->code ? 'selected' : '' }}>
                                    {{ $l->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Max Occupants</label>
                        <input type="number" name="max_occupants" value="{{ old('max_occupants', 1) }}"
                               class="form-control" min="1" max="10">
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end pb-2">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active"
                                   name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Org Scopes --}}
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <strong>Org Scopes</strong>
                <small class="ml-2 text-muted">Leave blank for wildcard (all access)</small>
                <button type="button" class="btn btn-sm btn-outline-primary ml-auto" id="addOrgScope">
                    <i class="la la-plus"></i> Add Scope
                </button>
            </div>
            <div class="card-body" id="orgScopesContainer">
                <div class="row font-weight-bold mb-1 px-2">
                    <div class="col-5">Scope Type</div>
                    <div class="col-5">Scope Value <small class="text-muted">(blank = wildcard)</small></div>
                    <div class="col-2"></div>
                </div>
                {{-- rows injected by JS --}}
            </div>
        </div>

        {{-- Vehicle Scopes --}}
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <strong>Vehicle Scopes</strong>
                <small class="ml-2 text-muted">Leave blank for wildcard (all access)</small>
                <button type="button" class="btn btn-sm btn-outline-primary ml-auto" id="addVehicleScope">
                    <i class="la la-plus"></i> Add Scope
                </button>
            </div>
            <div class="card-body" id="vehicleScopesContainer">
                <div class="row font-weight-bold mb-1 px-2">
                    <div class="col-5">Scope Type</div>
                    <div class="col-5">Scope Value <small class="text-muted">(blank = wildcard)</small></div>
                    <div class="col-2"></div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <button type="submit" class="btn btn-success"><i class="la la-save"></i> Save Post</button>
            <a href="{{ backpack_url('post') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('after_scripts')
<script>
const orgTypes     = @json(\App\Models\IAM\PostOrgScope::TYPES);
const vehicleTypes = @json(\App\Models\IAM\PostVehicleScope::TYPES);

function makeScopeRow(prefix, types, index) {
    const options = types.map(t => `<option value="${t}">${t}</option>`).join('');
    return `
    <div class="row mb-2 scope-row align-items-center px-2" id="${prefix}_row_${index}">
        <div class="col-5">
            <select name="${prefix}[${index}][type]" class="form-control form-control-sm">
                ${options}
            </select>
        </div>
        <div class="col-5">
            <input type="text" name="${prefix}[${index}][value]" class="form-control form-control-sm"
                   placeholder="e.g. NKH (blank = wildcard)">
        </div>
        <div class="col-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.scope-row').remove()">
                <i class="la la-trash"></i>
            </button>
        </div>
    </div>`;
}

let orgIdx = 0, vehIdx = 0;

document.getElementById('addOrgScope').addEventListener('click', function() {
    document.getElementById('orgScopesContainer').insertAdjacentHTML('beforeend',
        makeScopeRow('org_scopes', orgTypes, orgIdx++));
});

document.getElementById('addVehicleScope').addEventListener('click', function() {
    document.getElementById('vehicleScopesContainer').insertAdjacentHTML('beforeend',
        makeScopeRow('vehicle_scopes', vehicleTypes, vehIdx++));
});
</script>
@endpush