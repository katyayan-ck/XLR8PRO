<?php

namespace App\Http\Controllers\Admin\Org\User;

use App\Http\Requests\UserRequest;
use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Designation;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeHistory;
use App\Models\Admin\Location;
use App\Models\Admin\Person;
use App\Models\Admin\PersonBankingDetail;
use App\Models\Admin\PersonContact;
use App\Models\Admin\UserScope;
use App\Models\Admin\UserType;
use App\Models\Admin\Vertical;
use App\Models\IAM\Permission;
use App\Models\IAM\Role;
use App\Models\IAM\UserPermissionDenial;
use App\Models\User;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\AuthService;
use App\Services\HR\EmployeeJourneyService;
use App\Services\IAM\PermissionTreeService;
use App\Services\RBACService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * UserCrudController
 *
 * Manages user accounts with comprehensive RBAC, data scoping, and audit logging.
 *
 * Features:
 * - Role-based access control (RBAC) for all operations
 * - User permission and scope assignment
 * - Password management with hashing
 * - Account status management
 * - Audit logging of all user operations
 * - Data scoping based on user access levels
 *
 * @category Admin Controllers
 *
 * @author VDMS Development Team
 *
 * @version 2.0
 */
class UserCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use ShowOperation {
        show as traitShow;
    }
    use UpdateOperation;

    public function search()
    {
        if (! backpack_user()->can('ORG_USER_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view users.');
        }

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        if (! backpack_user()->can('ORG_USER_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view users.');
        }

        return $this->traitShowDetailsRow($id);
    }

    /**
     * Custom listing — Backpack's default table can't easily join the
     * Designation/Branch/Location/Department/Division/Vertical/Segment names
     * this screen needs (they live on the related Employee/UserScope rows,
     * not on `users` itself), so this bypasses setupListOperation()'s
     * Backpack table entirely, matching the pattern already used by every
     * other custom-grid entity in this app (Branch, Employee, Booking, ...).
     */
    public function index()
    {
        if (! backpack_user()->can('ORG_USER_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view users.');
        }

        $designations = Designation::pluck('name', 'code');
        $branches = Branch::pluck('name', 'code');
        $locations = Location::pluck('name', 'code');
        $departments = Department::pluck('name', 'code');
        $divisions = Division::pluck('name', 'code');
        $verticals = Vertical::pluck('name', 'code');
        $segments = Segment::pluck('name', 'code');

        $primaryMobiles = PersonContact::where('data_type', 'Mobile')->where('contact_type', 'Primary')
            ->pluck('contact_detail', 'person_code');
        $primaryEmails = PersonContact::where('data_type', 'Email')->where('contact_type', 'Primary')
            ->pluck('contact_detail', 'person_code');

        $users = User::with('employee', 'roles')->orderByDesc('id')->get();

        $gridData = $users->values()->map(function ($user, $index) use (
            $designations, $branches, $locations, $departments, $divisions, $verticals, $segments,
            $primaryMobiles, $primaryEmails
        ) {
            $employee = $user->employee;

            $mapped = [
                'id' => $user->id,
                'serial_no' => $index + 1,
                'user_type' => $user->user_type,
                'username' => $user->username,
                'mobile' => $primaryMobiles->get($user->person_code) ?? '—',
                'email' => $primaryEmails->get($user->person_code) ?? '—',
                'designation' => $employee ? ($designations->get($employee->designation_code) ?? '—') : '—',
                'branch' => $employee ? ($branches->get($employee->primary_branch_code) ?? '—') : '—',
                'location' => $employee ? ($locations->get($employee->primary_loc_code) ?? '—') : '—',
                'department' => $employee ? ($departments->get($employee->primary_dept_code) ?? '—') : '—',
                'division' => $employee ? ($divisions->get($employee->primary_div_code) ?? '—') : '—',
                'vertical' => $employee ? ($verticals->get($employee->vertical_code) ?? '—') : '—',
                'segment' => $employee ? ($segments->get($employee->segment_code) ?? '—') : '—',
                'role' => $user->roles->pluck('name')->implode(', ') ?: '—',
                'is_active' => $user->is_active ? 'Active' : 'Inactive',
            ];

            $viewUrl = backpack_url("org/user/{$user->id}/show");
            $editUrl = backpack_url("org/user/{$user->id}/edit");
            $hasRole = $user->roles->isNotEmpty();

            $statusActions = $user->is_active
                ? '<a href="#" class="btn btn-sm btn-outline-warning user-action" data-action="suspend" data-id="'.$user->id.'" title="Suspend">Suspend</a>
                   <a href="#" class="btn btn-sm btn-outline-danger user-action" data-action="revoke" data-id="'.$user->id.'" title="Revoke">Revoke</a>'
                : '<a href="#" class="btn btn-sm btn-outline-success user-action" data-action="activate" data-id="'.$user->id.'" title="Activate">Activate</a>'
                    .(! $hasRole ? '' : '<a href="#" class="btn btn-sm btn-outline-danger user-action" data-action="revoke" data-id="'.$user->id.'" title="Revoke">Revoke</a>');

            $mapped['action'] = '
                <div class="d-flex gap-1 justify-content-center flex-wrap">
                    <a href="'.$viewUrl.'" class="btn btn-sm btn-outline-primary" title="View">View</a>
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-secondary" title="Edit">Edit</a>
                    '.$statusActions.'
                </div>';

            return $mapped;
        })->values();

        return view('admin.user.list', [
            'title' => 'All Users',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'user_type', 'headerName' => 'User Type'],
                    ['field' => 'username', 'headerName' => 'Username'],
                    ['field' => 'mobile', 'headerName' => 'Mobile'],
                    ['field' => 'email', 'headerName' => 'Email'],
                    ['field' => 'designation', 'headerName' => 'Designation'],
                    ['field' => 'branch', 'headerName' => 'Branch'],
                    ['field' => 'location', 'headerName' => 'Location'],
                    ['field' => 'department', 'headerName' => 'Department'],
                    ['field' => 'division', 'headerName' => 'Division'],
                    ['field' => 'vertical', 'headerName' => 'Vertical'],
                    ['field' => 'segment', 'headerName' => 'Segment'],
                    ['field' => 'role', 'headerName' => 'Role'],
                    ['field' => 'is_active', 'headerName' => 'Status'],
                    ['field' => 'action', 'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    /**
     * Custom view page — Personal Info, Org Info + Scoping, Permission Block,
     * Banking Block, and Employment History cards. All real data; Employment
     * History is only ever non-empty if EmployeeHistorySeeder (or a future
     * write-side workflow this doesn't build) has recorded something.
     */
    public function show($id)
    {
        if (! backpack_user()->can('ORG_USER_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view users.');
        }

        $user = User::with('roles')->findOrFail($id);
        $person = $user->person_code ? Person::where('person_code', $user->person_code)->first() : null;
        $employee = $user->employee_code ? Employee::where('code', $user->employee_code)->first() : null;

        $mobiles = $person ? PersonContact::where('person_code', $person->person_code)->where('data_type', 'Mobile')->get() : collect();
        $emails = $person ? PersonContact::where('person_code', $person->person_code)->where('data_type', 'Email')->get() : collect();
        $banking = $person ? PersonBankingDetail::where('person_code', $person->person_code)->get() : collect();

        $scopeCodesFor = function (string $type) use ($user) {
            return $user->scopes()->where('scope_type', $type)->pluck('scope_code');
        };

        $orgInfo = [];
        if ($employee) {
            $orgInfo = [
                'designation' => $employee->designation_name ?? $employee->designation_code ?? '—',
                'branch' => $this->scopeBlock($employee->primary_branch_code, $scopeCodesFor('branch'), Branch::pluck('name', 'code')),
                'location' => $this->scopeBlock($employee->primary_loc_code, $scopeCodesFor('location'), Location::pluck('name', 'code')),
                'department' => $this->scopeBlock($employee->primary_dept_code, $scopeCodesFor('department'), Department::pluck('name', 'code')),
                'division' => $this->scopeBlock($employee->primary_div_code, $scopeCodesFor('division'), Division::pluck('name', 'code')),
                'vertical' => $this->scopeBlock($employee->vertical_code, $scopeCodesFor('vertical'), Vertical::pluck('name', 'code')),
                'segment' => $this->scopeBlock($employee->segment_code, $scopeCodesFor('segment'), Segment::pluck('name', 'code')),
                'sub_segment' => $this->scopeBlock($employee->sub_segment_code, $scopeCodesFor('sub_segment'), SubSegment::pluck('name', 'code')),
                // No primary column exists for these two on Employee — every assignment comes from scopes.
                'vehicle_model' => $this->scopeBlock(null, $scopeCodesFor('model'), VehicleModel::pluck('name', 'code')),
                'vehicle_variant' => $this->scopeBlock(null, $scopeCodesFor('variant'), Variant::pluck('display_name', 'code')),
            ];
        }

        $roleName = $user->roles->first()?->name;
        $rolePermissions = $user->roles->first()?->permissions->pluck('name')->sort()->values() ?? collect();
        $overrides = $user->permissionOverrides();

        $history = $employee
            ? EmployeeHistory::where('emp_code', $employee->code)->orderByDesc('effective_from')->get()
            : collect();

        return view('admin.user.show', [
            'title' => 'User Details',
            'user' => $user,
            'person' => $person,
            'employee' => $employee,
            'mobiles' => $mobiles,
            'emails' => $emails,
            'banking' => $banking,
            'orgInfo' => $orgInfo,
            'roleName' => $roleName,
            'rolePermissions' => $rolePermissions,
            'overrides' => $overrides,
            'history' => $history,
        ]);
    }

    /**
     * @param  Collection<int, string>  $additionalCodes
     * @param  Collection<string, string>  $nameLookup
     * @return array{primary: ?string, additional: array<int, string>}
     */
    private function scopeBlock(?string $primaryCode, $additionalCodes, $nameLookup): array
    {
        $additional = $additionalCodes
            ->filter(fn ($code) => $code !== $primaryCode)
            ->unique()
            ->map(fn ($code) => $nameLookup->get($code) ?? $code)
            ->values()
            ->all();

        return [
            'primary' => $primaryCode ? ($nameLookup->get($primaryCode) ?? $primaryCode) : null,
            'additional' => $additional,
        ];
    }

    /**
     * Temporarily deactivate the account, keeping its role intact — meant for
     * a reversible hold (e.g. leave, pending investigation). Use revoke() for
     * a harder cutoff (e.g. the person has left the company).
     */
    public function suspend($id)
    {
        if (! backpack_user()->can('ORG_USER_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit users.');
        }

        $user = User::findOrFail($id);

        if ($user->isSuperAdmin()) {
            return back()->withError('Cannot suspend a SuperAdmin account.');
        }

        $user->update(['is_active' => false]);

        Log::warning('User suspended', ['suspended_by' => backpack_user()->id, 'user_id' => $user->id]);

        \Alert::success('User suspended. Their role is preserved — use Activate to restore access.')->flash();

        return redirect(backpack_url('org/user'));
    }

    /**
     * Hard cutoff: deactivate AND strip the role plus any permission
     * overrides. Meant for someone who has left — reactivating afterwards
     * requires assigning a new role explicitly, it is not restored
     * automatically.
     */
    public function revoke($id)
    {
        if (! backpack_user()->can('ORG_USER_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to revoke user access.');
        }

        $user = User::findOrFail($id);

        if ($user->isSuperAdmin()) {
            return back()->withError('Cannot revoke a SuperAdmin account.');
        }

        $user->update(['is_active' => false]);
        $user->syncRoles([]);
        $user->syncPermissions([]);
        UserPermissionDenial::where('user_id', $user->id)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::warning('User access revoked', ['revoked_by' => backpack_user()->id, 'user_id' => $user->id]);

        \Alert::success('User access revoked — role and all permission overrides cleared.')->flash();

        return redirect(backpack_url('org/user'));
    }

    /**
     * Reverses suspend() (and, partially, revoke() — reactivates login but
     * does not restore a stripped role; assign one via Edit).
     */
    public function activate($id)
    {
        if (! backpack_user()->can('ORG_USER_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit users.');
        }

        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        Log::info('User activated', ['activated_by' => backpack_user()->id, 'user_id' => $user->id]);

        \Alert::success('User activated.')->flash();

        return redirect(backpack_url('org/user'));
    }

    /**
     * Service dependencies injected via constructor.
     *
     * @param  RBACService  $rbacService  Role-based access control service
     * @param  AuthService  $authService  Authentication service
     * @param  EmployeeJourneyService  $journeyService  Org/vehicle/permission history (employment journey)
     * @param  PermissionTreeService  $permissionTree  Module → Process → Permission tree for the Permission card
     */
    public function __construct(
        protected RBACService $rbacService,
        protected AuthService $authService,
        protected EmployeeJourneyService $journeyService,
        protected PermissionTreeService $permissionTree,
    ) {
        parent::__construct();
    }

    /** UserType.code (as seeded/entered) => users.user_type ENUM value ('Emp','Cust','DSA','Insurer','Associate'). */
    private const USER_TYPE_ENUM_MAP = [
        'EMP' => 'Emp',
        'DSA' => 'DSA',
        'CUST' => 'Cust',
    ];

    /**
     * Setup CRUD panel configuration
     *
     * Configures the CRUD model, routes, and entity names for the User resource.
     * Sets up operations and basic configuration.
     */
    public function setup(): void
    {
        $this->crud->setModel(User::class);
        $this->crud->setRoute(config('backpack.base.route_prefix').'/org/user');
        $this->crud->setEntityNameStrings('user', 'users');

        $this->crud->setCreateContentClass('col-md-8');
        $this->crud->setEditContentClass('col-md-8');

        $this->crud->allowAccess(['list', 'create', 'update', 'delete', 'show']);
    }

    /**
     * Setup List Operation
     *
     * Backpack's CrudController dispatches this hook automatically for any route
     * tagged 'operation' => 'list' (org.user.index, org.user.search), regardless
     * of which method actually handles the request — so this still runs even
     * though index() no longer uses Backpack's own table/DataTables rendering.
     * Only the permission check matters here now; the column/filter setup this
     * used to do was for Backpack's default table, unused since index() builds
     * its own AG Grid, and its PRO-only `dropdown` filter type (BUG-014) would
     * throw BackpackProRequiredException if left registered on every list load.
     */
    protected function setupListOperation(): void
    {
        if (! backpack_user()->can('ORG_USER_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view users.');
        }
    }

    /**
     * Backpack's CrudController dispatches this hook automatically for any route
     * tagged 'operation' => 'create' (org.user.create, org.user.store), regardless
     * of which method handles the request — same reason as setupListOperation()
     * above. create()/store() are fully custom now (real schema, no PRO fields),
     * so only the permission check matters here.
     */
    protected function setupCreateOperation(): void
    {
        if (! backpack_user()->can('ORG_USER_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create users.');
        }
    }

    /** Same reason as setupCreateOperation() above, for 'operation' => 'update'. */
    protected function setupUpdateOperation(): void
    {
        if (! backpack_user()->can('ORG_USER_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit users.');
        }
    }

    /**
     * Live search for the "pick a person" typeahead — only persons who don't
     * already have a user account, matching name/code/mobile/email.
     */
    public function searchPersons(Request $request)
    {
        if (! backpack_user()->can('ORG_USER_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create users.');
        }

        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $linkedPersonCodes = User::whereNotNull('person_code')->pluck('person_code');

        $persons = Person::whereNotIn('person_code', $linkedPersonCodes)
            ->where(function ($query) use ($q) {
                $query->where('display_name', 'like', "%{$q}%")
                    ->orWhere('person_code', 'like', "%{$q}%")
                    ->orWhereHas('contacts', fn ($c) => $c->where('contact_detail', 'like', "%{$q}%"));
            })
            ->limit(15)
            ->get()
            ->map(fn (Person $p) => [
                'person_code' => $p->person_code,
                'display_name' => $p->display_name ?: $p->full_name,
                'salutation' => $p->salutation,
                'mobile' => $p->primary_mobile,
                'email' => $p->primary_email,
                'photo' => $p->getFirstMediaUrl('profile_photos') ?: null,
            ]);

        return response()->json($persons);
    }

    /**
     * New User form — the person is always an existing Person (searched via
     * the typeahead, never created here); everything else (org card, vehicle
     * card, permission card) is driven client-side off the lookup data and
     * role/permission maps below.
     */
    public function create()
    {
        if (! backpack_user()->can('ORG_USER_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create users.');
        }

        return view('admin.user.create', array_merge([
            'title' => 'New User',
        ], $this->onboardingLookupData()));
    }

    public function store(UserRequest $request)
    {
        if (! backpack_user()->can('ORG_USER_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create users.');
        }

        $validated = $request->validated();
        $isEmployee = strtolower($validated['user_type_code']) === 'emp';

        $person = Person::where('person_code', $validated['person_code'])->firstOrFail();
        $employee = null;
        $addonScopeSnapshot = null;

        if ($isEmployee) {
            $employee = Employee::create([
                'code' => $this->generateEmployeeCode(),
                'person_code' => $person->person_code,
                'designation_code' => $validated['designation_code'],
                'primary_branch_code' => $validated['primary_branch_code'],
                'primary_loc_code' => $validated['primary_loc_code'],
                'primary_dept_code' => $validated['primary_dept_code'],
                'primary_div_code' => $validated['primary_div_code'],
                'vertical_code' => $validated['vertical_code'] ?? null,
                'segment_code' => $validated['primary_segment_code'] ?? null,
                'sub_segment_code' => $validated['primary_sub_segment_code'] ?? null,
                'employment_type' => 'permanent',
                'employment_status' => 'active',
                'joining_date' => $validated['date_of_joining'] ?? now()->toDateString(),
            ]);
        }

        $user = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'user_type' => $this->resolveUserTypeEnum($validated['user_type_code']),
            'person_code' => $person->person_code,
            'employee_code' => $employee?->code,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $roleCode = $isEmployee ? $validated['designation_code'] : null;
        $role = $roleCode ? Role::where('code', $roleCode)->where('guard_name', 'web')->first() : ($validated['role_id'] ?? null ? Role::find($validated['role_id']) : null);
        if ($role) {
            $user->syncRoles([$role]);
        }

        $permissionsSnapshot = $this->applyPermissionOverrides($user, $validated['added_permissions'] ?? [], $validated['removed_permissions'] ?? [], $role);

        if ($employee) {
            $addonScopeSnapshot = $this->syncAddonScopes($user, [
                'branch' => $validated['addon_branch_codes'] ?? [],
                'location' => $validated['addon_loc_codes'] ?? [],
                'department' => $validated['addon_dept_codes'] ?? [],
                'division' => $validated['addon_div_codes'] ?? [],
                'segment' => $validated['addon_segment_codes'] ?? [],
                'sub_segment' => $validated['addon_sub_segment_codes'] ?? [],
            ]);

            $this->journeyService->recordChange(
                employee: $employee,
                primary: [
                    'designation_code' => $employee->designation_code,
                    'primary_branch_code' => $employee->primary_branch_code,
                    'primary_loc_code' => $employee->primary_loc_code,
                    'primary_dept_code' => $employee->primary_dept_code,
                    'primary_div_code' => $employee->primary_div_code,
                    'vertical_code' => $employee->vertical_code,
                    'segment_code' => $employee->segment_code,
                    'sub_segment_code' => $employee->sub_segment_code,
                ],
                changeReason: 'other',
                effectiveFrom: $employee->joining_date ?? now(),
                notes: 'Initial onboarding via New User form',
                addonScopes: $addonScopeSnapshot,
                permissionsSnapshot: $permissionsSnapshot,
                actorId: backpack_user()->id,
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::info('User created', [
            'created_by' => backpack_user()->id,
            'user_id' => $user->id,
            'username' => $user->username,
        ]);

        \Alert::success('User created successfully!')->flash();

        return redirect(backpack_url('org/user/'.$user->id.'/show'));
    }

    /**
     * Edit form — account fields, and (if linked to an Employee) org/vehicle
     * cards plus the permission card, all pre-filled from the Employee's
     * current primary fields, current UserScope addons, and current
     * role+overrides. Any org/vehicle/permission change requires a reason +
     * effective date, which drives EmployeeJourneyService recording in
     * update().
     */
    public function edit($id)
    {
        if (! backpack_user()->can('ORG_USER_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit users.');
        }

        $user = User::with('roles')->findOrFail($id);
        $employee = $user->employee_code ? Employee::where('code', $user->employee_code)->first() : null;
        $person = $user->person_code ? Person::where('person_code', $user->person_code)->first() : null;

        return view('admin.user.edit', array_merge([
            'title' => 'Edit User',
            'user' => $user,
            'employee' => $employee,
            'person' => $person,
            'currentAddonScopes' => $employee ? $user->getAllScopes() : [],
            'currentOverrides' => $user->permissionOverrides(),
        ], $this->onboardingLookupData()));
    }

    public function update(UserRequest $request, $id)
    {
        if (! backpack_user()->can('ORG_USER_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit users.');
        }

        $user = User::findOrFail($id);
        $validated = $request->validated();
        $isEmployee = strtolower($validated['user_type_code']) === 'emp';

        $userData = [
            'username' => $validated['username'],
            'user_type' => $this->resolveUserTypeEnum($validated['user_type_code']),
            'is_active' => $request->boolean('is_active'),
        ];
        if (! empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }
        $user->update($userData);

        $employee = $user->employee_code ? Employee::where('code', $user->employee_code)->first() : null;

        $roleCode = $isEmployee ? ($validated['designation_code'] ?? null) : null;
        $role = $roleCode ? Role::where('code', $roleCode)->where('guard_name', 'web')->first() : ($validated['role_id'] ?? null ? Role::find($validated['role_id']) : null);
        $user->syncRoles($role ? [$role] : []);

        $permissionsSnapshot = $this->applyPermissionOverrides($user, $validated['added_permissions'] ?? [], $validated['removed_permissions'] ?? [], $role);

        $orgChanged = false;
        $addonScopeSnapshot = null;

        if ($employee) {
            $orgFields = [
                'designation_code', 'primary_branch_code', 'primary_loc_code', 'primary_dept_code',
                'primary_div_code', 'vertical_code',
            ];

            foreach ($orgFields as $field) {
                if (($validated[$field] ?? null) !== $employee->{$field}) {
                    $orgChanged = true;
                }
            }
            if (($validated['primary_segment_code'] ?? null) !== $employee->segment_code
                || ($validated['primary_sub_segment_code'] ?? null) !== $employee->sub_segment_code) {
                $orgChanged = true;
            }

            $newAddons = [
                'branch' => $validated['addon_branch_codes'] ?? [],
                'location' => $validated['addon_loc_codes'] ?? [],
                'department' => $validated['addon_dept_codes'] ?? [],
                'division' => $validated['addon_div_codes'] ?? [],
                'segment' => $validated['addon_segment_codes'] ?? [],
                'sub_segment' => $validated['addon_sub_segment_codes'] ?? [],
            ];
            $currentAddons = $user->getAllScopes();
            foreach ($newAddons as $type => $codes) {
                if (array_values(array_map('strtoupper', $codes)) !== array_values(array_map('strtoupper', $currentAddons[$type] ?? []))) {
                    $orgChanged = true;
                }
            }

            if ($orgChanged && (empty($validated['change_reason']) || empty($validated['effective_date']))) {
                return back()->withInput()->withErrors([
                    'change_reason' => 'A reason and effective date are required whenever org, vehicle, or scope info changes.',
                ]);
            }

            if ($orgChanged) {
                $employee->fill([
                    'designation_code' => $validated['designation_code'],
                    'primary_branch_code' => $validated['primary_branch_code'],
                    'primary_loc_code' => $validated['primary_loc_code'],
                    'primary_dept_code' => $validated['primary_dept_code'],
                    'primary_div_code' => $validated['primary_div_code'],
                    'vertical_code' => $validated['vertical_code'] ?? null,
                    'segment_code' => $validated['primary_segment_code'] ?? null,
                    'sub_segment_code' => $validated['primary_sub_segment_code'] ?? null,
                ]);
                $employee->save();

                $addonScopeSnapshot = $this->syncAddonScopes($user, $newAddons);

                $this->journeyService->recordChange(
                    employee: $employee,
                    primary: [
                        'designation_code' => $employee->designation_code,
                        'primary_branch_code' => $employee->primary_branch_code,
                        'primary_loc_code' => $employee->primary_loc_code,
                        'primary_dept_code' => $employee->primary_dept_code,
                        'primary_div_code' => $employee->primary_div_code,
                        'vertical_code' => $employee->vertical_code,
                        'segment_code' => $employee->segment_code,
                        'sub_segment_code' => $employee->sub_segment_code,
                    ],
                    changeReason: $validated['change_reason'],
                    effectiveFrom: $validated['effective_date'],
                    notes: $validated['remarks'] ?? null,
                    addonScopes: $addonScopeSnapshot,
                    permissionsSnapshot: $permissionsSnapshot,
                    actorId: backpack_user()->id,
                );
            } elseif ($this->permissionsChanged($permissionsSnapshot, $this->journeyService->currentState($employee->code))) {
                $this->journeyService->recordPermissionChange(
                    employee: $employee,
                    permissionsSnapshot: $permissionsSnapshot,
                    changeReason: 'permission_change',
                    effectiveFrom: $validated['effective_date'] ?? now(),
                    notes: $validated['remarks'] ?? null,
                    actorId: backpack_user()->id,
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::info('User updated', ['updated_by' => backpack_user()->id, 'user_id' => $user->id]);

        \Alert::success('User updated successfully!')->flash();

        return redirect(backpack_url('org/user/'.$user->id.'/show'));
    }

    /**
     * @return array{added: array<int,string>, removed: array<int,string>, role: ?string}
     */
    private function permissionsSnapshotOf(User $user, ?Role $role): array
    {
        $overrides = $user->permissionOverrides();

        return [
            'role' => $role?->name,
            'added' => $overrides['added'],
            'removed' => $overrides['removed'],
        ];
    }

    private function permissionsChanged(array $newSnapshot, ?EmployeeHistory $previousState): bool
    {
        $previous = $previousState?->scopes['permissions'] ?? ['role' => null, 'added' => [], 'removed' => []];

        return $newSnapshot !== [
            'role' => $previous['role'] ?? null,
            'added' => $previous['added'] ?? [],
            'removed' => $previous['removed'] ?? [],
        ];
    }

    /**
     * Applies the permission-tree override diff submitted by the Permission
     * card: grants every added permission directly (Spatie's native
     * model_has_permissions), and records an explicit denial for every role
     * permission the user unchecked (via UserPermissionDenial — Spatie has
     * no native concept of revoking a role-granted permission from one
     * user). Returns the resulting snapshot for EmployeeHistory.
     *
     * @param  array<int,string>  $added
     * @param  array<int,string>  $removed
     * @return array{role: ?string, added: array<int,string>, removed: array<int,string>}
     */
    private function applyPermissionOverrides(User $user, array $added, array $removed, ?Role $role): array
    {
        $user->syncPermissions($added);

        UserPermissionDenial::where('user_id', $user->id)->delete();
        foreach ($removed as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                UserPermissionDenial::create([
                    'user_id' => $user->id,
                    'permission_id' => $permission->id,
                    'created_by' => backpack_user()->id,
                ]);
            }
        }

        return $this->permissionsSnapshotOf($user, $role);
    }

    /**
     * Replaces a user's addon scopes of each given type with the new
     * selection — soft-deletes rows no longer selected, restores/updates
     * (rather than re-inserting, since (user_id, scope_type, scope_code) is
     * DB-unique even across soft-deleted rows — same pitfall as BUG-086)
     * any that are.
     *
     * @param  array<string, array<int,string>>  $addonsByType  scope_type => codes
     * @return array<string, array<int,string>>
     */
    private function syncAddonScopes(User $user, array $addonsByType): array
    {
        $snapshot = [];

        foreach ($addonsByType as $scopeType => $codes) {
            $codes = array_values(array_unique(array_filter($codes)));
            $snapshot[$scopeType] = $codes;

            UserScope::where('user_id', $user->id)
                ->where('scope_type', $scopeType)
                ->whereNotIn('scope_code', $codes)
                ->delete();

            foreach ($codes as $code) {
                UserScope::withTrashed()->updateOrCreate(
                    ['user_id' => $user->id, 'scope_type' => $scopeType, 'scope_code' => $code],
                    ['is_active' => true, 'from_date' => now()->toDateString(), 'to_date' => null, 'deleted_at' => null]
                );
            }
        }

        return $snapshot;
    }

    private function resolveUserTypeEnum(string $userTypeCode): string
    {
        return self::USER_TYPE_ENUM_MAP[strtoupper($userTypeCode)] ?? 'Emp';
    }

    /** Next sequential BMPL-#### employee code. */
    private function generateEmployeeCode(): string
    {
        $max = Employee::where('code', 'like', 'BMPL-%')
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(code, '-', -1) AS UNSIGNED)) as max_num")
            ->value('max_num') ?? 0;

        return 'BMPL-'.str_pad($max + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Everything the create/edit onboarding screens need client-side:
     * org/vehicle hierarchies (for cascading selects), the real Module →
     * Process → Permission tree, and every Designation-backed role's actual
     * permission set (so the Permission card can pre-check the right boxes
     * the instant a designation/role is picked, with zero extra requests —
     * same reasoning as demo/roles embedding the tree once).
     */
    private function onboardingLookupData(): array
    {
        $designations = Designation::where('is_active', true)->orderBy('name')->get(['code', 'name', 'rank']);
        $rolePermissions = Role::where('guard_name', 'web')->with('permissions:id,name')->get()
            ->mapWithKeys(fn (Role $role) => [$role->code ?? $role->name => $role->permissions->pluck('name')->values()]);

        return [
            'userTypes' => UserType::where('is_active', true)->orderBy('display_name')->get(['code', 'display_name']),
            'designations' => $designations,
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['code', 'name', 'branch_code']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'divisions' => Division::where('is_active', true)->orderBy('name')->get(['code', 'name', 'dept_code']),
            'verticals' => Vertical::where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'segments' => Segment::where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'subSegments' => SubSegment::where('is_active', true)->orderBy('name')->get(['code', 'name', 'segment_code']),
            'roles' => Role::where('guard_name', 'web')->where('name', '!=', 'superadmin')->orderBy('name')->get(['id', 'name', 'code']),
            'rolePermissions' => $rolePermissions,
            'permissionTree' => $this->permissionTree->buildTree(),
        ];
    }

    /**
     * Overrides the default delete operation to:
     * - Prevent deleting the last super admin
     * - Log deletion event
     * - Soft delete if available
     *
     * @return RedirectResponse
     */
    public function destroy()
    {
        if (! backpack_user()->can('ORG_USER_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete users.');
        }

        try {
            $user = $this->crud->getCurrentEntry();

            if ($user->isSuperAdmin() && User::role('superadmin')->count() === 1) {
                return back()->withError('Cannot delete the last super admin user.');
            }

            Log::warning('User deleted', [
                'deleted_by' => backpack_user()->id,
                'user_id' => $user->id,
                'username' => $user->username,
                'timestamp' => now(),
            ]);

            return parent::deleteCrud();
        } catch (\Exception $e) {
            Log::error('User deletion failed', [
                'error' => $e->getMessage(),
                'deleted_by' => backpack_user()->id,
            ]);

            return back()->withError('Failed to delete user: '.$e->getMessage());
        }
    }
}
