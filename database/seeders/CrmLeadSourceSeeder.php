<?php

namespace Database\Seeders;

use App\Models\Crm\LeadSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmLeadSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'code'        => 'WALK_IN',
                'name'        => 'Walk-in',
                'description' => 'Customer walked into the showroom directly',
                'sort_order'  => 1,
            ],
            [
                'code'        => 'PHONE_CALL',
                'name'        => 'Phone Call / Inbound',
                'description' => 'Customer called the showroom or central number',
                'sort_order'  => 2,
            ],
            [
                'code'        => 'WEBSITE',
                'name'        => 'Website / Online Enquiry',
                'description' => 'Enquiry received through company website or dealer portal',
                'sort_order'  => 3,
            ],
            [
                'code'        => 'SOCIAL_MEDIA',
                'name'        => 'Social Media (FB/Instagram)',
                'description' => 'Lead generated from Facebook, Instagram or other social platforms',
                'sort_order'  => 4,
            ],
            [
                'code'        => 'GOOGLE_ADS',
                'name'        => 'Google / Paid Search',
                'description' => 'Lead came through Google Ads or paid search campaigns',
                'sort_order'  => 5,
            ],
            [
                'code'        => 'WHATSAPP',
                'name'        => 'WhatsApp / Chatbot',
                'description' => 'Lead received via WhatsApp or automated chatbot',
                'sort_order'  => 6,
            ],
            [
                'code'        => 'REFERRAL',
                'name'        => 'Referral (Customer / Employee)',
                'description' => 'Referred by existing customer or dealership employee',
                'sort_order'  => 7,
            ],
            [
                'code'        => 'DSA',
                'name'        => 'DSA / Channel Partner',
                'description' => 'Lead sourced through DSA or external channel partner',
                'sort_order'  => 8,
            ],
            [
                'code'        => 'CORPORATE',
                'name'        => 'Corporate / Fleet Enquiry',
                'description' => 'Bulk or corporate/fleet requirement',
                'sort_order'  => 9,
            ],
            [
                'code'        => 'EXCHANGE',
                'name'        => 'Exchange / Upgrade',
                'description' => 'Customer wants to exchange their existing vehicle',
                'sort_order'  => 10,
            ],
            [
                'code'        => 'TEST_DRIVE',
                'name'        => 'Test Drive Follow-up',
                'description' => 'Follow-up from previous test drive activity',
                'sort_order'  => 11,
            ],
            [
                'code'        => 'EVENT',
                'name'        => 'Event / Exhibition / Road Show',
                'description' => 'Lead captured during marketing events or exhibitions',
                'sort_order'  => 12,
            ],
            [
                'code'        => 'DEALER_REFERRAL',
                'name'        => 'Other Dealer / Sub-dealer Referral',
                'description' => 'Referred by another Mahindra dealer or sub-dealer',
                'sort_order'  => 13,
            ],
            [
                'code'        => 'REPEAT_CUSTOMER',
                'name'        => 'Repeat / Existing Customer',
                'description' => 'Existing customer coming for another purchase',
                'sort_order'  => 14,
            ],
            [
                'code'        => 'OTHER',
                'name'        => 'Other / Miscellaneous',
                'description' => 'Any other source not listed above',
                'sort_order'  => 99,
            ],
        ];

        DB::transaction(function () use ($sources) {
            foreach ($sources as $source) {
                LeadSource::updateOrCreate(
                    ['code' => $source['code']],
                    [
                        'name'        => $source['name'],
                        'description' => $source['description'],
                        'is_active'   => true,
                        'sort_order'  => $source['sort_order'],
                        'created_by'  => 1, // Change to your system/admin user id if needed
                        'updated_by'  => 1,
                    ]
                );
            }
        });

        $this->command->info('Lead Sources seeded successfully!');
    }
}