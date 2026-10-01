<?php

namespace Tests\Feature\Platform;

use App\Models\Approval\ApprovalEvent;
use App\Models\Approval\ApprovalRequest;
use App\Models\Comms\CommCall;
use App\Models\Comms\CommOutbox;
use App\Models\Comms\CommSuppression;
use App\Models\Comms\CommTemplateVersion;
use App\Models\Comms\WaThread;
use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Models\Utilities\Docs\DocGroup;
use App\Models\Utilities\Noty\Alert;
use App\Models\Utilities\Task\Task;
use App\Models\Utilities\Ticket\Ticket;
use App\Services\Platform\Approval\ApprovalReportService;
use App\Services\Platform\Comms\ContactService;
use App\Services\Platform\Comms\OutboxService;
use App\Services\Platform\Docs\DocsService;
use App\Services\Platform\Settings\SettingsService;
use App\Services\Platform\Ticket\TicketService;
use App\Support\Facades\Approval;
use App\Support\Facades\Chat;
use App\Support\Facades\Docs;
use App\Support\Facades\Email;
use App\Support\Facades\Notify;
use App\Support\Facades\Sms;
use App\Support\Facades\Task as TaskFacade;
use App\Support\Facades\Telephony;
use App\Support\Facades\Templates;
use App\Support\Facades\WhatsApp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Platform\Concerns\PlatformFixtures;
use Tests\TestCase;

/**
 * FRS v1.1 §11 acceptance pack (minimum), one test per item.
 */
