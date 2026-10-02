# Xceler8 (XLRM, Track A): to-do and accomplishments

One file for **what is left** (the go-live to-do, with status and priority) and **what was done** (accomplishments,
newest last) — DEC-086. The live hand-over for the next session is `.ai/state/handoff.md`; bugs are in
`docs/bugs/open.md` / `closed.md`; every change is in `docs/changelog.md`; decisions in
`docs/decisions/decision-log.md`.

**Keeping it current (`.ai/guidelines/10-workflow.md`):** when an item moves, update its row here in the same commit;
when a task is **completed**, append an accomplishment entry at the end of *Accomplishments* under today's date (what and
why with the to-do / DEC / BUG ids, files / routes / settings / migrations, how it was verified, what is left).

**Contents:** [Part 1 — Go-live to-do](#part-1--go-live-to-do) · [Part 2 — Accomplishments](#part-2--accomplishments)

---

# Part 1 — Go-live to-do

**Date:** 29-09-2026. **Branch:** `dev/admin` (nothing pushed since the 28-09 stage merge).

**Sources:**
- decisions DEC-024 … DEC-083;
- changelogs 22-09 → 29-09;
- the bug tracker (now `docs/bugs/open.md` / `closed.md`);
- the owner decisions D1–D29 (§3);
- a code / DB audit on 29-09 (commands and counts are cited in each row).

**Status key:**

| Mark | Meaning |
|---|---|
| ✅ | Done |
| 🟡 | Partial / in progress |
| 🔴 | Not started |
| ⏸ | Waiting on an owner decision |
| 🧪 | Built; needs UAT / browser check |

**Priority key:**

| Priority | Meaning |
|---|---|
| **P0** | Blocks go-live |
| **P1** | Needed before go-live |
| **P2** | Soon after go-live |
| **P3** | Enhancement |

---

## 1. Where we are (last 7 days)

| Area | Status | Summary |
|---|---|---|
| Codebase hygiene | ✅ | Dead code and helpers purged (DEC-030, 044, 047, 060); PHP 8.4 / composer trimmed (DEC-045) |
| Entity services (one write path per entity) | ✅ | Org, Person, Employee, User, Scope, Keywords, Vehicle, Pricing, Accessories, Insurance masters (DEC-050…059, 083) |
| Platform utilities | ✅ | Settings, Notify, Chat, Docs, Task, Ticket, Approvals, Templates, Email / SMS / WhatsApp / Telephony (DEC-061…065). **SMS / WA / telephony are sandbox drivers only.** |
| UI layer | 🟡 | Shared UI kit, Tabler theme, AG-Grid theming (DEC-066…069). About 250 hex / inline-style violations remain outside Sales (`ui-design-progress.md`). |
| Data scoping | ✅ | Automatic hierarchical scope (DEC-071); 24,070 enquiries backfilled locally |
| My Account + dashboard | ✅ | DEC-072 |
| Pricing redesign | ✅ 🧪 | 11-step process, snapshots, getPricing API, Price List, quotation on published prices, masters with CRUD / import / export, auto recalculation, sync stamp, logo (DEC-073…083) |
| Bug sprint | 🟡 | Wave 1 done (DEC-070); **27 bugs open**, most waiting on D1–D29 |
| Tests | 🟡 | 449 tests (448 pass, 1 known skip). Sales / booking are thin: 1 unit file (see §9). |
| Track B (Laravel 13 / Filament rebuild) | ⏸ | Paused since DEC-033 |

---

## 2. NEW — Central formats, data dictionary and permission naming (your request, 29-09)

**Goal:** one SSOT for the format of every code, key and value (permissions, module / process / activity codes,
entity business keys, keyword codes, setting keys, route names, error codes). Every write goes through that SSOT's
validation. The permissions move to a lowercase dotted convention `{module}.{process}.{activity}`, e.g.
`sls.bkng.cr`.

**Note:** Laravel itself doesn't mandate a permission format. Lowercase dot notation is the common community
convention and matches our route names (`module.process.activity`), so it is a good choice.

**Audit on 29-09 (why this matters):**
- **Permissions:** 275 rows.
  - 168 `SLS_BKNG_VIEW` style, 103 legacy dotted (`branch.create`, still holding **850 role grants**), and 4 others
    (`*`, `FIN_IMPORT`, `INS_IMPORT`, `RTO_IMPORT`).
  - 223 distinct codes are referenced in **94 PHP / Blade files**.
- **IAM:** 15 modules, including junk `DEMO_MODULE`, `DEMOMODULE2`, `LEGACY`; 58 processes.
- **Keyword masters:** 133 with mixed styles and near-duplicates (`BODY_MAKE` and `BODY-MAKE`, `CUSTOM-MODEL`,
  `CHKLISTREC`); 5,459 values.
- **Mobile API:** `/api/v1/auth/me` returns permission names, so renaming them is an **API contract change**
  (DEC-004: v1 must stay backward compatible).

| # | Step | Status | Detail |
|---|---|---|---|
| F1 | Inventory every code family | ✅ P1 (29-09) | See the inventory list below the table. Each family gets its current formats, counts and offenders (a script, like the audit above). |
| F2 | **Decide the format per family** | ⏸ P1 — proposals + 5 questions in `tech-guides/architecture/data-dictionary-draft.md` | See the proposal below the table. Some choices need you. |
| F3 | **Data dictionary** | 🟡 P1 — DRAFT written, awaiting sign-off | `tech-guides/architecture/data-dictionary.md` + a generated JSON. Per family: format regex, examples, owner, where it is stored, where it is used; per module: process list, activity list. Reviewed and signed off by you before any change. |
| F4 | Central SSOT in code | 🔴 P1 | See the SSOT pieces below the table. |
| F5 | Permission migration plan | 🔴 P1 | See the migration steps below the table. |
| F6 | Keyword / code clean-up | ⏸ P2 | Merge duplicates (`BODY_MAKE` / `BODY-MAKE`), fix non-conforming codes through `KeyvalueService`, with a mapping table; no data rewritten without your approval (DEC-050: no correcting old data silently) |
| F7 | Guard rails | 🔴 P1 | See the guard rails below the table. |

**F1 — the code families to inventory:**
- permissions;
- IAM module / process codes;
- activities;
- designation codes;
- branch / location / department / division / vertical codes;
- `person_code` / `employee_code`;
- segment / sub-segment / model / variant codes;
- keyword master + value codes;
- setting keys;
- route names;
- `ErrorCodeEnum` / Result codes;
- entity type codes (`config/platform.entities`);
- document / approval topic codes;
- sheet / field codes of the pricing registry.

**F2 — proposal for the format decisions:**
- **Permission:** `^[a-z]{2,5}\.[a-z0-9]{2,8}\.[a-z_]{2,12}$`.
- **Activity dictionary:** `vw` view, `cr` create, `ed` edit, `dl` delete, `ex` export, `im` import, `ap` approve,
  `mg` manage. Or should activities be full words (`view`)? You chose `cr`, so short forms are assumed; confirm the
  list.
- **Module / process codes:** upper-case alphanumeric, 2–5 / 2–8 characters.
- **Keyword codes:** `UPPER_SNAKE`.
- **Business codes:** upper-case, hyphenated (DEC-049).
- **Setting keys:** lowercase dotted.

**F4 — the SSOT pieces in code:**
- `App\Support\Formats\CodeFormat` registry (family → regex, normaliser, example, message).
- `Field::code()` accepts a family (`Field::code('code', 30)->family('keyword')`).
- A validation rule `App\Rules\CodeFormat($family)`.
- An artisan `formats:audit` command that reports offenders.
- The IAM permission / process / module screens and every entity service validate through it.

**F5 — permission migration steps:**
1. A mapping table `old → new` (the legacy dotted ones mapped to their `{MOD}_{PROC}_{ACT}` twin or retired).
2. A migration that renames the rows in place (ids keep, so role grants survive) and merges duplicates.
3. Update the 94 files.
4. Update `.ai/rules/admin-backpack.md`.
5. Flush the permission cache.
6. **API compatibility:** `/auth/me` returns the old names as well until the app switches (DEC-004).
7. Full suite + smoke as a scoped user.

**F7 — guard rails:**
- a test that fails on any code / permission literal not in the registry;
- pint / phpstan rule (a grep in CI);
- the `record-rule` in `.ai/rules`.

---

## 3. Owner decisions pending (D1–D29, from the 28-09 triage) and new ones

| # | Decision | Priority | Blocks |
|---|---|---|---|
| D1 | Repair mobile OTP login: `users` has no `mobile`, so the app gets nulls (BUG-187). Same fix covers `sender.name` / `receiver.name` (null) in the notification / alert / message responses (found 30-09) — **✅ decided 02-10 (DEC-095): read from the person record → W18a** | **P0** | Mobile app login |
| D2 | `random_int` OTP instead of `rand()` (BUG-188) — **✅ decided 02-10 (DEC-095) → W18a** | **P0** | Security |
| D3 | v1 `docs/upload`, `history/{entityType}` accept any model class from input (BUG-182) — **✅ decided 02-10 (DEC-095): entity-type allowlist → W18a** | **P0** | Security |
| D4 | Lead lookups — **⏸ decided 02-10: after go-live** | P1 | Sales |
| D5–D12 | Deletions: Brand (BUG-009), ExportController (BUG-180), RBACService (BUG-190), Core graph models, getChassisNumbers (BUG-153), dead Org views (BUG-154), seeder test users, Booking scopes (BUG-191) | P1 | Clean-up |
| D13 | 52 dead menu links (BUG-056 / 062) — **✅ decided 02-10 (DEC-095): keep, show "coming soon" → done 02-10 (W18e)** | **P0** | UAT-visible |
| D14 | Import permissions (BUG-177) — **⏸ 02-10: later** | P1 | Access |
| D16 | Hard-coded user-id whitelists in booking (BUG-095) — **✅ decided 02-10 (DEC-095): permission → done 02-10 (BT-014)** | P1 | Access |
| D18 | Pricing | ✅ | Closed (BUG-178 fixed) |
| D19 | Accessory importer | ✅ | Closed (DEC-083) |
| D20 | RTO sheet id (BUG-029) | P1 | RTO import |
| D21 | BEV SO rule (BUG-101) — **✅ decided 02-10 (DEC-095): keep the rule, make it work → done 02-10 (BT-013)** | P2 | Booking |
| D23 | 5 booking reports 500 (BUG-122) — **✅ decided 02-10 (DEC-095): rewrite → W18f** | **P0** | Booking team |
| D24 | 36 employees on unknown designation codes (BUG-183) — **✅ decided 02-10 (DEC-095): no mapping — the user data is refreshed (W18j)** | P1 | Approvals routing |
| D25 | Variant code split (BUG-173) — **✅ decided 02-10 (DEC-095): codes with the colour suffix → W18i** | P1 | Booking ↔ pricing |
| D26 | Mask old KYC rows — **✅ decided 02-10 (DEC-095): mask → done 02-10 locally (W18h)** | P1 | Compliance |
| D28 | Spares module rebuild (BUG-031 / 032 / 116) — **⏸ decided 02-10: after go-live** | ⏸ | Spares (hidden) |
| D29 | Rotate the Google API key | **P0** | Security |
| N1 | **COD in on-road** (setting `pricing.dealer_charges.include_cod`, off today) | P1 | Pricing numbers |
| N2 | Stage merge — **local part done 30-09 (DEC-087):** `origin/stage` merged into `dev/admin` keeping both sides' work, DB aligned (DEC-088). **Left:** full suite on the final tip + delete the temp branch `merge/stage-30-09` (no decision needed, in progress); delete `backup/dev-admin-before-rewrite-30-09` once you confirm the history rewrite; **push `dev/admin` and merge it into `stage` — your call** — **⏸ 02-10: only on the owner's prompt** | **P0** | Deploy |
| N3 | Formats / permission naming (§2 F2) — **⏸ 02-10: F2 / F3 left open** | P1 | §2 |
| N4 | Security policy values (§4: idle minutes, lock, password rules, self-service rules) — **✅ decided 02-10 (DEC-095): values in Settings → W18k** | P1 | §4 |

---

## 4. Security, sessions and access (enterprise must-haves)

| # | Item | Status | Priority | Notes / evidence |
|---|---|---|---|---|
| S1 | **Auto-logout after inactivity** (web) | ✅ DEC-084 (off until `security.idle_logout_minutes` is set) | P0 | Today: `SESSION_LIFETIME=120`, no idle tracking. Build: setting `security.idle_logout_minutes`; client idle timer + a warning modal ("You'll be signed out in 60 s"); server-side last-activity check middleware (per-user / per-designation override via Settings scopes) |
| S2 | **Screen lock** | ✅ DEC-084 (menu item + `security.idle_lock_minutes`) | P1 | A "Lock screen" button and auto-lock after `security.idle_lock_minutes`: an overlay that blurs the page; unlock with password (or PIN); the session stays; failed unlocks count toward the lockout |
| S3 | API token expiry / rotation | 🔴 | P0 | `sanctum.expiration = null` (tokens never expire). Set an expiry + refresh flow; revoke on device removal (device sessions exist) |
| S4 | Login lockout + throttling | 🟡 throttle verified (5 / min, tested); persistent lock pending | P0 | Backpack throttles password recovery. Verify login throttling. Add an account lock after N failures (`xlr8_iam_account_lock` table exists), admin unlock, and an alert |
| S5 | Password policy | 🟡 min length / mixed case / symbols from Settings ✅ (DEC-084); expiry + history pending | P1 | My Account needs 8+ characters with letters and numbers. Add from settings: minimum length, complexity, expiry (days), history (no reuse of the last N), force change on first login / after an admin reset |
| S6 | 2-factor authentication for admins | 🔴 | P1 | TOTP (Authenticator app) or email / SMS OTP via the Comms plane; per-designation "2FA required" setting. **A new package (e.g. pragmarx/google2fa) needs your approval** |
| S7 | **Self-service profile controls from Settings** | 🟡 name / photo / password switches ✅ (DEC-084); email / mobile change with OTP waits on N4 | P1 | Settings `account.can_change_email`, `account.can_change_mobile`, `account.can_change_name`, `account.can_change_photo`, `account.change_requires_otp`, `account.change_requires_approval` (approval engine topic `IAM.PROFILE_CHANGE`). My Account obeys them; the admin can always change. Mobile / email changes verified by OTP to the new value and noted in Chat. |
| S8 | Forgot password / reset | 🧪 | P1 | Backpack recovery config exists; verify email delivery with the Comms plane; add an SMS OTP reset option |
| S9 | Active sessions / devices page | 🟡 | P2 | API devices exist; add web sessions (DB session driver) + "sign out everywhere" (password change already signs others out) |
| S10 | Security headers | ✅ DEC-084 (CSP report-only; enforce after UAT) | P1 | None set. Add middleware: HSTS, X-Frame-Options / frame-ancestors, X-Content-Type-Options, Referrer-Policy, a CSP (report-only first; CDN allow-list: jsdelivr, cdnjs, Google Fonts) |
| S11 | Production config hardening | 🔴 | P0 | `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS only, `LOG_LEVEL=warning`, `.env` not web-readable, `storage` / `bootstrap/cache` permissions, the Backpack registration off (already off outside local) |
| S12 | PII protection / DPDP Act 2023 — **incl. BUG-206: `person_code` = PAN / Aadhaar for 211 of 215 people** | 🟡 | **P0** | Aadhaar / PAN masked in history (BUG-195); mask old rows (D26); encrypt Aadhaar / PAN at rest; consent record; retention / erasure policy; export on request |
| S13 | Secrets rotation | ⏸ | P0 | Google key (D29); SMS / WA webhook secrets moved to encrypted settings ✅ |
| S14 | Audit log viewer | 🟡 | P2 | owen-it/auditing is used on 5 models; Settings has its own audit. Add an admin viewer (who changed what) + extend to the masters |
| S15 | Permission review with the business | ⏸ | P1 | 76 designations × 275 permissions; grant the new `PRC_*` master permissions (DEC-083); remove the `*` permission; RBAC matrix sign-off |
| S16 | Permission-cache rebuild takes about 10 s (BUG-198) | ✅ fixed 29-09 (9 queries / 312 ms) | P1 | Precompute / warm on deploy; Redis cache |

---

## 5. Pricing and vehicle master (after DEC-073…083)

| # | Item | Status | Priority | Notes |
|---|---|---|---|---|
| V1 | Run the full Pricing Process on the **real** environment and sign off numbers (3 vehicles by hand) | 🧪 | P0 | Verified locally (Scorpio-N matches) |
| V2 | Fix the reference sheets: CNG RTO rows (20 vehicles fail), Passenger insurance for more than 7 seats (12 fail) | ⏸ | P0 | Business sheet data |
| V3 | Browser check: quotation picker → load prices → save; Price List hover; masters CRUD / import | 🧪 | P0 | Server + JS syntax checked only |
| V4 | Queue worker in production (auto recalculation, imports, calculate) | 🔴 | **P0** | cPanel has no supervisor. Needs a cron `* * * * * php artisan schedule:run` plus `queue:work --stop-when-empty` every minute (or a VPS / supervisor). Without it nothing recalculates. |
| V5 | Grant the `PRC_*` master permissions to designations; upload the site logo | ⏸ | P1 | DEC-083 |
| V6 | Re-import the accessory workbook (fills the permits, BUG-204) | 🔴 | P1 | |
| V7 | Vehicle master purge + re-import before the first run (DEC-074 / BUG-199) | ⏸ | P0 | Needs the prod data plan (§8) |
| V8 | App sync: delta endpoints for vehicles / prices / accessories keyed on `pricing.last_updated_at` | 🔴 | P1 | The stamp exists; the app still has to pull everything |
| V9 | CSD / TZU / Electric price lists: business review of the columns | 🧪 | P1 | |
| V10 | Old RTO / insurance test-calculator screens: fold into the masters or retire | 🔴 | P3 | |

---

## 6. Sales (enquiry → quotation → booking → accounts)

The booking team owns it (DEC-034); these are the items we know of.

| # | Item | Status | Priority | Notes |
|---|---|---|---|---|
| SL1 | Booking reports 500 (BUG-122) | ⏸ | P0 | D23 |
| SL2 | Dead booking menu links (BUG-062 / 056) | ⏸ | P0 | D13 |
| SL3 | Sales flows don't call the platform utilities yet (Notify on stage change, approvals for discounts, templates for customer messages) | 🔴 | P1 | Engines are ready (DEC-061…065) |
| SL4 | Quotation approvals: parallel counter-offer engine (FRS §7–8) wired to the quotation discount gate | 🔴 | P1 | Engine ✅; wiring ❌ |
| SL5 | Hard-coded user-id whitelists (BUG-095) | ⏸ | P1 | D16 |
| SL6 | Booking controller carve-out (about 10.8k lines) into services | 🟡 | P2 | 10 booking services exist |
| SL7 | Variant code split between booking and pricing (BUG-173) | ⏸ | P1 | D25 |
| SL8 | Spares module (BUG-031 / 032 / 116) | ⏸ | P2 | Hidden; D28 |

---

## 7. Operations, deployment and infrastructure

| # | Item | Status | Priority | Notes / evidence |
|---|---|---|---|---|
| O1 | **CI gate before deploy** | 🔴 | P0 | `.github/workflows/deploy.yml` deploys on push with **no tests / pint / phpstan**. Add a test job (MySQL service), required before the deploy job; protect `main` / `uat` |
| O2 | Safer deploy | 🔴 | P0 | The server runs `git reset --hard` + `migrate --force` in place. Add a backup before migrate, `config:cache` / `route:cache` / `view:cache` / `event:cache`, `queue:restart`, a health check after `up`, and a rollback script |
| O3 | **Backups** | 🔴 | P0 | None configured. Daily DB dump + `storage/app/public` media, off-site (S3 / Drive), 30-day retention, **monthly restore drill**. A package (spatie/laravel-backup) needs approval, or cPanel backups + a script |
| O4 | Scheduler cron on each server | 🧪 | P0 | 4 scheduled jobs exist (docs purge, ticket SLA, auto-close, call recordings); verify the cPanel cron |
| O5 | Monitoring / alerting | 🔴 | P1 | Uptime check, failed-job alert (Notify to IT), disk / DB size, slow-query log, log daily rotation (`LOG_STACK=daily`, 14 days) |
| O6 | Error tracking | 🔴 | P1 | Sentry / Flare (package approval), or a mailed exception digest via Comms |
| O7 | Cache / session / queue on Redis in prod | 🟡 | P2 | App ready 03-10 (W18m: `.env` switch only, guide 16-reference §4); IT to provide Redis per environment |
| O8 | Environments parity (dev / uat / prod) + seeding of reference data | 🟡 | P1 | Settings seed pack ✅; keyword seeds ✅; permissions via migrations ✅ (DEC-083) |
| O9 | Go-live runbook + rollback plan + hypercare roster | 🔴 | P0 | Cut-over steps, data freeze, smoke list, who to call |
| O10 | Performance / load test (100 concurrent users, the grids with 60k enquiries) | 🔴 | P1 | Indexes on `*_code` (known wart), N+1 audit |

---

## 8. Data readiness

| # | Item | Status | Priority | Notes |
|---|---|---|---|---|
| DA1 | Production data migration plan (legacy → xlrm): order, reconciliation counts, sign-off | 🔴 | P0 | |
| DA2 | Vehicle masters re-import (colour-suffix codes, BUG-199) | ⏸ | P0 | DEC-074 |
| DA3 | Designation master mapping for 36 employees (BUG-183) | ⏸ | P1 | D24 |
| DA4 | 34 users without a role were disabled (DEC-043); confirm who gets access | 🟡 | P1 | |
| DA5 | Keyword duplicates / format clean-up | 🔴 | P2 | §2 F6 |
| DA6 | Junk IAM modules (`DEMO_MODULE`, `DEMOMODULE2`, `LEGACY`) | 🔴 | P1 | §2 F5 |

---

## 9. Quality

| # | Item | Status | Priority | Notes |
|---|---|---|---|---|
| Q1 | Sales / booking test coverage | 🔴 | P1 | 1 unit file for booking OTF; no feature tests for enquiry / quotation / booking flows |
| Q2 | Org / HR / IAM coverage | 🟡 | P2 | 3 Org + 2 IAM feature files |
| Q3 | Browser E2E (critical paths: login, enquiry → quote → booking, pricing process) | 🔴 | P2 | Dusk / Playwright not installed (package approval) |
| Q4 | Full-screen smoke sweep before each merge | ✅ | — | `--group=smoke` |
| Q5 | PHPStan baseline for legacy controllers (booking 441, quotation 123 errors) | 🔴 | P2 | New code is clean |
| Q6 | Accessibility + phone-width check of the main screens | 🟡 | P2 | UI kit enforces it; legacy views not all |
| Q7 | UI hex / inline style clean-up outside Sales (about 250) | 🟡 | P3 | `ui-design-progress.md` |

---

## 10. Additional features worth adding (enterprise polish)

| # | Feature | Priority | Why |
|---|---|---|---|
| X1 | Global search (enquiry no., mobile, chassis, booking, person) in the top bar, scope-aware | P2 | Daily speed for sales / CRM |
| X2 | Saved grid views / column layouts per user (AG Grid state) + export presets | P2 | Heavy list users |
| X3 | Notification preferences per user (in-app / email / WhatsApp / push, quiet hours) | P2 | Notify exists; preferences missing |
| X4 | In-app announcements / release notes ("What's new") | P3 | Change management |
| X5 | Help centre / contextual help per screen + user guides for each role | P1 | Training for go-live |
| X6 | Impersonate user (admin, audited, time-boxed) for support | P2 | Support desk |
| X7 | Maintenance banner + read-only mode switch from Settings | P2 | Safer cut-overs |
| X8 | Data export centre (queued exports with notification when ready) | P2 | Large exports block requests today |
| X9 | Report builder / scheduled reports by email | P3 | Management reporting |
| X10 | Approval inbox on mobile (API) | P2 | Managers approve on the go |
| X11 | SSO (Google Workspace) | P3 | Fewer passwords (package approval) |
| X12 | Hindi UI labels (lang files exist per module) | P3 | Field staff |
| X13 | Feature-flag screen for gradual roll-out per branch | P3 | `Settings` scopes support it |
| X14 | Duplicate-customer detection across enquiries / persons (mobile, PAN) | P2 | Data quality |
| X15 | Webhooks / integration API for DMS / OEM portals | P3 | Later integrations |

---

## 10b. UI / UX and platform standards (your request, 29-09 — standing rules in `.ai/rules/ui.md` / `app.md` / `api.md`)

| # | Item | Status | Priority | Plan |
|---|---|---|---|---|
| U1 | Standard form screens: same header style, minimal spacing, smaller font; every card collapsible + draggable (order remembered) with **required filled / total** in the header | 🟡 shared layer ✅ 29-09 (every form card with a header; compact density default); per-screen header conversion + a browser check left | P1 | See the U1 plan below the table. |
| U2 | Use the UI-kit elements for the best UX | 🟡 | P1 | Rule added; each screen converges when touched (`ui-design-progress.md` tracks the rest) |
| U3 | Font size + spacing / margin / padding controller | ✅ 29-09 | P1 | Settings `ui.density.text` / `ui.density.space` + the Appearance panel (`tech-guides/platform/ui-kit.md`) |
| U4 | Caching / optimisation, lazy loading | 🟡 lazy images ✅ 29-09 | P1 | See the U4 plan below the table. |
| U5 | Select2, flatpickr, badges, buttons, tabs, accordions wherever they fit | 🟡 | P1 | Rule added; the shared layer already upgrades native inputs; convert the dense legacy forms screen by screen |
| U6 | Guides updated on every change | ✅ rule | — | `.ai/rules/app.md` standing rule; the go-live wrap-up audits guides vs code |
| U7 | Central uniform error / response / exception handling with module-wise codes + messages | 🟡 API ✅ 29-09 (DEC-085: one envelope for every `api/*` exception, messages in `resources/lang/en/errors.php`); web flashes + `E002` rename left | P1 | See the U7 plan below the table. |
| U8 | Custom error pages (403 / 404 / 419 / 429 / 500 / 503) | ✅ 29-09 | P1 | Branded pages in `resources/views/errors/`: logo, plain message, a link to the dashboard, a reference id on 500 (no stack traces) |
| U9 | These instructions in the project rules for all agents | ✅ 29-09 | — |
| U10 | Changelog + task status + handoff with every commit; commented + formatted code | ✅ rule (`.ai/guidelines/10-workflow.md`) | — | `.ai/state/handoff.md` |
| U11 | **Module-wise API docs** (request, params, validation, every response + a Postman v2.1 collection per module) | ✅ 30-09 — every v1 module documented with Postman (index `tech-guides/api/index.md`) | P1 | `tech-guides/api/{module}.md` + `tech-guides/api/postman/`; the existing `tech-guides/api/*.md` are empty; start with pricing, auth, settings, docs / history, devices | `.ai/rules/ui.md`, `app.md`, `api.md` (synced to `.claude/rules`) |

**U1 plan:**
- One shared enhancement in `xl-ui.js` + `xl-ui.css` for every card inside a form: a collapse toggle, a drag handle
  (order saved per user + page), and a required-fields counter that updates live.
- A compact form density.
- A standard page-header partial.
- **Done 29-09:** the shared enhancement (collapse, drag + Alt+↑/↓, required badge, order / collapsed per screen, invalid
  fields open their card) and the compact density default. **Left:** screens whose cards have no header (most Backpack
  CRUD forms render one header-less card) get headers as they are converted; a real-browser check on the quotation /
  booking forms.

**U3 plan:**
- Site defaults in Settings (`ui.density.font_scale`, `ui.density.space_scale`).
- A per-user override in the Appearance panel (font size S / M / L, spacing compact / cozy / comfortable), applied as
  CSS variables on `<html>`.
- **Done 29-09** as `ui.density.text` (xs / sm / md / lg, default sm) and `ui.density.space` (compact / cozy /
  comfortable, default compact).

**U4 plan:**
- Images: `loading="lazy"` / `decoding="async"` everywhere (shared layer). ✅ 29-09
- Deploy: `config:cache` / `route:cache` / `view:cache` (O2).
- Cache: Redis (O7).
- Queries: settings / keyword / org caches ✅; add caches for the menu counts and the dashboard widgets ✅; review
  N+1 on the big lists.
- Grids load by AJAX ✅ (Price List, masters).
- Collapsed cards / tabs lazy-load their heavy content.
- HTTP: gzip / cache headers for static assets.

**U7 plan:**
1. Inventory every error path: `ErrorCodeEnum` (55 codes), Result codes per service, `abort()` messages, `Alert`
   flashes, try / catch in controllers.
2. A per-module code list (`{MODULE}_{NAME}`) with its messages in `resources/lang/en/errors.php` (customisable per
   module).
3. `withExceptions` renders:
   - the API: always the envelope;
   - the web: the branded pages + the flash for validation / business errors;
   - with a reference id logged.
4. Migrate controllers screen by screen; a test that fails on an unregistered code.
- **Done 29-09 (DEC-085, BUG-208):** steps 2 and 3 for the API — `App\Exceptions\ApiExceptionRenderer` in
  `withExceptions()->render()`, `BaseController::handleException` uses it, messages moved to the language file, four
  request-level codes added, the enum status map fixed, a test that fails when a code has no message. BUG-209 (settings
  API writes always 403) found and partly fixed.
- **Left:** the web side (admin flashes for business errors through the same codes), the `E002` rename (app team),
  the dead `app/Exceptions/Handler.php` was deleted on 30-09 (approved).

## 10c. Docs, AI-context and repository clean-up (your request, 29-09 — DEC-086)

| # | Item | Status | Priority | Plan |
|---|---|---|---|---|
| C1 | `tech-guides/`: every guide (architecture, modules, platform utilities, API, specs) consolidated, current, and summarised per module / feature, with a "what to load when" index so agents load only what a task needs | ✅ 29-09 (`tech-guides/README.md`; module cards; FRS, plans and workflow cards in `frs-and-workflows/`) | P1 | Module card (short) → detail guide → spec; `tech-guides/README.md` is the load map |
| C2 | AI-agent performance at minimum tokens (Claude Code, Codex): lean always-loaded files (`CLAUDE.md` / `AGENTS.md`), stable text for prompt caching, path-scoped rules, on-demand skills, unused skills removed, big / backup files blocked from reads | ✅ 29-09 (`CLAUDE.md` / `AGENTS.md` 21.1 → 12.4 KB; `config/boost.php` exclusions; `.claude/settings.json`) | P1 | `boost.json`, `.ai/guidelines`, `.claude/settings.json` |
| C3 | Bugs / known issues verified and split into `docs/bugs/open.md` and `docs/bugs/closed.md`; a fixed bug moves from open to closed with its full details | ✅ 29-09 (31 open verified, 182 closed; old audit verified, BUG-212 / 213 from it) | P1 | Verify every open entry and the old `docs/bugs/closed.md` audit against the code |
| C4 | One chronological changelog `docs/changelog.md` (all `ai-changelogs-*` + the root `changelog.md`) | ✅ 29-09 (findings of the same days included) | P1 | |
| C5 | One to-do + accomplishments file `docs/todo.md`, plus date-wise handoff / changelog / accomplishment files | ✅ 29-09 (`docs/daily/DD-MM-YYYY/`, kept in step with the cumulative files) | P1 | |
| C6 | Old context and superseded files moved to the git-ignored `_backup/` (kept on disk, out of the repo and out of agent searches) | ✅ 29-09 (removing `4c82d28`'s workbooks from history needs your approval) | P1 | Includes the pricing reference workbooks that were committed by mistake in `4c82d28` (not pushed) |
| C8 | Plans kept in the repo and current: `tech-guides/frs-and-workflows/plans/` (DEC-071, 072, 073, 083) with status headers + index; rule added | ✅ 29-09 | P1 | Update a plan's header when its work moves |
| C7 | The Laradocs site (`/docs`, `web` middleware only) served the whole `docs/` folder, including the bug tracker and the decision log | ✅ 30-09 (reads `tech-guides/`; admin login required — BUG-211) | P0 | Point it at `tech-guides/`; whether it needs a login is an owner decision (BUG-211) |

## 11. Deferred (after UAT)

- **Track B:** resume from xceler8 `d9009db`.
- **Framework / package upgrades:** Laravel 13, Excel 4, Permission 8, Firebase 8, PHPUnit 12 / 13, Swagger 11.
  Each is a major upgrade that needs your approval.

---

## 11b. Work order agreed 30-09 — items that need no owner decision first

Worked top to bottom; each finished item moves to Part 2 (Accomplishments) under its date.

| # | Item | Status |
|---|---|---|
| W1 | N2 wrap-up: full suite on the merged tip, delete `merge/stage-30-09` | ✅ 30-09 (476 passed; branch deleted) |
| W2 | U11 API docs: notifications / alerts / messages, documents, history, webhooks (+ Postman) | ✅ 30-09 (BUG-217 fixed on the way) |
| W8 | **My Account like the UI demo + a "Permissions & scope" tab** (your request 30-09): emp code, OEM Mile ID, designation, primary department / division / branch / location, add-on departments / divisions / branches / locations, segments, sub-segments, models, variants, verticals — blank when nothing is defined | ✅ 30-09 |
| W9 | **Vehicle Info export with controlled values** (your request 30-09): every lookup column a dropdown fed from the masters (codes stored, labels shown), so imported rows always relate to existing data | ✅ 30-09 |
| W10 | **Users bulk export / import redesign** (your request 30-09): headers `Emp Code*, Employee Name*, Personal Mail Id, Official Mail ID, Personal Contact Number*, Official Contact Number, OEM Mile ID, Aadhaar No, Primary Branch*, Addon Branch, Primary Location*, AddOn Location, Primary Department*, Addon Department, Primary Division, Add On Divisions, Designation*, Vertical, Segment, Sub Segment, Models, Reporting Manager`; master dropdowns, dependent lists (primary location ← primary branch, primary division ← primary department, add-on lists = the left-out children of the primaries + all children of the add-ons), multi-select with `All` first and `None` last, employee history kept | ✅ 30-09 (DEC-089 Phase B, DEC-090) |
| W11 | **Bulk user create / edit screen** (your request 30-09) with the same rules and multi-select filter-like pickers; writes employee history | ✅ 30-09 (DEC-089 Phase C: Org → Users → Bulk edit) |
| W12 | **Org rules as validation** (your request 30-09): no user without a primary branch / location / department / division (`All` / `None` not allowed there); every parent branch / department / segment has a same-name, same-code child location / division / sub-segment; verticals mandatory (multi-select, no `None`); segment / sub-segment / model / variant blank = all; every user has an employee code, FSCs may have a Mile ID | ✅ 30-09 (Phase A: rules in the entity services; legacy gaps → BUG-218) |
| W3 | Q1 Sales / booking feature tests (enquiry, quotation, booking flows) | ✅ 30-09 (15 HTTP tests; BUG-219 logged, BUG-220 fixed) |
| W4 | Q5 PHPStan baseline for the legacy controllers | ✅ 30-09 (`phpstan-baseline.neon`, 2 511 legacy errors; full run clean; BUG-221) |
| W5 | Q7 UI clean-up outside Sales (hex / inline styles → shared layer, same method as the Sales pass) | ✅ 30-09 (131 views; style blocks ~100 → 22 files; no hex in style blocks; PDFs excluded) |
| W6 | U7 web side: admin flash messages through the error codes / language file (same wording) | ✅ 30-09 (225 calls → `{module}.flash.*`); 01-10 exception texts → `ErrorRef::userMessage()` |
| W7 | U4 N+1 review of the big lists (enquiries, bookings, quotations) | ✅ 30-09 (bookings 567 → 95 queries / 42 s → ~3 s SQL; quotations 147 → 42; enquiries 142 → 37) |
| W13 | **One categorised Settings interface** (your request 30-09) — the only place settings are shown; changes apply at once on web and app / API. Today they are spread over Utilities → Settings (56 keys), the legacy System Setting screen, and the pricing TCS / Hold screens. Parts W13a–W13f; plan to be saved in `tech-guides/frs-and-workflows/plans/` | ✅ 30-09 — DEC-091 Phases 1–6 + W13g (one categorised Settings screen, applied everywhere, `GET app-settings`) |
| W13a | **Site / dealership:** name (default "Bikaner Motors"), website URL (default https://www.BikanerMotors.com), logo, address, favicon, e-mail, phone, GSTIN …; applied across the interface (header, login, PDFs, mails) | ✅ 30-09 (Phase 2; mail from-name comes with Phase 3) |
| W13b | **Communication:** global on / off per channel (mail, SMS, WhatsApp, push), SMTP settings used by the mail service, mail signature appended to every mail, plus the existing comms settings | ✅ 30-09 (Phase 3) |
| W13c | **Pricing:** global / per-list price-list hold, TCS threshold and rate, and the existing pricing settings; also open to the pricing-manage permission | ✅ 30-09 (Phase 4) |
| W13d | **User behaviour:** Appearance panel on / off; which profile fields a user may change (name, e-mail, profile photo, mobile, Aadhaar, PAN, password, DOB, DOJ, marital status, gender) — enforced on web and API | ✅ 30-09 (Phase 5; the API has no profile-edit endpoint — the flags go to the app via `app-settings` in Phase 6) |
| W13e | **Other module / utility settings** (security, data scope, documents, tickets / SLA, chat, notifications, approvals, display / date format, density …) grouped in the same interface | ✅ 30-09 (Phase 1: Security, Modules & utilities, Other tabs) |
| W13f | **Access and single place:** only the settings-manage permission (pricing group also pricing-manage); remove the other settings screens / menu entries; API reads the same values (cache flush on save) | ✅ 30-09 (Phase 6) |
| W13g | **Site tab feedback** (your notes 30-09): browser title = dealership + app name; footer "Made for <dealership>" linked to its website, tagline on hover; no site name / slogan (dealership tagline instead); current logo / favicon shown with drop-zone and Remove; menu logo = logo or text | ✅ 30-09 |
| W14 | **Vehicle content & compare** (your request 30-09) — parts W14a–W14d; plan to be written. Samples (root, not committed): `Vehicle_Specifications.xlsx` — one sheet per segment group (COMMERCIAL, LMM, LMM EV, PERSONAL, PERSONAL EV), rows Head → SubHead (Axle, Brakes, Engine, Battery, Dimensions, Warranty …), one column per model; `Vehicle-Features.xlsx` — one sheet per model, rows Feature group → Feature, one column per variant (by name), values Yes / --- | ✅ 01-10 — DEC-092 Phases 1–5 (data, screens, workbooks + your sample format, compare screen + API) |
| W14a | **Model level:** attach images, a PDF brochure and category-wise specifications (e.g. Engine, Dimensions, Safety …) to each vehicle model | ✅ 01-10 (Phase 2 screens) |
| W14b | **Variant level:** feature mapping and management per variant, and an image gallery per variant | ✅ 01-10 (features per trim; gallery bound to trim or colour) |
| W14c | **Excel import / export** of specifications (per model) and features (per variant), with master-fed dropdowns like the Vehicle Info workbook | ✅ 01-10 (our workbook by codes + your sample format by name, with a match report) |
| W14d | **Compare vehicles** within the same segment only: intra-model (variants of one model, by features) and inter-model (different models, by specifications) | ✅ 01-10 (admin screen + app API) |
| W15 | **No `DB::` queries — convert to Eloquent** (your rule 01-10, DEC-093). Guard test + baseline (473 uses / 72 files). Order: W15a services / jobs / console / imports / models / support; W15b admin controllers outside Booking; W15c tests / seeders; W15d Booking controller + booking models (with the booking team) | 🟡 unblocked part ✅ 02-10 (473 → 126 uses; all tests, services, platform, booking BT-001…007); left 126 in 8 files — blocked: deletions #6 / D5, reports D23, spares D28, importers (phase 5), schema tooling #21 |
| W16 | **Help & support utility** (your request 01-10, DEC-094) — F1 help pane, page tours, "Still need help?" support request with a diagnostic zip, support admin → executive routing; FRS `tech-guides/frs-and-workflows/frs/help-and-support-frs.md`, plan `…/plans/2026-10-01-help-and-support-DEC-094.md` | 🟡 planned — W16a ✅; build after W15a |
| W16a | FRS + plan + DEC-094 + to-do | ✅ 01-10 |
| W16b | Help engine: Markdown articles in `resources/help/`, route → article, `::: can CODE` sections, cache, search, coverage; F1 / `?` right-side pane; Help centre screen — ✅ 03-10 | ✅ |
| W16c | On-demand page tour (Driver.js via Basset): steps from the article / `data-xl-tour`, skip missing elements, "new" dot | 🔴 |
| W16d | Diagnostics collector: actions / AJAX / JS errors ring buffer (no typed values), server request trail, html2canvas screenshot with sensitive-field blanking + preview | 🔴 |
| W16e | Support request: permissions `UTL_SUPP_ADMIN` / `UTL_SUPP_EXEC`, categories + settings, `SupportRequestService` (masked zip → ticket, least-loaded admin as owner, executive-only assignment), screens, bundle rights, retention purge | 🔴 |
| W16f | Developer guide `tech-guides/platform/17-help-support.md` (+ reference) with the build; the **user-facing** help texts / "Getting help" articles move to §13 (last) | 🔴 |
| W18 | **Owner decisions 02-10 (DEC-095)** — build every item with a definitive answer; booking items as numbered, revertable BT changes | 🟡 in progress |
| W18a | Security: D2 `random_int` OTP (BUG-188), D3 entity allowlist for `docs/upload` / `history` (BUG-182), D1 OTP login from the person record (BUG-187) | ✅ 02-10 (+ BUG-227 fixed; BUG-228 SMS placeholder logged) |
| W18b | v1 `system-settings` read endpoints narrowed / retired (BUG-207), `BaseController::authorize()` fixed (BUG-209); note for the app team | ✅ 02-10 (managers only; writes via SettingsService) |
| W18c | Booking bugs BUG-223 / 224 / 225 / 226, BUG-219 (Dummy bookings validated), D21 BEV / Personal SO rule made to work (BUG-101) — ✅ 02-10 (BT-008 … BT-013) | ✅ |
| W18d | D16 — the hard-coded user-id lists in booking → a permission (BUG-095) — ✅ 02-10 (BT-014); grant `SLS_BKNG_ORDER_APPROVE` to designations (owner); BUG-229 found | ✅ |
| W18e | D13 — the 52 dead menu links open a "coming soon" page (BUG-056 / 062) — ✅ 02-10 (59 items, BT-015) | ✅ |
| W18f | D23 — the 5 booking reports rewritten on the current tables, inside a `Booking*Service` (BUG-122, #13) — ⏸ 02-10: needs report definitions R1–R8 (owner-decisions sheet, W18f section) | ⏸ |
| W18g | DEC-093 #21 — schema tooling exemption in the guard (`ai:refresh-context`) — ✅ 02-10 | ✅ |
| W18h | D26 — mask Aadhaar / PAN in old KYC rows (reversible: encrypted backup) — ✅ 02-10 local (`privacy:mask-kyc-history`); UAT / prod approved by the owner 03-10 — the owner adds the command to `deploy-hook.sh` (agent edit blocked by the permission guard) | ✅ |
| W18i | D25 / BUG-173 — booking reads / writes variant codes with the colour suffix; vehicle master purge + re-import (V7 / DA2, with the pricing run) — ⏸ goes with the pricing run (owner: pricing decided later) | ⏸ |
| W18j | #18 — local-only user reset command (keep a given list of accounts; dry run, backup) — run on the owner's list — ✅ built 02-10 (`users:reset`); run when the owner sends the list | ✅ |
| W18k | N4 — session / password / lockout / self-service values as Settings (S3 / S4 / S5 / S7) — ✅ 02-10 (part 1 sign-in / OTP / lockout / device limits; part 2 password expiry + history, off by default) | ✅ |
| W18l | BUG-206 — generated `person_code`; PAN / Aadhaar only masked (14 tables) — 🟡 02-10: new persons generated + upsert by ID ✅; remap of the remaining rows after the user reset (W18j) | 🟡 |
| W18m | #33 Redis for cache + queue (UAT / production config, with IT); #34 Playwright E2E (with Q3) — 🟡 03-10: Redis ready (no code change; guide + `.env.example`; BUG-230 fixed); Playwright approved by the owner 03-10; `playwright.config.ts` + `tests/E2E/smoke.spec.ts` ready; the install (`npm i -D @playwright/test`, `npx playwright install chromium`) is run by the owner (the agent's install was blocked by the tool's permission guard) | 🟡 |

**Needs you (not started):** D1–D29, N1, N3 / F2 formats, N4 security values (S3, S4, S5, S7), S6 / O3 / O6 / Q3 package
approvals, O1 / O2 / O5 CI and server changes, V8 app-sync endpoint shape, V10 / DA6 deletions, the push to `stage`.

## 12. Execution order (dependency-ordered, 01-10-2026)

The order to work in is `tech-guides/frs-and-workflows/plans/2026-10-01-execution-order.md` (phases 0–10 with the
reasons: dead code before conversion, booking convert + carve-out in one touch, keys before data migration, formats
before importers, HR data before approvals, stable code before QA, QA before user docs). Summary:
0 decisions → 1 security quick fixes → 2 dead-code removal → 3 booking to project level (BT-series) → 4 merge to
`stage` + hand the booking log to the team → 5 data foundations → 6 pricing sign-off → 7 platform in sales + security
features → 8 help & support (W16) → 9 go-live readiness → 10 QA sign-off, then user docs (§13).

## 13. LAST — user documentation (your instruction 01-10)

Written only after every open bug is fixed and QA has tested and vetted all functionality, so the user manual and the
help articles are written once and not rewritten after every fix. Until then the F1 pane (W16b) ships with the engine
and a "help for this screen is being prepared" page plus the support button; developer guides stay current with every
change as before.

| # | Item | Status |
|---|---|---|
| W17 | **User manual** (your request 01-10, DEC-094) — screen-wise and module / process-wise, with screenshots; same articles as F1 | ⏸ last — after QA sign-off |
| W17a | Article template, module / process overviews, coverage report in the Help centre | ⏸ |
| W17b | Articles for every admin screen (~210 screen routes → ~120 articles), business wording flagged for owner review | ⏸ |
| W17c | Playwright (dev-only) screenshot script with PII masking; superadmin + scoped-user runs | ⏸ |
| W17d | Screenshots into the articles, print stylesheet, owner review list | ⏸ |
| W17e | Help articles (F1 pane content) for every admin screen, incl. tours' step texts and "Getting help" / "Support requests" user guides | ⏸ last — after QA sign-off |

# Part 2 — Accomplishments

## Up to 28-09-2026 (summary)

The state of the project at the start of 29-09-2026 (previously `.ai/state/handoff.md`); details are in
`docs/changelog.md` by date and in the decision log.

**Branch:** `dev/admin` (working branch). **`stage`** = `6ccaf2a` (dev/admin merged and pushed 27-09-2026, deploys to dev.xceler8.in).
**Updated:** 29-09-2026 — `stage` = `dev/admin` (DEC-059…068 merged with the Sales team's 27-09 work; Sales parity done).

**Entity services (DEC-050…059), done:**
- Every data-entry entity has one write path, an `App\Support\Entity\EntityService` subclass whose `fields()` is the only rule set. It covers:
  - vehicle masters, Org masters, Person (+ contacts/addresses/banking), Employee, User, UserScope;
  - keyword masters/values;
  - pricing rules / add-ons / discounts / dealer charges / prices;
  - `VehicleService` (price-list stubs, Vehicle Info);
  - seeders.
- On update only changed values are validated. The model backstop (`HasColumnTransformations`) transforms only changed attributes and never blanks a value.
- Engine records (sessions, change flags, snapshots, history, completeness profiles) stay engine-written by design.

**Platform utilities (DEC-060…065, 28-09), done in Track A:**
- Helpers purged (`app/Helpers` gone).
- Services: Settings / Notify / Chat / Docs, Task / Ticket, approval engine (topics, rules, power sheet, report), Templates plus Email / SMS / WhatsApp / Telephony.
  - SMS, WhatsApp and telephony run on sandbox drivers; mail uses the Laravel mailer, with a `log` driver option.
- Rules: `.ai/rules/modules/platform.md`. Tests: `tests/Feature/Platform`.
- Open items:
  - BUG-182: v1 history/docs record access, an auth change.
  - BUG-183: 36 employees on unknown designation codes.
  - Real SMS / WhatsApp / telephony vendor drivers are needed before FRS acceptance #10.
  - No Sales flow calls the utilities yet.
  - Dead `App\Models\Core\{ApprovalHierarchy, GraphNode, GraphEdge}` await removal sign-off.

**UI / design (DEC-066…068):** Sales / booking converted 28-09; the shared UI layer, the Appearance panel (mode / colour / font /
radius / layout), AG-Grid themed via the global hook, and the dev UI kit `/admin/dev/ui`. The resume list and how to verify are in
`tech-guides/platform/ui-design-progress.md` (remaining: hex outside Sales, real dashboard).
**DEC-069 (on stage 0386230):** booking proofs in Docs, AG-Grid pinned in all 87 views, phone toolbars. **DEC-070 (dev/admin, not pushed):** bug-fix wave 1 done (OTP no longer logged, relation keys, VOTF duplicate guard, PAN masking, tracker triage); decisions D1–D29 await the owner — list in `~/.claude/plans/shiny-hopping-sky.md` / the DEC-070 changelog. Mobile OTP login is broken until D1 (BUG-187).
**DEC-071 (on stage 3f38f3e):** automatic user data scoping live; 24,070 enquiries backfilled locally. **DEC-072 (dev/admin, not pushed):** My Account rebuilt (`/admin/edit-account-info`) and the dynamic dashboard (`config/dashboard.php`, `DashboardService`, 23 permission-gated widgets, scoped + cached); guides `tech-guides/modules/dashboard.md`, iam-auth.md. BUG-198: permission-cache rebuild ~10 s.

**Pricing redesign DEC-073…082 (dev/admin, not pushed; 29-09): all phases done.**
- The 11-step Pricing Process runs as queued jobs with progress.
- Snapshots per permit × NV / OV × channel; `getPricing` (API `v1/vehicle/pricing/{oemCode}`, admin Price Lookup).
- Price List menu for all users (PV / Taxi / CV / Electric / LMM / TZU / CSD).
- The quotation runs on published prices: picker, gate + TCS re-check, hold guards; the legacy engine is removed.
- The reference sheets still lack CNG RTO rows and Passenger insurance above 7 seats (32 vehicles fail). COD in on-road
  is a setting (off).
- Local DBs hold no published snapshots; run the process to fill them. Browser check of the quotation picker is pending.

**MASTER TO-DO (29-09):** `docs/todo.md`, with status, P0–P3 priority and suggested order. It
includes the new central formats / data dictionary / permission rename (`module.process.activity`, e.g. `sls.bkng.cr`)
work: finalise the formats + dictionary first, then implement. Security (idle logout, screen lock, token expiry, 2FA,
self-service settings), ops (CI gate, backups, cPanel queue worker) and data readiness are there too. Update it as items
move.

**Waiting on the owner (DEC-070 decisions, details in the 28-09 changelog):**
- Security / API: D1 repair mobile login (BUG-187), D2 `random_int` OTP (BUG-188), D3 v1 entity access (BUG-182), D4 lead lookups.
- Deletions: D5 Brand, D6 ExportController, D7 RBACService, D8 Core graph models, D9 getChassisNumbers, D10 dead Org views, D11 seeder test users, D12 Booking scopes.
- UAT: D13 52 dead menu links, D14 import permissions (BUG-177), D15 Pricing menu (done DEC-081), D16 order-approve permission (BUG-095), D17 avatar (done DEC-072).
- Business / data: D18 pricing (BUG-178 fixed DEC-080/082), D19 accessory importer, D20 RTO sheet id, D21 BEV SO rule, D23 reports, D24 designations, D25 variant import, D26 mask old KYC rows, D28 Spares (D22 / D27 done in DEC-071; BUG-197 PRSNL division), D29 Google key rotation.

**For the booking team:** 52 dead menu links (D13, incl. BUG-056 / 062), BUG-095 hardcoded user ids (D16), BUG-122 reports (D23), BUG-161 / 092 booking columns (D22), BUG-191 dead scopes (D12).

**Local data:** vehicle master tables in `xlrm` purged (DEC-051), awaiting a fresh import. Keep `xlrm_testing` as is until then; both have the DEC-055 keyword masters migrated.

**Track B:** paused after B0 (DEC-033); resume from xceler8 `d9009db`. **Deferred until after UAT:** Laravel 13, Excel 4, Permission 8, Firebase 8, PHPUnit 12/13, Swagger 11.

**Verification cadence:** targeted smoke per change; full suite periodically (342 passed, 1 skipped on 28-09 before DEC-070; the one fixed skip is the MSG91 SMS test); `--group=smoke` sweep before merges.

**Environment:** Laragon, PHP 8.4.26 (+redis), MySQL 8.4.3. After a migration, run `DB_DATABASE=xlrm_testing php artisan migrate`. Do not `testing:refresh-db` until the vehicle import is in (DEC-051); it copies the empty vehicle tables over the test data.

## 29-09-2026

Branch `dev/admin`; nothing pushed (the stage merge waits on the owner).

---

### 1. Pricing redesign finished — DEC-073 phases 6–12, DEC-078…082

**Commits:** `3470722`, `5608d57`, `fc69228`, `ef4f3f4`, `d78f152`, `fab4b86`, `45ac557`.

**Delivered:**
- Insurance & RTO as standalone workbooks with safe formula / range parsers.
- The impact summary and the hold check.
- **Calculate & Publish:**
  - the snapshot engine (per permit × NV / OV × channel, the on-road rules confirmed by the owner);
  - a queued batch with progress;
  - the summary, retry-failed and complete-with-reopen.
- **getPricing:** the API `GET /api/v1/vehicle/pricing/{oemCode}` (404 / 423) and the admin Price Lookup.
- **Price List** screens for every logged-in user: PV / Taxi / CV / Electric / LMM / TZU / CSD. Read-only AG Grid,
  hover break-ups, cached.
- **Quotation on published prices:**
  - the mock BE6 prices are removed (BUG-203);
  - a vehicle picker;
  - server-side hold / discount-gate / TCS checks;
  - the pricing is stored with the quotation.
- A booking hold guard; the pricing reset hardened (GET preview, POST with a typed `RESET`, local only); the legacy
  engine removed.
- BUG-178 (dealer charges) fixed.

**Verified:**
- Real end-to-end run: 2,495 of 2,527 vehicles published, 9,266 snapshots. The 32 failures are gaps in the reference
  sheets.
- Full suite passed at each phase.
- Screens smoked for users 1 and 40.

**Left:** the sheet fixes (CNG RTO rows, passenger insurance above 7 seats); a browser check of the quotation picker;
the owner's COD decision.

### 2. Pricing masters, automatic recalculation, sync stamp, logo — DEC-083

**Commits:** `30eb92e`, `4c82d28`, `606e827`, `9930e84`.

**Delivered:**
- **Masters kit** (`App\Support\PricingMaster\*`): 14 screens under Admin → Pricing → Masters with list / CRUD /
  import / export (queued, with progress) and WEF versioning:
  - Dealer Charges, Discounting Breakup, RSA, Shield, Corporate, Exchange, **Loyalty** (new, end to end);
  - RTO Rules, Insurance Rules (heads + IDV slots + add-on rates in one form);
  - Insurance Companies, Insurance Preferences (segment + permit + a model override), Insurance Add-ons;
  - Accessories, Accessory Scopes.
- **Automatic recalculation:** `PricingParamObserver` → `PricingRecalcService` → a debounced `RecalculateAffectedJob`;
  a recalculation log screen.
- **Sync stamp:** `pricing.last_updated_at` for the app's offline sync.
- **Configurable site logo:** `branding.logo`, an image setting used in the header (linking to the dashboard), the
  login page and the prints.
- **Bugs:** BUG-179 / 204 / 205 fixed; 24 permissions minted.

**Verified:** full suite 448 passed; 14 master screens smoked (superadmin 200, user 40 403).

**Left:** granting the new `PRC_*` permissions to designations; re-importing the accessory workbook; a queue worker in
production.

### 3. Go-live master to-do — plan

**Commits:** `6f66bec`, `841975c`.

**Delivered:**
- `docs/todo.md`: every pending / partial / new item with status, P0–P3 priority and evidence
  — security, ops, data, quality, Sales, pricing and enterprise features.
- **Formats track (§2):**
  - an inventory of 20+ code families;
  - the draft data dictionary `tech-guides/architecture/data-dictionary-draft.md` with the proposed formats and 5 questions for
    sign-off;
  - BUG-206 logged (`person_code` = PAN / Aadhaar for 211 of 215 people).

**Left:** the owner's sign-off on the dictionary and the P0 decisions.

### 4. Security baseline — DEC-084 (to-do S1, S2, S4, S5, S7, S10)

**Commit:** `8679bb6`.

**Delivered:**
- **Idle auto-logout** and an **automatic screen lock:** settings-driven, off by default. The server enforces them
  (`EnforceIdleSession`); the browser warns first (`xl-idle.js`); background polls never keep a session alive.
- **Lock screen** in the user menu: password unlock, an attempt limit, same-site redirect only.
- **Security headers** on every response; the CSP report-only (`security.csp_mode`).
- **Login throttling** confirmed and tested.
- **My Account self-service switches** and a settings-driven password rule.

**Verified:** 12 new tests; full suite 458 passed; smoke for users 1 / 40.

**Left (policy N4):** email / mobile self-service with OTP, password expiry / history, a lasting account lock, token
expiry.

### 5. Standing rules for all agents

**Commits:** `3c2709d`, `d396322` (+ this one).

**Delivered:** rules in `.ai/rules/ui.md`, `app.md`, `api.md` and `.ai/guidelines/10-workflow.md` (regenerated into
`CLAUDE.md` / `AGENTS.md`, which were stale):
- dense standard forms with collapsible / draggable cards and required counters;
- the UI kit everywhere;
- the density controller;
- caching / lazy loading;
- branded error pages;
- one error / response pipeline with module codes;
- guides updated on every change;
- module-wise API docs + Postman;
- changelog + status + **handoff** (`.ai/state/handoff.md`) with every commit;
- commented + formatted code;
- this **accomplishments log**.

To-do section 10b (U1–U11) was added.

### 6. BUG-198 fixed — permission cache rebuild

**Commit:** `d396322`.

- **Root cause:** `App\Models\IAM\Role` had no declared table. Spatie fills attributes before it sets the table, so
  Laravel's guarded-column check queried `information_schema` for the non-existent `roles` table once per role
  instance: 2,879 queries.
- **Fix:** `$table = 'xlr8_admin_designation'`, plus a memoised `User::deniesPermission()` (it ran one query on every
  `can()`).
- **Result:** a rebuild + 21 checks took **9 queries / 312 ms**, down from 2,887 queries (≈ 10 s on the original
  measurement).
- **Verified:** IAM / Org / Dashboard / RBAC suites, 56 passed.

### 7. Branded error pages — to-do U8

**Delivered:**
- **Public / signed-out pages:** `resources/views/errors/{401,403,404,419,429,500,503}.blade.php` on the shared
  standalone layout `errors/xl.blade.php`:
  - the logo, the code, a plain title and message;
  - a dashboard / sign-in link and "Go back";
  - "Reload" for 419; "Try again" for 503.
  - `public/css/xl-errors.css` is self-contained, with light and dark modes, so it renders even when the app or the
    database is down; the logo falls back via `rescue()`.
- **Admin-panel pages:** the customised `resources/views/vendor/backpack/theme-tabler/errors/layout.blade.php` keeps
  the admin shell. It has working dashboard / back buttons (the stock button pointed at `./.`).
- **500s never show internals:** they show a **reference id** (`App\Support\ErrorRef`), which is also added to every
  logged exception (`bootstrap/app.php` → `withExceptions()->context()`), so support can find the exact log entry.

**Verified:**
- `ErrorPagesTest` (4): the 404 is branded; the 403 shows the reason; the 500 hides SQL / secrets and shows the
  reference; JSON requests still get JSON.
- The admin 403 for user 40 renders in-shell with "Go to dashboard".

### 8. Module-wise API documentation — to-do U11 (first two modules)

**Delivered:**
- **`tech-guides/api/index.md`:** every v1 module (pricing, auth, devices, system settings,
  notifications / alerts / messages, documents, history, webhooks), its endpoints and its documentation status.
- **`tech-guides/api/pricing.md`:** `GET vehicle/pricing/{oemCode}`:
  - auth / middleware;
  - the path + 15 query parameters with their validation and defaults;
  - the full contract v2 success example with the calculation rules;
  - 401 / 404 / 422 / 423 / 500 examples;
  - usage examples.
- **Postman v2.1 collections:**
  - `tech-guides/api/postman/pricing.postman_collection.json`: 4 requests with saved 200 / 401 / 404 / 422 / 423 examples;
  - `tech-guides/api/postman/system-settings.postman_collection.json`: 9 requests.
- **`tech-guides/api/system-settings.md`:**
  - all 9 endpoints (reads, admin update / export / import) with the shapes and errors;
  - the keys the app needs (the sync stamp `pricing.last_updated_at`, display formats, logo).
- **New finding, BUG-207:** the settings API exposes every setting (including secrets as ciphertext) to any app user,
  and writes go through the legacy service. The proposed allow-list needs the owner's decision.

**Verified:** both collections parse as JSON; the endpoints and parameters were checked against `routes/api.php` and the
controllers.

**Left (U11):** auth, devices, notifications / alerts / messages, documents, history, webhooks.

### 9. Form cards and the density controller — to-do U1 (shared layer), U3, U4 (images)

**Delivered:**
- **Every form card** (a `.card` with a header inside a `<form>`), with no per-screen code (`public/js/xl-ui.js`):
  - a collapse chevron;
  - a drag grip to reorder, with Alt + ↑ / ↓ from the keyboard;
  - a live **required filled / total** badge (amber → green). It counts Backpack's required wrappers and `[required]`
    controls, and a radio group counts once.
  - The order and the collapsed cards are remembered per screen (edit pages of one entity share a layout).
  - A collapsed card opens by itself when the browser flags an invalid field inside it, so a submit is never silently
    blocked.
  - Opt out with `data-xl="off"`.
- **Density controller (U3):**
  - site defaults in Settings: `ui.density.text` (xs / sm / md / lg, default **sm**) and `ui.density.space`
    (compact / cozy / comfortable, default **compact**), which gives the smaller font and minimal spacing asked for;
  - a per-user override in Appearance → *Text size* / *Spacing* (saved per browser);
  - applied before the first paint (`inc/theme_styles.blade.php`, `App\Support\UiDensity`), so nothing jumps; an
    invalid setting falls back to the Tabler standard.
- **Lazy images (U4):** every `<img>` without a `loading` attribute gets `loading="lazy"` + `decoding="async"`.
- **Guide:** `tech-guides/platform/ui-kit.md` (form cards, density, the JS API).

**Verified:**
- `UiDensityTest` (2: the defaults reach the page + the panel offers the choice; an unknown value falls back); the Utils
  suite 16 passed; the error-page + session suites 12 passed.
- Headless Chrome on a fixture form: the badge showed 1/4 → 4/4 after edits; the saved order and collapsed card were
  restored; a header-less card was left alone; an invalid field opened its collapsed card.
- Smoke: the dashboard and the UI-kit forms page render for users 1 and 40 with the density defaults.

**Left:** U1 per-screen work (give header-less Backpack form cards headers as screens are converted; one standard page
header); a real-browser pass on the quotation / booking forms.

### 10. One API error envelope with module-wise messages — to-do U7 (API side), DEC-085

**Delivered:**
- **Every exception on `api/*`** now answers with the standard envelope
  `{http_status, success, code, message, timestamp, errors?, error_ref?}`:
  - one renderer, `App\Exceptions\ApiExceptionRenderer`, registered in `bootstrap/app.php`;
  - `BaseController::handleException` uses the same mapping, so a controller's try / catch and an uncaught exception
    answer alike.
  - Before, unauthenticated calls, unknown routes, wrong methods, throttling and crashes returned Laravel's own JSON
    (or a debug trace).
- **Status codes are unchanged** and today's messages are kept; only fields were added, so the mobile app's v1 contract
  holds (DEC-004).
- **A 5xx never shows internals:** a generic message plus `error_ref`, the same id as in the log (U8).
- **Module-wise messages:** `resources/lang/en/errors.php` holds every code's message, grouped by module (Auth, request /
  validation, resources, Org / HR, pricing, settings, platform). `ErrorCodeEnum::message()` reads it.
- **New codes:** `REQUEST_INVALID` (400), `REQUEST_METHOD_NOT_ALLOWED` (405), `RESOURCE_LOCKED` (423),
  `REQUEST_RATE_LIMITED` (429).
- **Defects found and fixed (BUG-208):**
  - `message()` crashed for the six Org / HR codes;
  - `DomainException` defaulted to a code that did not exist;
  - the enum said 400 for validation while responses sent 422;
  - `app/Exceptions/Handler.php` was never registered (now marked deprecated).
- **BUG-209 found:** the settings API writes always failed with a 500 (`BaseController::authorize`). Now the intended
  403; making them work waits on the BUG-207 decision.
- **Docs:** the common-errors table in `tech-guides/api/index.md`; `pricing.md` and `system-settings.md` updated.

**Verified:**
- `ApiErrorEnvelopeTest` (6): 401 / 404 / 405 / 500 / domain-code envelopes; every code has a message; web 404s stay
  branded HTML.
- API, pricing, OTP, settings and error-page suites: 129 passed, 1 known skip.

**Left:** the web side (admin flashes through the same codes); renaming `E002` with the app team; deleting the dead
`Handler.php` (needs approval).

### 11. API docs for Auth and Devices; device registration repaired — to-do U11, BUG-210

**Delivered:**
- **`tech-guides/api/auth.md`:** the OTP flow, the limits (OTP validity, request / attempt limits, account lock, device
  limit, the 30-day token), every endpoint with its body validation, the success shape and each error with its code and
  message. The BUG-187 / BUG-188 warnings are up front.
- **`tech-guides/api/devices.md`:** the push-token endpoints, and a clear note that they do not sign devices out.
- **Postman:** `auth.postman_collection.json` (Verify OTP stores the token automatically) and
  `devices.postman_collection.json`, with saved success and error examples.
- **BUG-210 fixed (High):**
  - device registration validated against a table that does not exist, so every call failed with a 500 and no app
    device ever received a push token;
  - the rule is removed; registering again now refreshes the FCM token, as the service intended.

**Verified:** `DeviceRegistrationTest` (2); both collections parse as JSON; every documented field, rule, message and
status was read from the controllers, `AuthService` and the exception classes.

**Left (U11):** notifications / alerts / messages, documents, history, webhooks.

### 12. Docs, AI-context and repository clean-up — to-do 10c (C1–C8), DEC-086

**Delivered:**
- **`tech-guides/`** (new; Laradocs now serves it at `/docs`):
  - `README.md`, the load map (task → the few files to read) and `00-project.md`, a one-page project card;
  - `architecture/` (core, model reference, legacy utilities, data dictionary draft, a decisions summary regenerated
    from all 86 DEC headings);
  - `modules/` (12 guides + a short card per module in the README);
  - `platform/` (16 utility guides, UI kit, UI progress);
  - `api/` (docs + Postman);
  - `frs-and-workflows/`: `frs/` (8 FRS / specs), `plans/` (DEC-071 / 072 / 073 / 083 plans with current status
    headers + an index), `workflows/` (pricing process, sales lifecycle, approvals, the change workflow).
  - 55 guides moved with `git mv` (history kept); links and references rewritten across guides, rules, skills and code
    comments.
- **`docs/`** now holds records only: `todo.md` (to-do + accomplishments; the old `current.md` summary folded in),
  `changelog.md` (11 daily changelogs + findings + the root changelog, chronological), `bugs/open.md` +
  `bugs/closed.md`, `decisions/decision-log.md`.
- **Bugs verified:**
  - 27 of 29 open bugs confirmed open in the code / local data (each has a "Verified 29-09-2026" line);
  - BUG-029 closed (its controller was deleted on 25-09);
  - BUG-199 not verifiable locally;
  - the 06-09 audit (`knownissues.txt`): 21 of 23 listed items resolved; 2 became BUG-212 / 213;
  - BUG-211 logged (`/docs` served the tracker).
  - Result: 31 open, 182 closed. `ai:refresh-context` reads `docs/bugs/open.md`.
- **AI agents:**
  - `CLAUDE.md` / `AGENTS.md` down from 21.1 KB to 12.4 KB. The generic Boost / Laravel sections are excluded through
    `config/boost.php` and replaced by one compact `.ai/guidelines/30-laravel-tools.md`. No dates in always-loaded
    text, so the cached prompt prefix stays stable.
  - Laravel Cloud and Tailwind skills are no longer installed.
  - The pricing skill was rewritten for the DEC-073 architecture (it named removed services); the other skills now
    point at their guides.
  - `.claude/settings.json` denies reads of `_backup/`, `node_modules/`, `storage/`, `public/build/` and workbooks.
- **`_backup/`** (git-ignored): the old `.ai/_archive`, the daily changelog / findings files, the old bug tracker and
  audit, the old to-do / accomplishments / plans copies, empty placeholders, old pricing AI context and the reference
  workbooks. They were untracked; the ones from `4c82d28` remain in history.

**Verified:** link check across `tech-guides/`, `docs/`, `.ai/` (remaining flags are historical references to archived
files); `/docs` renders from `tech-guides/`; `php artisan ai:refresh-context` → 31 open; tests (see the commit).

**Left:** date-wise handoff / changelog / accomplishment files (later); the history rewrite for `4c82d28` and the
`/docs` login decision (owner).

### 13. Date-wise handoff, changelog and accomplishments — DEC-086 addendum

**Delivered:** `docs/daily/29-09-2026/` with today's handoff, changelog and accomplishments, an index
`docs/daily/README.md`, and the standing rule (always loaded) to update the day's files together with the cumulative
ones in every commit. **Verified:** the copies match today's sections of `docs/changelog.md`, `docs/todo.md` and
`.ai/state/handoff.md`. **Left:** —

## 30-09-2026

### 1. Owner-approved clean-up items: `/docs` login, dead files — BUG-211, BUG-212, BUG-213

**Delivered:**
- `/docs` (the developer guides) now requires the admin login and serves only `tech-guides/` (BUG-211 fixed).
- The unused `Module\Booking\XlInsurer` copy (BUG-212) and the never-registered `app/Exceptions/Handler.php` deleted.
- BUG-213 corrected and closed: the "lowercase `pricing.php`" was a false positive from my 29-09 check on a
  case-insensitive filesystem. The file is `Pricing.php` and is the live price model used by 9 services; deleting it
  would have broken pricing, so nothing was removed.
- A flaky time-dependent assertion in `ApiErrorEnvelopeTest` fixed.

**Verified:** `DocsSiteAccessTest` (guest → login redirect; signed-in → 200 from `tech-guides/`); API envelope tests
passed 3 runs in a row; the related API / pricing / booking / insurance suites, 93 passed.

**Left:** —

### 2. Pricing reference workbooks removed from `dev/admin` history

**Delivered:** the ~18 MB of workbooks / PDFs committed by mistake in `4c82d28` are gone from every commit of
`dev/admin` (16 unpushed commits rewritten); files stay on disk in `_backup/`. **Verified:** no commit touches the path;
tree identical to before. **Left:** delete `backup/dev-admin-before-rewrite-30-09` + `git gc` once confirmed.

### 3. `origin/stage` merged into `dev/admin` — both sides' work kept (DEC-087)

**Delivered:** the booking team's 27-file work (new enquiry view / exchange / finance pages, duplicate and lost lists,
list toolbars with exports, OTF / receipt / JV / import changes) is in `dev/admin`, and none of DEC-068…071 was lost
(the stage reverts are recorded as merged but not applied). 35 conflicts resolved by intent, not by side: our
architecture wins where theirs called removed code; their features win everywhere else. Two real bugs surfaced and were
fixed (BUG-214, BUG-215).

**Verified:** no conflict markers; all Blade views compile; no references to removed classes; full suite green after the
BUG-215 fix. **Left:** the booking team should create the `SALE_TYPE` / `REGISTRATION_NO_TYPE` keyword masters
(BUG-215 follow-up); pushing / merging back to `stage` waits on the owner.

### 4. Sales UI/UX pass — shared look without touching logic (to-do U1 / U2 / U5)

**Delivered:** every Sales list and form screen on the shared UI layer: one grid look, one toolbar / popover / loader
style, token colours (dark-mode safe), cached pinned libraries, toast messages instead of `alert()`, site-format
dates. 77 views changed, ~1,940 lines of duplicated per-view CSS removed. All ids, JS hooks, AJAX calls and routes are
unchanged. Guide: `tech-guides/platform/ui-kit.md` (List screens).

**Verified:** views compile; render smoke of 82 Sales screens for users 1 and 40 (no new errors; BUG-122 reports
unchanged). **Left:** a real-browser pass of the busiest forms (booking add, OTF, quotation); page-header eyebrow on
forms as they are next edited.

### 5. Booking team DB changes compared and migrated fail-safe (DEC-088)

**Delivered:** their 4-table dump compared column by column and index by index with our DB. One fail-safe migration brings
`xlr8_crm_enquiries` in line (the lost-reason columns their merged screens need, the `vh_code` rename, `idx_mobile`, the
empty `x8_enq_source` removed). The broken rollback of their earlier migration is guarded. Our newer variant schema
(DEC-073) is kept and the reason documented.

**Verified:** up / down / up on the test copy; applied locally; re-diff clean except the intended items; 109 related tests
pass. **Left:** their environments get this and our index migrations on the next deploy; `booking.sql` stays untracked in
the project root (not committed).

### 6. Colour-mode flicker fixed — BUG-216

**Delivered:** the dark / light flashing is gone: tabs now pick up another tab's colour mode once instead of bouncing it.
**Verified:** a two-tab reproduction in headless Chrome (old 300 flips in 3 s → new 1 per switch, tabs agree).
**Left:** —

### 7. Merge wrapped up and every mobile API documented — W1, W2 (to-do U11 done)

**Delivered:** the stage merge is closed out (full suite green on the final tip, temp branch deleted). The last four API
modules — notifications / alerts / messages, documents, record history and the provider webhooks — have full docs (every
parameter, rule and response) and Postman collections, so all v1 endpoints are documented. BUG-217 (bad sort values
crashed the lists) fixed with a regression test.

**Verified:** 476 passed; the new test fails on the old code and passes now; collections parse; every documented field
was read from the controllers, resources and services. **Left:** BUG-182 (documents / history access) and the null names
wait on D3 / D1.

### 8. My Account redesigned with a Permissions & scope section — W8

**Delivered:** My Account now looks like the UI demo (profile header + side-menu settings card) and has a Permissions &
scope section listing employee code, OEM Mile ID, designation, the primary and add-on departments / divisions / branches /
locations, segments, sub-segments, models, variants, verticals and the permissions by module — blank ("—") where nothing
is defined. **Verified:** `MyAccountTest` (5 passed, every field asserted); rendered for a super admin and a scoped user.
**Left:** —

### 9. Vehicle Info workbook with master dropdowns — W9

**Delivered:** the Vehicle Info export now carries a dropdown on every lookup column, filled from the masters (codes),
with Sub Segment limited to the chosen Segment and number ranges on Seating / Wheels / GST%. The import only accepts
values that exist in the masters — a typo can no longer create a new segment or sub-segment, and transmission /
drivetrain are checked too. `AWD` was added to the drivetrain master because vehicles already use it.

**Verified:** 2 new tests (strict import, dropdown workbook structure); the pricing suite, 71 passed; migration run on
both databases. **Left:** duplicate keyword codes (`AUTOMATIC` / `AUTOMATIC_1` …) are collapsed in the dropdowns; the
real clean-up is F6.

### 10. Org rules enforced everywhere — W12 (DEC-089 Phase A)

**Delivered:** every new branch / department / segment gets its same-code, same-name location / division / sub-segment,
and existing gaps were filled by a fail-safe migration. Every new employee must have a primary branch, location,
department, division and a vertical; a blank location / division takes the parent's same-code child; a location must
belong to its branch and a division to its department. Legacy rows stay editable (never silently corrected); the gaps
are listed in BUG-218 for HR. The user forms mark vertical as required.

**Verified:** 6 new tests; org / IAM / import / vehicle / pricing / booking suites 340 passed; migration up / down / up on
the test copy. **Left:** filling the legacy gaps (BUG-218) — easiest once the new workbook (W10) is in.

### 11. Users workbook in the owner's layout — W10 (DEC-089 Phase B, DEC-090)

**Delivered:** Org → Users → Bulk import now exports / imports the `Users` sheet with the exact 22 headers. Single values
are master-code dropdowns (Primary Location follows the row's branch, Primary Division its department); add-on, vertical
and vehicle columns take comma-separated codes, `ALL` or `NONE` with every valid code listed on the `Lists` sheet. Each
row creates / updates the person, employee, login, role and scopes in one transaction through the entity services, and
every change of designation, primaries, manager or scopes is written to the employee history. Aadhaar is masked in the
file and kept unless a full number is typed. The old RBAC workbook stays as an audit export.

**Why:** owner request 30-09 (to-do W10); DEC-089 answers; DEC-090 for `ALL` / unchanged-cell semantics (no silent
access changes, legacy values never block a re-import).

**Verified:** 7 new feature tests; Org / IAM suites 106 passed; phpstan clean on the new services; full export →
unchanged re-import of all 200 users on the test copy = 0 failures, 0 changes; HTTP: superadmin 200 (page + 3
downloads), scoped user 40 → 403. **Left:** W11 bulk screen on `UserRowService`; filling BUG-218 gaps with the new file.

### 12. Bulk user create / edit screen — W11 (DEC-089 Phase C)

**Delivered:** Org → Users → Bulk edit: a grid of every employee user in the workbook columns. Double-click a cell to
edit: master cells open a filter-like picker (search, tick / untick; multi-value lists start with `ALL`, end with `NONE`,
vertical has no `NONE`); lists follow the row (primary location ← branch, primary division ← department, add-on
locations ← primary + add-on branches, sub-segments ← segments, models ← sub-segments / segments), and changing a
primary branch / department clears a child that no longer belongs. "Add user" adds a row for a new employee. Save sends
only new / changed / failed rows through the same `UserRowService` as the workbook (rules, history); per-row results are
shown in place and failed rows stay marked with their messages. Filters: search, All / Changed / Failed.

**Verified:** 3 HTTP feature tests (screen, data, save order, 403); headless-Chrome run of the grid script on the 200
exported rows; HTTP smoke superadmin 200, user 40 → 403. **Not verified:** a visual pass at 390 / 768 px (the toolbar
wraps with the shared `.xl-toolbar`). **Left:** BUG-218 data gaps (HR can now fix them on this screen).

### 13. Sales / booking HTTP feature tests — W3

**Delivered:** 15 HTTP tests over the enquiry, quotation and booking write flows (list screens were already in the
smoke sweep; booking step services already had unit tests). The booking sweep proves all 25 write routes refuse a
user without Sales permissions. Found and fixed BUG-220 (mock customer names in quotation history); logged BUG-219
(dummy bookings skip base validation) for the owner.

**Verified:** Sales + quotation pricing + booking service suites 77 passed. **Left:** BUG-219 decision.

### 14. PHPStan baseline — W4

**Delivered:** the whole codebase now passes PHPStan level 5 with the legacy errors recorded in `phpstan-baseline.neon`
(2 511), so every new error fails the gate; the workflow rule says so. Two real defects fixed on the way
(AuthorizationException import, SystemSettingAudit user relation); the other "class not found" paths logged as BUG-221.

**Verified:** full `phpstan analyse` → No errors (twice, before and after the fixes). **Left:** burn the baseline down
module by module (largest: legacy admin controllers 567, booking services 475).

### 15. Admin flash messages from the language files — W6 (U7 web side)

**Delivered:** every typed success / error / warning message on the admin screens (225 calls in 55 controllers) now
comes from `resources/lang/en/{module}.php` → `flash`, with the same wording, so copy changes need no code change.
Converted by script (literal, interpolated and concatenated strings; the few ternaries by hand), then checked: every
key resolves and every placeholder is passed; a unit test keeps it so.

**Verified:** `FlashMessagesLangTest`; full suite 511 passed, 1 skipped; full PHPStan clean. **Left:** validator / Result messages already
come from their own sources; exception texts appended to some error flashes (`:message`) are unchanged behaviour.

### 16. One categorised Settings interface — W13 Phase 1 (DEC-091)

**Delivered:** Utilities → Settings is now a tabbed interface — Site / dealership, Communication, Pricing, User
behaviour, Security, Modules & utilities, and Other (legacy keys) — with proper inputs per setting (switches, selects,
e-mail / URL / number fields, masked secrets, image uploads), one save per section, search across all tabs and
branch / desk overrides kept. Only settings managers open it; pricing managers see just the Pricing tab. New settings
for the dealership (default "Bikaner Motors" / https://www.BikanerMotors.com), channel switches, SMTP, mail signature,
Appearance and profile-field flags are in place (their effects arrive in Phases 2–5).

**Verified:** 5 new feature tests (access matrix, save, validation, secrets encrypted and kept); platform + admin suites
117 passed; HTTP smoke of every tab. **Left:** Phases 2–6 (apply site settings, comms, pricing holds / TCS, profile
flags, single place + API).

### 17. Dealership settings applied across the interface — W13 Phase 2 (DEC-091)

**Delivered:** the dealership name from Settings → Site is the name in the header, page titles and login page on the
next request; an uploaded favicon replaces the built-in icons; the legal name printed on receipts, OTF forms,
quotations and PDFs comes from a new "Legal (company) name" setting instead of typed text.

**Verified:** 3 feature tests; platform + admin + sales suites 135 passed; dashboard smoke for superadmin and user 40.
**Left:** mail from-name / signature (Phase 3); check Settings → Site on UAT for leftover demo values.

### 18. Communication settings take effect — W13 Phase 3 (DEC-091)

**Delivered:** switching a channel off on Settings → Communication stops mail / SMS / WhatsApp / push at once (rows
recorded as suppressed for the audit; login OTPs still go out); a mail server entered there is used for the next mail
(password stored encrypted); the signature ends every mail; the default sender name falls back to the dealership name.

**Verified:** 5 feature tests (switch, OTP exempt, signature, sender, SMTP mailer); platform + API suites 73 passed.

### 19. Site tab as the owner asked — W13g (DEC-091)

**Delivered:** browser title "<page> :: <dealership> | Xceler8 DMS"; footer "Made for <dealership>" linked to the
dealership website with its tagline on hover; a tagline setting instead of the site name / slogan; logo and favicon
show the current (or built-in) image with a Remove button and the drop-zone upload; the menu can show the logo or the
dealership name as text. The image-field rule is now in the project UI rules.

**Verified:** 4 feature tests; platform + admin suites 126 passed; render smoke of the dashboard and the Site tab.

### 20. Price-list holds and TCS on Settings → Pricing — W13 Phase 4 (DEC-091)

**Delivered:** pricing managers (and settings managers) put price lists on hold / reopen them and set the TCS threshold
and rate on Settings → Pricing; the pricing process keeps its own hold steps on the same records; TCS changes go through
its entity rules and trigger the automatic recalculation. The old Price Holds and TCS pages lead to the new tab and are
off the menu.

**Verified:** 3 new feature tests (holds on / off, TCS save + validation, old pages redirect); pricing + platform + sales
suites 151 passed. **Left:** delete the two unused views once you agree.

### 21. User behaviour settings take effect — W13 Phase 5 (DEC-091)

**Delivered:** when a Settings switch is on, users can change that personal detail themselves on My Account (e-mail,
mobile, Aadhaar, PAN, date of birth, date of joining, marital status, gender — plus the existing name / photo /
password switches); anything switched off is ignored by the server, and the person / employee field rules still apply.
Turning the Appearance switch off removes the Appearance button, menu entry and panel for everyone.

**Verified:** 3 feature tests; platform + admin + IAM suites 158 passed. **Note:** tabs TCS threshold / rate were
already moved to Settings → Pricing in Phase 4 (owner note 30-09).

### 22. Settings in one place, for the web and the app — W13 Phase 6 (DEC-091); W13 complete

**Delivered:** the old System Setting pages now lead to the categorised Settings screen, so settings are shown in one
place only; the mobile app has `GET /api/v1/app-settings` with the dealership branding, channel switches, editable
profile fields and display formats, read live. Found on the way: the older settings API returned encrypted secrets
(as ciphertext) to any signed-in app user — they are never returned now (BUG-207 partly fixed; narrowing the rest of
that API is your call).

**W13 as a whole:** one Settings interface with Site / dealership, Communication, Pricing, User behaviour, Security and
Modules tabs; managers only (pricing managers: Pricing tab); every change applies at once on the web, in mails and in
the app. **Verified:** full suite 536 passed, 1 skipped (UiDensityTest fixture now grants UTL_SETTINGS_MANAGE); full PHPStan clean.

### 23. UI clean-up outside Sales — W5

**Delivered:** the same dark-mode-safe, shared-style treatment the Sales screens got, applied to 131 other admin
screens (Org, IAM, Accounts, Pricing, Vehicle, Utilities, Spares, imports …): token colours instead of white / light /
black classes, the shared grid / toolbar / export look, duplicated CSS removed, the permission tree and person picker
moved into the shared stylesheet (they were light-only in dark mode). No behaviour or script changes.

**Verified:** all views compile; full admin smoke sweep — no screen errors. **Left:** 22 files keep small screen-specific
style blocks (layout only, no colours); PDFs keep print colours by design.

### 24. Big lists much faster — W7 (N+1 review)

**Delivered:** measured every query on the booking, quotation and enquiry lists and removed the repeats: settings and
keyword lists are read once per request, the booking grid fetches consultant names, refunds and live-order counts in one
query per page instead of one per row (proved identical row by row), and the notification bell no longer counts twice.
Booking list: 567 → 95 queries, about 42 s → 3 s of database time on the test copy; quotations 147 → 42; enquiries
142 → 37.

**Verified:** equivalence test on the booking grid; full suite 536 passed, 1 skipped; PricingRecalcTest errors only in the full run in this sandbox (storage/basset not writable) and passes alone; tests/TestCase now clears the static memos per test; full PHPStan clean. **Suggested:** a Redis or
file cache on UAT (today every cache read is a database query).

### 25. Vehicle content data layer — W14 Phase 1 (DEC-092)

**Delivered:** the storage for vehicle content: master lists of specification and feature items, each model's
specification values, each trim's feature values, a trim record per variant code for the trim-level gallery, and media
collections for model images, the brochure and trim / colour galleries — all behind entity services, with the new
permissions for content editing and compare.

**Verified:** 5 feature tests; migrations up / down / up on the test copy. **Next:** Phase 2 screens.

## 01-10-2026

### 1. Vehicle content screens — W14 Phase 2 (DEC-092)

**Delivered:** Vehicles → Vehicle Content lists every model with what it already has; a model page edits its
category-wise specifications, images and PDF brochure and lists its trims; a trim page edits its features (shared by all
colours) and its gallery, where each upload is bound to all colours or to one colour. New specification / feature items
can be added from the pages; current files show with a Remove button.

**Verified:** 6 feature tests (permissions, saves, uploads, level-bound gallery, ownership, refused file); render smoke.
**Next:** Phase 3 — Excel import / export and the loader for your sample workbooks.

### 2. Specifications / features workbooks — W14 Phase 3 (DEC-092)

**Delivered:** Vehicle Content → Workbooks: export all models' specifications (sheet per segment) or all trims'
features (sheet per model) with codes, edit, and import back; your OEM sheets can be imported as they are — models and
trims are matched by name and every column that did not match is listed, new items are added and reported, blanks keep
what is stored.

**Verified:** 4 feature tests; your two sample files loaded on the test copy (rolled back): 765 specification values and
4 571 feature values matched; the unmatched columns are names that differ from ours (e.g. "ALFA LOAD", "MAXX CITY 1.3 VXI
BS6.2 - GOLD"). **Next:** Phase 4 — compare.

### 3. Compare vehicles — W14 Phase 4 (DEC-092); W14 complete

**Delivered:** Vehicles → Compare Vehicles: pick a model and 2–6 of its trims to see their features side by side, or a
segment and 2–6 of its models to see their specifications side by side; differing rows are highlighted, "only
differences" hides the rest, and the page prints cleanly. The mobile app gets the same comparison through
`GET api/v1/vehicles/compare/variants` and `/models` (documented, with a Postman collection). Mixing segments is refused
with a clear message.

**W14 as a whole:** model images, brochure and category-wise specifications; trim features and galleries (bound to all
colours or one colour); Excel export / import including your OEM sheets; compare on the web and the app.
**Verified:** Vehicle suite 26 passed; full PHPStan clean. **Left:** load your samples on UAT and fix the names the
import report lists; grant `VEH_CONT_VIEW` / `VEH_CONT_EDIT` / `VEH_CMPR_VIEW` to the designations that need them.

### 4. No raw exception text on admin screens — W6 remainder

**Delivered:** 17 admin error messages that showed the raw exception text (for a database error: the SQL and its values,
which could include customer mobile numbers) now go through `ErrorRef::userMessage()`. Messages written on purpose by
our code still appear as before; technical failures show "A technical error stopped this action (reference XXXXXXXX)"
and the same reference is in the log, so IT support can find the details.
**Verified:** new `ErrorRefUserMessageTest` (4) + flash-language and error-page tests, 10 passed; PHPStan clean on the
12 controllers. **Left:** nothing for W6.

### 5. Accessory export repaired — BUG-221 (part)

**Delivered:** `php artisan vehicle-accessories:export` failed with an SQL error as soon as an accessory had a model or
variant scope (it read columns that do not exist) and logged its runs in the wrong columns. It now uses the current
vehicle models, names each row's model and variant, and logs file, rows, size and duration in `export_logs`.
**Verified:** new `AccessoryExportTest` (writes the file, checks the names and the log); full PHPStan clean, baseline
2,500. **Left:** the dead Booking dashboard helpers, spare-master relations and production RBAC seeder need your OK
to delete (BUG-221).

### W15 — pricing session + vehicle content off the DB facade

**Delivered:** the pricing process's change log (used by Discard) and impact summary, and the vehicle content screens,
now reach the database only through Eloquent models (new `SessionChange` model). Discard still restores rows exactly as
they were.
**Verified:** Pricing + Vehicle feature tests (98) passed; PHPStan clean on the changed files.
**Baseline now:** 460 `DB::` uses in 68 files.

### W15 — pricing rule testers, accessory import and pricing reset off the DB facade

**Delivered:** the RTO / insurance rule testers, the accessory catalogue import and the local pricing reset now use
the Eloquent models only. The reset's queue flush, which had silently done nothing, now really clears the queue (BUG-222).
**Verified:** 41 unit / feature tests + the 98 Pricing / Vehicle feature tests passed; PHPStan clean. The reset itself is not
run in tests (TRUNCATE would empty the test database).
**Baseline now:** 441 `DB::` uses in 64 files.

### W15 — platform services off the DB facade (settings overrides, comms, chat, tickets, templates)

**Delivered:** the platform utilities — settings overrides, SMS OTP, consent and suppression lists, sandbox drivers,
timeline subscriptions, ticket numbering and template usage counts — use Eloquent models only (7 new models).
**Verified:** a new test covers the stores that had none (OTP issue / wrong code / correct code / reuse; consent and
suppression; subscribe / unsubscribe; branch override and clear; outbox counts); Platform tests 203 passed; PHPStan clean.
**Baseline now:** 414 `DB::` uses in 54 files.

### W15 — Org / data-scope services off the DB facade

**Delivered:** organisation and data-scope lookups (code resolution, ALL expansion, scope trees, derived codes,
customer lookup by enquiry / booking / VOTF) read through the models; the scope configuration now names models.
Deleted masters no longer resolve.
**Verified:** IAM, Org, Sales and service tests (210) passed; PHPStan clean.
**Baseline now:** 399 `DB::` uses in 50 files.

### W15 — users / RBAC workbook export off the DB facade

**Delivered:** the users / RBAC workbook export reads through models only (3 new read models for the Spatie pivots);
the workbook content is unchanged.
**Verified:** Org tests (33, incl. the RBAC workbook) passed; PHPStan clean.
**Baseline now:** 377 `DB::` uses in 49 files.

### W15 — dashboard and booking services off the DB facade

**Delivered:** the dashboard widgets and the booking exchange / KYC / OTF services read through models (2 new
models); the numbers and screens are unchanged.
**Verified:** a new test calls every converted widget; Sales, Dashboard and booking-service tests passed; PHPStan clean.
**Baseline now:** 362 `DB::` uses in 45 files.

### W15 — booking code (booking team's merged work) brought under DEC-093, with a shareable log

**Delivered:** owner instruction 01-10 — the booking team's final push is merged (`origin/stage` has nothing newer), so
the booking code is converted too, each change numbered, logged for the booking team in `docs/booking-team-changes.md`
(where, what, why, before → after, how checked, how to revert) and committed on its own:
- BT-001 financier-statement lookups (OTF form, DO amount, TA statement) → `FinancerStatement` model;
- BT-002 single-table lookups (consultant, delivered / RTO ids, variant colours, accessory, person / employee fallback).
Every change is checked before and after with the new `dev:route-snapshot` command: every screen / AJAX call reaching
the changed code, as superadmin and a scoped user, rolled back — BT-001 14 / 14 and BT-002 80 / 80 responses identical;
Sales tests 74 passed.
**Found:** a full sweep of all 90 booking screens on the unchanged code — 17 screens already fail (BUG-122 and the
chassis endpoint, known; BUG-223 / 224 / 225, new).
**Also:** user documentation (manual + help texts) moved to the end of the to-do list (your instruction).
**Baseline now:** 349 `DB::` uses in 45 files. **Left:** the rest of `BookingCrudController` (grid base query,
lookups, reports), `QuotationCrudController`, `EnquiryCrudController`, `ImportEnquiriesJob`, `SalesImportController`,
the other controllers, console, imports, tests.

### Booking grids (BT-003), execution plan and decision sheet — end of day 01-10

**Delivered:**
- **BT-003** — the booking grid base query (feeds ~30 list tabs), live-order counts and grid lookups no longer use the
  `DB` facade; the booking ↔ enquiry reference match, written three times, is one helper with the same SQL. Checked
  with `dev:route-snapshot`: all 41 list tabs 82 / 82 and the per-booking pages 80 / 80 identical (superadmin + user 40);
  Sales tests 74 passed. Logged in `docs/booking-team-changes.md`.
- **Execution order to go-live** — `tech-guides/frs-and-workflows/plans/2026-10-01-execution-order.md`: open bugs (34),
  pending to-do rows, decisions and clean-up ordered by dependency in phases 0–10 (dead code before conversion, booking
  convert + carve-out in one touch, keys before data migration, formats before importers, HR data before approvals,
  QA before user docs); `docs/todo.md` §12 points to it.
- **Owner decision sheet** — `docs/owner-decisions-2026-10-01.md`: 37 decisions by phase, with recommendations and an
  answer column.
**State of the booking controller:** done except the 5 reports (already 500, BUG-122 — decision D23) and the uncalled
`fetchCbrData()` / `fetchPendBkData()` (deletion list).
**Baseline now:** 314 `DB::` uses in 45 files (473 at the start of the day).
**Left:** BT-004 onward — `QuotationCrudController`, `EnquiryCrudController`, `ImportEnquiriesJob`,
`SalesImportController`, other controllers, console, imports, tests; owner answers to the decision sheet.

## 02-10-2026

### W15 — quotation and enquiry screens (BT-004, BT-005)

**Delivered:**
- **BT-004** — the quotation screens (list, create, edit, history, history PDF, preview) read vehicle names and the
  booking map through the models. Neither database has quotations yet, so the before / after check seeds one inside
  each rolled-back request (`dev:route-snapshot --setup=tests/RouteSnapshots/quotation-fixture.php`): 16 / 16 identical.
- **BT-005** — the enquiry screens (OTF list / detail, edit, view, finance / exchange edit / view, grid data of 9 list
  types) and the follow-up writes (CRE follow-up save, finance / exchange remarks) through models; new models
  `CRM\OtfBooking`, `CRM\CreFollowup`, `CRM\FinanceExchangeFollowup`. 38 / 38 responses identical; a new write test
  (`EnquiryFollowupWritesTest`) passes before and after (the OPEN placeholder stays a hard delete).
- **Snapshot tool** — `--setup` fixtures; requests keyed by their JSON body; CSRF skipped in-process; inline CSRF tokens,
  DD-MM-YYYY dates and error references ignored; run with `APP_DEBUG=false`.
**Found:** **BUG-226** — the enquiry view page crashes for any enquiry with a CRE follow-up (`cre_lost_reason`); added
to decision #10.
**Baseline now:** 271 `DB::` uses in 43 files. **Booking team's code:** done except the importers (phase 5),
the reports (D23) and uncalled helpers (deletion list).

### W15 — the unblocked part of DEC-093 done (BT-006, BT-007, platform, console, importers, all tests)

**Delivered:**
- **BT-006** — journal-voucher / receipt lists and number sequences through the models; `AccountsNumberingTest` pins the
  numbering (a series continues from rows the user cannot see). **BT-007** — `Enquiry::scopeMainListing()` `selectRaw`,
  same SQL (hash compared).
- Platform: comms screens and webhook (`CommWebhookEvent` model), admin vehicle-import lookups.
- Console / importers: `data-scope:backfill` (report and `--apply` runs compared statement by statement: identical),
  `RefreshTestingDatabase` (schema builder), the legacy user importers, the role backfill seeder.
- All 24 test files off the DB facade (query counting through `QueryExecuted` events).
**Verified:** each change compared before / after (screens, SQL or tests); full suite **573 passed, 1 skipped**.
**Left (126 uses in 8 files, all blocked):** deletions (#6, D5), booking reports (D23), spares (D28), the
enquiry / sales importers (phase 5, after the formats sign-off), `ai:refresh-context` schema cards (#21 exemption).

### Owner decisions closed (02-10, DEC-095)

**Closed questions (answered on the decision sheet):** D1 / D2 / D3 (app OTP + API security — build), BUG-207 / 209
(build), D23 (rewrite the booking reports), D13 (keep the dead links as "coming soon"), D16 (permission), BUG-223–226
(fix), BUG-219 (Dummy bookings validated), D21 (keep the BEV / Personal SO rule and make it work), booking carve-out with
the conversion (yes), BUG-206 (generated person code), D25 (codes with the colour suffix + re-import), HR gaps (no
mapping — the user data is refreshed; reset command built, run on your list), D26 (mask old KYC rows), DEC-093 defaults
(confirmed, schema tooling exempt), N4 (values in Settings), Redis (yes), Playwright for E2E (yes); N2 merge only on your
prompt; F2 / F3 left open; S6 2FA not now; D14 / S15 / runbook later; UAT settings no; D28 / D4 after go-live.
**Still open:** D29 key rotation, deletion list (#6), DEC-090 `ALL`, permission grants (#32), the pricing section (#22–27).
**Next:** to-do W18a–m, in order.

### W18a / W18b — app login fixed, app API access closed (DEC-095 #1–3, #5)

**Delivered:** the mobile-app OTP login works again (it answered 500 for every number): the user is found by the
person's primary mobile, the responses carry the person's name / mobile / e-mail, the code is generated securely, and a
token's expiry no longer resets when its row changes (BUG-227, found on the way). The app's history and document
endpoints accept only registered record types and check that the user may see the record (and own the document group);
the full settings API is for settings managers only — the app keeps `/app-settings`.
**Verified:** new API tests for the login, record access and settings (23 API tests passed); PHPStan clean.
**Found:** BUG-228 — the OTP SMS is still a placeholder (e-mail only); needs the SMS vendor / DLT details.
**Tell the app team:** use `/app-settings`; history / documents take entity codes (`BOOKING`, …) or the short names.

### W18c — booking screen bugs fixed (DEC-095 #10–12; BT-008 … BT-013)

**Delivered (six numbered, separately revertable booking-team changes, `docs/booking-team-changes.md`):**
- BT-008 (BUG-223): the finance view / payout edit of a booking with no finance record return to the finance list with a
  message (was a 500).
- BT-009 (BUG-224): "View" on the Invoiced list opens the booking.
- BT-010 (BUG-225): the refund view opens (missing receipt-log data).
- BT-011 (BUG-226): the enquiry view reads the CRE lost reason from the enquiry.
- BT-012 (BUG-219): a Dummy booking is refused, with nothing saved, when the customer, branch / location, vehicle or sale
  type is missing (was a database error).
- BT-013 (BUG-101, D21): the "BEV / Personal without a DMS SO → order 3" rule fires. It uses the segment codes BEV / PV
  and the segment of the enquiry or the model; the DMS form shows the SO field for those bookings.

**Verified:**
- `BookingBugFixesTest`, `EnquiryFollowupWritesTest`, `BookingDmsServiceTest` and `BookingFlowTest` pass.
- Route snapshots before and after each change as superadmin and user 40: only the fixed screens changed.
- PHPStan clean on the touched files.

**UAT-visible:**
- The Dummy validation message.
- For BEV / PV bookings, the SO field on the DMS form and order 3 when it is left empty (owner-approved rules).

**Left:** nothing in W18c. Next is W18d (D16 whitelists → permission).

### W18d — booking approvals by permission, not user ids (DEC-095 #9, D16; BT-014)

**Delivered:** new permission `SLS_BKNG_ORDER_APPROVE` (migration, run on `xlrm` + `xlrm_testing`). Order Verification
shows Accept / Reject only to its holders, and the `order-update` action requires it. The hard-coded ids `[5, 23, 123]`
pointed at unrelated people in this database, so nobody (superadmin included) could act before. The two id lists that
did nothing (Pending Order, Pending DMS) are removed with no change in behaviour. The permission tree labels it "Order
Approval". Guide `tech-guides/modules/sales-booking.md` updated (also the BT-012 / BT-013 rules and the order codes).
**Verified:**
- New `BookingBugFixesTest` case; 17 booking tests pass; IAM tests pass; PHPStan clean.
- Route snapshots as superadmin and user 40: only superadmin's action cell changed.
**Owner to do:** grant `SLS_BKNG_ORDER_APPROVE` to the approving designation(s).
**Found:** BUG-229 — Accept / Reject do not match `orderUpdate()` (Accept refused; Reject recorded as "hold released").
Needs the owner's rule for Reject.

### W18e — menu items without a screen show "coming soon" (DEC-095 #8, D13; BUG-056 / 062)

**Delivered:** one admin page, `/admin/coming-soon?feature=…`, names the feature and links back to the dashboard. All 59
rendered menu items that led to a 404 or `#` now open it: the booking ones are BT-015; the rest are CRM, refunds,
schemes, cashier, fee collection, accounts and others. Labels and icons are unchanged. Commented-out items are untouched.
**Verified:**
- New `MenuLinksTest`: no rendered menu link lacks a route, and the page escapes its input.
- Dashboard + page as superadmin and user 40 → 200; lang tests pass.
**Left:** each item gets its real route when its screen is built (rule in `tech-guides/platform/ui-kit.md`).

### W18g — schema tooling exempt from the Eloquent-only guard (DEC-095 #21)

**Delivered:** the DEC-093 guard has an explicit, reasoned exemption list. Its only entry is `RefreshAiContext`, which
reads `information_schema`. The file left the W15 baseline (now 7 files), and the rule text in every guideline copy
says so.
**Verified:** architecture test passes.
**Left:** W15's remaining 7 files are all waiting on owner answers (deletions #6, D5, D23 reports, D28, importers phase 5).

### W18h — old Aadhaar / PAN copies masked (D26, DEC-095 #19; BUG-195 closed for old rows)

**Delivered:** `privacy:mask-kyc-history` reports, masks (`--apply`) and undoes (`--restore`) the Aadhaar / PAN copies
kept in history: the booking timeline and the change log. It finds them by key, also inside nested JSON, so other
numbers are never touched, and keeps every original encrypted. Run on local `xlrm` and `xlrm_testing`: 24 Aadhaar and
20 PAN values in 25 cells. The KYC record itself is unchanged.
**Verified:**
- `KycHistoryMaskingTest`; a real apply → restore → apply cycle on the test copy.
- Booking 10 timeline renders for superadmin and user 40.
- PHPStan and the architecture guard are clean.
**Left:**
- Running it on UAT / production needs the owner's approval (non-local data change).
- Encrypting Aadhaar / PAN at rest and the other DPDP items stay under S12.

### W18j — user reset command ready (owner #18, DEC-095)

**Delivered:** `php artisan users:reset --keep=… [--apply]` (local only). It keeps the listed login accounts with their
roles, scopes and employee record, and removes every other user, employee and person permanently. Customer persons
used by enquiries are kept. It reports first, refuses unsafe lists (no superadmin, unknown names), and dumps the 22
affected tables before removing anything.
**Verified:** feature tests; local dry run; backup dump (22 tables); PHPStan clean.
**Left:**
- Run it when the owner sends the account list (1 super admin, 5 dev, 1 app dev). Pass
  `--bin-dir=D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin` (`MYSQL_BIN_DIR` is not in the local `.env`).
- Then the new user import (W10 workbook).

### W18k part 1 — sign-in limits editable as site settings (N4, DEC-095 #28)

**Delivered:** Settings → Security now holds the admin sign-in lockout (wrong passwords, lock minutes) and the mobile
app's OTP limits (validity, requests per window, wrong OTPs, lock length, device limit). Defaults are the previous fixed
values, so there is no change until edited. Idle logout, password rules and self-service changes were already
settings.
**Verified:** new lockout / OTP tests; 76 API / IAM / Utils / admin-auth tests pass; PHPStan clean.
**Left (part 2):** password expiry and password history do not exist yet. They will be added switched off by default
(0 = off), as settings.

### W18k part 2 — password expiry and history (N4, DEC-095 #28)

**Delivered:** two new Security / Account settings, both off by default:
- "passwords expire after N days": the user is sent to My Account to choose a new one;
- "a new password may not repeat the last N".
Each password change is now dated and kept, hashed, in a history table. With W18k part 1, every value in the owner's
N4 list (idle logout, lockout, password rules / expiry / history, self-service changes) is now a site setting.
**Verified:** `PasswordPolicyTest`; 36 IAM / Lang / architecture tests; smoke of dashboard, My Account and bookings for
superadmin and user 40.
**Left:** the owner chooses the values in Settings.

### W18l — new persons get a generated code instead of their Aadhaar / PAN (BUG-206, DEC-095 #15)

**Delivered:** every person created from now on is keyed `PERS-######`. The same person is still recognised on re-import
or re-entry by Aadhaar, PAN or TAN (a deleted one is restored), so there are no duplicates.
**Verified:** person and org suites: 46 tests, including the users workbook round-trip and a new dedupe test.
**Left:** the old codes of the persons that survive the user reset (W18j) are remapped afterwards, keeping an
old → new map for rollback, with the owner's go.

## 03-10-2026

### W18m (Redis part) — ready for Redis; duplicate-job risk fixed (DEC-095 #33, BUG-230)

**Delivered:**
- The app needs no code change to move cache, sessions and queue to Redis. IT sets five `.env` values per
  environment (guide in `16-reference.md` §4, notes in `.env.example`).
- On the way, fixed BUG-230: the queue gave up on a job after 90 s while pricing imports may run 30 minutes, which with
  two workers would run an import twice. The limit is now 1900 s, and a test keeps it above every job's timeout.

**Verified:** `QueueRetryAfterTest` fails on the old setting and passes on the new.
**Left:**
- Playwright E2E waits for the owner's go to install `@playwright/test` plus a Chromium download.
- IT provides Redis for UAT / production.

### W16b — F1 help on every screen (DEC-094)

**Delivered:** pressing F1 (or the new `?` in the top bar) on any admin screen opens a help pane on the right with that
screen's article, a search box and a link to the Help centre. Articles are Markdown files kept with the code. They can
hide sections from users without a permission, and screens without an article show a friendly "not written yet". The
Help centre lists the articles by module and, for settings managers, how many screens still lack help.
**Verified:** 4 new tests plus 91 related tests; screens checked for superadmin and a scoped user; PHPStan clean.
**Left:**
- W16c page tours (Driver.js), W16d diagnostics, W16e support requests, W16f guide.
- Writing the articles themselves is last (§13).
