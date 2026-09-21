<?php

namespace Tests\Feature\Admin\Org;

use App\Models\Admin\Person;
use App\Models\User;
use App\Services\PersonService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PersonCrudTest extends TestCase
{
    use DatabaseTransactions;

    /** See BUG-079 in known-bugs-report.md for why actingAs($user, 'backpack') can't be used here. */
    private function actingAsBackpackUser(User $user): static
    {
        $this->app['auth']->guard('backpack')->setUser($user);

        return $this;
    }

    private function userWithPersonPermissions(): User
    {
        foreach (['ORG_PRSN_VIEW', 'ORG_PRSN_CREATE', 'ORG_PRSN_EDIT'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $user = User::create([
            'username' => 'test_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
        $user->givePermissionTo(['ORG_PRSN_VIEW', 'ORG_PRSN_CREATE', 'ORG_PRSN_EDIT']);

        return $user;
    }

    public function test_creating_a_person_with_only_name_and_mobile_derives_a_fallback_code(): void
    {
        $user = $this->userWithPersonPermissions();

        $response = $this->actingAsBackpackUser($user)->post(backpack_url('org/person'), [
            'display_name' => 'Minimal Person',
            'mobile' => '9876500001',
        ]);

        $person = Person::where('display_name', 'Minimal Person')->first();

        $response->assertRedirect(backpack_url("org/person/{$person->id}/edit"));
        $this->assertStringStartsWith('PERS-', $person->person_code);
        $this->assertSame('9876500001', $person->primary_mobile);
    }

    public function test_creating_a_person_without_a_mobile_fails_validation(): void
    {
        $user = $this->userWithPersonPermissions();

        $response = $this->actingAsBackpackUser($user)->post(backpack_url('org/person'), [
            'display_name' => 'No Mobile Person',
        ]);

        $response->assertSessionHasErrors('mobile');
        $this->assertDatabaseMissing('xlr8_admin_person', ['display_name' => 'No Mobile Person']);
    }

    public function test_aadhaar_takes_priority_over_pan_when_deriving_the_person_code(): void
    {
        $user = $this->userWithPersonPermissions();

        $this->actingAsBackpackUser($user)->post(backpack_url('org/person'), [
            'display_name' => 'Aadhaar Priority Person',
            'mobile' => '9876500002',
            'aadhaar_no' => '123456789012',
            'pan_no' => 'ABCDE1234F',
        ]);

        $person = Person::where('display_name', 'Aadhaar Priority Person')->first();

        $this->assertSame('123456789012', $person->person_code);
    }

    public function test_updating_a_person_cannot_change_its_person_code(): void
    {
        $person = PersonService::upsert([
            'display_name' => 'Immutable Code Person',
            'contacts' => [['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9876500003', 'is_primary' => true]],
        ]);
        $originalCode = $person->person_code;
        $user = $this->userWithPersonPermissions();

        $this->actingAsBackpackUser($user)->put(backpack_url("org/person/{$person->id}"), [
            'display_name' => 'Renamed Person',
            'aadhaar_no' => '999999999999',
        ]);

        $person->refresh();
        $this->assertSame($originalCode, $person->person_code);
        $this->assertSame('Renamed Person', $person->display_name);
    }

    public function test_adding_and_removing_a_contact_from_the_edit_screen(): void
    {
        $person = PersonService::upsert([
            'display_name' => 'Contact Mgmt Person',
            'contacts' => [['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9876500004', 'is_primary' => true]],
        ]);
        $user = $this->userWithPersonPermissions();

        $addResponse = $this->actingAsBackpackUser($user)->post(backpack_url("org/person/{$person->id}/contacts"), [
            'data_type' => 'Email',
            'contact_type' => 'Primary',
            'contact_detail' => 'test@example.com',
        ]);
        $addResponse->assertRedirect();
        $this->assertSame(2, $person->contacts()->count());

        $emailContact = $person->contacts()->where('data_type', 'Email')->first();
        $removeResponse = $this->actingAsBackpackUser($user)->delete(backpack_url("org/person/{$person->id}/contacts/{$emailContact->id}"));
        $removeResponse->assertRedirect();
        $this->assertSame(1, $person->contacts()->count());
    }

    public function test_promoting_an_alternate_contact_to_primary_demotes_the_old_primary(): void
    {
        $person = PersonService::upsert([
            'display_name' => 'Primary Swap Person',
            'contacts' => [
                ['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9876500005', 'is_primary' => true],
                ['data_type' => 'Mobile', 'contact_type' => 'Alternate', 'contact_detail' => '9876500006'],
            ],
        ]);
        $user = $this->userWithPersonPermissions();
        $altContact = $person->contacts()->where('contact_type', 'Alternate')->first();

        $response = $this->actingAsBackpackUser($user)->post(backpack_url("org/person/{$person->id}/contacts/{$altContact->id}/primary"));

        $response->assertRedirect();
        $person->refresh();
        $this->assertSame('9876500006', $person->primary_mobile);
        $this->assertSame(2, $person->contacts()->count());
    }

    public function test_adding_a_primary_address_and_making_a_second_one_primary(): void
    {
        $person = PersonService::upsert([
            'display_name' => 'Address Mgmt Person',
            'contacts' => [['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9876500007', 'is_primary' => true]],
        ]);
        $user = $this->userWithPersonPermissions();

        $this->actingAsBackpackUser($user)->post(backpack_url("org/person/{$person->id}/addresses"), [
            'address_type' => 'Primary',
            'city' => 'CityOne',
        ]);
        $this->actingAsBackpackUser($user)->post(backpack_url("org/person/{$person->id}/addresses"), [
            'address_type' => 'Alternate',
            'city' => 'CityTwo',
        ]);

        $altAddress = $person->addresses()->where('address_type', 'Alternate')->first();
        $response = $this->actingAsBackpackUser($user)->post(backpack_url("org/person/{$person->id}/addresses/{$altAddress->id}/primary"));

        $response->assertRedirect();
        $this->assertSame('CityTwo', $person->addresses()->where('address_type', 'Primary')->first()->city);
        $this->assertSame(2, $person->addresses()->count());
    }

    public function test_a_user_without_the_permission_cannot_update_a_person(): void
    {
        $person = PersonService::upsert([
            'display_name' => 'No Perm Person',
            'contacts' => [['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9876500008', 'is_primary' => true]],
        ]);
        $user = User::create([
            'username' => 'noperm_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);

        $response = $this->actingAsBackpackUser($user)->put(backpack_url("org/person/{$person->id}"), [
            'display_name' => 'Attempted Update',
        ]);

        $response->assertForbidden();
        $this->assertSame('No Perm Person', $person->fresh()->display_name);
    }
}
