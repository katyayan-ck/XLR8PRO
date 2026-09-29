@extends(backpack_view('blank'))

@section('title', $title)

{{--
    My Account (DEC-072; layout of the dev UI kit "Pages" profile + settings, owner request 30-09).
    Profile header (photo, name, designation, facts), then one settings card with a side menu:
    Profile (display name / photo), Permissions & scope (identity, primary + add-on org scope, vehicle scope, verticals,
    permissions, effective access), Employment history (employees), Contact, Security (change password).
    Read-only except the three forms; everything is the signed-in user's own record. Pane keys (?tab=…) are unchanged.
--}}
@section('content')
@php
    $name = $user->display_name ?? $user->username;
    $tab = $errors->password->any() ? 'security' : (old('_tab') ?: request('tab', 'profile'));
    $tabs = array_filter([
        'profile' => ['Profile', 'la-id-card'],
        'org' => ['Permissions & scope', 'la-user-shield'],
        'history' => $isEmployee ? ['Employment history', 'la-history'] : null,
        'contact' => ['Contact', 'la-address-book'],
        'security' => ['Security', 'la-lock'],
    ]);
    if (! array_key_exists($tab, $tabs)) {
        $tab = 'profile';
    }
    $fmt = fn ($d) => $d ? site_date($d) : '—';
    $chips = function (array $items) {
        if ($items === []) {
            return '<span class="text-body-secondary">—</span>';
        }

        return collect($items)->map(fn ($i) => '<span class="badge bg-secondary-lt me-1 mb-1" title="'.e($i['code']).'">'.e($i['name']).'</span>')->implode('');
    };
    $userTypes = ['Emp' => 'Employee', 'Cust' => 'Customer', 'DSA' => 'DSA', 'Insurer' => 'Insurer', 'Associate' => 'Associate'];
