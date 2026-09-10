<?php
namespace Tests\Unit;

use App\Imports\Sheets\StandaloneUsersImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;


class StandaloneUsersImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_import_creates_person_employee_user_address_banking()
    {
        Excel::import(new StandaloneUsersImport, 'storage/user_data.xlsx');

        $this->assertDatabaseHas('xlr8_admin_person', ['person_code' => 'RZSFJ7726R']);
        $this->assertDatabaseHas('xlr8_admin_employee', ['code' => 'BMPL-0018']);
        $this->assertDatabaseHas('users', ['username' => 'bmpl-0018']);
        $this->assertDatabaseHas('xlr8_admin_person_addresses', ['person_code' => 'RZSFJ7726R']);
        $this->assertDatabaseHas('xlr8_admin_person_banking_details', ['person_code' => 'RZSFJ7726R']);
    }
}
