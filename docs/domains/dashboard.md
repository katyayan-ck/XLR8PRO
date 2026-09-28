# Dashboard — dynamic, permission- and scope-aware (DEC-072)

What each user sees on `/admin/dashboard`: the cards their **permissions** allow, with numbers counted inside their
**data scope** (DEC-071), for a chosen period. Designations are never named in code — a new role gets the right cards
through its permissions.

| Piece | Where |
|---|---|
| widget registry | `config/dashboard.php` (`groups`, `widgets`, `cache_seconds`, `open_enquiry_stages`) |
| page / endpoint | `App\Http\Controllers\Admin\DashboardController` — `index()` (`backpack.dashboard`, `?period=`), `widget($key)` (`dashboard.widget`, JSON) |
| numbers | `App\Services\Dashboard\DashboardService` — one method per widget |
| periods | `App\Services\Dashboard\DashboardPeriod` |
| view / script | `resources/views/admin/dashboard/index.blade.php`, `public/js/xl-dashboard.js` (ApexCharts 3.54.1, Tabler tokens, rebuilt on theme change) |
| indexes | migration `2026_09_28_200421_add_dashboard_indexes` |

## Adding a widget
1. Add a method to `DashboardService`: `public function myWidget(DashboardPeriod $p, User $u): array`, returning one of
   - kpi `['value' => int|float, 'format' => 'number'|'money', 'lines' => [['label', 'value', 'format'?, 'tone'?]], 'hint' => ?string]`
   - chart `['labels' => [...], 'series' => [['name', 'data' => [...]]], 'format' => …]`
   - list `['columns' => [...], 'rows' => [[...]], 'empty' => 'text']`
2. Add it to `config/dashboard.php` `widgets`: `title`, `group`, `type` (`kpi` / `chart` + `chart` = `bar` | `donut` | `funnel` / `list`),
   `permission` (or `null` for everyone), `method`, `icon` / `link` (route name) for KPIs, `size` (column classes).
3. Count through data-scoped models (`HasDataScope`) or `DataScope::apply($rawQuery, Entity::class, 'alias')` for joins.
   Personal queues (approvals, tasks) are per user instead.

The endpoint re-checks the widget's permission (403) and caches the result for `cache_seconds`, keyed on
user id + `DataScope::current()->hash()` + period — so two users with the same scope never see each other's cached numbers
unless their scopes are equal.

## DashboardPeriod
| Member | Returns |
|---|---|
| `make(?string $key, ?CarbonImmutable $now = null)` | `today` / `week` (Mon–Sun) / `month` (default) / `quarter` / `fy` (1 Apr – 31 Mar); unknown key → month |
| `label()` | "This month" … |
| `between()` | `[from, to]` datetime strings for `whereBetween` |
| `KEYS`, `DEFAULT` | the period switcher |

## DashboardService — widgets and their definitions
| Method (widget) | Permission | Definition |
|---|---|---|
| `approvals()` | `UTL_APPR_VIEW` | `ApprovalService::inbox(TO_ACT)->total()`; raised-by-me open |
| `tasks()`, `tickets()` | `UTL_TASK_VIEW`, `UTL_TCKT_VIEW` | `inboxCounts()` (assigned / created / following) |
| `notifications()` | everyone | `NotifyService::counts()` unread |
| `enquiries()` | `SLS_ENQR_VIEW` | open = `stage` in `open_enquiry_stages` (Enquiry, Test Drive, Quotation, Booking, Postponed — user decision); new / lost by `enquiry_date` in period |
| `followups()`, `followupList()` | `SLS_ENQR_VIEW` | `xlr8_crm_enquiries_fup` `followup_status = Open`, by `planned_followup_date` (today / overdue / next 7 days), scoped through the enquiry |
| `testDrives()` | `SLS_ENQR_VIEW` | `td_created_date` in period, completed, scheduled today; scoped through the enquiry |
| `quotations()` | `SLS_QUOT_VIEW` | raised (`created_at`) in period, `pending_approval`, approved |
| `funnel()` | `SLS_ENQR_VIEW` | enquiries → test drives → quotations → bookings (non-cancelled) → delivered, all in period |
| `enquirySources()` | `SLS_ENQR_VIEW` | new enquiries by `current_origin` |
| `liveBookings()` | `SLS_BKNG_VIEW` | `Booking::live()`, `pendingData()`, `onHold()` |
| `newBookings()` | `SLS_BKNG_VIEW` | `booking_date` / `inv_date` / `cancel_date` in period |
| `deliveries()` | `SLS_BKNG_VIEW` | invoiced (status 2) with `del_date` in period, delivered (delivery row status 1) vs pending — user decision |
| `pendingSteps()` | `SLS_BKNG_VIEW` | pending KYC; invoiced bookings without insurance / RTO / delivery rows |
| `bookingsByModel()` | `SLS_BKNG_VIEW` | bookings in period by `model_code` ("Not set" until codes are filled) |
| `receipts()`, `receiptModes()`, `receiptList()` | `ACC_RCPT_VIEW` | `xlr8_booking_amount` type 1 (plus legacy rows with no type) by `date`; amount is `CAST(amount AS DECIMAL)` |
| `journalVouchers()` | `ACC_JRVCH_VIEW` | type 2 in period |
| `refunds()` | `ACC_RCPT_VIEW` | queued (status 4), refunded / rejected in period, refunded amount (`Xl_Refunds.ref_date`) |
| `stock()`, `stockByModel()` | `SLS_BKNG_VIEW` | free received stock, in transit, allotted-not-invoiced; **not data-scoped** (legacy location ids) |
| `catalogue()` | `VEH_VAR_VIEW` | active variants / models / segments |

## Gotchas
- Don't use the `Booking::pending*` scopes that point at old `xcelr8_*` tables (BUG-191); the definitions above are the
  working ones.
- `xlr8_crm_enquiries.origin` is a real column — alias grouped expressions differently (`GROUP BY` resolves to the column).
- Legacy money columns are varchar: always `CAST(… AS DECIMAL(15,2))`.
- Tests: `tests/Feature/Dashboard/DashboardTest.php` (permission gating, 403 / 404, scope, FY bounds).
