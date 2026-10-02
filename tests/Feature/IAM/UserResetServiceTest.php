<?php

namespace Tests\Feature\IAM;

use App\Models\Admin\Employee;
use App\Models\Admin\Person;
use App\Models\Admin\UserScope;
use App\Models\CRM\Enquiry;
use App\Models\User;
use App\Services\IAM\UserResetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Owner #18 (W18j): the reset keeps only the listed accounts (with their roles, scopes and employee record), removes
 * every other user / employee / person permanently, keeps persons used by business records, and refuses a keep list
 * without an active superadmin.
 */
class UserResetServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_keep_list_without_an_active_superadmin_is_refused_and_nothing_changes(): void
    {
        $user = User::query()->whereDoesntHave('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail();
        $count = User::withTrashed()->count();

        $plan = app(UserResetService::class)->apply([$user->username]);

        $this->assertFalse($plan['ok']);
        $this->assertSame($count, User::withTrashed()->count());
    }

    public function test_only_the_kept_accounts_and_their_identity_remain(): void
    {
        $super = User::role('superadmin')->where('is_active', 1)->firstOrFail();
        $kept = User::query()->whereIn('person_code', Employee::query()->select('person_code'))
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'superadmin'))->whereHas('roles')->firstOrFail();
        $keptRoles = $kept->getRoleNames()->all();
        $keptScopes = UserScope::query()->where('user_id', $kept->id)->count();
        $customer = Person::query()->whereNotIn('person_code', [$kept->person_code, (string) $super->person_code])->firstOrFail();
        $enquiry = Enquiry::query()->withoutGlobalScopes()->firstOrFail();
        $enquiry->forceFill(['person_code' => $customer->person_code])->saveQuietly();   // a person used by a business record

        $plan = app(UserResetService::class)->apply([$super->username, $kept->username]);

        $this->assertTrue($plan['ok']);
        $this->assertEqualsCanonicalizing([$super->id, $kept->id], User::withTrashed()->pluck('id')->all());
        $this->assertSame($keptRoles, $kept->fresh()->getRoleNames()->all());
        $this->assertSame($keptScopes, UserScope::query()->where('user_id', $kept->id)->count());
        $this->assertTrue(Employee::withTrashed()->where('person_code', $kept->person_code)->exists(), 'the kept user keeps its employee record');
        $this->assertSame(0, Employee::withTrashed()->where('person_code', '!=', $kept->person_code)->where('person_code', '!=', (string) $super->person_code)->count());
        $this->assertTrue(Person::withTrashed()->where('person_code', $customer->person_code)->exists(), 'a person used by an enquiry stays');
        $this->assertSame(0, UserScope::query()->whereNotIn('user_id', [$super->id, $kept->id])->count());
    }
}
