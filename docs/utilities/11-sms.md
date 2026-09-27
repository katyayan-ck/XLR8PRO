# 11. SMS

Transactional, operational and (consented) promotional SMS through any aggregator, plus one-time passwords.
Service `App\Services\Platform\Comms\SmsService` · facade `Sms`.

## Concepts
- Numbers are normalised to E.164 (`+91…`); invalid → `INVALID_MSISDN` without any vendor call. Recipient may be a
  person code (primary mobile), a user id or a raw number.
- Copy always from an ACTIVE template (guide 9). `raw: true` + `text` only for users with `UTL_COMM_SMS_RAW`.
- **DLT (India):** transactional / promotional / OTP templates need DLT template id, entity id and header when
  `sms.dlt_required` is on → otherwise `DLT_MAP_MISSING`.
- **Consent:** PROMOTIONAL needs a `SMS` consent row for the person, and the send window `comms.promo_window`
  (`10:00-18:00`, IST). Transactional / OTP go unless the number is suppressed.
- **STOP** (inbound webhook) revokes consent, suppresses the number and replies with `sms.stop.ack`.
- **Drivers:** `sms.driver` (today `sandbox` — messages land in Outbox → Sandbox), optional `sms.failover_driver`
  (tried once with the same idempotency key when the primary fails).

## API
| Call | Returns / codes |
|---|---|
| `Sms::send(['to' => …, 'template' => …, 'vars' => […], 'from' => 'HEADER', 'ref_type' => …, 'ref_id' => …, 'idempotency_key' => …])` | Result `{outbox_id, status, sent, category}` — `INVALID_MSISDN`, `TEMPLATE_REQUIRED`, `FORBIDDEN`, `DLT_MAP_MISSING`, `CONSENT_DENIED`, `OUTSIDE_WINDOW`, `RENDER_ERROR` |
| `Sms::otp($personCode, 'LOGIN', $ttlSeconds = null)` | Result `{outbox_id, destination (masked), expires_in}` — `RATE_LIMITED`, `INVALID_MSISDN` |
| `Sms::verify($personCode, 'LOGIN', $code)` | Result — `OTP_EXPIRED`, `OTP_LOCKED` (5 wrong tries), `OTP_INVALID` |
| `Sms::status($outboxId)` | normalised state |

OTP: 6 digits, stored **hashed**, never in the outbox, queue or logs; TTL `sms.otp_ttl_seconds` (300); at most
`sms.otp_max_per_15min` (3) per person and purpose; OTP messages cannot be resent.

## Use cases

**1. Transactional message**
```php
Sms::send(['to' => $booking->customer_person_code, 'template' => 'booking.delivery.reminder',
    'vars' => ['cust_name' => $name, 'vehicle' => $model, 'date' => site_date($booking->delivery_date)],
    'ref_type' => 'BOOKING', 'ref_id' => $booking->id, 'idempotency_key' => "booking.{$booking->id}.delivery.sms"]);
```

**2. OTP to confirm a customer's mobile**
```php
$sent = Sms::otp($personCode, 'MOBILE_VERIFY');        // show "code sent to +9198XXXXXX12"
…
$check = Sms::verify($personCode, 'MOBILE_VERIFY', $request->input('code'));
if (! $check->ok) { return back()->withErrors(['code' => $check->message]); }
```

**3. Promotional campaign** — record consent first, then send (non-consented / out-of-window are refused):
```php
app(ContactService::class)->setConsent($personCode, 'SMS', true, 'WALK_IN_FORM', backpack_user()->id);
Sms::send(['to' => $personCode, 'template' => 'promo.diwali.sms']);
```

**4. Via Notify** — `->channels(['SMS' => ['template' => 'ticket.p1.oncall', 'vars' => [...]]])`.

## Screens & webhooks
Outbox / Sandbox `/admin/utils/comms/outbox`. Webhook `POST /api/webhooks/comms/sms`:
`{"event_id": "…", "type": "dlr", "message_id": "<provider id>", "status": "DELIVERED|FAILED|EXPIRED"}` or
`{"event_id": "…", "type": "inbound", "from": "+91…", "text": "STOP"}`.

## Gotchas
- Never call a vendor SDK / HTTP API from a module — only `Services\Platform\Comms\Drivers\*`.
- A real vendor driver (MSG91 / Kaleyra …) is one class + `sms.driver`; module code does not change.
