# Accomplishments — 01-10-2026

Tasks completed today, with full details (the date-wise copy). The same entries are in `docs/todo.md` Part 2 under
`## 01-10-2026`; append every new entry to **both**.

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