@endphp
<div class="container-xl">
    <div class="row row-cards">

        {{-- Profile header --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <span class="avatar avatar-xl bg-primary-lt"
                                  @if ($photoUrl) style="background-image: url('{{ $photoUrl }}')" @endif
                                  aria-label="{{ $name }}">@unless ($photoUrl){{ $user->avatar_initials }}@endunless</span>
                        </div>
                        <div class="col">
                            <h2 class="mb-1">{{ $name }}</h2>
                            <div class="text-body-secondary">
                                <i class="la la-id-badge me-1"></i>{{ $designation ?? ($isEmployee ? 'Employee' : ($userTypes[$user->user_type] ?? $user->user_type)) }}
                                @if (! empty($primaries['location']))
                                    · <i class="la la-map-marker ms-1 me-1"></i>{{ $primaries['location']['name'] }}
                                @endif
                                @if ($employee && $employee->joining_date)
                                    · <i class="la la-calendar ms-1 me-1"></i>Joined {{ $fmt($employee->joining_date) }}
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="badge bg-primary-lt">{{ $userTypes[$user->user_type] ?? $user->user_type }}</span>
                                @if (! empty($primaries['branch']))
                                    <span class="badge bg-azure-lt"><i class="la la-building me-1"></i>{{ $primaries['branch']['name'] }}</span>
                                @endif
                                @if (! empty($primaries['department']))
                                    <span class="badge bg-green-lt"><i class="la la-sitemap me-1"></i>{{ $primaries['department']['name'] }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($manager)
                            <div class="col-12 col-md-auto">
                                <div class="text-body-secondary small mb-1">Reports to</div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar-sm bg-secondary-lt" @if ($manager['photoUrl']) style="background-image: url('{{ $manager['photoUrl'] }}')" @endif>
                                        @unless ($manager['photoUrl']){{ mb_strtoupper(mb_substr($manager['name'], 0, 1)) }}@endunless
                                    </span>
                                    <div>
                                        <div class="fw-medium">{{ $manager['name'] }}</div>
                                        <div class="text-body-secondary small">{{ $manager['designation'] ?? '—' }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row text-center g-2">
                        @foreach (['Employee code' => $access['identity']['employee_code'], 'OEM Mile ID' => $access['identity']['mile_id'], 'Username' => $user->username, 'Designation' => $access['identity']['designation']] as $label => $value)
                            <div class="col-6 col-md-3">
                                <div class="h3 mb-0 text-truncate">{{ $value ?: '—' }}</div>
                                <div class="text-body-secondary small">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Settings: side menu + panes --}}
        <div class="col-12">
            <div class="card">
                <div class="row g-0">
                    <div class="col-12 col-md-3 border-end">
                        <div class="card-body">
                            <h4 class="subheader">My account</h4>
                            <div class="list-group list-group-transparent" role="tablist">
                                @foreach ($tabs as $key => [$label, $icon])
                                    <a href="#tab-{{ $key }}" class="list-group-item list-group-item-action d-flex align-items-center {{ $tab === $key ? 'active' : '' }}"
                                       data-bs-toggle="list" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
                                        <i class="la {{ $icon }} me-2"></i>{{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-9">
                        <div class="card-body tab-content">

                            {{-- Profile --}}
                            <div class="tab-pane {{ $tab === 'profile' ? 'active show' : '' }}" id="tab-profile" role="tabpanel">
                                <h2 class="mb-4">Profile</h2>
                                <div class="row g-4">
                                    <div class="col-lg-6">
                                        <h3 class="card-title">Personal information</h3>
                                        <dl class="row mb-0">
                                            <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Username</dt><dd class="col-7 col-sm-8">{{ $user->username }}</dd>
                                            @if ($person)
                                                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Full name</dt>
                                                <dd class="col-7 col-sm-8">{{ trim(implode(' ', array_filter([$person->salutation, $person->first_name, $person->middle_name, $person->last_name]))) ?: '—' }}</dd>
                                                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Gender</dt><dd class="col-7 col-sm-8">{{ $person->gender ?: '—' }}</dd>
                                                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Date of birth</dt><dd class="col-7 col-sm-8">{{ $fmt($person->dob) }}</dd>
                                                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Marital status</dt><dd class="col-7 col-sm-8">{{ $person->marital_status ?: '—' }}</dd>
                                            @endif
                                            @if ($employee)
                                                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Employee code</dt><dd class="col-7 col-sm-8">{{ $employee->code }}</dd>
                                                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Joined</dt><dd class="col-7 col-sm-8">{{ $fmt($employee->joining_date) }}</dd>
                                                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Confirmed</dt><dd class="col-7 col-sm-8">{{ $fmt($employee->confirmation_date) }}</dd>
                                                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Employment</dt>
                                                <dd class="col-7 col-sm-8">{{ trim(($employee->employment_type ?? '').' '.($employee->employment_status ? '· '.$employee->employment_status : '')) ?: '—' }}</dd>
                                            @endif
                                        </dl>
                                    </div>
                                    <div class="col-lg-6">
                                        @if ($person)
                                            <h3 class="card-title">Display name</h3>
                                            @if (! setting('account.can_change_display_name', true))
                                                <p class="mb-4">{{ $person->display_name }} <span class="text-body-secondary small">— managed by your administrator</span></p>
                                            @else
                                            <form method="post" action="{{ route('backpack.account.info.store') }}" class="mb-4">
                                                @csrf
                                                <input type="hidden" name="_tab" value="profile">
                                                <label for="display_name" class="form-label">Name shown across the app</label>
                                                <div class="input-group">
                                                    <input type="text" id="display_name" name="display_name" maxlength="120" required
                                                           class="form-control @error('display_name') is-invalid @enderror"
                                                           value="{{ old('display_name', $person->display_name) }}">
                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                </div>
                                                @error('display_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                            </form>
                                            @endif

                                            <h3 class="card-title">Profile photo</h3>
                                            @if (! setting('account.can_change_photo', true))
                                                <p class="text-body-secondary small">Managed by your administrator.</p>
                                            @else
                                            <form method="post" action="{{ route('backpack.account.photo') }}" enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="_tab" value="profile">
                                                <label for="xl-profile_photo" class="form-label">JPG, PNG or WebP</label>
                                                <x-ui.upload name="profile_photo" :accept="$imageTypes" id="xl-profile_photo" />
                                                @error('profile_photo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                                <div class="d-flex flex-wrap gap-2 mt-2">
                                                    <button type="submit" class="btn btn-primary">Upload photo</button>
                                                    @if ($photoUrl)
                                                        <button type="submit" name="remove" value="1" class="btn btn-outline-danger" formnovalidate>Remove photo</button>
                                                    @endif
                                                </div>
                                            </form>
                                            @endif
                                        @else
                                            <div class="alert alert-warning mb-0">Your account is not linked to a person record, so the name and photo
                                                can't be changed here. Ask an administrator to link one.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Permissions & scope (owner request 30-09) --}}
                            <div class="tab-pane {{ $tab === 'org' ? 'active show' : '' }}" id="tab-org" role="tabpanel">
                                <h2 class="mb-4">Permissions &amp; scope</h2>
                                <div class="row g-4">
                                    <div class="col-lg-6">
                                        <h3 class="card-title">Identity</h3>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <tbody>
                                                    <tr><th scope="row" class="w-50">Employee code</th><td>{{ $access['identity']['employee_code'] ?: '—' }}</td></tr>
                                                    <tr><th scope="row">OEM Mile ID</th><td>{{ $access['identity']['mile_id'] ?: '—' }}</td></tr>
                                                    <tr><th scope="row">Designation</th><td>{{ $access['identity']['designation'] ?: '—' }}</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <h3 class="card-title">Primary assignment</h3>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <tbody>
                                                    @foreach (['department' => 'Department', 'division' => 'Division', 'branch' => 'Branch', 'location' => 'Location'] as $level => $label)
                                                        <tr>
                                                            <th scope="row" class="w-50">Primary {{ strtolower($label) }}</th>
                                                            <td>@if ($access['primary'][$level]) {{ $access['primary'][$level]['name'] }} <span class="text-body-secondary small">({{ $access['primary'][$level]['code'] }})</span> @else — @endif</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <h3 class="card-title">Add-on scope</h3>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <tbody>
                                                    @foreach (['department' => 'Departments', 'division' => 'Divisions', 'branch' => 'Branches', 'location' => 'Locations'] as $level => $label)
                                                        <tr><th scope="row" class="w-50">Add-on {{ strtolower($label) }}</th><td>{!! $chips($access['addon'][$level]) !!}</td></tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <h3 class="card-title">Vehicle scope &amp; verticals</h3>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <tbody>
                                                    @foreach (['segment' => 'Segments', 'sub_segment' => 'Sub-segments', 'model' => 'Models', 'variant' => 'Variants'] as $level => $label)
                                                        <tr><th scope="row" class="w-50">{{ $label }}</th><td>{!! $chips($access['vehicle'][$level]) !!}</td></tr>
                                                    @endforeach
                                                    <tr><th scope="row">Verticals</th><td>{!! $chips($access['verticals']) !!}</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="form-hint mt-1">A blank segment / sub-segment / model / variant level is not restricted.</div>
                                    </div>
                                    <div class="col-12">
                                        <h3 class="card-title">Permissions</h3>
                                        @if ($access['superAdmin'])
                                            <p class="mb-0"><span class="badge bg-red-lt">Super admin</span> — every permission.</p>
                                        @elseif ($access['permissions'] === [])
                                            <p class="text-body-secondary mb-0">—</p>
                                        @else
                                            <div class="accordion" id="xl-perm-modules">
                                                @foreach ($access['permissions'] as $module => $names)
                                                    <div class="accordion-item">
                                                        <h2 class="accordion-header" id="xl-perm-h-{{ $loop->index }}">
                                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                                                    data-bs-target="#xl-perm-{{ $loop->index }}" aria-expanded="false" aria-controls="xl-perm-{{ $loop->index }}">
                                                                {{ $module }} <span class="badge bg-secondary-lt ms-2">{{ count($names) }}</span>
                                                            </button>
                                                        </h2>
                                                        <div id="xl-perm-{{ $loop->index }}" class="accordion-collapse collapse" aria-labelledby="xl-perm-h-{{ $loop->index }}" data-bs-parent="#xl-perm-modules">
                                                            <div class="accordion-body">
                                                                @foreach ($names as $permission)
                                                                    <span class="badge bg-azure-lt me-1 mb-1">{{ $permission }}</span>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-12">
                                        <h3 class="card-title">Effective data access</h3>
                                        @include('admin.org.user._effective_access', ['scope' => $effective])
                                    </div>
                                </div>
                            </div>

                            {{-- Employment history --}}
                            @if ($isEmployee)
                                <div class="tab-pane {{ $tab === 'history' ? 'active show' : '' }}" id="tab-history" role="tabpanel">
                                    <h2 class="mb-4">Employment history</h2>
                                    @if ($history === [])
                                        <p class="text-body-secondary mb-0">No employment history recorded yet.</p>
                                    @else
                                        <ul class="timeline">
                                            @foreach ($history as $i => $h)
                                                <li class="timeline-event">
                                                    <div class="timeline-event-icon {{ $i === 0 ? 'bg-green' : 'bg-secondary' }} text-white"><i class="la la-briefcase"></i></div>
                                                    <div class="card timeline-event-card">
                                                        <div class="card-body">
                                                            <div class="text-body-secondary float-end small">{{ $fmt($h['from']) }} – {{ $h['to'] ? $fmt($h['to']) : 'present' }}</div>
                                                            <h4 class="mb-1">{{ $h['designation'] ?? '—' }}</h4>
                                                            <p class="text-body-secondary mb-0">
                                                                {{ implode(' · ', array_filter([$h['branch'], $h['location'], $h['department']])) ?: '—' }}
                                                                @if ($h['reason']) <span class="badge bg-blue-lt ms-1">{{ str_replace('_', ' ', $h['reason']) }}</span> @endif
                                                            </p>
                                                            @if ($h['notes']) <p class="small mb-0 mt-1">{{ $h['notes'] }}</p> @endif
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif

                            {{-- Contact --}}
                            <div class="tab-pane {{ $tab === 'contact' ? 'active show' : '' }}" id="tab-contact" role="tabpanel">
                                <h2 class="mb-4">Contact</h2>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <h3 class="card-title">Phone</h3>
                                        @forelse ($contacts['mobiles'] as $c)
                                            <div class="mb-1"><i class="la la-phone me-1 text-body-secondary"></i>{{ $c['value'] }} <span class="badge bg-secondary-lt ms-1">{{ $c['type'] }}</span></div>
                                        @empty
                                            <p class="text-body-secondary mb-0">No phone numbers.</p>
                                        @endforelse
                                    </div>
                                    <div class="col-md-6">
                                        <h3 class="card-title">Email</h3>
                                        @forelse ($contacts['emails'] as $c)
                                            <div class="mb-1 text-break"><i class="la la-envelope me-1 text-body-secondary"></i>{{ $c['value'] }} <span class="badge bg-secondary-lt ms-1">{{ $c['type'] }}</span></div>
                                        @empty
                                            <p class="text-body-secondary mb-0">No email addresses.</p>
                                        @endforelse
                                    </div>
                                    <div class="col-12">
                                        <h3 class="card-title">Address</h3>
                                        <p class="mb-0">{{ $contacts['address'] ?? '—' }}</p>
                                    </div>
                                    <div class="col-12">
                                        <p class="text-body-secondary small mb-0">To change contact details, ask HR or an administrator (they are kept on your person record).</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Security --}}
                            <div class="tab-pane {{ $tab === 'security' ? 'active show' : '' }}" id="tab-security" role="tabpanel">
                                <h2 class="mb-4">Security</h2>
                                <div class="row">
                                    <div class="col-lg-8">
                                        <h3 class="card-title">Change password</h3>
                                        @if (! setting('account.can_change_password', true))
                                            <p class="text-body-secondary">Your password is managed by your administrator.</p>
                                        @else
                                        <form method="post" action="{{ route('backpack.account.password') }}" autocomplete="off">
                                            @csrf
                                            @foreach (['current_password' => 'Current password', 'new_password' => 'New password', 'new_password_confirmation' => 'Confirm new password'] as $field => $label)
                                                <div class="mb-3">
                                                    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                                    <input type="password" id="{{ $field }}" name="{{ $field }}" required
                                                           autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"
                                                           class="form-control @error($field, 'password') is-invalid @enderror">
                                                    @error($field, 'password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                            @endforeach
                                            <p class="text-body-secondary small">At least {{ max(8, (int) setting('account.password_min_length', 8)) }} characters with letters and numbers{{ setting('account.password_require_mixed_case', false) ? ', upper- and lower-case' : '' }}{{ setting('account.password_require_symbols', false) ? ', a symbol' : '' }}, different from the current one.
                                                Other sessions are signed out after the change.</p>
                                            <button type="submit" class="btn btn-primary">Change password</button>
                                        </form>
                                        @endif
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
