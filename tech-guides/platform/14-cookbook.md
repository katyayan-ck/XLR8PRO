# 14. Cookbook — wiring a module to the utilities, end to end

One worked example that uses every utility the way a real module should. The record is a **hypothetical
workshop job card** (`App\Models\Service\JobCard`, table `xlr8_svc_job_card`). Swap in your own model; the steps are
identical. Individual guides (1–13) have the full API; this page shows how the pieces fit together.

> Rule of thumb: your module keeps **its own business columns only**. Status history, remarks, files, reminders,
> approvals, notifications and messages live in the utilities and are linked by `ref_type` + `ref_id`.

---

## Step 0 — register the record type (one-time)

`config/platform.php` → `entities`:
```php
'JOBCARD' => [
    'model' => App\Models\Service\JobCard::class,
    'url' => 'service/job-card/{id}/edit',        // deep link used by notifications, timelines, inboxes
    'label' => 'Job card',
    'permission' => 'SVC_JOBC_VIEW',               // who may read its chat / attached files by default
],
```
Everything below refers to the record as `'JOBCARD', $jobCard->id`.

## Step 1 — opt the model in

```php
namespace App\Models\Service;

use App\Models\BaseModel;
use App\Models\Traits\HasCommunications;
use App\Models\Traits\HasDocuments;

class JobCard extends BaseModel
{
    use HasCommunications, HasDocuments;

    /** Optional: narrower than the entity permission — the advisor of the job card, or anyone with the view permission. */
    public function chatCanView(int $userId): bool
    {
        return (int) $this->advisor_user_id === $userId || (bool) \App\Models\User::find($userId)?->can('SVC_JOBC_VIEW');
    }

    /** Optional: freeze the conversation once the job is invoiced. */
    public function chatCanRemark(int $userId): bool
    {
        return $this->status !== 'INVOICED';
    }
}
```
Documents attached to the job card follow `chatCanView` too (Docs `PARENT` rule), unless you entitle them explicitly.

## Step 2 — the service: persist → Chat event → Notify → domain event

```php
namespace App\Services\Service\JobCard;

use App\Services\Platform\Notify\Audience;
use App\Support\Facades\{Chat, Notify, Task, Settings};
use App\Support\Result;
use Illuminate\Support\Facades\DB;

class JobCardService
{
    public function open(array $data, int $actorId): Result
    {
        $jobCard = DB::transaction(fn () => JobCard::create($data + ['status' => 'OPEN']));   // 1. persist (your table)

        Chat::event($jobCard, 'CREATED', "Job card {$jobCard->number} opened", ['bay' => $jobCard->bay], $actorId);   // 2. history

        Notify::audience(Audience::designation('SVC_ADVISOR')->andBranch($jobCard->branch_code))   // 3. tell people
            ->about('JOBCARD', $jobCard->id)
            ->title('New job card {number} in bay {bay}')->vars(['number' => $jobCard->number, 'bay' => $jobCard->bay])
            ->idempotency("jobcard.{$jobCard->id}.opened")
            ->send();

        Chat::subscribe($jobCard, $jobCard->advisor_user_id);   // the advisor follows every new remark

        // 4. a follow-up the advisor must do, due by the promised time
        Task::create([
            'title' => 'Call customer with the estimate', 'type' => 'ASSIGNED_TASK', 'priority' => 'HIGH',
            'assignees' => [$jobCard->advisor_user_id], 'deadline' => $jobCard->promised_at,
            'ref_type' => 'JOBCARD', 'ref_id' => $jobCard->id,
        ], $actorId);

        return Result::ok(['id' => $jobCard->id]);
    }

    public function move(JobCard $jobCard, string $to, int $actorId): Result
    {
        $from = $jobCard->status;
        $jobCard->update(['status' => $to]);
        Chat::event($jobCard, 'STATUS_CHANGED', "Status {$from} → {$to}", ['from' => $from, 'to' => $to], $actorId);

        if ($to === 'READY') {
            Notify::to($jobCard->advisor_user_id)->about('JOBCARD', $jobCard->id)
                ->title('{number} is ready for delivery')->vars(['number' => $jobCard->number])->send();
        }

        return Result::ok();
    }
}
```

## Step 3 — the screen: timeline, files, tasks, call, SLA — all components

```blade
@extends(backpack_view('blank'))

@section('content')
<div class="row row-cards">
    <div class="col-lg-8">
        {{-- your own form / details card here --}}

        <x-docs.uploader :model="$jobCard" collection="docs" title="Photos & estimates" />
        <x-chat.thread :model="$jobCard" title="History & remarks" />
    </div>
    <div class="col-lg-4">
        @if ($approval) <x-approval.panel :request="$approval" /> @endif
        <x-telephony.click-to-call :person="$jobCard->customer_person_code" :ref="$jobCard" label="Call customer" />
        <x-task.inbox box="ASSIGNED" :limit="5" />
    </div>
</div>
@endsection
```
And in the controller's `edit()` / `show()`, clear the user's unread notifications for this record:
```php
Notify::purgeFor(backpack_user()->id, 'JOBCARD', $jobCard->id);
```

## Step 4 — an approval for an extra discount on labour

