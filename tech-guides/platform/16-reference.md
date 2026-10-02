# 16. Reference — result codes, events, jobs, settings, permissions

Lookup tables for all utilities. Each guide (1–13) explains its own subset; this page is the complete list.
Source of truth: the services in `app/Services/Platform/*`. If code and this page disagree, the code wins —
fix the page in the same change.

## 1. `Result` codes
Every write returns `App\Support\Result` (`ok`, `code`, `message`, `data`). Branch on **`code`**; show `message` to
the user (it is written for them). `ok` results have code `OK`.

### Shared codes
| Code | Meaning | Typical handling |
|---|---|---|
| `NOT_FOUND` | the task / ticket / request / document / remark / outbox row does not exist | 404 or "no longer exists" |
| `FORBIDDEN` | the actor may see it but not do this (e.g. snooper remarking, non-owner deleting, non-requester revising) | show the message; hide the action next time using `user_can` |
| `UNAUTHORISED` | no signed-in actor, or the viewer may not see the record at all (`get()` returns no data) | 403 |
| `INVALID` | a required field is missing (title, subject + body, card title) | validation error on the form |
| `EMPTY` | nothing to do: empty remark, empty cart, pack with no visible files, sheet with no rows | inform |

### By utility
| Utility | Code | Meaning |
|---|---|---|
| Settings | `READ_ONLY` | the setting is flagged read-only |
| | `INVALID_VALUE` | value does not fit the setting's type |
| | `INVALID_SCOPE` | scope must be COMPANY / BRANCH / DESK with a code |
| | `WRITE_FAILED` | storage error (logged) |
| Notify | `INVALID_KIND` | kind is not N / A / M. An empty audience is **not** an error (`ok`, `sent: 0`) |
| Chat | `EDIT_WINDOW_CLOSED` | past `chat.edit_window_minutes` |
| | `INVALID_PARENT` | reply target is not in this conversation |
| Docs | `INVALID_FILE` | too large (`docs.max_upload_kb`) or type not allowed (`docs.allowed_mimes`) |
| Tasks | `INVALID_TYPE` / `INVALID_PRIORITY` | not in KeyValue `TASK_TYPE` / `TASK_PRIORITY` |
| | `INVALID_OWNER` / `INVALID_PEOPLE` | owner / some people do not exist |
| | `ASSIGNEE_REQUIRED` | `ASSIGNED_TASK` without assignees |
| | `INVALID_DEADLINE` | deadline not parseable |
| | `FORBIDDEN_TRANSITION` | status move not allowed for this role from this status (rights matrix) |
| | `CONFIRM_REQUIRED` | deleting a CLOSED task needs `$confirm = true` |
| Tickets | `INVALID_CATEGORY` / `INVALID_PRIORITY` | not in `TICKET_CATEGORY` / `TICKET_PRIORITY` |
| | `INVALID_REQUESTER` / `INVALID_PEOPLE` | unknown users |
| | `FORBIDDEN_TRANSITION` | illegal edge or wrong role |
| | `REASON_REQUIRED` | owner force-close without a reason |
| Approvals | `UNKNOWN_TOPIC` | topic code not active |
| | `NO_RULE` | no rule covers this topic + scope — tell the user authority is not configured |
| | `INVALID_ASK` / `INVALID_VALUE` | negative, or percentage > 100 |
| | `CLOSED` | request already ACCEPTED / WITHDRAWN |
| | `NO_AUTHORITY` | actor holds no level on this request |
| | `NOT_YOUR_TURN` | LINEAR topic, another level holds the baton |
| | `EXCEEDS_POWER` | counter above the actor's level maximum |
| | `NO_GRANT` | accepting before anyone countered the current ask |
| | `INVALID_OUTCOME` | close with something other than ACCEPTED / WITHDRAWN |
| Power sheet | `UNREADABLE` / `MISSING_COLUMN` / `EMPTY` | file problems; row problems are listed in `data.errors` instead |
| Templates | `INVALID_CODE` | codes are lower-case dotted (`quote.customer.send`) |
| | `INVALID_CHANNEL` / `INVALID_CATEGORY` / `INVALID_VARIABLES` | bad draft payload |
| | `NOT_DRAFT` / `INVALID_STATE` / `NOT_APPROVED` | lifecycle order: submit a DRAFT → approve → activate an APPROVED version |
| | `TEMPLATE_NOT_ACTIVE` | no ACTIVE version for code × channel × locale |
| | `RENDER_ERROR` | required variable missing or unknown placeholder |
| Email | `FROM_NOT_ALLOWED` | From not in `mail.identities` / `mail.allowed_from` |
| | `TEMPLATE_REQUIRED` | customer email without a template (use `raw: true` only for internal mail) |
| | `ATTACHMENT_MISSING` | a Docs id in `attach` does not exist |
| SMS | `INVALID_MSISDN` | no valid mobile (no vendor call made) |
| | `DLT_MAP_MISSING` | template lacks DLT ids while `sms.dlt_required` |
| | `CONSENT_DENIED` | promotional without consent, or the person opted out (also WhatsApp) |
| | `OUTSIDE_WINDOW` | promotional outside `comms.promo_window` |
| | `RATE_LIMITED` | more than `sms.otp_max_per_15min` OTPs |
| | `OTP_EXPIRED` / `OTP_INVALID` / `OTP_LOCKED` | no live code / wrong code / 5 wrong tries |
| | `NOT_RESENDABLE` | OTP rows are never resent |
| WhatsApp | `SESSION_CLOSED` | free-form outside the 24 h window — send a template |
| | `TEMPLATE_NO_EXTRA_TEXT` | template sends carry variables only |
| | `INVALID_TYPE` / `INVALID_POLL` / `INVALID_LABEL` | bad message type / poll without 2 options / label not OPEN, PENDING, DONE |
| Telephony | `AGENT_NO_PHONE` | the agent's Person has no primary mobile |
| | `DIAL_FAILED` | driver refused the call |
| | `INVALID_DISPOSITION` | not in KeyValue `CALL_DISPOSITION` |
| | `NOT_READY` | recording not fetched yet |
| | `INVALID_USER` | unknown agent |

