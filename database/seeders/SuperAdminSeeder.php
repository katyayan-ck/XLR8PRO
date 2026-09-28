<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Admin\Person;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Person record
        $person = Person::create([
            'person_code'  => 'SUP001',   // or use a real PAN if known
            'entity_type'  => 'individual',
            'first_name'   => 'Super',
            'last_name'    => 'Admin',
            'display_name' => 'Super Admin',
        ]);

        // 2. Create User record
        $user = User::create([
            'username'      => 'SUP001',
            'password'      => Hash::make('admin1234'),  // force change on first login
            'user_type'     => 'Emp',
            'person_code'   => $person->person_code,
            'is_active'     => true,
        ]);

        // 3. Assign super_admin role (Spatie)
        $user->assignRole('super_admin');

        $this->command->info("Super admin created — username: SUP001");
        $this->command->warn("Password : admin1234");
    }
}
