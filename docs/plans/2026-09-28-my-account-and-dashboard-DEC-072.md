# Plan: My Account rebuild and a dynamic, scope-aware dashboard (DEC-072)

## Context
The user asked for two pages (28-09):
1. **My Account.**
   - What it shows, by user type (employee users in full):
     - personal info;
     - designation;
     - primary and add-on branch, location, department, division, vertical, segment, sub-segment, model and variant scopes (read-only);
     - employment history;
     - contact info;
     - reporting manager.
   - What the user can change:
     - the password, after validating the current one;
     - their display name;
     - their profile image.
   - Header: name and image, with the designation under the name.
2. **Dashboard.** A dynamic page built per role and permission, showing data within the user's data scope. Examples:
   - Sales: open enquiries, today's and pending follow-ups.
   - Accounts: receipts and refunds.
   - Everyone else: open bookings, pending approvals, quotes, vehicles, stock, and deliveries lined up for today / this week / this month.

**Today (read-only exploration):**
- **My Account** is stock Backpack (`MyAccountController`, view `vendor/backpack/theme-tabler/my_account.blade.php`). It is broken:
  - it reads and writes `users.name`, which doesn't exist;
  - it has no photo;
  - the top-bar avatar uses Gravatar, and users have no email, so it always shows initials.
- **Dashboard** (`DashboardController`, `vendor/backpack/ui/dashboard.blade.php`) is a static profile card plus a banner. An older KPI view has every value hard-coded to 0.
- **Building blocks that exist:**
  - `Person.display_name` and the `profile_photos` media collection, written by `Person\PersonRecordService` (`profile_photo`, `remove_profile_photo`);
  - `EmployeeJourneyService::journey()` (history);
  - `OrgService::getUpline()` / `UserReportingService::getDefaultReportingManager()`;
  - `ScopeResolver` (effective scope) and the "Effective data access" panel;
  - the dev UI kit dashboard (KPI cards, ApexCharts, theme-aware JS);
  - automatic data scoping (`HasDataScope`, `DataScope::apply()` for raw queries);
  - `TaskService` / `TicketService` / `NotifyService::counts()` / `ApprovalService::inbox()`.

## A. My Account (`/admin/edit-account-info`, same route names as Backpack)
- **Controller:** `App\Http\Controllers\Admin\Account\MyAccountController`, bound in place of Backpack's through the same route names (`backpack.account.info`, `.store`, `backpack.account.password`). Each action is gated on being logged in (no module permission; it is the user's own record).
- **Service:** `App\Services\IAM\MyAccountService` assembles one read model:
  - identity (`display_name`, username, `user_type`, photo URL, initials, designation name);
  - person (name parts, DOB, gender …);
  - contacts (mobiles, emails, primary address);
  - employee (code, joining / confirmation dates, employment type / status, primary branch / location / department / division / vertical / segment / sub-segment with names);
  - add-on scopes (`getAllScopes()` minus primaries) and the effective scope (`ScopeResolver`);
  - reporting manager (name, designation, photo);
  - history (`EmployeeJourneyService::journey()`, newest first).
- **Layout (Tabler, tokens only, responsive):**
  - **Header card:** avatar (photo, else initials), display name, designation underneath, user-type badge, primary branch / location.
  - **Tabs (or stacked cards on phones):**
    - Profile: personal info plus editable display name and photo.
    - Organisation & access: primaries, add-ons, and the effective-access table, shared as a partial with the User screen.
    - Employment history: a Tabler timeline of designation / transfer / scope changes with dates and reasons.
    - Contact.
    - Security: change password.
  - **Non-employee user types** (Associate / DSA / Customer / Insurer) see Profile, Contact and Security only.
- **Writes:**
  - Display name and photo go through `PersonRecordService` (the only write path), using the shared `<x-ui.upload>` with image types from Settings.
  - Password: current password checked with `Hash::check`; new password confirmed and must differ from the current one; other sessions are logged out, as today.
  - Username is read-only (it is the login id).
- **Top bar:** `avatar_type` → a User `avatar_url` accessor (person photo, else null → initials), which removes the Gravatar call (D17).