### Duplicates are success
Sends and notifications are idempotent. A repeat returns `ok` with `duplicate: true` and nothing is sent again.
- **Outbox (Email / SMS / WhatsApp):** without an `idempotency_key` the outbox derives one from channel + recipient +
  template + payload + record. Sending the **same content to the same person about the same record twice** is
  therefore treated as a duplicate. When you really mean a second send (a reminder), give each one its own key, e.g.
  `"booking.{$id}.reminder.".now()->toDateString()`.
- **Notify:** only de-duplicates when you pass `->idempotency($key)`.

## 2. Events (`App\Events\Platform\*`)
Listen instead of polling. All are dispatched **after** the change is persisted and the Chat event is written.

| Event | Payload | Fired when (`change` values) |
|---|---|---|
| `SettingsChanged` | `key, old, new, scopeType, scopeCode, actorId` (old / new `null` for encrypted) | any `Settings::set / reset / clearScope` |
| `NotificationSent` | `dispatchId, kind, refType, refId, recipients[]` | a Notify dispatch created inbox rows |
| `ChatEntryAdded` | `masterId, threadId, kind (EVENT/REMARK), action` | every timeline entry |
| `TaskChanged` | `taskId, change, actorId` | `CREATED`, `UPDATED`, `DELETED`, `REMARKED`, or the new status (`INPROGRESS`, `HOLD`, `SUBMITTED`, `CLOSED`, `REOPENED`) |
| `TicketChanged` | `ticketId, change, actorId` | `CREATED`, `UPDATED`, the new status, `SLA_BREACHED` (actor null), `CLOSED` by auto-close (actor null) |
| `ApprovalChanged` | `requestId, change, actorId` | `OPENED`, `COUNTERED`, `REVISED`, `ACCEPTED`, `WITHDRAWN` |
| `OutboxAccepted` | `outboxId, channel, status` | the driver accepted a message |
| `ChannelLinked` | `threadId, refType, refId` | a WhatsApp thread was linked to a record |
| `CallRecorded` | `callId, docId, refType` | a call recording was stored in Docs |

