# 13. Telephony

Click-to-call from any screen, a call log with dispositions, and recordings stored in Docs — behind one API so the
CPaaS / PBX can change without touching screens. Service `App\Services\Platform\Comms\TelephonyService` · facade `Telephony`.

## Concepts
- **Dial:** rings the agent's mobile (their Person primary mobile) first, then the customer. The call row exists
  before the vendor answers (`DIALING` → `RINGING` → `ANSWERED` / `COMPLETED` / `NO_ANSWER` / `BUSY` / `FAILED`).
- **Masking:** with `telephony.mask` on, browsers only ever see `+9198XXXXXX12`; the dial form sends a person code.
- **Recordings:** fetched when the vendor reports them ready, stored in Docs (`call-recordings`, attached to the
  call), entitled to the agent and to whoever may see the call; a `CALL_RECORDED` event lands on the linked record.
  Missing after `telephony.recording_grace_minutes` → flagged + Alert to `UTL_COMM_VIEW` holders (sweep every 15 min).
- **Dispositions** (KeyValue `CALL_DISPOSITION`): CONNECTED, NO_ANSWER, BUSY, WRONG_NUMBER, VOICEMAIL, CALLBACK_REQUESTED.
- **Inbound calls** are matched to a Person by number (never dropped) and alert `UTL_COMM_CALL` holders.
- Consent / promo window apply only to campaign calls (`'campaign' => true`).
- Driver `telephony.driver` (today `sandbox`).

## API
| Call | Returns / codes |
|---|---|
| `Telephony::dial($agentUserId, $personCodeOrNumber, ['type' => 'ENQUIRY', 'id' => $eid], ['caller_id' => 'JAIPUR_SALES', 'campaign' => false])` | Result `{call_id, status, to_masked}` — `AGENT_NO_PHONE`, `INVALID_MSISDN`, `CONSENT_DENIED`, `DIAL_FAILED` |
| `Telephony::dispose($callId, 'CALLBACK_REQUESTED', 'after 5pm')` | Result — `INVALID_DISPOSITION` |
| `Telephony::recording($callId, $viewerId)` | Result = Docs DTO + `can_download` — `FORBIDDEN`, `NOT_FOUND` |
| `Telephony::calls(['person' => …, 'agent' => …, 'status' => …, 'from' => …, 'to' => …, 'ref_type' => …, 'ref_id' => …])` | paginator |
| `Telephony::display($number)` | masked when masking is on |

## Use cases

**1. Call button on an enquiry / person / ticket screen**
```blade
<x-telephony.click-to-call :person="$enquiry->person_code" :ref="$enquiry" label="Call customer" />
```

**2. Call from code** (e.g. a callback task)
```php
$r = Telephony::dial(backpack_user()->id, $personCode, ['type' => 'TASK', 'id' => $task->id]);
if ($r->code === 'AGENT_NO_PHONE') { /* ask the user to add a mobile to their profile */ }
```

**3. Mandatory disposition before the next call** — read `Telephony::calls(['agent' => $uid, 'status' => 'COMPLETED'])`
and block while one has no `disposition`.

**4. Play vs download** — the call log plays recordings for anyone who may see the call; downloading needs
`UTL_COMM_RECORDING_DOWNLOAD`.

## Screens & webhooks
**Utilities → Calls** `/admin/utils/calls` (filters, play / download, disposition) — `UTL_COMM_CALL`; all agents'
calls with `UTL_COMM_VIEW`, otherwise only your own. Webhook `POST /api/webhooks/comms/telephony`:
`{"event_id","vendor_call_id","status":"ANSWERED|COMPLETED|NO_ANSWER|BUSY|FAILED","duration":64,"recording_ready":true}`;
inbound: `{"event_id","vendor_call_id","direction":"IN","from":"+91…","to":"<DID>"}`.

## Gotchas
- Never send raw customer numbers to the browser; use `display()` or the component.
- Listen / whisper / barge return `NOT_SUPPORTED` on drivers without them — never let that break dialling.
