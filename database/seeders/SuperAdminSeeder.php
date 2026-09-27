<?php

namespace Database\Seeders;

use App\Services\IAM\UserService;
use App\Services\Person\PersonRecordService;
use Illuminate\Database\Seeder;

/**
 * Bootstrap super admin for a fresh install. Idempotent: the person and the account are found
 * or created through their entity services (DEC-050); an existing account is never changed.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $person = app(PersonRecordService::class)->firstOrCreate(
            ['person_code' => 'SUP001'],
            ['entity_type' => 'individual', 'display_name' => 'Super Admin'],
        );

        $user = app(UserService::class)->firstOrCreate(
            ['username' => 'sup001'],
            [
                'password' => 'admin1234', // change on first login
                'user_type' => 'Emp',
                'person_code' => $person->person_code,
                'is_active' => true,
            ],
        );

        // The SuperAdmin role is the `superadmin` designation (roles = designations).
        $user->assignRole('superadmin');

        $this->command->info('Super admin ready — username: sup001');
        $this->command->warn('Password : admin1234 (only set when the account is first created)');
    }
}
