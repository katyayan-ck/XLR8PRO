<?php

namespace App\Services\IAM;

use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeHistory;
use App\Models\Admin\Person;
use App\Models\Admin\PersonAddress;
use App\Models\Admin\PersonBankingDetail;
use App\Models\Admin\PersonContact;
use App\Models\Admin\PersonUserType;
use App\Models\Admin\UserReporting;
use App\Models\Admin\UserScope;
use App\Models\CRM\Enquiry;
use App\Models\IAM\AccountLock;
use App\Models\IAM\DeviceSession;
use App\Models\IAM\LegacyUserBranch;
use App\Models\IAM\ModelHasPermission;
use App\Models\IAM\ModelHasRole;
use App\Models\IAM\OtpAttemptLog;
use App\Models\IAM\OtpToken;
use App\Models\IAM\UserDeviceToken;
use App\Models\IAM\UserPermissionDenial;
use App\Models\IAM\UserRoleAssignment;
use App\Models\User;
use App\Models\Utilities\CommHistory\CommSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\PermissionRegistrar;

/**
 * Owner #18 (DEC-095, W18j): before the new user data is imported, keep only a given list of login accounts and remove
 * every other user and the HR identity behind it **permanently** — the user, its access rows (roles, permissions,
 * scopes, tokens, devices, OTP, reporting, legacy branches, subscriptions), all employees and employee history except
 * the kept users', and every person not belonging to a kept user and not used by a business record (enquiries).
 * History and business rows that name a user (enquiries, audits, timeline, tasks, documents) are kept as they are;
 * media files are not touched (counted only). Run through `users:reset`, which refuses outside `local` and dumps the
 * affected tables first.
 *
 * Example:
 *
 *   $plan = app(UserResetService::class)->plan(['superadmin', 'dev1']);   // ['ok' => true, 'remove' => [...], ...]
 */
