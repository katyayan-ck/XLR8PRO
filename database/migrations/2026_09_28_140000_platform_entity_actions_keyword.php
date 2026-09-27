<?php

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Services\KeywordValueService;
use App\Services\Utils\KeyvalueService;
use App\Services\Utils\KeywordMasterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-065: ENTITY_ACTIONS — the Chat event vocabulary (comm_thread.action_id resolves against it).
 * Idempotent; written through the keyword services.
 */
return new class extends Migration
{
    private const ACTIONS = [
        'CREATED' => 'Created', 'UPDATED' => 'Updated', 'DELETED' => 'Deleted', 'STATUS_CHANGED' => 'Status changed',
        'REMARKED' => 'Remark', 'ATTACHED' => 'File attached', 'SUBMITTED' => 'Submitted', 'APPROVED' => 'Approved',
        'ACTIVATED' => 'Activated', 'OPENED' => 'Opened', 'COUNTERED' => 'Countered', 'REVISED' => 'Ask revised',
        'ACCEPTED' => 'Accepted', 'WITHDRAWN' => 'Withdrawn', 'AUTO_ACCEPTED' => 'Auto-accepted',
        'APPROVAL_REQUESTED' => 'Approval requested', 'APPROVAL_ACCEPTED' => 'Approval accepted', 'APPROVAL_WITHDRAWN' => 'Approval withdrawn',
        'TASK_CREATED' => 'Task created', 'TICKET_OPENED' => 'Ticket opened', 'SLA_BREACHED' => 'SLA breached', 'AUTO_CLOSED' => 'Auto-closed',
        'EMAIL_SENT' => 'Email sent', 'SMS_SENT' => 'SMS sent', 'WHATSAPP_SENT' => 'WhatsApp sent', 'WHATSAPP_INBOUND' => 'WhatsApp received',
        'CHANNEL_LINKED' => 'Channel linked', 'CALL_DIALLED' => 'Call dialled', 'CALL_RECORDED' => 'Call recorded', 'CALL_DISPOSED' => 'Call disposition',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_utils_keyword_master')) {
            return;
        }
        if (! KeywordMaster::withTrashed()->where('code', 'ENTITY_ACTIONS')->exists()) {
            app(KeywordMasterService::class)->create(['code' => 'ENTITY_ACTIONS', 'keyword' => 'Entity Actions']);
        }
        foreach (self::ACTIONS as $code => $label) {
            if (! Keyvalue::withTrashed()->where('keyword_code', 'ENTITY_ACTIONS')->where('code', $code)->exists()) {
                app(KeyvalueService::class)->create(['keyword_code' => 'ENTITY_ACTIONS', 'code' => $code, 'value' => $label]);
            }
        }
        KeywordValueService::clearCache('ENTITY_ACTIONS');
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_utils_keyvalue')) {
            Keyvalue::where('keyword_code', 'ENTITY_ACTIONS')->whereIn('code', array_keys(self::ACTIONS))->forceDelete();
            if (! Keyvalue::where('keyword_code', 'ENTITY_ACTIONS')->exists()) {
                KeywordMaster::where('code', 'ENTITY_ACTIONS')->forceDelete();
            }
        }
    }
};
