# Changes to the booking team's code — numbered log (share with the booking team)

**Why these changes:** the project rule DEC-093 (01-10-2026, owner): no code may query the database through the `DB`
facade (`DB::table()`, `DB::raw()`, `DB::select()` …); every read and write goes through an Eloquent model. Owner
instruction 01-10: bring the booking code (merged from `stage`) up to the project level and log every change so the
booking team can see what changed, where and why, and revert any single change.

**Rules followed in every change**
- **Behaviour stays the same.** The same rows, columns, order and totals are returned. Where the old raw query also
  read soft-deleted rows or rows outside the user's data scope, the model query keeps that (`withTrashed()`,
  `withoutGlobalScopes()`), and the reason is noted in the entry. Plain result objects are kept (`->toBase()`) where
  the views read them as plain objects.
- **One numbered change = one commit.** The commit subject starts with the number (`refactor(booking): BT-007 …`).
- **Checked before and after.** Each entry lists the screens / AJAX calls that reach the changed code. They are
  requested as superadmin and as a scoped user (id 40) on the test copy (`xlrm_testing`), before and after the edit,
  inside a rolled-back transaction. Status codes and response bodies (with CSRF tokens and times removed) are
  compared, and the entry records the result. The related automated tests are run too.

**To revert one change:** `git revert $(git log --format=%h --grep="BT-007")` (use the entry's number). Each change
touches only the lines listed, so a revert does not affect the others. Changes that depend on an earlier one say so.

| # | Where (file · method) | What changed | Why | Checked | Commit |
|---|---|---|---|---|---|
| BT-001 | `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `liveNotInvoiced()` (trade-advance statement per row), `getDoAmount()`, `getTAStatement()` | Financier-statement lookups by DO number read through the `FinancerStatement` model | DEC-093 (no `DB::` queries). New model `App\Models\Module\Finance\FinancerStatement` (table `xlr8_financer_statement`). | OTF form (`sales/booking/otf-form`), `get-do-amount` (existing DO, unknown DO, empty), `get-ta-statement` (existing, `0`, unknown) as superadmin + user 40: 14 / 14 identical; booking tests 63 passed | `git log --grep=BT-001` |

## Entries

<!-- entries are appended below, newest last -->

### BT-001 — Financier-statement lookups by DO number read through the `FinancerStatement` model
- **Where:** `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `liveNotInvoiced()` (trade-advance statement per row), `getDoAmount()`, `getTAStatement()`
- **Why:** DEC-093 (no `DB::` queries). New model `App\Models\Module\Finance\FinancerStatement` (table `xlr8_financer_statement`).
- **Details (before → after):**
  - `DB::table('xlr8_financer_statement')->where('do_no', …)->whereNull('deleted_at')->orderByDesc('created_at')->first()`
    → `FinancerStatement::query()->where('do_no', …)->orderByDesc('created_at')->toBase()->first()` (3 places; the model's
    soft-delete scope replaces `whereNull('deleted_at')`; `toBase()` keeps the plain row object the code reads).
  - `use App\Models\Module\Finance\FinancerStatement;` added.
- **Depends on:** none
- **Checked:** OTF form (`sales/booking/otf-form`), `get-do-amount` (existing DO, unknown DO, empty), `get-ta-statement` (existing, `0`, unknown) as superadmin + user 40: 14 / 14 identical; booking tests 63 passed
- **Revert:** `git revert $(git log --format=%h --grep="BT-001")`