class UserResetService
{
    /**
     * What a reset with this keep list would remove (nothing is written).
     *
     * @param  list<string>  $keepUsernames
     * @return array{ok: bool, error: ?string, keep: list<string>, remove_user_ids: list<int>, remove_person_codes: list<string>, counts: array<string, int>, media_left: int}
     */
    public function plan(array $keepUsernames): array
    {
        $keepUsernames = array_values(array_unique(array_filter(array_map('trim', $keepUsernames))));
        $kept = User::withTrashed()->whereIn('username', $keepUsernames)->get();
        $empty = ['keep' => $keepUsernames, 'remove_user_ids' => [], 'remove_person_codes' => [], 'counts' => [], 'media_left' => 0];

        $missing = array_values(array_diff($keepUsernames, $kept->pluck('username')->all()));
        if ($keepUsernames === [] || $missing !== []) {
            return ['ok' => false, 'error' => $keepUsernames === [] ? 'The keep list is empty.' : 'Unknown username(s): '.implode(', ', $missing)] + $empty;
        }
        if (! $kept->contains(fn (User $u) => $u->getAttribute('deleted_at') === null && (bool) $u->getAttribute('is_active') && $u->isSuperAdmin())) {
            return ['ok' => false, 'error' => 'The keep list must include an active superadmin.'] + $empty;
        }

        $removeIds = User::withTrashed()->whereNotIn('id', $kept->pluck('id'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $keptPersons = $kept->pluck('person_code')->filter()->values()->all();
        $usedByBusiness = Enquiry::query()->withoutGlobalScopes()->whereNotNull('person_code')->distinct()->pluck('person_code')->all();
        $removePersons = Person::withTrashed()->whereNotIn('person_code', array_merge($keptPersons, $usedByBusiness))
            ->pluck('person_code')->filter()->values()->all();

        $counts = [];
        foreach ($this->targets($removeIds, $removePersons, $keptPersons) as $label => $query) {
            $counts[$label] = (clone $query)->count();
        }
        $mediaLeft = Media::query()->where(fn ($q) => $q
            ->where(fn ($m) => $m->where('model_type', User::class)->whereIn('model_id', $removeIds))
            ->orWhere(fn ($m) => $m->where('model_type', Person::class)->whereIn('model_id', Person::withTrashed()->whereIn('person_code', $removePersons)->pluck('id'))))
            ->count();

        return ['ok' => true, 'error' => null, 'keep' => $keepUsernames, 'remove_user_ids' => $removeIds,
            'remove_person_codes' => $removePersons, 'counts' => $counts, 'media_left' => $mediaLeft];
    }

    /**
     * Removes everything the plan lists, in one transaction.
     *
     * @param  list<string>  $keepUsernames
     * @return array{ok: bool, error: ?string, keep: list<string>, remove_user_ids: list<int>, remove_person_codes: list<string>, counts: array<string, int>, media_left: int}
     */
    public function apply(array $keepUsernames): array
    {
        $plan = $this->plan($keepUsernames);
        if (! $plan['ok']) {
            return $plan;
        }
        $keptPersons = User::withTrashed()->whereIn('username', $plan['keep'])->pluck('person_code')->filter()->values()->all();

        DB::transaction(function () use ($plan, $keptPersons) {
            foreach ($this->targets($plan['remove_user_ids'], $plan['remove_person_codes'], $keptPersons) as $query) {
                $query->delete();   // base query: permanent, no model events
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $plan;
    }

    /** Tables `users:reset` dumps before it applies (everything `targets()` writes). @return list<string> */
    public function backupTables(): array
    {
        return array_values(array_unique(array_map(fn (Model $m) => $m->getTable(), [
            new User, new ModelHasRole, new ModelHasPermission, new UserRoleAssignment, new UserPermissionDenial, new UserScope,
            new UserReporting, new PersonUserType, new PersonalAccessToken, new DeviceSession, new UserDeviceToken, new OtpToken,
            new OtpAttemptLog, new AccountLock, new LegacyUserBranch, new CommSubscription, new Employee, new EmployeeHistory,
            new PersonContact, new PersonAddress, new PersonBankingDetail, new Person,
        ])));
    }

    /**
     * Base delete queries, children before parents.
     *
     * @param  list<int>  $userIds
     * @param  list<string>  $personCodes  persons to remove
     * @param  list<string>  $keptPersons  person codes of the kept users (their employees stay)
     * @return array<string, Builder>
     */
    private function targets(array $userIds, array $personCodes, array $keptPersons): array
    {
        $byUser = fn (Model $m, string $column = 'user_id') => $m->newQueryWithoutScopes()->whereIn($column, $userIds)->toBase();
        $byPerson = fn (Model $m) => $m->newQueryWithoutScopes()->whereIn('person_code', $personCodes)->toBase();
        $notKeptPerson = fn (Model $m) => $m->newQueryWithoutScopes()->where(fn ($q) => $q->whereNull('person_code')->orWhereNotIn('person_code', $keptPersons))->toBase();

        return [
            'roles' => (new ModelHasRole)->newQueryWithoutScopes()->where('model_type', User::class)->whereIn('model_id', $userIds)->toBase(),
            'direct permissions' => (new ModelHasPermission)->newQueryWithoutScopes()->where('model_type', User::class)->whereIn('model_id', $userIds)->toBase(),
            'legacy role pivot' => $byUser(new UserRoleAssignment),
            'permission denials' => $byUser(new UserPermissionDenial),
            'data scopes' => $byUser(new UserScope),
            'reporting lines' => $byUser(new UserReporting),
            'person user types' => $byUser(new PersonUserType),
            'API tokens' => (new PersonalAccessToken)->newQueryWithoutScopes()->where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)->toBase(),
            'device sessions' => $byUser(new DeviceSession),
            'device tokens' => $byUser(new UserDeviceToken),
            'OTP tokens' => $byUser(new OtpToken),
            'OTP attempts' => $byUser(new OtpAttemptLog),
            'account locks' => $byUser(new AccountLock),
            'legacy branches' => $byUser(new LegacyUserBranch),
            'chat subscriptions' => $byUser(new CommSubscription),
            'users' => (new User)->newQueryWithoutScopes()->whereIn('id', $userIds)->toBase(),
            'employee history' => $notKeptPerson(new EmployeeHistory),
            'employees' => $notKeptPerson(new Employee),
            'person contacts' => $byPerson(new PersonContact),
            'person addresses' => $byPerson(new PersonAddress),
            'person banking' => $byPerson(new PersonBankingDetail),
            'persons' => $byPerson(new Person),
        ];
    }
}
