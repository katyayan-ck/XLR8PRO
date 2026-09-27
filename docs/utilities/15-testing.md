# 15. Testing code that uses the utilities

How to write feature tests for module code that calls Settings, Notify, Chat, Docs, Task, Ticket, Approval,
Templates or the channels. Project rules first: `.ai/rules/testing.md` — tests run on **`xlrm_testing`**, never `xlrm`,
and use `DatabaseTransactions` over the real test copy (no `RefreshDatabase`). Working examples:
`tests/Feature/Platform/*` (the FRS acceptance pack plus one test class per service).

## What the test environment gives you (phpunit.xml)
| Setting | Effect on the utilities |
|---|---|
| `QUEUE_CONNECTION=sync` | outbox delivery (`SendOutboxMessage`) and push (`SendPushNotification`) run inline, so after a send the outbox row already has its final status and the sandbox row exists |
| `MAIL_MAILER=array` | even the `laravel` mail driver never leaves the process |
| `CACHE_STORE=array` | the Settings cache is per test; `Settings::set()` in `setUp()` is enough |
| SMS / WhatsApp / telephony drivers default to `sandbox` | messages land in `xlr8_comm_sandbox`; calls are simulated |

Put channel settings in `setUp()` so a developer's local settings can't change the result:
```php
protected function setUp(): void
{
    parent::setUp();
    Settings::set('mail.driver', 'log');                                   // email → sandbox too
    Settings::set('mail.identities', ['default' => 'Xceler8 <noreply@xceler8.in>']);
    Settings::set('approval.auto_accept_own_power', false);                // make approval paths explicit
    Settings::set('comms.webhook_secret', 'test-secret');                  // only if you post webhooks
}
```
Settings writes are rolled back with the transaction.

