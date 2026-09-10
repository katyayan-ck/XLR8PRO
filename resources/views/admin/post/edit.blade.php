@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h2 class="text-capitalize mb-0">Edit Post</h2>
        <p class="ml-2 ml-md-4 mb-0"><code>{{ $post->post_code }}</code></p>
    </section>
@endsection

@section('content')
<div class="container-fluid animated fadeIn">
    <form method="POST" action="{{ backpack_url('post/'.$post->id) }}">
        @csrf @method('PUT')

        {{-- Read-only Info --}}
        <div class="card">
            <div class="card-header"><strong>Post Info</strong> <small class="text-muted ml-2">(Designation/Org cannot be changed — delete and recreate if needed)</small></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3"><label>Post Code</label><p class="form-control-plaintext"><code>{{ $post->post_code }}</code></p></div>
                    <div class="col-md-3"><label>Designation</label><p class="form-control-plaintext">{{ $post->designation?->name ?? '—' }}</p></div>
                    <div class="col-md-3"><label>Branch</label><p class="form-control-plaintext">{{ $post->branch?->name ?? '—' }}</p></div>
                    <div class="col-md-3"><label>Department</label><p class="form-control-plaintext">{{ $post->department?->name ?? '—' }}</p></div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-2 form-group">
                        <label>Max Occupants</label>
                        <input type="number" name="max_occupants" value="{{ old('max_occupants', $post->max_occupants) }}"
                               class="form-control" min="1" max="10">
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end pb-2">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active"
                                   name="is_active" value="1" {{ $post->is_active ? 'checked' : '' }}>
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
                <small class="ml-2 text-muted">Existing scopes will be replaced on save</small>
                <button type="button" class="btn btn-sm btn-outline-primary ml-auto" id="addOrgScope">
                    <i class="la la-plus"></i> Add Scope
                </button>
            </div>
            <div class="card-body" id="orgScopesContainer">
                <div class="row font-weight-bold mb-1 px-2">
                    <div class="col-5">Scope Type</div>
                    <div class="col-5">Scope Value</div>
                    <div class="col-2"></div>
                </div>
                @foreach($post->orgScopes as $i => $scope)
                <div class="row mb-2 scope-row align-items-center px-2">
                    <div class="col-5">
                        <select name="org_scopes[{{ $i }}][type]" class="form-control form-control-sm">
                            @foreach($orgScopeTypes as $t)
                                <option value="{{ $t }}" {{ $scope->scope_type === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <input type="text" name="org_scopes[{{ $i }}][value]"
                               value="{{ $scope->scope_value }}"
                               class="form-control form-control-sm" placeholder="blank = wildcard">
                    </div>
                    <div class="col-2">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.scope-row').remove()">
                            <i class="la la-trash"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Vehicle Scopes --}}
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <strong>Vehicle Scopes</strong>
                <button type="button" class="btn btn-sm btn-outline-primary ml-auto" id="addVehicleScope">
                    <i class="la la-plus"></i> Add Scope
                </button>
            </div>
            <div class="card-body" id="vehicleScopesContainer">
                <div class="row font-weight-bold mb-1 px-2">
                    <div class="col-5">Scope Type</div>
                    <div class="col-5">Scope Value</div>
                    <div class="col-2"></div>
                </div>
                @foreach($post->vehicleScopes as $i => $scope)
                <div class="row mb-2 scope-row align-items-center px-2">
                    <div class="col-5">
                        <select name="vehicle_scopes[{{ $i }}][type]" class="form-control form-control-sm">
                            @foreach($vehicleScopeTypes as $t)
                                <option value="{{ $t }}" {{ $scope->scope_type === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <input type="text" name="vehicle_scopes[{{ $i }}][value]"
                               value="{{ $scope->scope_value }}"
                               class="form-control form-control-sm" placeholder="blank = wildcard">
                    </div>
                    <div class="col-2">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.scope-row').remove()">
                            <i class="la la-trash"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="mb-4">
            <button type="submit" class="btn btn-success"><i class="la la-save"></i> Update Post</button>
            <a href="{{ backpack_url('post') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('after_scripts')
<script>
const orgTypes     = @json($orgScopeTypes);
const vehicleTypes = @json($vehicleScopeTypes);
let orgIdx = {{ $post->orgScopes->count() }};
let vehIdx = {{ $post->vehicleScopes->count() }};

function makeScopeRow(prefix, types, index) {
    const options = types.map(t => `<option value="${t}">${t}</option>`).join('');
    return `
    <div class="row mb-2 scope-row align-items-center px-2">
        <div class="col-5">
            <select name="${prefix}[${index}][type]" class="form-control form-control-sm">${options}</select>
        </div>
        <div class="col-5">
            <input type="text" name="${prefix}[${index}][value]" class="form-control form-control-sm" placeholder="blank = wildcard">
        </div>
        <div class="col-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.scope-row').remove()">
                <i class="la la-trash"></i>
            </button>
        </div>
    </div>`;
}

document.getElementById('addOrgScope').addEventListener('click', () => {
    document.getElementById('orgScopesContainer').insertAdjacentHTML('beforeend', makeScopeRow('org_scopes', orgTypes, orgIdx++));
});
document.getElementById('addVehicleScope').addEventListener('click', () => {
    document.getElementById('vehicleScopesContainer').insertAdjacentHTML('beforeend', makeScopeRow('vehicle_scopes', vehicleTypes, vehIdx++));
});
</script>
@endpush