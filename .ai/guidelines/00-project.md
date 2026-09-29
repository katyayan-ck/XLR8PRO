# Xceler8 (XLRM) — project context

**What:** BMPL's dealership management system: enquiries → quotations → bookings (KYC, DMS, finance, insurance, RTO,
exchange, delivery, refunds, OTF) → accounts, plus vehicle master & pricing, org/HR/IAM, spares, platform utilities.

**Track A — this repo:** live app for UAT. Laravel 12, PHP 8.4, Backpack 7 (Tabler, no PRO), MySQL 8.4, Spatie
permission/medialibrary, Sanctum API (mobile app). **Do not add Filament here.** Track B (`D:\laragon\www\xceler8`,
Laravel 13 + Filament 5) is the rebuild; this app's data migrates to it at switch-over (DEC-001).

**Golden rules**
1. Read the real file before changing it; grep before writing new logic (SSOT services: `.ai/rules/services.md`).
2. Controllers thin (validate → one service → respond); services own logic; models own data access.
3. Business keys are codes (`person_code`, `branch_code`, `segment_code`…), not integer FKs.
4. Lookups via `KeywordValueService` / `OrgService` (cached), never direct KeyValue/org queries.
5. Never guess a business rule or override a locked spec — stop and ask (`tech-guides/frs-and-workflows/`).
6. Log every decision in `docs/decisions/decision-log.md` (DEC-NNN) **before** the change.
7. Tests run on `xlrm_testing`, never on `xlrm`.

**Load context on demand, not up front:** `tech-guides/README.md` maps each task to the few files to read (project card,
module cards → module guide → FRS section, workflow cards). Live state: `.ai/state/handoff.md`. Open bugs:
`.ai/state/bugs-index.md`. Rules in `.ai/rules/` load automatically by path; skills load by description. Never read
`_backup/` (superseded) and never load `docs/changelog.md` whole (grep it by date or id).
