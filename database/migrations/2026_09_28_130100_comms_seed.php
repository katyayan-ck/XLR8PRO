<?php

use App\Models\Comms\CommTemplate;
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Services\KeywordValueService;
use App\Services\Platform\Templates\TemplateService;
use App\Services\Utils\KeyvalueService;
use App\Services\Utils\KeywordMasterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-064: system templates (Notify fallback copy, OTP, STOP acknowledgement) and the
 * CALL_DISPOSITION keyword. Idempotent; written through TemplateService / keyword services.
 */
return new class extends Migration
{
    private const DISPOSITIONS = [
        'CONNECTED' => 'Connected', 'NO_ANSWER' => 'No answer', 'BUSY' => 'Busy', 'WRONG_NUMBER' => 'Wrong number',
        'VOICEMAIL' => 'Voicemail', 'CALLBACK_REQUESTED' => 'Callback requested',
    ];

    public function up(): void
    {
        if (Schema::hasTable('xlr8_comm_template')) {
            $generic = [
                ['name' => 'title', 'required' => true, 'sample' => 'Quote #8821 assigned'],
                ['name' => 'body', 'required' => false, 'sample' => 'Please review today.'],
                ['name' => 'link', 'required' => false, 'sample' => 'https://xceler8.in'],
            ];
            $templates = app(TemplateService::class);
            $templates->seedSystem('notify.generic', [
                'channel' => 'EMAIL', 'category' => 'OPERATIONAL', 'name' => 'Notification (generic email)',
                'subject' => '{{title}}',
                'body_html' => '<p>{{title}}</p><p>{{body}}</p><p><a href="{{link}}">Open in Xceler8</a></p><p>— {{brand}}</p>',
                'body_text' => "{{title}}\n\n{{body}}\n\n{{link}}", 'variables' => $generic, 'sample_vars' => collect($generic)->pluck('sample', 'name')->all(),
            ]);
            $templates->seedSystem('notify.generic', [
                'channel' => 'SMS', 'category' => 'OPERATIONAL', 'name' => 'Notification (generic SMS)',
                'body_text' => '{{title}} {{body}} - {{brand}}', 'variables' => $generic, 'sample_vars' => collect($generic)->pluck('sample', 'name')->all(),
            ]);
            $templates->seedSystem('notify.generic', [
                'channel' => 'WHATSAPP', 'category' => 'OPERATIONAL', 'name' => 'Notification (generic WhatsApp)',
                'body_text' => "*{{title}}*\n{{body}}", 'variables' => $generic, 'sample_vars' => collect($generic)->pluck('sample', 'name')->all(),
            ]);
            $templates->seedSystem('otp.sms', [
                'channel' => 'SMS', 'category' => 'OTP', 'name' => 'One-time password',
                'body_text' => '{{code}} is your {{brand}} code for {{purpose}}. It is valid for {{minutes}} minutes. Do not share it.',
                'variables' => [['name' => 'code', 'required' => true, 'pii' => true, 'sample' => '123456'], ['name' => 'minutes', 'required' => true, 'sample' => '5'], ['name' => 'purpose', 'required' => false, 'sample' => 'login']],
                'sample_vars' => ['code' => '123456', 'minutes' => '5', 'purpose' => 'login'],
            ]);
            $templates->seedSystem('sms.stop.ack', [
                'channel' => 'SMS', 'category' => 'TRANSACTIONAL', 'name' => 'STOP acknowledgement',
                'body_text' => 'You will no longer receive SMS from {{brand}}.', 'variables' => [], 'sample_vars' => [],
            ]);
        }

        if (Schema::hasTable('xlr8_utils_keyword_master')) {
            if (! KeywordMaster::withTrashed()->where('code', 'CALL_DISPOSITION')->exists()) {
                app(KeywordMasterService::class)->create(['code' => 'CALL_DISPOSITION', 'keyword' => 'Call Disposition']);
            }
            foreach (self::DISPOSITIONS as $code => $label) {
                if (! Keyvalue::withTrashed()->where('keyword_code', 'CALL_DISPOSITION')->where('code', $code)->exists()) {
                    app(KeyvalueService::class)->create(['keyword_code' => 'CALL_DISPOSITION', 'code' => $code, 'value' => $label]);
                }
            }
            KeywordValueService::clearCache('CALL_DISPOSITION');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_comm_template')) {
            CommTemplate::withTrashed()->where('is_system', true)->whereIn('code', ['notify.generic', 'otp.sms', 'sms.stop.ack'])->get()
                ->each(function (CommTemplate $t) {
                    $t->versions()->forceDelete();
                    $t->forceDelete();
                });
        }
        if (Schema::hasTable('xlr8_utils_keyvalue')) {
            Keyvalue::where('keyword_code', 'CALL_DISPOSITION')->forceDelete();
            KeywordMaster::where('code', 'CALL_DISPOSITION')->forceDelete();
        }
    }
};
