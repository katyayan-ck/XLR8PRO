# 9. Templates — message copy

The single catalogue of customer-facing copy for Email, SMS, WhatsApp (and push / print). Versioned and approved;
runtime sends only the **ACTIVE** version. Service `App\Services\Platform\Templates\TemplateService` · facade `Templates`.

## Concepts
- A **family** = `code` × `channel` × `locale` (e.g. `quote.customer.send` / EMAIL / en-IN). Copy lives in **versions**:
  `DRAFT → IN_REVIEW → APPROVED → ACTIVE → RETIRED` (+ `PENDING_PROVIDER`, `REJECTED` for WhatsApp / SMS vendor approval).
- Editing never touches ACTIVE: saving forks a new DRAFT. Activating a version retires the previous ACTIVE (kept, so
  old sends can be replayed exactly — the outbox stores code + version).
- **Category:** TRANSACTIONAL, OTP, OPERATIONAL, PROMOTIONAL (promotional needs consent + send window).
- **Placeholders:** `{{name}}`. Declare variables: `[{"name":"cust_name","required":true,"sample":"Ravi","pii":true}]`.
  Missing required or undeclared placeholders fail a real render (`RENDER_ERROR`); the preview highlights them.
  In HTML bodies values are escaped; `{{brand}}` is always available. PII values are masked in outbox previews.
- **Approval:** `submit()` opens an approval on topic `COMMS.TEMPLATE`; when accepted the version becomes APPROVED.
  If no approval rule exists for that topic, a `UTL_TPL_ACTIVATE` holder may approve directly.
- System templates (seeded ACTIVE): `notify.generic` (EMAIL / SMS / WHATSAPP — used by Notify channels),
  `otp.sms`, `sms.stop.ack`.

## API
| Call | Returns |
|---|---|
| `Templates::render($code, 'EMAIL'|'SMS'|'WHATSAPP', $vars, $locale = null)` | Result `{subject, html, text, wa_payload, missing, unknown, warnings, template_code, template_version, category, dlt, pii}` — `TEMPLATE_NOT_ACTIVE`, `RENDER_ERROR` |
| `Templates::get($code, $channel, $locale)` | Result `{template, version, warnings}` (en-IN fallback unless `templates.strict_locale`) |
| `Templates::saveDraft($code, [channel, category, name, subject, body_html, body_text, variables, sample_vars, wa_components, provider_template_id, dlt_entity_id, dlt_header])` | Result `{template_id, version_id, version}` |
| `Templates::submit($versionId)` | Result `{approval_id}` |
| `Templates::approveDirect($versionId)` / `Templates::activate($versionId)` | Result |
| `Templates::diff($versionA, $versionB)` / `export()` / `import($items)` | compare / move drafts between environments |

## Use cases

**1. Create the copy for a new message**
```php
Templates::saveDraft('booking.delivery.reminder', [
    'channel' => 'SMS', 'category' => 'TRANSACTIONAL', 'name' => 'Delivery reminder',
    'body_text' => 'Dear {{cust_name}}, your {{vehicle}} is ready for delivery on {{date}}. -{{brand}}',
    'variables' => [['name' => 'cust_name', 'required' => true, 'pii' => true], ['name' => 'vehicle', 'required' => true], ['name' => 'date', 'required' => true]],
    'dlt_entity_id' => '1101…', 'dlt_header' => 'BMPLTX', 'provider_template_id' => '1107…',
]);
```
Then on **Utilities → Message templates**: preview, **Submit** (approval) → **Activate**.

**2. Send using it** — never render yourself for sending; pass code + vars to the channel:
```php
Sms::send(['to' => $personCode, 'template' => 'booking.delivery.reminder',
           'vars' => ['cust_name' => $name, 'vehicle' => 'Nexon XZ+', 'date' => site_date($booking->delivery_date)]]);
```

**3. Show copy in an admin screen** `<x-template.preview code="quote.customer.send" channel="EMAIL" :vars="$vars" />`

**4. Promote drafts from UAT to production:** Export JSON on UAT → Import on production (drafts only) → approve and
activate there.

## Screens & permissions
`/admin/utils/templates` — `UTL_TPL_VIEW` (list / preview), `UTL_TPL_EDIT` (drafts, submit, import),
`UTL_TPL_ACTIVATE` (approve directly, activate).

## Events & testing
Going live runs through approval topic `COMMS.TEMPLATE` (`ApprovalChanged`). In tests use `approveDirect()` + `activate()`. See [15-testing.md](15-testing.md); every code is in
[16-reference.md](16-reference.md).

## Gotchas
- No customer-facing text in PHP / Blade for messages — it belongs here.
- Put a date into a variable already formatted with `site_date()`.
- WhatsApp templates must match the provider-approved body exactly; keep `provider_template_id` filled.
