<?php

namespace Tests\Feature\Person;

use App\Models\Admin\Person;
use App\Models\Admin\PersonAddress;
use App\Models\Admin\PersonContact;
use App\Models\User;
use App\Services\Person\PersonAddressService;
use App\Services\Person\PersonContactService;
use App\Services\Person\PersonRecordService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-053: persons and their contacts/addresses/banking are written only through their entity
 * services — the Person screen, the standalone contact screen and the user importer call these.
 */
class PersonEntityServicesTest extends TestCase
{
    use DatabaseTransactions;

    private function aPerson(): Person
    {
        return app(PersonRecordService::class)->create(['display_name' => 'slot test person']);
    }

    /** BUG-206 (DEC-095 #15): the code is generated, never the Aadhaar / PAN. */
    public function test_person_fields_are_normalised_and_the_code_is_generated_not_a_government_id(): void
    {
        $person = app(PersonRecordService::class)->create([
            'display_name' => '  ravi   kumar sharma ',
            'aadhaar_no' => '2345 6789 0199',
            'pan_no' => 'abcde1234f',
            'gender' => 'male',
            'salutation' => 'mr.',
            'dob' => '1990-05-17',
            'mobile' => '+91 98765 00111',
        ]);

        $this->assertMatchesRegularExpression('/^PERS-\d{6,}$/', $person->person_code);
        $this->assertSame('234567890199', $person->aadhaar_no);
        $this->assertSame('Ravi Kumar Sharma', $person->display_name);
        $this->assertSame(['Ravi', 'Kumar', 'Sharma'], [$person->first_name, $person->middle_name, $person->last_name]);
        $this->assertSame(['ABCDE1234F', 'Male', 'Mr'], [$person->pan_no, $person->gender, $person->salutation]);
        $this->assertSame('9876500111', $person->primary_mobile);
    }

    /** BUG-206: an upsert without a code finds the same person by Aadhaar (then PAN), also a deleted one. */
    public function test_an_upsert_finds_the_existing_person_by_a_government_id(): void
    {
        $service = app(PersonRecordService::class);
        $first = $service->create(['display_name' => 'Dedupe Person', 'aadhaar_no' => '345678901288', 'pan_no' => 'BCDEF2345G']);

        $byAadhaar = $service->upsert(['display_name' => 'Dedupe Person Again', 'aadhaar_no' => '3456 7890 1288']);
        $this->assertSame($first->person_code, $byAadhaar->person_code);

        $first->delete();
        $byPan = $service->upsert(['display_name' => 'Dedupe Person', 'pan_no' => 'bcdef2345g']);
        $this->assertSame($first->person_code, $byPan->person_code);
        $this->assertNull($byPan->fresh()->deleted_at, 'the deleted person is restored');
        $this->assertSame(1, Person::withTrashed()->where('pan_no', 'BCDEF2345G')->count());
    }

    public function test_a_two_word_name_splits_into_first_and_last_only(): void
    {
        $person = app(PersonRecordService::class)->create(['display_name' => 'Asha Verma']);

        $this->assertSame(['Asha', null, 'Verma'], [$person->first_name, $person->middle_name, $person->last_name]);
        $this->assertStringStartsWith('PERS-', $person->person_code);
    }

    public function test_an_identifier_used_by_a_deleted_person_is_rejected_not_a_database_error(): void
    {
        $first = app(PersonRecordService::class)->create(['display_name' => 'First Holder', 'pan_no' => 'ZZZZZ9999Z']);
        $first->delete();

        $this->expectException(ValidationException::class);
        app(PersonRecordService::class)->create(['display_name' => 'Second Holder', 'pan_no' => 'zzzzz9999z']);
    }

    public function test_contact_detail_is_formatted_and_validated_by_its_data_type(): void
    {
        $person = $this->aPerson();
        $contacts = app(PersonContactService::class);

        $email = $contacts->create(['person_code' => $person->person_code, 'data_type' => 'email', 'contact_detail' => ' Ravi.K@Example.COM ']);
        $this->assertSame(['Email', 'Primary', 'ravi.k@example.com'], [$email->data_type, $email->contact_type, $email->contact_detail]);

        $this->expectException(ValidationException::class);
        $contacts->create(['person_code' => $person->person_code, 'data_type' => 'Email', 'contact_detail' => 'ravi k@example.com']);
    }

    public function test_asking_for_primary_promotes_the_new_contact_and_demotes_the_old_one(): void
    {
        $person = $this->aPerson();
        $contacts = app(PersonContactService::class);
        $old = $contacts->create(['person_code' => $person->person_code, 'data_type' => 'Mobile', 'contact_detail' => '9876500201']);

        $new = $contacts->create(['person_code' => $person->person_code, 'data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9876500202']);

        $this->assertSame('Primary', $new->fresh()->contact_type);
        $this->assertNotSame('Primary', $old->fresh()->contact_type);
        $this->assertSame('9876500202', $person->primary_mobile);
    }

    public function test_promoting_swaps_slots_even_when_alternate_is_taken(): void
    {
        $person = $this->aPerson();
        $contacts = app(PersonContactService::class);
        $base = ['person_code' => $person->person_code, 'data_type' => 'Mobile'];
        $primary = $contacts->create($base + ['contact_type' => 'Primary', 'contact_detail' => '9876500301']);
        $contacts->create($base + ['contact_type' => 'Alternate', 'contact_detail' => '9876500302']);
        $office = $contacts->create($base + ['contact_type' => 'Office', 'contact_detail' => '9876500303']);

        $office->makesPrimary();

        $this->assertSame('Primary', $office->fresh()->contact_type);
        $this->assertSame('Office', $primary->fresh()->contact_type);
    }

    public function test_a_deleted_address_frees_its_slot_for_a_new_one(): void
    {
        $person = $this->aPerson();
        $addresses = app(PersonAddressService::class);
        $home = $addresses->create(['person_code' => $person->person_code, 'address_type' => 'Home', 'city' => 'jaipur', 'pincode' => '302001']);
        $this->assertSame('Jaipur', $home->city);

        $addresses->delete($home);
        $again = $addresses->create(['person_code' => $person->person_code, 'address_type' => 'Home', 'city' => 'Kota']);

        $this->assertSame('Home', $again->address_type);
        $this->assertSame(1, PersonAddress::withTrashed()->where('person_code', $person->person_code)->count());
    }

    public function test_a_used_non_primary_slot_is_a_validation_error(): void
    {
        $person = $this->aPerson();
        $contacts = app(PersonContactService::class);
        $contacts->create(['person_code' => $person->person_code, 'data_type' => 'Mobile', 'contact_type' => 'Home', 'contact_detail' => '9876500401']);

        $this->expectException(ValidationException::class);
        $contacts->create(['person_code' => $person->person_code, 'data_type' => 'Mobile', 'contact_type' => 'Home', 'contact_detail' => '9876500402']);
    }

    public function test_the_standalone_contact_screen_writes_through_the_service(): void
    {
        $person = $this->aPerson();
        Permission::firstOrCreate(['name' => 'ORG_PRSN_CREATE', 'guard_name' => 'web']);
        $user = User::create(['username' => 'test_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo('ORG_PRSN_CREATE');
        $this->app['auth']->guard('backpack')->setUser($user);

        $this->post(backpack_url('org/person-contact'), [
            'person_code' => strtolower($person->person_code),
            'data_type' => 'Mobile',
            'contact_type' => 'Primary',
            'contact_detail' => '0 98765 00501',
        ])->assertRedirect();

        $this->assertSame('9876500501', PersonContact::where('person_code', $person->person_code)->value('contact_detail'));
    }
}
