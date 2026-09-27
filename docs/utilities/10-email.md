# 10. Email

Send email with full envelope control — from / reply-to / to / cc / bcc, template copy, Docs attachments,
calendar invites — through the outbox. Service `App\Services\Platform\Comms\EmailService` · facade `Email`.

## Concepts
- **Outbox first:** each send writes one `xlr8_comm_outbox` row (snapshot encrypted for exact resend), then a queued
  job delivers it. One send = one row whatever the number of recipients.
- **Recipients:** person codes (primary email from Person contacts), user ids, or raw addresses. Suppressed
  addresses (bounces / complaints) are skipped and reported.
- **From:** an alias from Settings `mail.identities` (`{"default": "Xceler8 <noreply@…>", "quotes": "BMPL Quotes <quotes@bmpl.in>"}`)
  or an allowed address (`mail.allowed_from`). Anything else → `FROM_NOT_ALLOWED`.
- **Copy:** template mode (default) or `raw: true` for internal mail.
- **Drivers:** Settings `mail.driver` = `laravel` (the Laravel mailer, `config/mail.php`) or `log` (sandbox — nothing
  leaves the server; see Outbox → Sandbox). `mail.redirect_to` sends every mail to one address (dev safety).

## `Email::send(array $options)` — locked shape
```php
Email::send([
    'to'        => [$personCodeOrEmail, …],     // required
    'cc'        => [...], 'bcc' => [...],        // optional (BCC never shown in timelines)
    'from'      => 'quotes',                     // alias or full address; default 'default'
    'reply_to'  => 'fsc.ravi@bmpl.in',
    'template'  => 'quote.customer.send',        // ACTIVE template (guide 9)
    'vars'      => ['cust_name' => 'Ravi', 'link' => $url],
    'attach'    => [$pdfDocId, ['doc_id' => $id, 'as' => 'Quote-8821.pdf'], ['ics' => $icsText, 'as' => 'test-drive.ics']],
    'ref_type'  => 'QUOTE', 'ref_id' => $qid,    // links the send to the record (timeline event EMAIL_SENT)
    'idempotency_key' => "quote.$qid.send.v3",   // same key = same send, never twice
    'locale'    => 'en-IN',
]);
// raw internal mail: ['to' => [...], 'raw' => true, 'subject' => '…', 'html' => '…', 'text' => '…']
```
Result `{outbox_id, status, sent, suppressed[], duplicate}`. Codes: `FROM_NOT_ALLOWED`, `TEMPLATE_REQUIRED`,
`TEMPLATE_NOT_ACTIVE`, `RENDER_ERROR`, `ATTACHMENT_MISSING`, `INVALID`. Nobody left after suppression → `ok`,
`sent: 0`, status `SUPPRESSED`.

Other: `Email::status($outboxId)` (QUEUED / RETRY / SENT / DELIVERED / BOUNCED / FAILED …), `Email::resend($outboxId)`
(same snapshot, new key — works after a driver change).

## Use cases

**1. Email a quote to the customer, SM in copy, audit in bcc**
```php
Email::send(['to' => [$quote->customer_person_code], 'cc' => ['sm@bmpl.in'], 'bcc' => ['audit@bmpl.in'],
    'from' => 'quotes', 'template' => 'quote.customer.send',
    'vars' => ['cust_name' => $name, 'vehicle' => $variantName, 'link' => $url],
    'attach' => [$quotePdfDocId], 'ref_type' => 'QUOTE', 'ref_id' => $quote->id,
    'idempotency_key' => "quote.{$quote->id}.send.{$quote->revision}"]);
```

**2. Test-drive invite with a calendar file** — `'attach' => [['ics' => $ics, 'as' => 'test-drive.ics']]`.

**3. Through Notify** (when the user should also get a bell item) — see guide 2, use case 4.

**4. Ops "send again" panel** `<x-email.send-panel template="quote.customer.send" :to="$email" :ref="$quote" :attach="[$pdfDocId]" />`

## Screens & permissions
**Utilities → Outbox** `/admin/utils/comms/outbox` (filter by channel / status / template / person / record, sandbox
tab, details, **Resend**) — `UTL_COMM_VIEW`; resend / send panel `UTL_COMM_SEND`. Bounce / complaint webhooks
(`POST /api/webhooks/comms/email`) mark the row and suppress the address.

## Events & testing
`OutboxAccepted` when the driver takes a message; timeline `EMAIL_SENT`. Assert the outbox / sandbox row, not `Mail::fake()`. See [15-testing.md](15-testing.md); every code is in
[16-reference.md](16-reference.md).

## Gotchas
- Without an `idempotency_key` the outbox derives one from channel + recipient + template + content + record, so an
  identical second send is a **duplicate** (`ok`, `duplicate: true`, nothing sent). Give deliberate repeats (reminders)
  their own key.
- Never `Mail::send/raw` in module code — only the mail driver may.
- Never put customer copy in code; pass `vars`, and escape nothing yourself (HTML vars are escaped by the renderer).
- Local `.env` points at a real SMTP host — use `mail.driver = log`, `Mail::fake()` in tests, or `mail.redirect_to`.
