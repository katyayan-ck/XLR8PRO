<?php

declare(strict_types=1);

namespace App\Services\IAM;

use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeHistory;
use App\Models\Admin\Person;
use App\Models\IAM\PasswordHistory;
use App\Models\User;
use App\Services\HR\EmployeeJourneyService;
use App\Services\HR\UserReportingService;
use App\Services\IAM\DataScope\ScopeResolver;
use App\Services\IAM\DataScope\ScopeSet;
use App\Services\Org\EmployeeService;
use App\Services\OrgService;
use App\Services\Person\PersonRecordService;
use App\Services\PersonService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in user's own account (DEC-072): one read model for the My Account page and the three things a user
 * may change themselves — display name, profile photo (both on their Person, through PersonRecordService) and
 * password (current one checked first). Username, organisation and scopes are read-only here.
 */
class MyAccountService
{
    /** scope level => label, in display order */
    public const SCOPE_LEVELS = [
        'branch' => 'Branch', 'location' => 'Location', 'department' => 'Department', 'division' => 'Division',
        'vertical' => 'Vertical', 'segment' => 'Segment', 'sub_segment' => 'Sub-segment', 'model' => 'Model', 'variant' => 'Variant',
    ];

    public function __construct(
        private readonly PersonRecordService $persons,
        private readonly EmployeeJourneyService $journeys,
        private readonly UserReportingService $reporting,
        private readonly ScopeResolver $scopes,
    ) {}

    /**
     * Everything the page shows.
     *
     * @return array{
     *   user: User, person: ?Person, employee: ?Employee, isEmployee: bool, photoUrl: ?string, designation: ?string,
     *   contacts: array{mobiles: list<array{value: string, type: string}>, emails: list<array{value: string, type: string}>, address: ?string},
     *   primaries: array<string, array{code: string, name: string}|null>, addons: array<string, list<string>>,
     *   effective: ScopeSet, manager: ?array{name: string, designation: ?string, photoUrl: ?string, code: ?string},
     *   history: list<array<string, mixed>>
     * }
     */
    public function profile(User $user): array
    {
        $person = $user->person;
        $employee = $user->employee;
        $isEmployee = $employee !== null;

        return [
            'user' => $user,
            'person' => $person,
            'employee' => $employee,
            'isEmployee' => $isEmployee,
            'photoUrl' => $this->photoUrl($person),
            'designation' => $user->primary_designation,
            'contacts' => $this->contacts($person),
            'primaries' => $isEmployee ? $this->primaries($employee) : [],
            'addons' => $isEmployee ? $this->addons($user, $employee) : [],
            'effective' => $this->scopes->for($user),
            'manager' => $isEmployee ? $this->manager($user) : null,
            'history' => $isEmployee ? $this->history($employee) : [],
        ];
    }

    /** @throws ValidationException when the user has no person record or the name is invalid */
    public function updateDisplayName(User $user, string $displayName): Person
    {
        $person = $this->requirePerson($user, 'display_name');

        /** @var Person $saved */
        $saved = $this->persons->update($person, ['display_name' => $displayName]);

        return $saved;
    }

    /** Replace (or with null, remove) the profile photo. @throws ValidationException */
    public function updatePhoto(User $user, ?UploadedFile $photo): Person
    {
        $person = $this->requirePerson($user, 'profile_photo');

        /** @var Person $saved */
        $saved = $this->persons->update($person, $photo
            ? ['profile_photo' => $photo, 'remove_profile_photo' => true]
            : ['remove_profile_photo' => true]);

        return $saved->refresh();
    }

    /**
     * Personal details a user may change themselves (Settings → User behaviour, DEC-091) — form field => setting key.
     */
    public const PERSONAL_FIELDS = [
        'email' => 'account.can_change_email', 'mobile' => 'account.can_change_mobile', 'aadhaar_no' => 'account.can_change_aadhaar',
        'pan_no' => 'account.can_change_pan', 'dob' => 'account.can_change_dob', 'joining_date' => 'account.can_change_doj',
        'marital_status' => 'account.can_change_marital_status', 'gender' => 'account.can_change_gender',
    ];

