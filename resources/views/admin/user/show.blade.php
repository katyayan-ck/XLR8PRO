@extends(backpack_view('blank'))

@section('header')
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <div>
                <h2 class="fw-bold mb-0">{{ $person->display_name ?? $user->username }}</h2>
                <small class="text-muted">
                    {{ $user->username }}
                    &middot; {{ $user->user_type }}
                    &middot;
                    <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ backpack_url('org/user') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="la la-arrow-left"></i> Back to list
                </a>
                @if (backpack_user() && backpack_user()->can('ORG_USER_EDIT'))
                    <a href="{{ backpack_url('org/user/'.$user->id.'/edit') }}" class="btn btn-primary btn-sm">
                        <i class="la la-pen"></i> Edit
                    </a>
                @endif
            </div>
        </div>

        {{-- ============== PERSONAL INFO ============== --}}
        <div class="card shadow-sm" style="border-radius: 12px">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="la la-user-circle"></i>
                <strong>Personal Info</strong>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="text-muted small">Full Name</div>
                        <div class="fw-semibold">{{ $person->display_name ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Username</div>
                        <div class="fw-semibold">{{ $user->username }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">User Type (primary)</div>
                        <div class="fw-semibold">
                            {{ $user->user_type }}
                            @if ($user->user_type_id)
                                <span class="text-muted small">(addon type id: {{ $user->user_type_id }})</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Mobile</div>
                        @forelse ($mobiles as $mobile)
                            <span class="badge {{ $mobile->contact_type === 'Primary' ? 'text-bg-primary' : 'text-bg-light border' }} me-1 mb-1">
                                {{ $mobile->contact_detail }} <span class="opacity-75">({{ $mobile->contact_type }})</span>
                            </span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Email</div>
                        @forelse ($emails as $email)
                            <span class="badge {{ $email->contact_type === 'Primary' ? 'text-bg-primary' : 'text-bg-light border' }} me-1 mb-1">
                                {{ $email->contact_detail }} <span class="opacity-75">({{ $email->contact_type }})</span>
                            </span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- ============== ORG INFO + SCOPING ============== --}}
        <div class="card mt-4 shadow-sm" style="border-radius: 12px">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="la la-sitemap"></i>
                <strong>Org Info &amp; Scoping</strong>
            </div>
            <div class="card-body">
                @if (! $employee)
                    <p class="text-muted mb-0">This user is not linked to an Employee record — no org scoping applies.</p>
                @else
                    <div class="row g-4 mb-2">
                        <div class="col-md-4">
                            <div class="text-muted small">Designation</div>
                            <div class="fw-semibold">{{ $orgInfo['designation'] ?? '—' }}</div>
                        </div>
                    </div>

                    <hr>

                    <div class="row g-4">
                        @foreach ([
                            'department' => 'Department',
                            'division' => 'Division',
                            'branch' => 'Branch',
                            'location' => 'Location',
                            'segment' => 'Segment',
                            'sub_segment' => 'Sub Segment',
                            'vehicle_model' => 'Vehicle Models',
                            'vehicle_variant' => 'Vehicle Variants',
                            'vertical' => 'Vertical',
                        ] as $key => $label)
                            <div class="col-md-4">
                                <div class="text-muted small">{{ $label }}</div>
                                <div>
                                    <span class="badge text-bg-primary">
                                        {{ $orgInfo[$key]['primary'] ?? 'None' }}
                                    </span>
                                    @if (! empty($orgInfo[$key]['additional']))
                                        <div class="mt-1">
                                            @foreach ($orgInfo[$key]['additional'] as $extra)
                                                <span class="badge text-bg-light border me-1 mb-1">{{ $extra }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ============== PERMISSION BLOCK ============== --}}
        <div class="card mt-4 shadow-sm" style="border-radius: 12px">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="la la-lock"></i>
                <strong>Permissions</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="text-muted small">Assigned role:</span>
                    <span class="badge text-bg-dark fs-6">{{ $roleName ?? 'No role assigned' }}</span>
                </div>

                @if ($overrides['added'] || $overrides['removed'])
                    <div class="mb-3">
                        @if ($overrides['added'])
                            <div class="mb-1">
                                <span class="text-success small fw-semibold"><i class="la la-plus-circle"></i> Added for this user only:</span>
                                @foreach ($overrides['added'] as $code)
                                    <span class="badge text-bg-success me-1 mb-1">{{ $code }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($overrides['removed'])
                            <div>
                                <span class="text-danger small fw-semibold"><i class="la la-minus-circle"></i> Removed for this user only:</span>
                                @foreach ($overrides['removed'] as $code)
                                    <span class="badge text-bg-danger me-1 mb-1">{{ $code }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <hr>
                @endif

                <div class="text-muted small mb-1">Effective permissions ({{ $rolePermissions->count() }} from role{{ $overrides['added'] ? ' + '.count($overrides['added']).' added' : '' }}{{ $overrides['removed'] ? ' − '.count($overrides['removed']).' removed' : '' }}):</div>
                <div style="max-height: 220px; overflow-y: auto;">
                    @forelse ($rolePermissions as $permission)
                        <span class="badge {{ in_array($permission, $overrides['removed']) ? 'text-bg-light border text-decoration-line-through text-muted' : 'text-bg-secondary' }} me-1 mb-1">
                            {{ $permission }}
                        </span>
                    @empty
                        <span class="text-muted">No permissions.</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ============== BANKING BLOCK ============== --}}
        <div class="card mt-4 shadow-sm" style="border-radius: 12px">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="la la-university"></i>
                <strong>Banking Details</strong>
            </div>
            <div class="card-body">
                @if ($banking->isEmpty())
                    <p class="text-muted mb-0">No banking details on record.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Bank</th>
                                    <th>Account Holder</th>
                                    <th>Account Number</th>
                                    <th>IFSC</th>
                                    <th>Verified</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($banking as $bank)
                                    <tr>
                                        <td><span class="badge text-bg-light border">{{ $bank->account_type }}</span></td>
                                        <td>{{ $bank->bank_name }}</td>
                                        <td>{{ $bank->account_holder_name }}</td>
                                        <td><code>{{ 'XXXX'.substr($bank->account_number, -4) }}</code></td>
                                        <td>{{ $bank->ifsc_code }}</td>
                                        <td>
                                            @if ($bank->is_verified)
                                                <span class="badge text-bg-success">Verified</span>
                                            @else
                                                <span class="badge text-bg-warning">Unverified</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ============== EMPLOYMENT HISTORY ============== --}}
        <div class="card mt-4 mb-4 shadow-sm" style="border-radius: 12px">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="la la-history"></i>
                <strong>Employment History</strong>
            </div>
            <div class="card-body">
                @if (! $employee)
                    <p class="text-muted mb-0">Not an employee — no employment history applies.</p>
                @elseif ($history->isEmpty())
                    <p class="text-muted mb-0">No history recorded yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Effective From</th>
                                    <th>Effective To</th>
                                    <th>Reason</th>
                                    <th>Designation</th>
                                    <th>Branch</th>
                                    <th>Department</th>
                                    <th>Division</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $entry)
                                    <tr>
                                        <td>{{ $entry->effective_from?->format('d-M-Y') }}</td>
                                        <td>{{ $entry->effective_to?->format('d-M-Y') ?? 'Current' }}</td>
                                        <td><span class="badge text-bg-info text-capitalize">{{ str_replace('_', ' ', $entry->change_reason) }}</span></td>
                                        <td>{{ $entry->designation_code ?? '—' }}</td>
                                        <td>{{ $entry->primary_branch_code ?? '—' }}</td>
                                        <td>{{ $entry->primary_dept_code ?? '—' }}</td>
                                        <td>{{ $entry->primary_div_code ?? '—' }}</td>
                                        <td class="text-muted small">{{ $entry->notes }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