class PlatformAcceptanceTest extends TestCase
{
    use DatabaseTransactions, PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $settings = app(SettingsService::class);
        $settings->set('mail.driver', 'log');
        $settings->set('mail.identities', ['default' => 'Xceler8 <noreply@xceler8.in>', 'quotes' => 'BMPL Quotes <quotes@bmpl.in>']);
        $settings->set('approval.auto_accept_own_power', false);
        $settings->set('comms.webhook_secret', 'acceptance-secret');
    }

    private function booking(): Booking
    {
        return Booking::query()->first() ?? $this->markTestSkipped('Needs a booking in the test database.');
    }

    private function webhook(string $channel, array $body)
    {
        $json = json_encode($body);

        return $this->call('POST', "/api/webhooks/comms/{$channel}", [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $json, 'acceptance-secret'),
        ], $json);
    }

    public function test_01_a_record_uses_approval_docs_chat_and_task_facades_without_module_tables(): void
    {
        $staff = $this->staffWithDesignations(2);
        $this->approvalRule('extra_disc', [[$staff[1]->designation_code, 10000]]);
        $booking = $this->booking();
        $tablesBefore = count(Schema::getTableListing());
        $this->actingAs(User::find($staff[0]->id), 'backpack');

        $approval = Approval::open($booking, 'DISCOUNT.EXTRA', 'extra_disc', ['asked' => 5000]);
        $file = Docs::attach($booking, UploadedFile::fake()->create('pan.pdf', 10, 'application/pdf'), 'docs', ['title' => 'PAN']);
        $remark = Chat::remark($booking, 'Customer asked for more discount');
        $task = TaskFacade::create(['title' => 'Collect PAN', 'type' => 'ASSIGNED_TASK', 'assignees' => [$staff[1]->id], 'ref_type' => 'BOOKING', 'ref_id' => $booking->id]);

        $this->assertTrue($approval->ok && $file->ok && $remark->ok && $task->ok);
        $this->assertSame($tablesBefore, count(Schema::getTableListing()));
        $actions = collect(Chat::events($booking))->pluck('action');
        $this->assertTrue($actions->contains('APPROVAL_REQUESTED') && $actions->contains('ATTACHED') && $actions->contains('TASK_CREATED'));
    }

    public function test_02_bell_counts_match_the_inbox_after_writes(): void
    {
        $user = $this->staffWithDesignations(1)->first()->id;
        Notify::to($user)->title('One')->send();
        Notify::to($user)->kind('A')->title('Two')->data(['severity' => 'warning'])->send();

        $counts = Notify::counts($user);

        $this->assertSame(Notify::list($user, 'N', 'UNREAD')->total(), $counts['notifications']['unread']);
        $this->assertSame(Notify::list($user, 'A', 'UNREAD')->total(), $counts['alerts']['unread']);
    }

    public function test_03_task_snooper_sees_the_task_and_chat_but_cannot_remark(): void
    {
        $staff = $this->staffWithDesignations(3)->pluck('id')->all();
        $id = TaskFacade::create(['title' => 'Audit', 'type' => 'ASSIGNED_TASK', 'owner_id' => $staff[0], 'assignees' => [$staff[1]], 'snoopers' => [$staff[2]]], $staff[0])->get('id');
        $task = Task::query()->findOrFail($id);

        $this->assertTrue(TaskFacade::get($id, $staff[2])->ok);
        $this->assertTrue($task->chatCanView($staff[2]));
        $this->assertSame('FORBIDDEN', Chat::remark($task, 'hello', null, null, false, $staff[2])->code);
    }

    public function test_04_highest_level_counter_changes_the_grant_without_an_assigned_to_baton(): void
    {
        $staff = $this->staffWithDesignations(3);
        $this->approvalRule('extra_disc', [[$staff[0]->designation_code, 1000], [$staff[1]->designation_code, 5000], [$staff[2]->designation_code, 9000]]);
        $id = Approval::open(null, 'DISCOUNT.EXTRA', 'extra_disc', ['asked' => 6000], $staff[0]->id)->get('id');

        Approval::counter($id, $staff[1]->id, 5000);
        Approval::counter($id, $staff[2]->id, 3000);

        $this->assertSame(3, Approval::effective($id)['level']);
        $this->assertFalse(Schema::hasColumn('xlr8_approval_request', 'assigned_to'));
    }

    public function test_05_settings_flag_switches_blade_output_without_a_deploy(): void
    {
        $template = "@feature('quote.csd_enabled') CSD-ON @else CSD-OFF @endfeature";
        app(SettingsService::class)->set('quote.csd_enabled', false);
        $this->assertStringContainsString('CSD-OFF', Blade::render($template));

        app(SettingsService::class)->set('quote.csd_enabled', true);

        $this->assertStringContainsString('CSD-ON', Blade::render($template));
    }

    public function test_06_cart_zip_contains_only_files_the_user_may_see(): void
    {
        $staff = $this->staffWithDesignations(2)->pluck('id')->all();
        $docs = app(DocsService::class);
        $mine = $docs->attach(null, UploadedFile::fake()->create('mine.pdf', 5, 'application/pdf'), 'docs', ['title' => 'mine'], $staff[0])->get('id');
        $hidden = $docs->attach(null, UploadedFile::fake()->create('hidden.pdf', 5, 'application/pdf'), 'docs', ['title' => 'hidden'], $staff[1])->get('id');
        $docs->entitle($hidden, ['users' => [$staff[1]]]);
        $docs->cartAdd($staff[0], $mine);
        $this->assertFalse($docs->cartAdd($staff[0], $hidden)->ok);
        $group = $docs->cartSaveAs($staff[0], 'Pack')->get('group_id');
        DocGroup::find($group)->documents()->syncWithoutDetaching([$hidden]);

        $zip = $docs->zip($staff[0], $group);
        $archive = new \ZipArchive;
        $archive->open($zip->get('path'));

        $this->assertSame(1, $archive->numFiles);
        $this->assertStringStartsWith($mine.'-', $archive->getNameIndex(0));
        $archive->close();
        @unlink($zip->get('path'));
    }

    public function test_07_p1_breach_sends_an_alert_using_settings_hours(): void
    {
        app(SettingsService::class)->set('sla.ticket.p1_hours', 2);
        $requester = $this->staffWithDesignations(1)->first()->id;
        $admin = $this->superAdmin()->id;
        $id = app(TicketService::class)->open(['title' => 'Access down', 'category' => 'ACCESS', 'priority' => 'P1'], $requester)->get('id');
        app(TicketService::class)->transition($id, $admin, 'ACKNOWLEDGED');
        $ticket = Ticket::query()->findOrFail($id);
        $this->assertTrue($ticket->due_at->equalTo($ticket->created_at->copy()->addHours(2)));

        $this->travel(3)->hours();
        app(TicketService::class)->flagBreaches();

        $this->assertTrue(Alert::query()->where('reference_type', 'TICKET')->where('reference_id', $id)->where('title', 'like', 'SLA breached%')->exists());
    }

    public function test_08_topic_report_matches_accepted_request_events_for_the_fy(): void
    {
        $staff = $this->staffWithDesignations(2);
        $this->approvalRule('extra_disc', [[$staff[0]->designation_code, 100], [$staff[1]->designation_code, 9000]]);
        foreach ([2000, 3000] as $ask) {
            $id = Approval::open(null, 'DISCOUNT.EXTRA', 'extra_disc', ['asked' => $ask], $staff[0]->id)->get('id');
            Approval::counter($id, $staff[1]->id, $ask);
            Approval::close($id, $staff[0]->id, 'ACCEPTED');
        }
        $fy = ApprovalRequest::query()->latest('id')->value('fy');

        $report = app(ApprovalReportService::class)->summary(['fy' => $fy, 'topic' => 'DISCOUNT.EXTRA']);
        $events = ApprovalEvent::query()->from('xlr8_approval_event as e')->toBase()->join('xlr8_approval_request as r', 'r.id', '=', 'e.request_id')
            ->where('e.type', 'ACCEPTED')->where('r.fy', $fy)->where('r.topic_code', 'DISCOUNT.EXTRA');

        $this->assertSame($events->count(), $report['totals']['accepted']);
        $this->assertEquals((float) $events->sum('e.value'), $report['totals']['granted']);
    }

    public function test_09_notify_email_options_make_one_outbox_row_that_resends_after_a_driver_swap(): void
    {
        $user = $this->staffWithDesignations(1)->first()->id;
        $pdf = Docs::attach(null, UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf'), 'docs', ['title' => 'Quote'])->get('id');
        $before = CommOutbox::query()->count();

        Notify::to($user)->title('Quote sent')->notifySelf()->channels(['INAPP' => true, 'EMAIL' => [
            'template' => 'notify.generic', 'from' => 'quotes', 'to' => ['ravi@example.com'], 'cc' => ['sm@example.com'], 'bcc' => ['audit@example.com'],
            'attach' => [$pdf], 'vars' => ['title' => 'Your quote', 'body' => 'Attached'],
        ]])->send();
        $row = CommOutbox::query()->latest('id')->first();
        $this->assertSame($before + 1, CommOutbox::query()->count());

        app(SettingsService::class)->set('mail.driver', 'laravel');
        Mail::fake();
        $copy = CommOutbox::query()->find(app(OutboxService::class)->resend($row->id)->get('outbox_id'));

        $this->assertSame(['laravel', $row->payload], [$copy->driver, $copy->payload]);
    }

    public function test_10_otp_uses_the_same_call_whatever_the_sms_driver(): void
    {
        $this->markTestSkipped('Only the sandbox SMS driver exists until MSG91 credentials are provided (DEC-064); Sms::otp does not depend on the driver name.');
    }

    public function test_11_whatsapp_template_works_outside_the_window_and_free_form_is_refused(): void
    {
        $wa = '+9198'.random_int(10000000, 99999999);

        $this->assertSame('SESSION_CLOSED', WhatsApp::send(['to' => $wa, 'type' => 'TEXT', 'text' => 'hi'])->code);
        $this->assertTrue(WhatsApp::send(['to' => $wa, 'template' => 'notify.generic', 'vars' => ['title' => 'Quote ready']])->ok);
    }

    public function test_12_inbound_whatsapp_image_becomes_a_docs_row_in_the_thread(): void
    {
        $wa = '+9198'.random_int(10000000, 99999999);
        $image = UploadedFile::fake()->image('dent.jpg', 10, 10);

        $response = $this->webhook('whatsapp', ['event_id' => 'acc-12', 'type' => 'message', 'message' => [
            'id' => 'wamid.acc12', 'from' => $wa, 'type' => 'IMAGE', 'media_base64' => base64_encode((string) file_get_contents($image->getRealPath())), 'media_name' => 'dent.jpg', 'media_mime' => 'image/jpeg',
        ]]);

        $docId = $response->assertOk()->json('data.doc_id');
        $this->assertNotNull($docId);
        $this->assertTrue(collect(WhatsApp::thread($wa)->items())->contains('doc_id', $docId));
    }

    public function test_13_click_to_call_creates_call_then_recording_doc_then_chat_event(): void
    {
        [$agent, $customer] = $this->peopleWithMobiles(2)->all();
        $booking = $this->booking();

        $call = CommCall::query()->find(Telephony::dial($agent->id, $customer->person_code, ['type' => 'BOOKING', 'id' => $booking->id])->get('call_id'));
        $this->assertSame('RINGING', $call->status);
        $this->webhook('telephony', ['event_id' => 'acc-13', 'vendor_call_id' => $call->vendor_call_id, 'status' => 'COMPLETED', 'recording_ready' => true])->assertOk();

        $this->assertNotNull($call->fresh()->recording_doc_id);
        $this->assertTrue(collect(Chat::events($booking))->contains('action', 'CALL_RECORDED'));
    }

    public function test_14_only_active_templates_send_and_activating_v2_keeps_v1_snapshots(): void
    {
        $admin = $this->superAdmin()->id;
        $code = 'acceptance.fourteen';
        $v1 = Templates::saveDraft($code, ['channel' => 'EMAIL', 'category' => 'OPERATIONAL', 'name' => 'A14', 'subject' => 'First {{x}}', 'body_text' => 'b', 'variables' => [['name' => 'x']]]);
        $this->assertSame('TEMPLATE_NOT_ACTIVE', Email::send(['to' => ['a@example.com'], 'template' => $code, 'vars' => ['x' => '1']])->code);
        Templates::approveDirect($v1->get('version_id'), $admin);
        Templates::activate($v1->get('version_id'), $admin);
        $sent = CommOutbox::query()->find(Email::send(['to' => ['a@example.com'], 'template' => $code, 'vars' => ['x' => '1']])->get('outbox_id'));

        $v2 = Templates::saveDraft($code, ['channel' => 'EMAIL', 'category' => 'OPERATIONAL', 'name' => 'A14', 'subject' => 'Second {{x}}']);
        Templates::approveDirect($v2->get('version_id'), $admin);
        Templates::activate($v2->get('version_id'), $admin);

        $this->assertSame('RETIRED', CommTemplateVersion::find($v1->get('version_id'))->status);
        $this->assertSame([1, 'First 1'], [$sent->fresh()->template_version, $sent->fresh()->subject]);
    }

    public function test_15_stop_on_sms_and_whatsapp_flips_consent_and_is_honoured(): void
    {
        $customer = $this->peopleWithMobiles(1)->first();
        $mobile = '+91'.substr(preg_replace('/\D/', '', $customer->mobile), -10);
        CommSuppression::query()->where('address', $mobile)->delete();

        $this->webhook('sms', ['event_id' => 'acc-15a', 'type' => 'inbound', 'from' => $mobile, 'text' => 'STOP'])->assertOk();
        $this->webhook('whatsapp', ['event_id' => 'acc-15b', 'type' => 'message', 'message' => ['id' => 'wamid.acc15', 'from' => $mobile, 'text' => 'STOP']])->assertOk();

        $contacts = app(ContactService::class);
        $this->assertTrue($contacts->hasOptedOut($customer->person_code, 'SMS'));
        $this->assertSame('SUPPRESSED', Sms::send(['to' => $customer->person_code, 'template' => 'notify.generic', 'vars' => ['title' => 'x']])->get('status'));
        $this->assertSame('CONSENT_DENIED', WhatsApp::send(['to' => $mobile, 'template' => 'notify.generic', 'vars' => ['title' => 'x']])->code);
        $this->assertNotNull(WaThread::query()->where('wa_id', ltrim($mobile, '+'))->first());
    }
}
