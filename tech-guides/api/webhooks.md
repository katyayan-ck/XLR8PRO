# API — Inbound comms webhooks (module UTL, DEC-064)

For the **providers** (email / SMS / WhatsApp / telephony vendors), not the mobile app.
**URL:** `POST {{base_url}}/api/webhooks/comms/{channel}` — `channel` = `email` | `sms` | `whatsapp` | `telephony`.
**Postman:** [`postman/webhooks.postman_collection.json`](postman/webhooks.postman_collection.json).
**Source:** `app/Http/Controllers/Api/CommsWebhookController.php`; log table `xlr8_comm_webhook_event`.

**Security:**
- No token or session. Every request must carry `X-Signature` = hex HMAC-SHA256 of the **raw body** with the secret in
  Settings `comms.webhook_secret` (encrypted setting). Without a secret configured, requests are refused outside
  `local` / `testing`.
- Rate limit 600 / minute. **Idempotent** on `event_id`: a repeated event returns `{"ok": true, "duplicate": true}` and is
  not processed again. Personal data in the stored payload is redacted.

**Response shape** (the provider-facing `Result`, not the app envelope): `{ "ok": bool, "code": "…", "message": "…", "data": {} }`.

| HTTP | Body | When |
|---|---|---|
| 200 | `{"ok": true, …}` | Processed (or duplicate) |
| 401 | `{"ok": false, "code": "BAD_SIGNATURE"}` | Signature missing / wrong |
| 404 | `{"ok": false, "code": "UNKNOWN_CHANNEL"}` | Unknown channel |
| 422 | `{"ok": false, "code": "EVENT_ID_REQUIRED"}` or the handler's failure (`UNKNOWN_EVENT`, `NOT_FOUND`, …) | Bad event |
| 429 | Laravel throttle | Over 600 / minute |

## Events per channel (JSON body; `event_id` always required)

| Channel | `type` | Other fields | Effect |
|---|---|---|---|
| email | `delivered` \| `opened` \| `bounced` \| `complained` | `message_id`, `address` | Outbox status (DELIVERED / READ / BOUNCED); bounce / complaint suppresses the address |
| sms | `inbound` | `from`, `text` | Inbound SMS (keywords / STOP) |
| sms | (status) | `message_id`, `status` (default DELIVERED) | Outbox status |
| whatsapp | `message` | `message {id, from, text, …}` | Inbound message → agent inbox |
| whatsapp | `status` | `message_id`, `status` | Outbox status |
| whatsapp | `template_status` | `provider_template_id`, `status` (APPROVED / REJECTED) | Template approval |
| telephony | — | `vendor_call_id`, `direction`, `status`, `answered_at`, `ended_at`, `duration`, `recording_ready` | Call log; `data: {call_id, status}` |

**Example** (sms delivery):
```http
POST /api/webhooks/comms/sms
Content-Type: application/json
X-Signature: 5f2b…c9

{"event_id": "evt-2026-09-30-0001", "message_id": "prov-771", "status": "DELIVERED"}
```
