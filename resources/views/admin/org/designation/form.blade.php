@extends(backpack_view('blank'))

@section('title', $title ?? 'Designation Form')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- MAIN FORM CARD --}}
            <div class="card">
                <div class="card-header text-body">
                    <h2 class="mb-0">
                        {{ isset($designation) ? 'Edit Designation Information' : 'Add New Designation' }}
                    </h2>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors->all() as $error): ?>
                                    <li>{{ $error }}</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ isset($designation) ? backpack_url('org/designation/' . $designation->id) : backpack_url('org/designation') }}" enctype="multipart/form-data">
                        @csrf
                        @if(isset($designation))
                            @method('PUT')
                        @endif

                        <div class="row">
                            {{-- CODE --}}
                            <div class="col-md-3 mb-3">
                                <label>
                                    Designation Code
                                    @if(!isset($designation)) <span class="text-danger">*</span> @endif
                                </label>
                                
                                @if(isset($designation))
                                    <!-- Visually disabled field for the user -->
                                    <input type="text" class="form-control" value="{{ $designation->code }}" readonly disabled>
                                    <!-- Hidden field to safely pass validation -->
                                    <input type="hidden" name="code" value="{{ $designation->code }}">
                                    <div class="form-text">Code cannot be changed after creation.</div>
                                @else
                                    <input type="text" name="code" class="form-control text-uppercase" value="{{ old('code') }}" maxlength="10" minlength="3" required>
                                    <div id="codeError" class="text-danger mt-1"></div>
                                @endif
                            </div>

                            {{-- NAME --}}
                            <div class="col-md-3 mb-3">
                                <label>Designation Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $designation->name ?? '') }}" required>
                            </div>

                            {{-- RANK --}}
                            <div class="col-md-3 mb-3">
                                <label>Rank</label>
                                <select name="rank" class="form-control form-select">
                                    <option value="">Select Rank</option>
                                    <option value="1" {{ old('rank', $designation->rank ?? '') == 1 ? 'selected' : '' }}>A</option>
                                    <option value="2" {{ old('rank', $designation->rank ?? '') == 2 ? 'selected' : '' }}>B</option>
                                    <option value="3" {{ old('rank', $designation->rank ?? '') == 3 ? 'selected' : '' }}>C</option>
                                    <option value="4" {{ old('rank', $designation->rank ?? '') == 4 ? 'selected' : '' }}>D</option>
                                    <option value="5" {{ old('rank', $designation->rank ?? '') == 5 ? 'selected' : '' }}>E</option>
                                </select>
                            </div>

                            {{-- REPORTS TO --}}
                            <div class="col-md-3 mb-3">
                                <label>Reports To</label>
                                <select name="parent_desig_code" class="form-control form-select">
                                    <option value="">-- Select Designation --</option>
                                    <?php 
                                        $filteredDesignations = isset($designation) 
                                            ? $designations->where('id', '!=', $designation->id) 
                                            : $designations;
                                        foreach($filteredDesignations as $desig): 
                                    ?>
                                        <option value="{{ $desig->code }}" data-rank="{{ $desig->rank }}" {{ old('parent_desig_code', $designation->parent_desig_code ?? '') == $desig->code ? 'selected' : '' }}>
                                            {{ $desig->name }} ({{ $desig->code }}) — Rank {{ $desig->rank_label }}
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Must be same or higher rank (A is highest, E is lowest).</div>
                            </div>

                            {{-- TOP MGMT --}}
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Is Top Management?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_top_mgmt" value="0">
                                    <input type="checkbox" name="is_top_mgmt" value="1" class="form-check-input" 
                                        {{ old('is_top_mgmt', $designation->is_top_mgmt ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- ACTIVE --}}
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Is Active?</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" 
                                        {{ old('is_active', $designation->is_active ?? true) ? 'checked' : '' }}>
                                </div>
                            </div>

                            {{-- DESCRIPTION --}}
                            <div class="col-md-6 mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="4">{{ old('description', $designation->description ?? '') }}</textarea>
                            </div>
                        </div>

                        @include('admin.org.partials.media-fields', ['imageCollection' => 'designation_image', 'model' => $designation ?? null])

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="la la-save"></i> 
                                {{ isset($designation) ? 'Update Designation' : 'Create Designation' }}
                            </button>
                            <a href="{{ backpack_url('org/designation') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- PERMISSIONS CARD (ONLY IN EDIT MODE) --}}
            @if(isset($designation))
                <div class="card mt-4">
                    <div class="card-header text-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h2 class="mb-0">Permissions — {{ $designation->name }}</h2>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-primary fs-6" id="totalCountBadge">0 / 0 permissions</span>
                            <button type="button" class="btn btn-success btn-sm" id="savePermissionsBtn">
                                <i class="la la-save"></i> Save permissions
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="expandAllBtn"><i class="la la-expand"></i> Expand all</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAllBtn"><i class="la la-compress"></i> Collapse all</button>
                            <div class="vr d-none d-md-block"></div>
                            <button type="button" class="btn btn-outline-success btn-sm" id="checkAllBtn">Select all</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="uncheckAllBtn">Clear all</button>
                        </div>
                        @include('demo.partials.tree', ['tree' => $permissionTree])
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection

@push('after_scripts')
@basset('https://cdn.jsdelivr.net/npm/sweetalert2@11')

<script>
    document.getElementById('name')?.focus();

    $('input[name="code"]').on('input', function () {
        this.value = this.value
            .replace(/[^A-Za-z0-9]/g, '')
            .toUpperCase()
            .slice(0, 10);

        let code = this.value.trim();
        let error = $('#codeError');

        if (code.length > 0 && code.length < 3) {
            error.text('Designation Code must be at least 3 characters.');
        } else {
            error.text('');
        }
    });

    $('form').on('submit', function (e) {
        let codeInput = $('input[name="code"]');
        
        if (codeInput.length > 0 && !codeInput.prop('disabled')) {
            const code = codeInput.val().trim();

            if (code.length < 3) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Designation Code must be at least 3 characters.'
                });
                return false;
            }
        }
    });
