# Decisions — one line each (generated from `docs/decisions/decision-log.md`)

Every DEC-NNN with its date, track and title. Read the full entry (why, the decision, risk, reversal) by grepping
`### DEC-NNN` in the decision log. Regenerate this list when you add a decision (the heading format is
`### DEC-NNN | DD-MM-YYYY | track | title`).

| DEC | Date | Track | Decision |
|---|---|---|---|
| 001 | 26-09-2026 | Programme | Two-track delivery |
| 002 | 26-09-2026 | Track B | UI stack = Filament 5 + Livewire 4 |
| 003 | 26-09-2026 | Track A | Full DB normalisation, manifest-driven, with automated code fixing |
| 004 | 26-09-2026 | Both | API v1 contract preserved; v2 alongside |
| 005 | 26-09-2026 | Both | PHP 8.4; technology chosen on merit (no downgrades) |
| 006 | 26-09-2026 | Programme | Postponed until after pilot/UAT |
| 007 | 26-09-2026 | Programme | Execution protocol |
| 008 | 26-09-2026 | A0 | Commit the current tree on `stage` as-is, then branch `feature/integrations` |
| 009 | 26-09-2026 | A0 | `gscreds.json`: untrack now, keep the local file, purge history only with approval |
| 010 | 26-09-2026 | A0 | Fix wrong command class in `bootstrap/app.php` |
| 011 | 26-09-2026 | A0 | Test DB = local full copy `xlrm_testing` (schema + data), refreshed by a script |
| 012 | 26-09-2026 | A0 | API auth fixes |
| 013 | 26-09-2026 | A0 | Roles model: `IAM\Role` uses the configured Spatie roles table (designation) |
| 014 | 26-09-2026 | A0 | Install PHP 8.4 x64 TS into Laragon and switch; keep 8.3.30 for rollback |
| 015 | 26-09-2026 | Track B | Greenfield project at `D:\laragon\www\xceler8`, new git repo, no remote yet |
| 016 | 26-09-2026 | A0 | Admin settings API gated by permission `UTL_SETTINGS_MANAGE`, not `role:admin|super_admin` |
| 017 | 26-09-2026 | A0 | Remove controller-level `$this->middleware()` calls (BUG-159) |
| 018 | 26-09-2026 | A0 | Roles = designations: remove the duplicate Role screen; fix hard-coded `xlr8_iam_roles` |
| 019 | 26-09-2026 | A0 | PHP 8.4.26 installed side by side; the switch happens from Laragon's menu; composer constraint unchanged for now |
| 020 | 26-09-2026 | A0 | Dead routes: remove unreachable ones, implement the ones with ready service methods, ask for business-rule ones |
| 021 | 26-09-2026 | A0 | `SystemSetting`: add the missing topic/group methods; topic = `topic` column or key prefix |
| 022 | 26-09-2026 | A0 | BUG-160: grant `admin.dashboard` to every designation (role), via an idempotent seeder |
| 023 | 26-09-2026 | A0 | Hide Quotation → Pending and Enquiry → Erroneous until Track B |
| 024 | 26-09-2026 | A0 | Implement booking refund-details edit (`editRefund`) |
| 025 | 26-09-2026 | A0 | BUG-104: production also lacks the 7 booking columns; add them via migration (types reviewed with the user before running) |
| 026 | 26-09-2026 | A0 | Retire WAMP/XAMPP from PATH; Laragon PHP 8.4.26 becomes the CLI PHP |
| 027 | 26-09-2026 | A0 | BUG-104 follow-ups: VOTF branch from the linked enquiry; replace `dd()` in booking store |
| 028 | 26-09-2026 | Track B | Adopt support-utility requirements from `tech-guides/frs-and-workflows/frs/support-utilities-requirements.md` |
| 029 | 26-09-2026 | A / B | User decisions batch |
| 030 | 26-09-2026 | A1 | Dead-code purge (reference-checked; git history keeps everything) |
| 031 | 26-09-2026 | A (AI context) | Consolidate AI context into one canonical `.ai/`; archive the originals |
| 032 | 26-09-2026 | B0 | Track B foundation choices |
| 033 | 26-09-2026 | Programme | Track B paused; focus on Track A UAT |
| 034 | 26-09-2026 | A3 (UAT) | UAT scope and priorities (target: 3 days, with the booking team's merge) |
| 035 | 26-09-2026 | A3 (UAT) | User bulk importer = `import:users` (`StandaloneUsersImport`); fix identity corruption (BUG-162) |
| 036 | 26-09-2026 | A3 (UAT) | Web user import rebuilt on the fixed importer; export and history routes removed |
| 037 | 27-09-2026 | A3 (UAT) | Hide broken HR create/edit; hide out-of-scope broken menu links |
| 038 | 27-09-2026 | A3 (UAT) | Remove three dead in-scope links (org-demo, sub-segment brand AJAX, Price List) |
| 039 | 27-09-2026 | B2b (Track B, planning) | Ticket intake channels: staff UI + API now |
| 040 | 27-09-2026 | A3 (UAT) | User & RBAC export workbook that round-trips through the user importer |
| 041 | 27-09-2026 | A3 (UAT) | Merge the booking team's origin/stage with feature/integrations |
| 042 | 27-09-2026 | A3 (UAT) | Enable Backpack's guard-switch middleware (BUG-055) |
| 043 | 27-09-2026 | A3 (UAT) | Disable the 34 users that have no role (BUG-090/166) |
| 044 | 27-09-2026 | A1 (purge follow-up) | Remove dead code the 26-09 purge missed; fix or remove pivot-table relations (BUG-022/024/037/081/082/084/158) |
| 045 | 27-09-2026 | A0 (platform) | composer.json for PHP 8.4; trim unused packages; env-driven config/app.php |
| 046 | 27-09-2026 | A3 (UAT) | Timestamps stay IST; add an IT department |
| 047 | 27-09-2026 | A1 (purge follow-up) | Remove remaining dead IAM/legacy pieces (BUG-006/017/076); close stale tracker items |
| 048 | 27-09-2026 | A3 (UAT) | Vehicle masters: codes immutable on edit; variant = one row per colour (BUG-171/172) |
| 049 | 27-09-2026 | A3 (UAT) | Canonical code format: upper-case with hyphens (THAR-ROXX) |
| 050 | 27-09-2026 | A3 / project-wide | One field-rule set per entity, enforced by the entity service (SSOT); no correcting old data |
| 051 | 27-09-2026 | A3 (UAT) | Purge the local vehicle master data before a fresh import |
| 052 | 27-09-2026 | A3 (UAT) | Org masters on entity services (DEC-050 roll-out) |
| 053 | 27-09-2026 | A3 (UAT) | Person, contacts, addresses and banking on entity services (DEC-050 roll-out) |
| 054 | 27-09-2026 | A3 (UAT) | Employees, login accounts and data scopes on entity services (DEC-050 roll-out) |
| 055 | 27-09-2026 | A3 (UAT) | Keyword masters and values on entity services (DEC-050 roll-out); backstop transforms only changed attributes |
| 056 | 27-09-2026 | A3 (UAT) | Pricing rules on entity services (DEC-050 roll-out, pricing group 1 of 4) |
| 057 | 27-09-2026 | A3 (UAT) | Add-ons, discounts and dealer charges on entity services (pricing group 2 of 4) |
| 058 | 27-09-2026 | A3 (UAT) | Price-list vehicles and prices on entity services (pricing group 3 of 4) |
| 059 | 27-09-2026 | A3 | Seeders write through the entity services and are idempotent (DEC-050 roll-out, last group) |
| 060 | 28-09-2026 | A3 | Legacy helpers removed; every call goes through a service |
| 061 | 28-09-2026 | A (platform) | Platform core: Settings, Notify, Chat, Docs (FRS §1–4) |
| 062 | 28-09-2026 | A (platform) | Task and Ticket utilities (FRS §5–6) |
| 063 | 28-09-2026 | A (platform) | Approval engine, topic tree, power sheet and reports (FRS §7–8) |
| 064 | 28-09-2026 | A (platform) | Comms plane: Templates, outbox, Email / SMS / WhatsApp / Telephony (FRS §12–17) |
| 065 | 28-09-2026 | A (platform) | Platform integration: acceptance pack, keyword seeds, rules |
| 066 | 28-09-2026 | A (UI) | Project-wide UI standards and the shared UI layer |
| 067 | 28-09-2026 | A (UI) | Tabler-parity shell, theme settings, AG-Grid theming and the dev UI kit |
| 068 | 28-09-2026 | A (process) | Guides, stage merge, Sales/booking parity, branches in sync |
| 069 | 28-09-2026 | A (Sales / platform) | Booking proofs move into Docs; the rest of the "later" list |
| 070 | 28-09-2026 | A (all modules) | Bug-fix sprint, wave 1: fixes that need no owner decision |
| 071 | 28-09-2026 | A (IAM, all modules) | Automatic, hierarchical data scoping with a code-level opt-out |
| 072 | 28-09-2026 | A (IAM / UI) | My Account rebuild and a dynamic, permission- and scope-aware dashboard |
| 073 | 28-09-2026 | A (Vehicle pricing) | Pricing import process redesign — 11 steps, gate to on-demand getPricing |
| 074 | 28-09-2026 | A (Vehicle pricing) | BUG-199: purge and re-import the vehicle masters before the first DEC-073 run |
| 075 | 28-09-2026 | A (Vehicle pricing) | Vehicle Info (DEC-073 step 3): value formats and import rules |
| 076 | 28-09-2026 | A (Vehicle pricing) | Price import (DEC-073 step 4): which columns are the price, and the write rules |
| 077 | 28-09-2026 | A (Vehicle pricing) | Add-ons & discounts (DEC-073 step 5): workbook shape and import rules |
| 078 | 28-09-2026 | A (Vehicle pricing) | Insurance & RTO (DEC-073 step 6): standalone workbooks, lossless storage |
| 079 | 29-09-2026 | A (Vehicle pricing) | Impact summary and hold check (DEC-073 steps 7–8) |
| 080 | 29-09-2026 | A (Vehicle pricing) | Calculate & Publish (DEC-073 step 9): on-road rules and the snapshot contract |
| 081 | 29-09-2026 | A (Vehicle pricing) | getPricing (DEC-073 step 11) and the standalone Price List screens |
| 082 | 29-09-2026 | A (Sales / pricing) | Quotation on getPricing (DEC-073 phase 11): no mock prices, hold + server re-validation |
| 083 | 29-09-2026 | A (Pricing admin) | Pricing masters with CRUD + import/export, auto recalculation, pricing sync stamp, configurable logo |
| 084 | 29-09-2026 | A (IAM / platform) | Security baseline: idle auto-logout, screen lock, security headers (go-live to-do S1, S2, S4, S10) |
| 085 | 29-09-2026 | A (platform / API) | One API error envelope for every exception; module-wise error messages in a language file (go-live to-do U7) |
| 086 | 29-09-2026 | A (docs / AI context) | Repository docs and AI-context clean-up (go-live to-do 10c) |

**Standing locked points:** the approval engine follows FRS v1.1 §7–8 (parallel counter-offers, highest level wins,
approvers never reject); the Vehicle Pricing Machine Spec v3.1.1 is locked (GAP-01…11 open until instructed; §3.3
amended by DEC-073).

Open decisions: ticket intake channels (SUP-DEC-003, needs explanation), SLA targets/calendars, retention,
approval scope dimensions (company/zone/state/desk), gscreds.json history purge. The owner decisions D1–D29 and the new ones: `docs/todo.md` §3.
