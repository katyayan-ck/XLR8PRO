# Xceler8 (XLRM, Track A): go-live to-do list and status

**Date:** 29-09-2026. **Branch:** `dev/admin` (nothing pushed since the 28-09 stage merge).

**Sources:**
- decisions DEC-024 … DEC-083;
- changelogs 22-09 → 29-09;
- `docs/refactor/known-bugs-report.md` (27 open);
- `.ai/state/current.md` (owner decisions D1–D29);
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
| F2 | **Decide the format per family** | ⏸ P1 — proposals + 5 questions in `docs/reference/data-dictionary-draft.md` | See the proposal below the table. Some choices need you. |
| F3 | **Data dictionary** | 🟡 P1 — DRAFT written, awaiting sign-off | `docs/reference/data-dictionary.md` + a generated JSON. Per family: format regex, examples, owner, where it is stored, where it is used; per module: process list, activity list. Reviewed and signed off by you before any change. |
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
| D1 | Repair mobile OTP login: `users` has no `mobile`, so the app gets nulls (BUG-187) | **P0** | Mobile app login |
| D2 | `random_int` OTP instead of `rand()` (BUG-188) | **P0** | Security |
| D3 | v1 `docs/upload`, `history/{entityType}` accept any model class from input (BUG-182) | **P0** | Security |
| D4 | Lead lookups | P1 | Sales |
| D5–D12 | Deletions: Brand (BUG-009), ExportController (BUG-180), RBACService (BUG-190), Core graph models, getChassisNumbers (BUG-153), dead Org views (BUG-154), seeder test users, Booking scopes (BUG-191) | P1 | Clean-up |
| D13 | 52 dead menu links (BUG-056 / 062) | **P0** | UAT-visible |
| D14 | Import permissions (BUG-177) | P1 | Access |
| D16 | Hard-coded user-id whitelists in booking (BUG-095) | P1 | Access |
| D18 | Pricing | ✅ | Closed (BUG-178 fixed) |
| D19 | Accessory importer | ✅ | Closed (DEC-083) |
| D20 | RTO sheet id (BUG-029) | P1 | RTO import |
| D21 | BEV SO rule (BUG-101) | P2 | Booking |
| D23 | 5 booking reports 500 (BUG-122) | **P0** | Booking team |
| D24 | 36 employees on unknown designation codes (BUG-183) | P1 | Approvals routing |
| D25 | Variant code split (BUG-173) | P1 | Booking ↔ pricing |
| D26 | Mask old KYC rows | P1 | Compliance |
| D28 | Spares module rebuild (BUG-031 / 032 / 116) | ⏸ | Spares (hidden) |
| D29 | Rotate the Google API key | **P0** | Security |
| N1 | **COD in on-road** (setting `pricing.dealer_charges.include_cod`, off today) | P1 | Pricing numbers |
| N2 | Stage merge: origin/stage has 4 reverts by the booking team; merge `dev/admin` after your talk | **P0** | Deploy |
| N3 | Formats / permission naming (§2 F2) | P1 | §2 |
| N4 | Security policy values (§4: idle minutes, lock, password rules, self-service rules) | P1 | §4 |

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
| O7 | Cache / session / queue on Redis in prod | 🔴 | P2 | All `database` today; fine for UAT, slow at scale (BUG-198) |
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
| U1 | Standard form screens: same header style, minimal spacing, smaller font; every card collapsible + draggable (order remembered) with **required filled / total** in the header | 🔴 → in progress | P1 | See the U1 plan below the table. |
| U2 | Use the UI-kit elements for the best UX | 🟡 | P1 | Rule added; each screen converges when touched (`ui-design-progress.md` tracks the rest) |
| U3 | Font size + spacing / margin / padding controller | 🔴 → in progress | P1 | See the U3 plan below the table. |
| U4 | Caching / optimisation, lazy loading | 🟡 | P1 | See the U4 plan below the table. |
| U5 | Select2, flatpickr, badges, buttons, tabs, accordions wherever they fit | 🟡 | P1 | Rule added; the shared layer already upgrades native inputs; convert the dense legacy forms screen by screen |
| U6 | Guides updated on every change | ✅ rule | — | `.ai/rules/app.md` standing rule; the go-live wrap-up audits guides vs code |
| U7 | Central uniform error / response / exception handling with module-wise codes + messages | 🔴 | P1 | See the U7 plan below the table. |
| U8 | Custom error pages (403 / 404 / 419 / 429 / 500 / 503) | 🔴 → in progress | P1 | Branded pages in `resources/views/errors/`: logo, plain message, a link to the dashboard, a reference id on 500 (no stack traces) |
| U9 | These instructions in the project rules for all agents | ✅ 29-09 | — |
| U10 | Changelog + task status + handoff with every commit; commented + formatted code | ✅ rule (`.ai/guidelines/10-workflow.md`) | — | `.ai/state/handoff.md` |
| U11 | **Module-wise API docs** (request, params, validation, every response + a Postman v2.1 collection per module) | 🔴 → in progress | P1 | `docs/api/{module}.md` + `docs/api/postman/`; the existing `docs/api/*.md` are empty; start with pricing, auth, settings, docs / history, devices | `.ai/rules/ui.md`, `app.md`, `api.md` (synced to `.claude/rules`) |

**U1 plan:**
- One shared enhancement in `xl-ui.js` + `xl-ui.css` for every card inside a form: a collapse toggle, a drag handle
  (order saved per user + page), and a required-fields counter that updates live.
- A compact form density.
- A standard page-header partial.

**U3 plan:**
- Site defaults in Settings (`ui.density.font_scale`, `ui.density.space_scale`).
- A per-user override in the Appearance panel (font size S / M / L, spacing compact / cozy / comfortable), applied as
  CSS variables on `<html>`.

**U4 plan:**
- Images: `loading="lazy"` / `decoding="async"` everywhere (shared layer).
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

## 11. Deferred (after UAT)

- **Track B:** resume from xceler8 `d9009db`.
- **Framework / package upgrades:** Laravel 13, Excel 4, Permission 8, Firebase 8, PHPUnit 12 / 13, Swagger 11.
  Each is a major upgrade that needs your approval.

---

## 12. Suggested order

1. **This week (P0):**
   - owner decisions D1–D3, D13, D23, D29, N2;
   - S1, S3, S4, S11;
   - V1–V4, V7;
   - O1–O4, O9;
   - DA1, DA2.
2. **Next:**
   - §2 formats: F1–F3, then your sign-off, then F4, F5, F7;
   - S2, S5–S8, S10, S12, S15, S16;
   - SL3, SL4;
   - O5, O6, O8, O10;
   - Q1.
3. **After go-live:** P2 / P3 items and Track B.

Each implementation step follows the usual loop:
- a DEC entry before the change;
- tests;
- changelog;
- guides;
- a commit per phase;
- a push / merge only with your approval.