</script>

@if(isset($designation))
<script src="{{ asset('js/demo-rbac.js') }}"></script>
<script>
    // ---- Permission tree (Module -> Process -> Permission) ----
    const tree = RbacTree.init(document.getElementById('rbacTree'), { mode: 'role' });
    const ASSIGNED_PERMISSIONS = @json($assignedPermissions);

    function updateTotalBadge() {
        const total = document.querySelectorAll('.rbac-check-perm').length;
        const checked = tree.getCheckedCodes().length;
        document.getElementById('totalCountBadge').textContent = `${checked} / ${total} permissions`;
    }

    tree.onChange = updateTotalBadge;
    tree.applyPermissionSet(ASSIGNED_PERMISSIONS);
    updateTotalBadge();

    document.getElementById('expandAllBtn').addEventListener('click', () => tree.expandAll(true));
    document.getElementById('collapseAllBtn').addEventListener('click', () => tree.expandAll(false));
    document.getElementById('checkAllBtn').addEventListener('click', () => {
        const allCodes = Array.from(document.querySelectorAll('.rbac-check-perm')).map(cb => cb.dataset.code);
        tree.applyPermissionSet(allCodes);
        updateTotalBadge();
    });
    document.getElementById('uncheckAllBtn').addEventListener('click', () => {
        tree.applyPermissionSet([]);
        updateTotalBadge();
    });

    document.getElementById('savePermissionsBtn').addEventListener('click', function () {
        const btn = this;
        const payload = { permissions: tree.getCheckedCodes() };

        btn.disabled = true;
        fetch(@json(route('org.designation.permissions', $designation->id)), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) throw new Error(data.message || 'Failed to save permissions.');
            Swal.fire({ icon: 'success', title: 'Saved', text: data.message, timer: 1800, showConfirmButton: false });
        })
        .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message }))
        .finally(() => { btn.disabled = false; });
    });
</script>
@endif
@endpush