```php
// app/Providers/EventServiceProvider.php (or a listener class with handle())
Event::listen(\App\Events\Platform\TicketChanged::class, function ($e) {
    if ($e->change === 'SLA_BREACHED') { /* page the on-call */ }
});
```

## 3. Chat action codes (KeyValue `ENTITY_ACTIONS`)
`CREATED`, `UPDATED`, `DELETED`, `STATUS_CHANGED`, `REMARKED`, `ATTACHED`, `TASK_CREATED`, `TICKET_OPENED`,
`APPROVAL_REQUESTED`, `APPROVAL_ACCEPTED`, `APPROVAL_WITHDRAWN`, `OPENED`, `COUNTERED`, `REVISED`, `ACCEPTED`,
`WITHDRAWN`, `AUTO_ACCEPTED`, `SUBMITTED`, `APPROVED`, `ACTIVATED`, `SLA_BREACHED`, `AUTO_CLOSED`, `EMAIL_SENT`,
`SMS_SENT`, `WHATSAPP_SENT`, `WHATSAPP_INBOUND`, `CHANNEL_LINKED`, `CALL_DIALLED`, `CALL_DISPOSED`, `CALL_RECORDED`.
A new action = a new KeyValue row via `KeyvalueService` (in a migration), then use it in `Chat::event()`. An unregistered code
still writes (the row just has no `action_id`); the timeline label is always derived from the code (`STATUS_CHANGED` →
"Status Changed"), so register codes for reporting / filtering, not for display.

## 4. Jobs and schedule (`routes/console.php`)
| Job | When | Does |
|---|---|---|
| `SendOutboxMessage` | queued per outbox row (after commit) | delivers through the channel driver; retries up to `comms.max_attempts`; failover for SMS |
| `SendPushNotification` | queued per inbox row | FCM push to the user's devices (skipped in `notify.quiet_hours`) |
| `FlagTicketSlaBreaches` | hourly | Alert once per breached ticket (owner, assignees, desk) |
| `AutoCloseResolvedTickets` | daily 03:00 | closes RESOLVED tickets after `ticket.autoclose_days` when enabled |
| `FlagMissingCallRecordings` | every 15 min | flags calls without a recording after `telephony.recording_grace_minutes` and alerts `UTL_COMM_VIEW` |
| `PurgeDeletedDocuments` | daily 02:30 | permanently removes documents soft-deleted more than `docs.purge_after_days` ago |
| `PurgeHelpUsage` | daily 02:50 | deletes help-usage events older than `help.usage_retention_days` (W16f) |
| `PurgeSupportBundles` | daily 02:45 | deletes support-request diagnostic zips older than `support.bundle_retention_days` (DEC-094) |

Workers: `php artisan queue:work` must run for sends and push outside tests. The scheduler needs the usual
`php artisan schedule:run` cron.

**Queue `retry_after` (BUG-230):** 1900 s on the database and Redis queues — longer than the longest job (pricing,
1800 s), so a running job is never handed to a second worker. `QueueRetryAfterTest` fails if a job's `$timeout` reaches
it. Run workers with `--timeout` at or below the job timeouts (pricing worker: `queue:work --timeout=1800 --tries=1`).

