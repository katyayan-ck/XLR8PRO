# 12. WhatsApp

Send and read WhatsApp conversations: approved templates at any time, free-form inside the 24-hour customer-care
window, media via Docs, polls, and an agent inbox. Service `App\Services\Platform\Comms\WhatsAppService` · facade `WhatsApp`.

## Concepts
- **Thread** = one wa_id (person or group), optionally linked to a Person and a record (QUOTE, TICKET, …), assigned to
  an agent, labelled OPEN / PENDING / DONE.
- **Session window:** each inbound message opens the window for `whatsapp.session_hours` (24). Outside it only a
  template can be sent → free-form gives `SESSION_CLOSED`.
- **Template send** carries variables only (no extra text → `TEMPLATE_NO_EXTRA_TEXT`).
- **Polls:** when the driver has no poll support the poll is sent as a numbered list and a reply like `2` is stored as
  the structured answer.
- **Inbound media** is stored in Docs (collection `wa-inbound`, attached to the thread) — never a vendor URL.
- **STOP** flips `consent.whatsapp` off and suppresses the number; later sends give `CONSENT_DENIED`. Promotional
  templates need consent.
- Linking a thread to a record adds `CHANNEL_LINKED` to the record's timeline; later inbound messages appear there
  (`WHATSAPP_INBOUND`).
- Driver `whatsapp.driver` (today `sandbox`).

## API
| Call | Returns / codes |
|---|---|
| `WhatsApp::send(['to' => $personCodeOrNumber, 'template' => 'quote.customer.send.wa', 'vars' => [...], 'attach' => [$docId], 'ref_type' => 'QUOTE', 'ref_id' => $id])` | Result `{outbox_id, message_id, thread_id, degraded}` |
| `WhatsApp::send(['to' => …, 'type' => 'TEXT'|'IMAGE'|'DOCUMENT'|'POLL'…, 'text' => …, 'attach' => […], 'poll' => ['question' => …, 'options' => […]]])` | session message — `SESSION_CLOSED`, `INVALID_POLL`, `EMPTY` |
| `WhatsApp::thread($waId, $since = null)` | paginated messages (text, doc_id, poll answers, status) |
| `WhatsApp::history(['person' => …, 'group_id' => …, 'direction' => 'IN'|'OUT'|'ANY', 'type' => …, 'from' => …, 'to' => …, 'with_media' => true])` | list |
| `WhatsApp::markRead($messageId)`, `link($threadId, 'QUOTE', $id)`, `assign($threadId, $userId, 'PENDING')`, `inbox($userId, 'MINE'|'QUEUE'|'ALL'|'DONE')` | inbox actions |
Common codes: `INVALID_MSISDN`, `CONSENT_DENIED`, `TEMPLATE_NO_EXTRA_TEXT`, `TEMPLATE_NOT_ACTIVE`, `RENDER_ERROR`.

## Use cases

**1. Share a quote (template with PDF header)**
```php
WhatsApp::send(['to' => $customerPersonCode, 'template' => 'quote.customer.send.wa',
    'vars' => ['cust_name' => $name, 'vehicle' => 'Nexon', 'link' => $url], 'attach' => [$pdfDocId],
    'ref_type' => 'QUOTE', 'ref_id' => $quote->id, 'idempotency_key' => "quote.{$quote->id}.wa"]);
```

**2. Agent replies inside the window** (from the inbox composer, or code)
```php
$r = WhatsApp::send(['to' => '+9198…', 'type' => 'TEXT', 'text' => 'We can do Saturday 11am.']);
if ($r->code === 'SESSION_CLOSED') { /* offer a template instead */ }
```

**3. Ask a question with options**
```php
WhatsApp::send(['to' => $wa, 'type' => 'POLL', 'poll' => ['question' => 'Pickup or drop?', 'options' => ['Pickup', 'Drop']]]);
// the customer's "1" / "2" reply is stored as payload.poll_answer on the inbound message
```

**4. Pull a conversation's media for a claim file** — `history(['wa_id' => $wa, 'with_media' => true])` →
doc ids → Docs cart / zip (guide 4).

## Screens, components & webhooks
**Utilities → WhatsApp** `/admin/utils/whatsapp` (Mine / Unassigned / All / Done, thread, assign / label / link,
composer with message · poll · template tabs) — `UTL_COMM_WA_INBOX`. Components: `<x-whatsapp.inbox>`,
`<x-whatsapp.thread :thread="$t">`, `<x-whatsapp.composer :thread="$t">`.
Webhook `POST /api/webhooks/comms/whatsapp`:
- message: `{"event_id","type":"message","message":{"id":"wamid…","from":"+91…","type":"IMAGE","text":"…","media_base64":"…","media_name":"a.jpg","media_mime":"image/jpeg","group_id":null}}`
- status: `{"event_id","type":"status","message_id":"<provider id>","status":"DELIVERED|READ|FAILED"}`
- template approval: `{"event_id","type":"template_status","provider_template_id":"…","status":"APPROVED|REJECTED"}`

## Events & testing
`OutboxAccepted`, `ChannelLinked`; timeline `WHATSAPP_SENT` / `WHATSAPP_INBOUND`. Webhooks are HMAC-signed. See [15-testing.md](15-testing.md); every code is in
[16-reference.md](16-reference.md).

## Gotchas
- Without an `idempotency_key` the outbox derives one from channel + recipient + template + content + record, so an
  identical second send is a **duplicate** (`ok`, `duplicate: true`, nothing sent). Give deliberate repeats (reminders)
  their own key.
- Webhooks are HMAC-signed (`comms.webhook_secret`, header `X-Signature`) and idempotent on `event_id` / message id.
- A real provider (Gupshup / 360dialog / Meta) is one driver class + `whatsapp.driver`.
