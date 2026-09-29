# Accomplishments — 29-09-2026

Tasks completed today, with full details (the date-wise copy). The same entries are in `docs/todo.md` Part 2 under
`## 29-09-2026`; append every new entry to **both**.

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
