# 2. Notify

Tell people something: the bell (in-app inbox), mobile push (FCM), and — through the comms plane — email,
SMS or WhatsApp. Service `App\Services\Platform\Notify\NotifyService` · facade `Notify`.

## Concepts
- **Kinds:** `N` notification (default), `A` alert (urgent, red badge), `M` message (person-to-person, @mentions).
- **Audience:** user ids, designations, branches, person codes, or everyone following a record — resolved to
  active users, the actor excluded unless `notifySelf()`.
- **Channels:** `INAPP` + `FCM` by default; add `EMAIL`, `SMS`, `WHATSAPP` (see guides 10–12). An options array per
  channel is forwarded unchanged to that service.
- **Idempotency:** give a key and the same notification is never sent twice.
- **Deep link:** `about('TASK', 42)` links the item to the record's screen (from `config/platform.php` entities).
- Inbox rows: `N`/`M` in `xlr8_utils_noty_notification`, `A` in `xlr8_utils_noty_alert`; one dispatch row per send.

## API (fluent)
```php
Notify::to($userId)            // or ->toMany([$a, $b]) / ->audience(Audience::designation('SM'))
    ->kind('N')                // N | A | M
    ->about('BOOKING', $id)    // ref_type + ref_id → deep link, grouping, purgeFor()
    ->title('Booking {id} is ready for delivery')   // placeholders: {actor} {id} {brand} + ->vars([...])
    ->body('Customer confirmed the slot.')
    ->priority('high')
    ->data(['severity' => 'critical'])              // alerts: severity info|warning|critical
    ->channels(['INAPP' => true, 'FCM' => true])
    ->idempotency("booking.$id.ready")
    ->notifySelf()                                  // also notify the actor
    ->send();                                       // Result: data dispatch_id, sent
```
| Other calls | Purpose |
|---|---|
| `Notify::counts($userId)` | unread / read / total per kind (what the bell shows) |
| `Notify::list($userId, 'N'|'A'|'M', 'ALL'|'UNREAD'|'READ'|'ARCHIVE')` | paginated inbox |
| `Notify::mark($userId, $kind, $inboxId, 'READ'|'UNREAD'|'ARCHIVE')` | one row (READ/UNREAD also un-archive) |
| `Notify::markAll($userId, ?$kind)` | mark everything read |
| `Notify::purgeFor($userId, 'TASK', $id)` | the user opened the record → its notifications become read |

`Audience`: `Audience::users([..])`, `::designation('SM', 'GM')`, `::branch('JPR')`, `::persons('P123')`,
`::watchersOf('QUOTE', $id)`, chain with `->andUsers()`, `->andDesignation()`, … and `->except($id)`.

## Details worth knowing
- **Placeholders** in title / body: `{actor}` (display name, or `System`), `{id}` (ref id), `{brand}`, plus anything in
  `->vars()`. Unknown placeholders are left as typed.
- **`->template($code, $vars)`** renders the ACTIVE `PUSH`-channel template for the title / body; if there is none the
  plain `title()` / `body()` are used (no error).
- **Channels without options** (`'EMAIL' => true`) send the generic template `notify.generic` with the title, body and
  link to every recipient user (their Person email / mobile). Email goes as **one** message with all recipients in To
  unless you pass `to`; SMS / WhatsApp go one per person. Each channel gets the key `"{key}.{CHANNEL}"`.
- **Actor:** defaults to the signed-in admin; set `->actor($id)` in jobs / imports so `{actor}` and "exclude the
  actor" work.
- **`priority()`** is stored on notifications (`normal` by default) for sorting in the app; alerts use
  `data(['severity' => …])`.

## Use cases

**1. Tell one person their record changed**
```php
Notify::to($booking->consultant_user_id)->about('BOOKING', $booking->id)
    ->title('{actor} approved the discount on booking {id}')->send();
```

**2. Alert a role in one branch**
```php
Notify::audience(Audience::designation('SM')->andBranch('JPR'))
    ->kind('A')->title('Stock below minimum: {model}')->vars(['model' => 'Nexon'])
    ->data(['severity' => 'warning'])->send();
```

**3. Everyone following a quote, except whoever just acted**
```php
Notify::audience(Audience::watchersOf('QUOTE', $quote->id))->about('QUOTE', $quote->id)
    ->title('New remark on quote {id}')->send();          // the actor is excluded automatically
```

**4. Notification + customer email in one call** (options go to `Email::send`, guide 10)
```php
Notify::to($fscUserId)->about('QUOTE', $qid)->title('Quote sent to {cust}')->vars(['cust' => $name])
    ->channels(['INAPP' => true, 'EMAIL' => [
        'to' => [$customerPersonCode], 'from' => 'quotes', 'cc' => ['sm@bmpl.in'],
        'template' => 'quote.customer.send', 'vars' => ['cust_name' => $name, 'link' => $url], 'attach' => [$pdfDocId],
    ]])->send();
```

**5. Never twice** (retries, double clicks, jobs)
```php
Notify::to($uid)->title('Price list live')->idempotency("pricelist.$sessionId.live.$uid")->send();
```

**6. Mark read when the user opens the record** (e.g. in a controller's `show()`)
```php
Notify::purgeFor(backpack_user()->id, 'TICKET', $ticket->id);
```

## Screens & components
- Top bar `<x-notify.bell />` (already in the layout): one bell, combined badge (red when an alert is unread),
  tabs Notifications / Alerts / Messages, mark read, deep links.
- **Utilities → My Inbox** `/admin/utils/inbox` (tabs × Inbox/Unread/Read/Archive). Everyone may open their own inbox.
- Mobile v1 API (`/api/v1/notifications…`) reads the same rows.

## Settings
`notify.quiet_hours` (`HH:MM-HH:MM`, may cross midnight, e.g. `22:00-07:00`). Inside the window **push is skipped, not
delayed**: the bell / inbox row is still written, so users see it when they next open the app. Plus the channel
settings in guides 10–12.

## Errors
`INVALID_KIND`. An empty audience is **not** an error: `ok` with `sent: 0` (logged). A repeated idempotency key returns
`ok` with `duplicate: true`, `sent: 0`.

**7. From a queued job** (no signed-in user): name the actor and use a key so retries don't duplicate
```php
Notify::audience(Audience::designation('ACC_MGR'))->actor($systemUserId)->kind('A')
    ->title('{count} receipts failed to post')->vars(['count' => $failed])->data(['severity' => 'critical'])
    ->idempotency("receipts.post.{$batchId}")->send();
```

**8. Message a person directly** (kind `M`, shows under Messages)
```php
Notify::to($colleagueId)->kind('M')->title('{actor}: can you cover my 4 pm test drive?')->send();
```

## Events & testing
`NotificationSent` after every dispatch; one `SendPushNotification` job per inbox row. Test with `Notify::counts()` /
`Notify::list()` and `Bus::fake([SendPushNotification::class])`: see [15-testing.md](15-testing.md). Codes:
[16-reference.md](16-reference.md).

## Gotchas
- Don't write `Notification` / `Alert` models directly; don't call `FirebaseService` directly — push is queued by Notify.
- Titles are plain text (HTML is stripped). Keep them short; put detail in `body()`.
- `NotificationService` exists only as the adapter for the mobile v1 API.
