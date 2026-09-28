<?php

declare(strict_types=1);

namespace App\Services\IAM;

use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeHistory;
use App\Models\Admin\Person;
use App\Models\User;
use App\Services\HR\EmployeeJourneyService;
use App\Services\HR\UserReportingService;
use App\Services\IAM\DataScope\ScopeResolver;
use App\Services\IAM\DataScope\ScopeSet;
use App\Services\OrgService;
use App\Services\Person\PersonRecordService;
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
     * Change the password after checking the current one; other sessions of this user are signed out.
     *
     * @throws ValidationException on a wrong current password or a new one equal to it
     */
    public function changePassword(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is not correct.']);
        }
        if (Hash::check($new, (string) $user->password)) {
            throw ValidationException::withMessages(['new_password' => 'The new password must be different from the current one.']);
        }

        $user->forceFill(['password' => Hash::make($new)])->save();

        $guard = Auth::guard(backpack_guard_name());
        if ($guard->check() && (int) $guard->id() === (int) $user->id && method_exists($guard, 'logoutOtherDevices')) {
            $guard->logoutOtherDevices($new);
        }
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
