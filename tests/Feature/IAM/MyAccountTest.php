<?php

namespace Tests\Feature\IAM;

use App\Models\IAM\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * DEC-072: My Account shows the signed-in user's own organisation, scopes and history read-only, and lets them change
 * their display name, profile photo and password (after checking the current one). The username is not editable.
 */
class MyAccountTest extends TestCase
{
    use DatabaseTransactions;

    private function employeeUser(): User
    {
        $user = User::query()->where('is_active', 1)->whereNotNull('employee_code')->whereNotNull('person_code')
            ->get()->first(fn (User $u) => ! $u->isSuperAdmin() && $u->employee && $u->person);
        if (! $user) {
            $this->markTestSkipped('Needs an active employee user with a person record.');
        }
        $user->forceFill(['password' => Hash::make('OldPass123')])->save();

        return $user;
    }

    public function test_an_employee_sees_their_organisation_scopes_and_history(): void
    {
        $user = $this->employeeUser();

        $response = $this->actingAs($user, 'backpack')->get(route('backpack.account.info'));

        $response->assertOk()
            ->assertSee($user->display_name)
            ->assertSee('Permissions &amp; scope', false)
            // owner request 30-09: the fields the Permissions & scope section always lists (blank → "—")
            ->assertSeeInOrder(['Employee code', 'OEM Mile ID', 'Designation'])
            ->assertSeeInOrder(['Primary department', 'Primary division', 'Primary branch', 'Primary location'])
            ->assertSeeInOrder(['Add-on departments', 'Add-on divisions', 'Add-on branches', 'Add-on locations'])
            ->assertSeeInOrder(['Segments', 'Sub-segments', 'Models', 'Variants', 'Verticals'])
            ->assertSee('Permissions')
            ->assertSee('Effective data access')
            ->assertSee('Employment history');
        if ($user->primary_designation) {
            $response->assertSee($user->primary_designation);
        }
    }

    public function test_a_user_without_an_employee_record_sees_profile_contact_and_security_only(): void
    {
        // only user_type Emp may enter the admin panel (CheckIfAdmin); this one has no employee / person record
        $user = User::create(['username' => 'acct_test_'.uniqid(), 'password' => Hash::make('OldPass123'), 'user_type' => 'Emp', 'is_active' => 1]);
        // the admin area itself needs a role that may open the dashboard
        $role = Role::query()->whereHas('permissions', fn ($q) => $q->where('name', 'admin.dashboard'))
            ->where('name', '!=', 'superadmin')->first();
        if (! $role) {
            $this->markTestSkipped('Needs a role with admin.dashboard.');
        }
        $user->assignRole($role);

        $this->actingAs($user, 'backpack')->get(route('backpack.account.info'))
            ->assertOk()
            ->assertSee('Security')
            ->assertDontSee('Employment history')
            ->assertSee('not linked to a person record');
    }

    public function test_the_display_name_is_saved_on_the_person_and_the_username_is_ignored(): void
    {
        $user = $this->employeeUser();
        $username = $user->username;

        $this->actingAs($user, 'backpack')
            ->post(route('backpack.account.info.store'), ['display_name' => 'ravi kumar sharma', 'username' => 'hacked'])
            ->assertRedirect(route('backpack.account.info'));

        $this->assertSame('Ravi Kumar Sharma', $user->person->fresh()->display_name);
        $this->assertSame($username, $user->fresh()->username);
    }

    public function test_a_profile_photo_can_be_uploaded_and_removed(): void
    {
        $user = $this->employeeUser();
        $this->actingAs($user, 'backpack');

        $this->post(route('backpack.account.photo'), ['profile_photo' => UploadedFile::fake()->image('me.jpg', 200, 200)])
            ->assertRedirect(route('backpack.account.info'));
        $this->assertCount(1, $user->person->fresh()->getMedia('profile_photos'));
        $this->assertNotNull($user->fresh()->profilePhotoUrl());

        $this->post(route('backpack.account.photo'), ['remove' => 1])->assertRedirect(route('backpack.account.info'));
        $this->assertCount(0, $user->person->fresh()->getMedia('profile_photos'));
    }

    public function test_the_password_changes_only_with_the_right_current_password_and_a_valid_new_one(): void
    {
        $user = $this->employeeUser();
        $this->actingAs($user, 'backpack');
        $url = route('backpack.account.password');

        $this->from(route('backpack.account.info'))->post($url, ['current_password' => 'Wrong999', 'new_password' => 'NewPass456', 'new_password_confirmation' => 'NewPass456'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->from(route('backpack.account.info'))->post($url, ['current_password' => 'OldPass123', 'new_password' => 'onlyletters', 'new_password_confirmation' => 'onlyletters'])
            ->assertSessionHasErrorsIn('password', 'new_password');
        $this->assertTrue(Hash::check('OldPass123', $user->fresh()->password));

        $this->post($url, ['current_password' => 'OldPass123', 'new_password' => 'NewPass456', 'new_password_confirmation' => 'NewPass456'])
            ->assertRedirect(route('backpack.account.info'));
        $this->assertTrue(Hash::check('NewPass456', $user->fresh()->password));
    }
}