1. Seed the topic once: `WORKSHOP.LABOUR_DISC`, item key `labour_disc`, value type `AMOUNT` (guide 8).
2. Load who may grant how much through the **power-sheet import** (no code).
3. In the module:
```php
use App\Support\Facades\Approval;

$scope = ['branch' => $jobCard->branch_code, 'model' => $jobCard->model_code];

if (Approval::authorize($actorId, 'labour_disc', $scope, $discount)) {
    $jobCard->update(['labour_discount' => $discount]);          // within own power: apply now
} else {
    $r = Approval::open($jobCard, 'WORKSHOP.LABOUR_DISC', 'labour_disc',
        ['asked' => $discount, 'scope' => $scope, 'remark' => $reason], $actorId);
    if ($r->code === 'NO_RULE') { return back()->withErrors(['discount' => 'No authority is configured for this vehicle.']); }
    $jobCard->update(['labour_disc_request_id' => $r->get('id')]);   // your column: just the request id
}
```
4. React when it is decided — listen, don't poll:
```php
// App\Providers\EventServiceProvider (or a listener class)
Event::listen(\App\Events\Platform\ApprovalChanged::class, function ($e) {
    if ($e->change !== 'ACCEPTED') { return; }
    $jobCard = JobCard::where('labour_disc_request_id', $e->requestId)->first();
    $grant = \App\Support\Facades\Approval::effective($e->requestId);        // ['value' => 1500.0, 'level' => 2, …]
    $jobCard?->update(['labour_discount' => $grant['value']]);
});
```

## Step 5 — tell the customer (template → channel), with the estimate PDF

1. Copy lives in Templates (guide 9): `jobcard.estimate.customer` for EMAIL and WHATSAPP, variables `cust_name`,
   `number`, `amount`, `link`. Submit and activate it on **Utilities → Message templates**.
2. Store the PDF through Docs, then send:
```php
use App\Support\Facades\{Docs, Email, WhatsApp};

$pdf = Docs::attach($jobCard, $uploadedPdf, 'docs', ['title' => "Estimate {$jobCard->number}"]);

$vars = ['cust_name' => $name, 'number' => $jobCard->number, 'amount' => number_format($total), 'link' => $url];
$key  = "jobcard.{$jobCard->id}.estimate.v{$jobCard->estimate_version}";   // same estimate is never sent twice

Email::send(['to' => [$jobCard->customer_person_code], 'from' => 'service', 'template' => 'jobcard.estimate.customer',
    'vars' => $vars, 'attach' => [$pdf->get('id')], 'ref_type' => 'JOBCARD', 'ref_id' => $jobCard->id,
    'idempotency_key' => "$key.email"]);

$wa = WhatsApp::send(['to' => $jobCard->customer_person_code, 'template' => 'jobcard.estimate.customer', 'vars' => $vars,
    'attach' => [$pdf->get('id')], 'ref_type' => 'JOBCARD', 'ref_id' => $jobCard->id, 'idempotency_key' => "$key.wa"]);
if ($wa->code === 'CONSENT_DENIED') { /* customer opted out of WhatsApp — email already went */ }
```
Each send adds `EMAIL_SENT` / `WHATSAPP_SENT` to the job card's timeline automatically. The `service` From alias
must exist in Settings `mail.identities` (otherwise `FROM_NOT_ALLOWED`); `default` always works.

## Step 6 — something broke: open a ticket instead of logging and forgetting

```php
use App\Support\Facades\Ticket;

Ticket::open(['title' => "Estimate PDF failed for {$jobCard->number}", 'category' => 'BUG', 'priority' => 'P3',
    'details' => e($exception->getMessage()), 'ref_type' => 'JOBCARD', 'ref_id' => $jobCard->id], $systemUserId);
```

## Step 7 — settings instead of constants

```php
// config/platform.php → 'settings' (seed pack): typed, labelled, editable without a deploy
'jobcard.promise_buffer_minutes' => ['value' => 30, 'type' => 'int', 'label' => 'Job card promise buffer (minutes)'],

$buffer = Settings::getFor($jobCard->branch_code, 'jobcard.promise_buffer_minutes');   // branch override wins
```

## Step 8 — tests

See [15-testing.md](15-testing.md). Minimum: the service's happy path asserts the timeline event and the task;
the approval path asserts `NO_RULE` handling; the customer message asserts one outbox row per channel and no
duplicate on a second call with the same key.

---

## Checklist for a new module
- [ ] Entity registered in `config/platform.php` with a view permission.
- [ ] Model uses `HasCommunications` (+ `HasDocuments` if it has files); `chatCanView` / `chatCanRemark` if needed.
- [ ] Every state change: persist → `Chat::event` → `Notify` → domain event.
- [ ] No module columns for assignee / remarks / attachments / approval baton — only link ids.
- [ ] Customer copy in Templates; sends through `Email` / `Sms` / `WhatsApp` with an idempotency key.
- [ ] Limits and switches in the settings seed pack.
- [ ] Screen uses `<x-chat.thread>`, `<x-docs.uploader>`, `<x-approval.panel>`, `<x-telephony.click-to-call>` and follows the UI kit.
- [ ] `Notify::purgeFor()` when the user opens the record.
- [ ] Tests per [15-testing.md](15-testing.md).
