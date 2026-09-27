<?php

use App\Models\Approval\ApprovalTopic;
use App\Services\Platform\Approval\Entities\ApprovalTopicService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-063: starter topic tree from the FRS examples (topics only — authority comes from the power
 * sheet, never from code). Idempotent, written through ApprovalTopicService.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: ?string, 2: string, 3: ?string, 4: ?string}> code, parent, title, item_key, value_type */
    private const TOPICS = [
        ['DISCOUNT', null, 'Discounts', null, 'AMOUNT'],
        ['DISCOUNT.EXTRA', 'DISCOUNT', 'Extra discount', 'extra_disc', 'AMOUNT'],
        ['ACCESSORIES', null, 'Accessories', null, 'AMOUNT'],
        ['ACCESSORIES.PACK', 'ACCESSORIES', 'Accessory pack discount', 'apack_disc', 'AMOUNT'],
        ['INSURANCE', null, 'Insurance', null, 'AMOUNT'],
        ['INSURANCE.WAIVER', 'INSURANCE', 'Insurance loading waiver', 'ins_waiver', 'AMOUNT'],
        ['RTO', null, 'RTO', null, 'FLAG'],
        ['RTO.EXEMPT', 'RTO', 'RTO exemption', 'rto_exempt', 'FLAG'],
        ['DOCS', null, 'Documents', null, 'FLAG'],
        ['DOCS.APPROVAL', 'DOCS', 'Document approval', 'docs_approval', 'FLAG'],
        ['COMMS', null, 'Communications', null, 'FLAG'],
        ['COMMS.TEMPLATE', 'COMMS', 'Message template approval', 'comms_template', 'FLAG'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_approval_topic')) {
            return;
        }
        foreach (self::TOPICS as [$code, $parent, $title, $itemKey, $valueType]) {
            if (ApprovalTopic::withTrashed()->where('code', $code)->exists()) {
                continue;
            }
            app(ApprovalTopicService::class)->create([
                'code' => $code,
                'parent_id' => $parent ? ApprovalTopic::query()->where('code', $parent)->value('id') : null,
                'title' => $title,
                'item_key' => $itemKey,
                'value_type' => $valueType,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_approval_topic')) {
            ApprovalTopic::withTrashed()->whereIn('code', array_column(self::TOPICS, 0))->whereDoesntHave('rules')->forceDelete();
        }
    }
};