## B. Dynamic dashboard (`/admin/dashboard`)
- **Widget registry** `config/dashboard.php`: each widget has a key, title, permission (`can()`), size, group, type (kpi / list / chart) and a provider class. The page shows only the widgets the user may see, grouped as below. Designations aren't hard-coded, so a new role gets the right cards through its permissions.
- **Providers** `App\Services\Dashboard\Widgets\*`:
  - They return `{value, delta, series, rows}` for a period (Today / This week / This month / This FY / custom). The default is This month, with a "last activity" hint when the period is empty.
  - All counts go through scoped models, or `DataScope::apply()` for raw joins, so every number is the user's own scope.
  - Counts are cached for 5 minutes, keyed on user id + scope hash + period.
  - Heavy widgets load lazily through `GET /admin/dashboard/widget/{key}?period=` (same permission check).
- **Widget groups** (holders shown for reference; gating is by permission):
  - **Sales** (`SLS_ENQR_VIEW`):
    - open enquiries;
    - new enquiries in period;
    - today's follow-ups, overdue follow-ups ("mine" for consultants via `mile_id`, scope-wide for managers);
    - test drives in period;
    - funnel: Enquiry → Test drive → Quote → Booking → Delivery;
    - enquiries by source (donut);
    - today's follow-up list.
  - **Bookings** (`SLS_BKNG_VIEW`):
    - live / pending-data / on-hold bookings;
    - bookings in period by model (bar);
    - pending KYC / DMS;
    - deliveries lined up today / week / month;
    - pending deliveries, insurance, RTO (the controller's working definitions; not the broken `pending*` model scopes).
  - **Quotations** (`SLS_QUOT_VIEW`): raised in period, pending approval.
  - **Accounts** (`ACC_RCPT_VIEW`): receipts count and amount (today / period, by mode), JVs, refunds queued / refunded in period with amounts.
  - **My work** (everyone): approvals to act on (`UTL_APPR_VIEW`, `inbox(TO_ACT)->total()`), my open tasks (`UTL_TASK_VIEW`), my tickets (`UTL_TCKT_VIEW`), unread notifications.
  - **Vehicles & stock** (`SLS_BKNG_VIEW`; vehicle masters `VEH_VAR_VIEW`):
    - vehicles in stock (free received), in transit, allotted-not-invoiced;
    - stock by model;
    - variants / models count.
- **UI:** the dev-kit dashboard patterns (KPI cards with sparkline and delta, chart cards, list cards, ApexCharts 3.54.1 via `@basset`, `XL.theme.token`, rebuild on theme change), a period switcher in the page header, and an empty state per card. The current static view and the orphan `admin/dashboard.blade.php` / `admin/widgets/*` are replaced (deleted).
- **Performance migration** (guarded, reversible, local only): indexes on
  - enquiries `(stage, enquiry_date)`, `(dealer_branch, dealer_location)`, `sc_mile_id`, `next_planned_followup_date`;
  - follow-ups `(followup_status, planned_followup_date)`, `sc_mile_id`;
  - test drives `enquiry_no`;
  - satellite `bid` columns.
  Counts use conditional aggregation (one scan per table).

## Order and commits
0. Merge DEC-071 to stage after the smoke sweep (approved: "push to remote and merge in Stage").
1. DEC-072 logged; My Account (service, controller, views, avatar accessor, tests); commit.
2. Dashboard: registry, providers, endpoint, view, indexes, tests; commit.
3. Guides (`docs/domains/iam-auth.md`, new `docs/domains/dashboard.md`, `ui-kit.md`), changelog, state; full suite + smoke; ask before pushing.

## Verification
- **Feature tests (My Account):**
  - the page shows scopes and history for an employee;
  - a non-employee sees Profile / Contact / Security only;
  - the display name and photo update the Person;
  - a wrong current password is rejected;
  - a correct one changes the password;
  - the username can't be changed.
- **Feature tests (dashboard):**
  - a user without `ACC_RCPT_VIEW` gets no receipt widget (and a 403 on its endpoint);
  - a scoped user's counts exclude out-of-scope rows;
  - the period filter changes values;
  - the cache key includes the scope hash.
- **HTTP smoke and screenshots:** superadmin, a Sales Consultant, an Accounts Executive and a Sales Manager; light and dark; 1366 and 390 px.
