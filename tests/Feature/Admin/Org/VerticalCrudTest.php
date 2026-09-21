<?php

namespace Tests\Feature\Admin\Org;

use App\Models\Admin\Employee;
use App\Models\Admin\Person;
use App\Models\Admin\Vertical;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VerticalCrudTest extends TestCase
{
    use DatabaseTransactions;

    /** See BUG-079 in known-bugs-report.md for why actingAs($user, 'backpack') can't be used here. */
    private function actingAsBackpackUser(User $user): static
    {
        $this->app['auth']->guard('backpack')->setUser($user);

        return $this;
    }

    private function userWithManagePermission(): User
    {
        Permission::firstOrCreate(['name' => 'ORG_ENTITY_MANAGE', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'test_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
        $user->givePermissionTo('ORG_ENTITY_MANAGE');

        return $user;
    }

    public function test_updating_a_vertical_cannot_change_its_code(): void
    {
        $vertical = Vertical::create(['code' => 'ORIGVERT', 'name' => 'Original Vertical']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/vertical/'.$vertical->id),
            ['code' => 'HACKEDVERT', 'name' => 'Updated Vertical', 'is_active' => 1]
        );

        $response->assertRedirect(backpack_url('org/vertical'));
        $vertical->refresh();
        $this->assertSame('ORIGVERT', $vertical->code);
        $this->assertSame('Updated Vertical', $vertical->name);
    }

    public function test_disabling_a_vertical_is_blocked_while_it_has_an_active_employee(): void
    {
        $vertical = Vertical::create(['code' => 'BLOCKVERT', 'name' => 'Blocked Vertical']);
        $person = Person::create(['person_code' => 'PSN_'.uniqid()]);
        Employee::create([
            'code' => 'EMP_'.uniqid(),
            'person_code' => $person->person_code,
            'vertical_code' => 'BLOCKVERT',
            'employment_status' => 'active',
        ]);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/vertical/'.$vertical->id),
            ['code' => 'BLOCKVERT', 'name' => 'Blocked Vertical', 'is_active' => 0]
        );

        $response->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $vertical->fresh()->is_active);
    }

    public function test_disabling_a_vertical_succeeds_once_it_has_no_active_employees(): void
    {
        $vertical = Vertical::create(['code' => 'FREEVERT', 'name' => 'Free Vertical']);
        $user = $this->userWithManagePermission();

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/vertical/'.$vertical->id),
            ['code' => 'FREEVERT', 'name' => 'Free Vertical', 'is_active' => 0]
        );

        $response->assertRedirect(backpack_url('org/vertical'));
        $this->assertFalse((bool) $vertical->fresh()->is_active);
    }

    public function test_a_user_without_the_permission_cannot_update_a_vertical(): void
    {
        $vertical = Vertical::create(['code' => 'NOPERMVERT', 'name' => 'No Perm Vertical']);
        $user = User::create([
            'username' => 'noperm_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);

        $response = $this->actingAsBackpackUser($user)->put(
            backpack_url('org/vertical/'.$vertical->id),
            ['code' => 'NOPERMVERT', 'name' => 'Attempted Update', 'is_active' => 1]
        );

        $response->assertForbidden();
        $this->assertSame('No Perm Vertical', $vertical->fresh()->name);
    }
}
