<?php

namespace Tests\Unit;

use App\Models\Admin\Person;
use App\Models\Admin\Employee;
use App\Models\User;
use App\Services\KeywordValueService;
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RBACPersonEmployeeUserTest extends TestCase
{
    use DatabaseTransactions;

    /** ==================== KEYWORD VALUE SERVICE ==================== */
    public function test_keyword_value_service_works_with_uppercase()
    {
        KeywordMaster::create(['code' => 'VTRANS', 'keyword' => 'Vehicle Transmission']);
        Keyvalue::create([
            'keyword_code' => 'VTRANS',
            'code'         => 'AUTO',
            'value'        => 'Automatic'
        ]);

        $this->assertEquals('AUTO', KeywordValueService::getCode('VTRANS', 'AUTO'));
        $this->assertEquals('AUTO', KeywordValueService::getCode('vtrans', 'auto'));
        $this->assertIsArray(KeywordValueService::getEnum('VTRANS'));
    }

    /** ==================== PERSON MODEL ==================== */
    public function test_person_creates_with_name_split_and_all_accessors()
    {
        $person = Person::create([
            'person_code'  => 'TEST_' . time() . '_001',
            'display_name' => 'Ashraf Ali Khan',
            'first_name'   => 'Ashraf',
            'middle_name'  => 'Ali',
            'last_name'    => 'Khan',
            'gender'       => 'Male',
            'dob'          => '1990-05-15',
            'pan_no'       => 'TESTPAN' . rand(1000, 9999),
        ]);

        $this->assertEquals('Ashraf', $person->first_name);
        $this->assertEquals('Ali', $person->middle_name);
        $this->assertEquals('Khan', $person->last_name);
        $this->assertEquals('Ashraf Ali Khan', $person->full_name);
        $this->assertEquals('Ashraf Ali Khan', $person->display_name);
    }

    public function test_person_address_and_banking_accessors()
    {
        $person = Person::create([
            'person_code'  => 'TEST_' . time() . '_002',
            'display_name' => 'Test User'
        ]);

        $person->addresses()->create([
            'address_type'   => 'Primary',
            'address_line_1' => 'Near Bhairav Temple',
            'city'           => 'Bikaner',
            'state'          => 'Rajasthan',
            'pincode'        => '334001',
        ]);

        $person->bankingDetails()->create([
            'account_type'       => 'Primary',
            'bank_name'          => 'Sample Bank',
            'account_number'     => '9988000050',
            'ifsc_code'          => 'SAMP0001234',
            'account_holder_name'=> 'Test User',
        ]);

        $this->assertNotNull($person->primary_address);
        $this->assertNotNull($person->primary_bank);
        $this->assertCount(1, $person->all_addresses);
        $this->assertCount(1, $person->all_banking);
    }

    /** ==================== EMPLOYEE + USER MODEL ==================== */
    public function test_employee_and_user_proxy_accessors_work()
    {
        $personCode = 'TEST_' . time() . '_003';

        $person = Person::create([
            'person_code'  => $personCode,
            'display_name' => 'John Doe',
            'first_name'   => 'John',
            'last_name'    => 'Doe',
        ]);

        $employee = Employee::create([
            'code'                => 'BMPL-TEST-' . time(),
            'person_code'         => $personCode,
            'desig_code'          => 'MAN',
            'primary_branch_code' => 'BKN',
        ]);

        $user = User::create([
            'username'      => 'testuser_' . time(),
            'password'      => bcrypt('password123'),
            'user_type'     => 'Emp',
            'person_code'   => $personCode,
            'employee_code' => $employee->code,
            'is_active'     => true,
        ]);

        // All proxy accessors
        $this->assertEquals('John Doe', $person->display_name);
        $this->assertEquals('John Doe', $employee->display_name);
        $this->assertEquals('John Doe', $user->display_name);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $user->all_emails);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $user->all_mobiles);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $user->all_addresses);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $user->all_banking);

        $this->assertTrue($user->isEmployee());
    }
}