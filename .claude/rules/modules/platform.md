---
description: Platform utilities (Settings, Notify, Chat, Docs, Task, Ticket, Approval, Templates, Email/SMS/WhatsApp/Telephony). Load for any work that notifies, comments, stores files, raises tasks/tickets/approvals or sends messages.
paths:
  - app/Services/Platform/**
  - app/Support/Facades/**
  - app/Support/Result.php
  - app/Models/Approval/**
  - app/Models/Comms/**
  - app/Models/Utilities/**
  - app/Models/Traits/HasCommunications.php
  - app/Models/Traits/HasDocuments.php
  - app/Http/Controllers/Admin/Utils/Platform/**
  - app/Http/Controllers/Api/CommsWebhookController.php
  - app/Jobs/Platform/**
  - app/Events/Platform/**
  - config/platform.php
  - routes/backpack/utils.php
  - resources/views/components/**
  - resources/views/admin/utils/platform/**
---

# Platform utilities (FRS v1.1, DEC-061..065)

Spec: `docs/refactor/Platform-Utilities-FRS.md`. Modules **consume** these services; they never re-implement
notify, chat, docs, tasks, tickets, settings, approval or messaging, and never add a table for them.

## Laws (every service)
- One service is the only writer of its tables; call it through its facade (`Settings`, `Notify`, `Chat`, `Docs`,
  `Task`, `Ticket`, `Approval`, `Topics`, `Rules`, `Templates`, `Email`, `Sms`, `WhatsApp`, `Telephony`).
- Results are `App\Support\Result` (`ok`, `code`, `message`, `data`) — check `->ok`, show `->message`; never
  expect exceptions for business failures.
- State change order: persist → `Chat::event` → `Notify` → domain event (`App\Events\Platform\*`).
- Entity types are codes in `config('platform.entities')` (model, deep link, view permission). A new record type
  that needs chat/docs/notify deep links = one row there. Record access = `ChatService::canView()`
  (model `chatCanView()` or the entity permission).
- Enums come from KeyValue (`TASK_TYPE`, `TASK_PRIORITY`, `TICKET_CATEGORY`, `TICKET_PRIORITY`, `CALL_DISPOSITION`,
  `ENTITY_ACTIONS`) or Settings — no magic strings in callers.

## Traps
- Settings keys are dotted (`sla.ticket.p1_hours`): read seeds by exact key (`config('platform.settings')[$key]`),
  never `config("platform.settings.{$key}")`. A declared seed beats a caller's fallback.
- Approvals: callers only `open/counter/effective/reviseAsk/close`; never compute "who is next". Authority is
  the request snapshot; rule changes never touch open requests. Topics/rules are written through
  `Entities\Approval{Topic,Rule}Service` (DEC-050); authority comes from the power sheet, never from code.
- Messaging: customer-facing copy only from ACTIVE templates; `Mail::` and vendor SDKs only inside
  `Services\Platform\Comms\Drivers\*`; every send writes `xlr8_comm_outbox` first; OTP codes are never stored
  or logged (`Sms::otp/verify`). Local `.env` mail is a real SMTP host — test with `mail.driver=log`
  or `Mail::fake()`, or set `mail.redirect_to`.
- Webhooks `/api/webhooks/comms/{channel}` are HMAC-signed (`comms.webhook_secret`) and idempotent on `event_id`.
- Docs: record-attached files follow the record's access; library files without entitlements follow
  `UTL_DOCS_VIEW`; `DocsService::canView()` is the only visibility check.
- Legacy adapters (`EntityHistoryService`, `NotificationService`, `DocService`) exist only for the mobile v1 API.

## Permissions (module UTL)
Everyday (all designations): `UTL_DOCS_VIEW/UPLOAD`, `UTL_TASK_VIEW/CREATE`, `UTL_TCKT_VIEW/CREATE`,
`UTL_APPR_VIEW/REQUEST`. Admin: `UTL_SETTINGS_*`, `UTL_NOTY_BROADCAST`, `UTL_CHAT_MODERATE`, `UTL_DOCS_MANAGE`,
`UTL_TASK_ADMIN`, `UTL_TCKT_DESK/REPORT`, `UTL_APPR_ADMIN/REPORT`, `UTL_TPL_VIEW/EDIT/ACTIVATE`,
`UTL_COMM_VIEW/SEND/SMS_RAW/WA_INBOX/CALL/RECORDING_DOWNLOAD`.

## Tests
`tests/Feature/Platform/*` (acceptance pack FRS §11 + per-service). Fixtures pick real users from the
`xlrm_testing` copy (`Concerns\PlatformFixtures`). After a new migration, migrate the copy in place:
`DB_DATABASE=xlrm_testing php artisan migrate`. Do **not** `testing:refresh-db` while the local vehicle masters
are purged (DEC-051) — it copies the empty tables over the test data; if it happened, reload the five vehicle
tables into `xlrm_testing` from `storage/app/backups/xlrm-vehicle-masters-pre-purge-27-09-2026.sql`.
