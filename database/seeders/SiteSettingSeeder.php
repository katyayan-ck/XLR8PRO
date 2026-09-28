<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Core\KeywordMaster;
use App\Models\Core\Keyvalue;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Site Identity Group
            'site_identity' => [
                'name'              => 'VDMS - Vehicle Dealership Management System',
                'from_email'        => 'no-reply@bmpl.com',
                'logo'              => 'logo.png',
                'slogan'            => 'Drive Success with VDMS',
                'footer_text'       => '© 2025 BMPL. All rights reserved.',
                'owner_name'        => 'BMPL Group',
                'owner_site_url'    => 'https://bmpl.com',
            ],

            // Site Settings Group
            'site_settings' => [
                'default_theme'     => 'tabler',
                'locale'            => 'en',
                'currency_symbol'   => '₹',
                'date_format'       => 'd-m-Y',
                'time_format'       => 'H:i',
                'show_help_bubble'  => 'true',
                'enable_live_chat'  => 'true',
                'live_chat_timings' => json_encode(['start' => '09:00', 'end' => '18:00']),
            ],

            // Pricing Group
            'pricing' => [
                'gst_rate'              => '18',
                'tds_rate'              => '2',
                'round_to_place'        => '0',
                'financial_year_start'  => '04-01',
                'default_discount_type' => 'percentage',
            ],
        ];

        foreach ($settings as $group => $items) {
            $master = KeywordMaster::firstOrCreate(
                ['keyword' => $group],
                ['details' => ucfirst(str_replace('_', ' ', $group)) . ' Settings', 'status' => 1]
            );

            foreach ($items as $key => $value) {
                Keyvalue::updateOrCreate(
                    [
                        'keyword_master_id' => $master->id,
                        'key'               => $key,
                    ],
                    [
                        'value'     => $value,
                        'details'   => ucfirst(str_replace('_', ' ', $key)),
                        'status'    => 1,
                        'level'     => 0,
                    ]
                );
            }
        }

        $this->command->info('Site settings seeded successfully using Keyword/Keyvalue system!');
    }
}