**Redis for UAT / production (O7, DEC-095 #33)** — no code change needed; set in `.env`:
- `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis` (PHP extension, already
  in the local PHP 8.4 build; no Composer package), `REDIS_HOST` / `REDIS_PASSWORD` / `REDIS_PORT`, `REDIS_PREFIX`
  per environment (e.g. `xlrm-uat-`).
- The cache uses its own Redis database (`REDIS_CACHE_DB`, default 1); sessions and queue use `REDIS_DB` (0).
  `Cache::flush()` (keyword cache clear, pricing reset) clears only the cache database, so users stay signed in and
  queued jobs stay. Give each environment its own Redis instance or database numbers; never share them with another
  app.
- Locks (`Cache::lock`: VOTF numbering, pricing recalculation) work on Redis as they do now.
- Batches and failed jobs stay in MySQL (`xlr8_system_job_batches`, `xlr8_system_failed_jobs`).
- After switching: `php artisan config:cache`, restart the workers (`queue:restart`). The `xlr8_system_sessions` and
  `xlr8_system_jobs` tables stop filling; existing sessions end (users sign in again once).

## 5. Settings seed pack (`config/platform.php` → `settings`)
| Key | Default | Type | Used by |
|---|---|---|---|
| `brand.name` | BMPL | string | `{brand}` / `{{brand}}` in notifications and templates |
| `display.date_format` / `display.time_format` | d-M-Y / H:i | string | site date format everywhere (UI kit) |
| `chat.edit_window_minutes` | 15 | int | remark edit window |
| `docs.max_upload_kb` / `docs.allowed_mimes` | 10240 / (blank = any) | int / string | Docs + drop-zone limits; `allowed_mimes` is a comma list of file **extensions** (`pdf,jpg,png`), not MIME types |
| `docs.purge_after_days` | 30 | int | purge job |
| `notify.quiet_hours` | blank | string `HH:MM-HH:MM` | push skipped in the window |
| `sla.ticket.p1_hours` … `p4_hours` | 4 / 8 / 24 / 72 | int | ticket due time (branch overrides via `getFor`) |
| `ticket.autoclose_enabled` / `ticket.autoclose_days` | true / 3 | bool / int | auto-close job |
| `approval.auto_accept_own_power` | true | bool | asks within own power close immediately |
| `mail.driver` | laravel | string | `laravel` or `log` (sandbox) |
| `mail.identities` / `mail.allowed_from` | — | json / string | allowed From aliases / addresses |
| `mail.redirect_to` | blank | string | dev safety: every email to one address |
| `sms.driver` / `sms.failover_driver` | sandbox / blank | string | SMS vendor |
| `sms.dlt_required` / `sms.default_header` | true / BMPLTX | bool / string | DLT checks, header |
| `sms.otp_ttl_seconds` / `sms.otp_max_per_15min` | 300 / 3 | int | OTP |
| `whatsapp.driver` / `whatsapp.session_hours` | sandbox / 24 | string / int | WhatsApp |
| `whatsapp.webhook_secret` | blank | encrypted | legacy WhatsApp webhook secret |
| `telephony.driver` / `telephony.mask` / `telephony.recording_grace_minutes` | sandbox / true / 30 | | telephony |
| `comms.promo_window` | 10:00-18:00 | string | promotional send window |
| `comms.webhook_secret` | blank | encrypted | HMAC for `/api/webhooks/comms/*` |
| `comms.max_attempts` | 3 | int | outbox retries |
| `templates.strict_locale` | false | bool | fail instead of falling back to en-IN |
| `pricing.insurance.od_discount_pct` | 30 | int | insurance OD discount applied in every snapshot (DEC-080) |
| `pricing.insurance.gst_pct` | 18 | int | GST on insurance OD, TP and add-ons |
| `pricing.insurance.goods_tp_gst_pct` | 12 | int | GST on insurance TP for Goods permits |
| `pricing.rto.round_up_to` | 1000 | int | the RTO tax base (ESR / BH base) is rounded up to this (₹) |
| `pricing.dealer_charges.include_cod` | false | bool | add COD charges to the default on-road total; applies at the next Calculate & Publish (snapshots are frozen) |
| `account.can_change_display_name` / `account.can_change_photo` / `account.can_change_password` | true | bool | My Account self-service switches (403 + "managed by your administrator" when off) |
| `account.password_min_length` | 8 | int | minimum password length (never below 8; letters + numbers always) |
| `account.password_require_mixed_case` / `account.password_require_symbols` | false | bool | extra password rules |
| `account.password_expiry_days` | 0 | int | password expires after N days (0 = never); `EnforcePasswordExpiry` sends every admin screen to My Account (JSON 403 `PASSWORD_EXPIRED`); age from `users.password_changed_at`, else the account's creation; off when users may not change passwords (N4) |
| `account.password_history_count` | 0 | int | a new password may not repeat the last N (`xlr8_iam_password_history`; N4) |
| `security.idle_logout_minutes` | 0 | int | sign out after this many idle minutes (0 = off); server-enforced (`EnforceIdleSession`), warning first (DEC-084) |
| `security.idle_lock_minutes` | 0 | int | lock the screen after this many idle minutes (0 = off) |
| `security.idle_warning_seconds` | 60 | int | warning before the automatic sign-out |
| `security.screen_lock_enabled` | true | bool | "Lock screen" in the user menu |
| `security.unlock_max_attempts` | 5 | int | wrong unlock passwords before sign-out |
| `security.csp_mode` | report | string | Content Security Policy: off / report / enforce (`SecurityHeaders`) |
| `security.admin_login_max_attempts` / `security.admin_login_lock_minutes` | 5 / 1 | int | admin sign-in lockout (`Admin\Account\AdminLoginController`, bound over Backpack's login; N4) |
| `security.app_otp_expiry_minutes` | 10 | int | mobile-app OTP validity (`AuthService`; N4) |
| `security.app_otp_max_requests` / `security.app_otp_request_window_minutes` | 5 / 15 | int | OTP requests per window |
| `security.app_otp_max_attempts` / `security.app_otp_attempt_window_minutes` | 5 / 15 | int | wrong OTPs before the account locks, counting window |
| `security.app_lock_minutes` | 30 | int | app account lock length |
| `security.app_device_limit` | 5 | int | devices a user may be signed in on |
| `branding.logo` | '' | image | site logo (DEC-083): admin header / sidebar (links to the dashboard), login page, quotation / OTF prints; upload on Settings (`utils.settings.image`); blank = the built-in images (`site_logo_url($fallback)`) |
| `pricing.last_updated_at` | '' | string | ISO-8601 stamp set automatically (`PricingSyncStamp`, DEC-083) on any published-price, vehicle master or accessory change; the app re-syncs offline data when it moves (served by `v1/settings/category/pricing`) |
| `dealership.*` | name 'Bikaner Motors', legal_name 'Bikaner Motors Private Limited', tagline '', url 'https://www.BikanerMotors.com', favicon (image), address, email, phone, gstin | string / image | Site tab (DEC-091): title `site_title()`, footer link + tagline, favicon in `header_metas`, legal name on prints (`dealership()`) |
| `branding.menu_logo` | 'logo' | string | menu brand: `logo` (image) or `text` (dealership name) |
| `display.date_format`, `display.time_format` | 'd-M-Y', 'H:i' | string | site date & time formats (`site.name` / `site.slogan` are superseded and hidden) |
| `comms.enabled.{mail,sms,whatsapp,push}` | true | bool | global channel switches (enforced in W13 Phase 3) |
| `mail.smtp.{host,port,encryption,username,password,from_address,from_name}`, `mail.signature` | '', 587, 'tls', '', '' (encrypted), '', '', '' | string / int / encrypted | SMTP used by the mail service and the signature added to every mail (W13 Phase 3; blank host = .env) |
| `ui.appearance_enabled` | true | bool | show the Appearance panel (W13 Phase 5) |
| `account.can_change_{email,mobile,aadhaar,pan,dob,doj,marital_status,gender}` | false | bool | profile fields users may change (W13 Phase 5) |
| `feature.live_chat`, `feature.notifications` | true | bool | module switches |
| `scope.enabled` | true | bool | user data scoping master switch (DEC-071) |
| `scope.unassigned_rows` | visible | string | `visible` / `hidden`: rows with an empty scope code for scoped users |

## 6. Permissions (`UTL_*`, minted by `2026_09_28_100000_platform_permissions`)
| Permission | Grants |
|---|---|
| `UTL_SETTINGS_MANAGE` | open and change every Settings tab (DEC-091; `UTL_SETTINGS_VIEW` no longer opens the screen) |
| `PRC_WKFL_MANAGE` | also opens the Settings → Pricing tab (DEC-091) |
| `UTL_NOTY_BROADCAST` | reserved for a broadcast screen (not used by any screen yet) |
| `UTL_CHAT_MODERATE` | read internal remarks, delete anyone's remark, read any conversation |
| `UTL_DOCS_VIEW` / `UTL_DOCS_UPLOAD` / `UTL_DOCS_MANAGE` | browse library / upload / manage and delete any document |
| `UTL_TASK_VIEW` / `UTL_TASK_CREATE` / `UTL_TASK_ADMIN` | tasks screens / create / see any task |
| `UTL_TCKT_VIEW` / `UTL_TCKT_CREATE` / `UTL_TCKT_DESK` / `UTL_TCKT_REPORT` | tickets / open / service desk / report |
| `UTL_APPR_VIEW` / `UTL_APPR_REQUEST` / `UTL_APPR_ADMIN` / `UTL_APPR_REPORT` | approvals inbox / raise by hand / topics, rules, import, simulate, close any / report |
| `UTL_TPL_VIEW` / `UTL_TPL_EDIT` / `UTL_TPL_ACTIVATE` | templates list / drafts, submit, import / approve directly and activate |
| `UTL_COMM_VIEW` / `UTL_COMM_SEND` | outbox and sandbox, all calls / resend, send panel |
| `UTL_COMM_SMS_RAW` | send non-template SMS (internal only) |
| `UTL_COMM_WA_INBOX` | WhatsApp agent inbox |
| `UTL_COMM_CALL` / `UTL_COMM_RECORDING_DOWNLOAD` | click-to-call and own call log / download recordings |

Record access (who may read a record's chat and attached files) is **not** a `UTL_*` permission. It comes from the
entity's `permission` in `config/platform.php`, or from the model's `chatCanView()`.

**Support requests (DEC-094, W16e):** `UTL_SUPP_ADMIN` (receives new support tickets; sees and manages every `SUP_*`
ticket like the desk; sees all diagnostics), `UTL_SUPP_EXEC` (the only assignees a support ticket takes). Diagnostics
are for the support team only (owner, assignees, snoopers, support admins) — never the requester — minted by
`2026_10_03_004646_create_support_requests_dec094`, superadmin only. Settings `help.usage_retention_days` (180), `support.pane_requests` (true — the pane's "Still need help?"), `support.bundle_retention_days` (90),
`support.max_screenshot_kb` (4096).

## 7. Webhooks (`POST /api/webhooks/comms/{channel}`)
HMAC-SHA256 of the raw body with `comms.webhook_secret` in header `X-Signature`. Idempotent on `event_id` (and on the
vendor message / call id). Responses: `401` bad signature, `200` with `duplicate: true` on a repeat, `404` unknown
message. Payload shapes: email in guide 10, SMS in 11, WhatsApp in 12, telephony in 13.

## 8. Privacy: masking KYC copies in history (D26, DEC-095 #19)
`App\Services\Platform\Privacy\KycHistoryMaskingService` + command `privacy:mask-kyc-history`.
- `run(bool $apply): array{cells, values{aadhaar, pan, other}, tables, applied}` — scans `xlr8_utils_comm_thread.extra_data`
  and `audits.old_values` / `new_values`; masks by key (`adhar_no` / `aadhaar_no` / `aadhar_no` → `XXXXXXXX` + last 4
  digits, spaces / dashes allowed; `pan_no` → `XXXXXX` + last 4), at any depth and inside JSON stored as a string.
  Other numbers (TRC, application, account, GSTIN) are never touched; malformed values are counted as `other`.
- `--apply` keeps each changed cell's original in `xlr8_privacy_kyc_mask_backup` (`KycMaskBackup`), encrypted with
  the app key (restore needs the same `APP_KEY`), then writes with base-query updates (no events, no new audit rows).
- `restore(): array{restored, failed}` / `--restore` puts every original back and removes its backup row; a backup that
  cannot be decrypted (other `APP_KEY`) stays and is counted as failed (the command then exits 1). A second `--apply`
  finds nothing.
- The KYC record itself (booking `adhar_no` / `pan_no`) is not masked. New timeline entries are masked at source
  (BUG-195). Run on UAT / production only with the owner's approval.
- Local 02-10: 25 cells (24 Aadhaar, 20 PAN values) masked on `xlrm` and `xlrm_testing`. Test `KycHistoryMaskingTest`.