## Real people from the test copy
Use the fixtures trait instead of creating users (the suite's convention). It skips cleanly when the data is missing:
```php
use Tests\Feature\Platform\Concerns\PlatformFixtures;

class JobCardServiceTest extends TestCase
{
    use DatabaseTransactions, PlatformFixtures;

    public function test_opening_a_job_card_logs_history_and_creates_the_advisor_task(): void
    {
        [$advisor, $manager] = $this->staffWithDesignations(2)->all();   // active staff, distinct valid designations
        // $this->peopleWithMobiles(1)  → users with a valid primary mobile (SMS / WhatsApp / calls)
        // $this->superAdmin()          → the superadmin user
        // $this->approvalRule('labour_disc', [['SVC_ADVISOR', 1000], ['SVC_MGR', 5000]], ['branch' => 'JPR'])
        …
    }
}
```

## Assert through the utilities' read APIs, not their tables
| To check… | Assert with |
|---|---|
| a history event was written | `collect(Chat::events($model))->contains('action', 'STATUS_CHANGED')` |
| a remark exists / is hidden | `Chat::timeline($model, $viewerId)['remarks']` |
| someone was notified | `Notify::counts($userId)['notifications']['unread']` before / after, or `Notify::list($userId, 'N')->items()` |
| an alert was raised | `Notify::list($userId, 'A')` |
| a task was created for the record | `Task::inbox($assigneeId, 'ASSIGNED')->items()` then `->ref_type` / `->ref_id` |
| who may do what on a task / ticket | `Task::get($id, $userId)->get('user_can')`, `Ticket::get($id, $userId)->get('user_can')` |
| a file is attached and visible | `Docs::listFor($model, 'kyc', $viewerId)`, `Docs::canView($docId, $userId)` |
| an approval grant | `Approval::effective($requestId)['value']`, request `status` via `ApprovalRequest::find($id)->status` |
| a message was queued / sent | `CommOutbox::query()->latest('id')->first()` → `channel`, `status`, `template_code`, `to_address` |
| the sandbox "received" it | `$this->assertDatabaseHas('xlr8_comm_sandbox', ['channel' => 'SMS', 'outbox_id' => $outboxId])` |
| a Result failure | `$this->assertSame('ASSIGNEE_REQUIRED', $result->code)` — always assert the **code**, never the message |

## Common patterns

**Business failure codes** — every write returns `Result`; test the refusal paths you handle:
```php
$r = Task::create(['title' => 'x', 'type' => 'ASSIGNED_TASK'], $ownerId);
$this->assertFalse($r->ok);
$this->assertSame('ASSIGNEE_REQUIRED', $r->code);
```

**Idempotency** — the second call with the same key must not add a row:
```php
$before = CommOutbox::query()->count();
Email::send($options + ['idempotency_key' => 'jobcard.1.estimate.email']);
$dup = Email::send($options + ['idempotency_key' => 'jobcard.1.estimate.email']);
$this->assertTrue($dup->get('duplicate'));
$this->assertSame($before + 1, CommOutbox::query()->count());
```
Notify behaves the same: a repeated key returns `ok` with `duplicate: true` and `sent: 0`.

**Time: SLA, edit windows, OTP expiry, auto-close**
```php
$this->travel(5)->hours();                         // past a P1 SLA of 4 h
app(TicketService::class)->flagBreaches();         // run the job's work directly
```
Prefer calling the service method the scheduled job calls (`flagBreaches()`, `autoClose()`,
`flagMissingRecordings()`, `purge()`) over dispatching the job.

**Domain events** — fake only the event you assert, so Chat / Notify listeners keep working:
```php
Event::fake([\App\Events\Platform\TaskChanged::class]);
Task::create([...]);
Event::assertDispatched(\App\Events\Platform\TaskChanged::class, fn ($e) => $e->change === 'CREATED');
```
Never `Event::fake()` with no arguments in these tests: it silences `ChatEntryAdded` / `NotificationSent` too.

**Push notifications** — the push job runs inline and calls Firebase only for users with device tokens. To assert
push without touching FCM:
```php
Bus::fake([\App\Jobs\Platform\SendPushNotification::class]);
Notify::to($userId)->title('Hi')->send();
Bus::assertDispatched(\App\Jobs\Platform\SendPushNotification::class);
```
Quiet hours (`notify.quiet_hours`) skip push entirely, so clear the setting in tests that assert push.

**Uploads**
```php
$file = UploadedFile::fake()->create('estimate.pdf', 20, 'application/pdf');   // KB, mime
$doc = Docs::attach($jobCard, $file, 'docs', ['title' => 'Estimate']);
$this->assertTrue($doc->ok);
```
The size / type limits come from `docs.max_upload_kb` and `docs.allowed_mimes`; set them in the test when you test
a refusal (`INVALID_FILE`).

**Webhooks** (delivery reports, inbound WhatsApp / SMS, call events) are HMAC-signed:
```php
$json = json_encode($body);
$this->call('POST', '/api/webhooks/comms/whatsapp', [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
    'HTTP_X_SIGNATURE' => hash_hmac('sha256', $json, 'test-secret'),
], $json)->assertOk();
```
Use a unique `event_id` per test: repeats are ignored as duplicates.

**Templates** — sends need an **ACTIVE** version. For a module test either use a seeded system template
(`notify.generic` for EMAIL / SMS / WHATSAPP) or create and activate one in the test:
```php
$v = Templates::saveDraft('jobcard.test', ['channel' => 'SMS', 'category' => 'TRANSACTIONAL', 'name' => 'T',
    'body_text' => 'Hi {{name}}', 'variables' => [['name' => 'name', 'required' => true]]], $adminId);
Templates::approveDirect($v->get('version_id'), $adminId);
Templates::activate($v->get('version_id'), $adminId);
```
With `sms.dlt_required` on, an SMS template also needs its DLT fields, otherwise `DLT_MAP_MISSING`. Turn the setting off
in the test if DLT is not what you are testing.

**Approvals** — build the authority with `approvalRule()` from the fixtures, then drive the request:
```php
[$fsc, $gm] = $this->staffWithDesignations(2)->all();
$this->approvalRule('extra_disc', [[$fsc->designation_code, 2000], [$gm->designation_code, 8000]]);
$r = Approval::open(['type' => 'QUOTE', 'id' => 1], 'DISCOUNT.EXTRA', 'extra_disc', ['asked' => 5000], $fsc->id);
Approval::counter($r->get('id'), $gm->id, 4000);
$this->assertSame(4000.0, Approval::effective($r->get('id'))['value']);
```

**OTP** — the code is never readable (hashed, not in the outbox). Test your flow's handling of `OTP_INVALID` /
`OTP_EXPIRED` / `RATE_LIMITED` rather than a successful verify; `SmsService::verify` itself is covered by the
platform tests.

## Don'ts
- Don't insert into utility tables to set up state (`xlr8_utils_*`, `xlr8_approval_*`, `xlr8_comm_*`): call the service.
- Don't `Mail::fake()` to check a customer email; assert the outbox / sandbox row instead. (`Mail::fake()` is only
  for proving the `laravel` driver hands the message to Laravel.)
- Don't run `php artisan testing:refresh-db` while DEC-051 is open (see `.ai/state/current.md`).
- Don't assert on message wording; copy changes in Templates without a deploy.
