# Xceler8 (XLRM) — project context

**What:** BMPL's dealership management system (DMS): enquiries → quotations (with approvals) → bookings
(KYC, DMS, finance, insurance, RTO, exchange, delivery, refunds, OTF) → accounts, plus vehicle master &
pricing, org/HR/IAM, spares, and shared platform utilities.

**Two tracks (see `docs/decisions/decision-log.md` DEC-001):**
- **Track A — this repo (`xlrm`)**: live app, stabilised for UAT. Laravel 12, PHP 8.4, Backpack 7
  (Tabler, no PRO), MySQL 8.4, Spatie permission/medialibrary, Sanctum API (mobile app consumer).
  **Do not add Filament here.**
- **Track B — `D:\laragon\www\xceler8`**: greenfield rebuild (Laravel 13, Filament 5, modular monolith)
  with an ETL toolkit so this app's data migrates at switch-over.

**Golden rules**
1. Read the real file before changing it; match existing contracts. Grep before writing new logic —
   the SSOT services are listed in `.ai/rules/services.md`.
2. Models own data access, Services own business logic, Controllers stay thin (validate → service → respond).
3. Business keys are code-based (`person_code`, `branch_code`, `segment_code`…), not integer FKs.
4. Never query KeyValue/org tables directly — use `KeywordValueService` / `OrgService` (cached).
5. Never guess a business rule or override a locked spec — stop and ask. Specs: `.ai/knowledge/specs/index.md`.
6. Every decision is logged in `docs/decisions/decision-log.md` (DEC-NNN) **before** the change.
7. Tests never touch `xlrm` — they run on `xlrm_testing` (`php artisan testing:refresh-db`).

**Where context lives** (load only what the task needs — see `.ai/README.md`):
`.ai/rules/` (auto-loaded by path) · `.ai/skills/` (on demand) · `.ai/knowledge/` (read when linked,
incl. generated DB schema cards in `knowledge/db/`) · `.ai/state/current.md` (active work) ·
`.ai/state/bugs-index.md` (open bugs; full tracker `docs/refactor/known-bugs-report.md` is grep-only).
