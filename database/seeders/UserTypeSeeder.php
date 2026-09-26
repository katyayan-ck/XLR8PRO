<?php

namespace Database\Seeders;

use App\Models\Admin\UserType;
use Illuminate\Database\Seeder;

/**
 * xlr8_iam_user_type only had one row ('emp' / Employee) before this — backfills
 * the other two user types this rollout's Person→User flow needs.
 */
class UserTypeSeeder extends Seeder
{
    private const TYPES = [
        ['code' => 'DSA', 'display_name' => 'Direct Selling Agent'],
        ['code' => 'CUST', 'display_name' => 'Customer'],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            UserType::firstOrCreate(
                ['code' => $type['code']],
                ['display_name' => $type['display_name'], 'is_active' => true]
            );
        }

        $this->command?->info('UserTypeSeeder: ensured '.count(self::TYPES).' user types exist.');
    }
}
