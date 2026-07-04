<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Models\Utilities\KeyValue\Keyvalue;
use Illuminate\Support\Facades\Cache;

class CrmStatusSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedKeyword('LEAD_STATUS', [
            ['code' => 'NEW', 'value' => 'New Lead'],
            ['code' => 'IN_VERIFICATION', 'value' => 'In Verification'],
            ['code' => 'VERIFIED', 'value' => 'Verified'],
            ['code' => 'CONVERTED', 'value' => 'Converted to Enquiry'],
            ['code' => 'REJECTED', 'value' => 'Rejected'],
            ['code' => 'LOST', 'value' => 'Lost'],
            ['code' => 'ON_HOLD', 'value' => 'On Hold'],
        ]);

        $this->seedKeyword('ENQUIRY_STATUS', [
            ['code' => 'NEW', 'value' => 'New Enquiry'],
            ['code' => 'IN_FOLLOWUP', 'value' => 'In Follow-up'],
            ['code' => 'QUOTATION_SENT', 'value' => 'Quotation Sent'],
            ['code' => 'QUOTATION_APPROVED', 'value' => 'Quotation Approved'],
            ['code' => 'BOOKING_DONE', 'value' => 'Booking Done'],
            ['code' => 'OTF_GENERATED', 'value' => 'OTF Generated'],
            ['code' => 'VEHICLE_ALLOTTED', 'value' => 'Vehicle Allotted'],
            ['code' => 'INVOICED', 'value' => 'Invoiced'],
            ['code' => 'DELIVERED', 'value' => 'Delivered'],
            ['code' => 'LOST', 'value' => 'Lost'],
            ['code' => 'CANCELLED', 'value' => 'Cancelled'],
        ]);

        $this->seedKeyword('QUOTE_STATUS', [
            ['code' => 'RAISED', 'value' => 'Raised'],
            ['code' => 'PENDING_APPROVAL', 'value' => 'Pending Approval'],
            ['code' => 'APPROVED', 'value' => 'Approved'],
            ['code' => 'REJECTED', 'value' => 'Rejected'],
            ['code' => 'MODIFIED', 'value' => 'Modified'],
            ['code' => 'ESCALATED', 'value' => 'Escalated'],
            ['code' => 'CLOSED', 'value' => 'Closed'],
            ['code' => 'REVIVED', 'value' => 'Revived'],
        ]);

        $this->seedKeyword('QUOTE_ACTION', [
            ['code' => 'REQUEST', 'value' => 'Request Quote'],
            ['code' => 'PROPOSE', 'value' => 'Propose Changes'],
            ['code' => 'EDIT', 'value' => 'Edit Quote'],
            ['code' => 'COMMENT', 'value' => 'Add Comment'],
            ['code' => 'INQUIRE', 'value' => 'Raise Query'],
            ['code' => 'ANSWER', 'value' => 'Answer Query'],
            ['code' => 'ESCALATE', 'value' => 'Escalate'],
            ['code' => 'APPROVE', 'value' => 'Approve'],
            ['code' => 'REJECT', 'value' => 'Reject'],
            ['code' => 'REOPEN', 'value' => 'Reopen'],
            ['code' => 'CANCEL', 'value' => 'Cancel'],
        ]);

        Cache::flush();
        $this->command->info('CRM Statuses seeded via KeyValue system.');
    }

    private function seedKeyword(string $keyword, array $statuses): void
    {
        $km = KeywordMaster::firstOrCreate(
            ['code' => $keyword],
            [
                'keyword' => $keyword,
                'description' => ucfirst(str_replace('_', ' ', strtolower($keyword))) . ' statuses for CRM pipeline',
                'is_active' => true,
                'status' => 1,
            ]
        );

        foreach ($statuses as $status) {
            Keyvalue::firstOrCreate(
                [
                    'keyword_code' => $keyword,
                    'code' => $status['code'],
                ],
                [
                    'key' => $status['code'],
                    'value' => $status['value'],
                    'is_active' => true,
                    'status' => 1,
                ]
            );
        }
    }
}