    /** @return list<string> the personal fields switched on for self-service (date of joining only for employees) */
    public function editablePersonalFields(User $user): array
    {
        $fields = [];
        foreach (self::PERSONAL_FIELDS as $field => $setting) {
            if ((bool) setting($setting, false) && ($field !== 'joining_date' || $user->employee !== null)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Save the user's own personal details. Only the switched-on fields are written (anything else in $input is
     * ignored); person fields go through the person entity services, the joining date through EmployeeService, so
     * their field rules apply. A blank value keeps the stored one.
     *
     * @param  array<string, mixed>  $input
     * @return list<string> the fields that changed
     *
     * @throws ValidationException
     */
    public function updatePersonal(User $user, array $input): array
    {
        $person = $this->requirePerson($user, 'personal');
        $allowed = $this->editablePersonalFields($user);
        $data = [];
        foreach ($allowed as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        $changed = [];
        $personData = array_intersect_key($data, array_flip(['aadhaar_no', 'pan_no', 'dob', 'marital_status', 'gender']));
        foreach ($personData as $field => $value) {
            if ((string) $person->getAttribute($field) === $value) {
                unset($personData[$field]);
            }
        }
        if ($personData !== []) {
            $this->persons->update($person, $personData);
            $changed = array_keys($personData);
        }

        $contacts = $this->contacts($person);
        foreach (['email' => ['Email', 'emails'], 'mobile' => ['Mobile', 'mobiles']] as $field => [$dataType, $list]) {
            $primary = collect($contacts[$list])->firstWhere('type', 'Primary')['value'] ?? null;
            if (isset($data[$field]) && $data[$field] !== $primary) {
                PersonService::upsertContact((string) $person->getAttribute('person_code'), ['data_type' => $dataType, 'contact_type' => 'Primary', 'contact_detail' => $data[$field]]);
                $changed[] = $field;
            }
        }

        $employee = $user->employee;
        if (isset($data['joining_date']) && $employee !== null && (string) $employee->getAttribute('joining_date')?->format('Y-m-d') !== $data['joining_date']) {
            app(EmployeeService::class)->update($employee, ['joining_date' => $data['joining_date']]);
            $changed[] = 'joining_date';
        }

        return $changed;
    }

    /**
     * Change the password after checking the current one; other sessions of this user are signed out. With
     * `account.password_history_count` = N (> 0), none of the last N passwords may be reused (N4, DEC-095 #28); the
     * change date (`password_changed_at`) drives `account.password_expiry_days`.
     *
     * @throws ValidationException on a wrong current password, a new one equal to it, or a recently used one
     */
    public function changePassword(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is not correct.']);
        }
        if (Hash::check($new, (string) $user->password)) {
            throw ValidationException::withMessages(['new_password' => 'The new password must be different from the current one.']);
        }
        $keep = max(0, (int) setting('account.password_history_count', 0));
        if ($keep > 0) {
            $recent = PasswordHistory::query()->where('user_id', $user->id)->latest('id')->limit($keep)->pluck('password');
            if ($recent->contains(fn (string $hash) => Hash::check($new, $hash))) {
                throw ValidationException::withMessages(['new_password' => __('iam.validation.password_recently_used', ['count' => $keep])]);
            }
        }

        $hash = Hash::make($new);
        $user->forceFill(['password' => $hash, 'password_changed_at' => now()])->save();
        PasswordHistory::query()->create(['user_id' => $user->id, 'password' => $hash]);

        $guard = Auth::guard(backpack_guard_name());
        if ($guard->check() && (int) $guard->id() === (int) $user->id && method_exists($guard, 'logoutOtherDevices')) {
            $guard->logoutOtherDevices($new);
        }
    }

    /**
     * Whether the user's password is older than `account.password_expiry_days` (0 = never). The age counts from
     * `password_changed_at`, else from the account's creation. Never expired when users may not change their password.
     */
    public function passwordExpired(User $user): bool
    {
        $days = (int) setting('account.password_expiry_days', 0);
        if ($days <= 0 || ! setting('account.can_change_password', true)) {
            return false;
        }
        $since = $user->getAttribute('password_changed_at') ?? $user->getAttribute('created_at');

        return $since === null || now()->subDays($days)->greaterThan($since);
    }

    public function photoUrl(?Person $person): ?string
    {
        $url = $person?->getFirstMediaUrl('profile_photos');

        return $url !== null && $url !== '' ? $url : null;
    }

    private function requirePerson(User $user, string $field): Person
    {
        $person = $user->person;
        if (! $person) {
            throw ValidationException::withMessages([$field => 'Your account has no person record; ask an administrator to link one.']);
        }

        return $person;
    }

    /** @return array{mobiles: list<array{value: string, type: string}>, emails: list<array{value: string, type: string}>, address: ?string} */
    private function contacts(?Person $person): array
    {
        $mobiles = $emails = [];
        foreach ($person?->contacts ?? [] as $contact) {
            $row = ['value' => (string) $contact->contact_detail, 'type' => (string) $contact->contact_type];
            match ($contact->data_type) {
                'Mobile', 'Landline' => $mobiles[] = $row,
                'Email' => $emails[] = $row,
                default => null,
            };
        }

        $address = $person?->primary_address?->full_address;

        return ['mobiles' => $mobiles, 'emails' => $emails, 'address' => $address !== null && $address !== '' ? $address : null];
    }

    /**
     * The "Permissions & scope" section (owner request 30-09): identity, the primary assignment, the add-on scopes, the
     * vehicle scope, verticals and the permission names grouped by module. Empty parts come back empty (the page shows
     * "—"), never invented. Codes carry their master name.
     *
     * @param  array<string, array{code: string, name: string}|null>  $primaries  from profile()
     * @param  array<string, list<string>>  $addons  from profile()
     * @return array{
     *   identity: array{employee_code: ?string, mile_id: ?string, designation: ?string},
     *   primary: array<string, array{code: string, name: string}|null>,
     *   addon: array<string, list<array{code: string, name: string}>>,
     *   vehicle: array<string, list<array{code: string, name: string}>>,
     *   verticals: list<array{code: string, name: string}>,
     *   superAdmin: bool,
     *   permissions: array<string, list<string>>
     * }
     */
    public function access(User $user, array $primaries, array $addons): array
    {
        $names = [
            'branch' => fn ($c) => OrgService::branchName($c), 'location' => fn ($c) => OrgService::locationName($c),
            'department' => fn ($c) => OrgService::departmentName($c), 'division' => fn ($c) => OrgService::divisionName($c),
            'vertical' => fn ($c) => OrgService::verticalName($c), 'segment' => fn ($c) => OrgService::segmentName($c),
            'sub_segment' => fn ($c) => OrgService::subSegmentName($c), 'model' => fn ($c) => OrgService::modelName($c),
            'variant' => fn ($c) => OrgService::variantName($c),
        ];
        $named = fn (string $level, array $codes) => array_values(array_map(
            fn ($code) => ['code' => (string) $code, 'name' => (string) ($names[$level]($code) ?: $code)],
            array_unique(array_filter(array_map('strval', $codes)))
        ));
        $withPrimary = fn (string $level) => $named($level, array_merge(
            isset($primaries[$level]) ? [$primaries[$level]['code']] : [],
            $addons[$level] ?? []
        ));

        $permissions = [];
        if (! $user->isSuperAdmin()) {
            foreach ($user->getAllPermissions()->pluck('name')->sort()->values() as $name) {
                $module = strtoupper((string) strtok((string) $name, '_.'));
                $permissions[$module][] = (string) $name;
            }
            ksort($permissions);
        }

        return [
            'identity' => [
                'employee_code' => $user->employee?->code,
                'mile_id' => $user->employee?->mile_id ?: null,
                'designation' => $user->primary_designation,
            ],
            'primary' => array_intersect_key($primaries, array_flip(['department', 'division', 'branch', 'location'])) + ['department' => null, 'division' => null, 'branch' => null, 'location' => null],
            'addon' => [
                'department' => $named('department', $addons['department'] ?? []),
                'division' => $named('division', $addons['division'] ?? []),
                'branch' => $named('branch', $addons['branch'] ?? []),
                'location' => $named('location', $addons['location'] ?? []),
            ],
            'vehicle' => [
                'segment' => $withPrimary('segment'),
                'sub_segment' => $withPrimary('sub_segment'),
                'model' => $named('model', $addons['model'] ?? []),
                'variant' => $named('variant', $addons['variant'] ?? []),
            ],
            'verticals' => $withPrimary('vertical'),
            'superAdmin' => $user->isSuperAdmin(),
            'permissions' => $permissions,
        ];
    }

    /** @return array<string, array{code: string, name: string}|null> */
    private function primaries(Employee $employee): array
    {
        $pick = fn (?string $code, callable $name) => $code ? ['code' => $code, 'name' => $name($code)] : null;

        return [
            'branch' => $pick($employee->primary_branch_code, fn ($c) => OrgService::branchName($c)),
            'location' => $pick($employee->primary_loc_code, fn ($c) => OrgService::locationName($c)),
            'department' => $pick($employee->primary_dept_code, fn ($c) => OrgService::departmentName($c)),
            'division' => $pick($employee->primary_div_code, fn ($c) => OrgService::divisionName($c)),
            'vertical' => $pick($employee->vertical_code, fn ($c) => OrgService::verticalName($c)),
            'segment' => $pick($employee->segment_code, fn ($c) => OrgService::segmentName($c)),
            'sub_segment' => $pick($employee->sub_segment_code, fn ($c) => OrgService::subSegmentName($c)),
        ];
    }

    /** Scope codes the user holds beyond their primary ones. @return array<string, list<string>> */
    private function addons(User $user, Employee $employee): array
    {
        $primary = [
            'branch' => $employee->primary_branch_code, 'location' => $employee->primary_loc_code,
            'department' => $employee->primary_dept_code, 'division' => $employee->primary_div_code,
            'vertical' => $employee->vertical_code, 'segment' => $employee->segment_code, 'sub_segment' => $employee->sub_segment_code,
        ];
        $out = [];
        foreach ($user->getAllScopes() as $type => $codes) {
            $type = strtolower((string) $type);
            $extra = array_values(array_diff($codes, [strtoupper((string) ($primary[$type] ?? ''))]));
            if ($extra !== []) {
                $out[$type] = $extra;
            }
        }

        return $out;
    }

    /** @return array{name: string, designation: ?string, photoUrl: ?string, code: ?string}|null */
    private function manager(User $user): ?array
    {
        $manager = $this->reporting->getDefaultReportingManager($user);
        if ($manager) {
            return [
                'name' => (string) $manager->display_name,
                'designation' => $manager->primary_designation,
                'photoUrl' => $this->photoUrl($manager->person),
                'code' => $manager->employee_code,
            ];
        }

        $code = $user->employee?->reporting_manager_code;
        $employee = $code ? Employee::where('code', $code)->first() : null;

        return $employee ? [
            'name' => (string) ($employee->person?->display_name ?? $code),
            'designation' => $employee->designation?->name ?? $employee->designation_code,
            'photoUrl' => $this->photoUrl($employee->person),
            'code' => $code,
        ] : null;
    }

    /** Newest first, with names resolved. @return list<array<string, mixed>> */
    private function history(Employee $employee): array
    {
        return $this->journeys->journey((string) $employee->code)
            ->sortByDesc(fn (EmployeeHistory $h) => $h->effective_from?->timestamp ?? 0)
            ->values()
            ->map(fn (EmployeeHistory $h) => [
                'from' => $h->effective_from,
                'to' => $h->effective_to,
                'designation' => $h->designation_code,
                'branch' => $h->primary_branch_code ? OrgService::branchName($h->primary_branch_code) : null,
                'location' => $h->primary_loc_code ? OrgService::locationName($h->primary_loc_code) : null,
                'department' => $h->primary_dept_code ? OrgService::departmentName($h->primary_dept_code) : null,
                'reason' => $h->change_reason,
                'notes' => $h->notes,
            ])->all();
    }
}
