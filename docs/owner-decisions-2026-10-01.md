# Owner decisions — 01-10-2026 (for your review and answers)

Write your answer in the **Your answer** column (a word is enough: "yes", "no", "rewrite", a value…). The plan these
unblock is `tech-guides/frs-and-workflows/plans/2026-10-01-execution-order.md` (phases 0–10). Items are grouped by the
phase they unblock; **Rec.** is the agent's recommendation.

## Phase 1 — security quick fixes
| # | Decision | Rec. | Your answer |
|---|---|---|---|
| 1 | **D2** — generate the app login OTP with `random_int` instead of `rand()` (BUG-188) | yes | |
| 2 | **D3** — `docs/upload` and `history/{entityType}` in the app API accept any model class from the request (BUG-182): allow only a fixed list of entity types | yes | |
| 3 | **D1** — app OTP login reads `mobile` / `email` / `name` that `users` does not have (BUG-187): read them from the person record | yes | |
| 4 | **D29** — rotate the Google API key (you do it; the agent updates the config reference) | yes | |
| 5 | **BUG-207 / BUG-209** — narrow or retire the remaining v1 `system-settings` endpoints (the app uses `app-settings`); fix `BaseController::authorize()` | yes, tell the app team | |

## Phase 2 — dead-code removal
| # | Decision | Rec. | Your answer |
|---|---|---|---|
| 6 | Delete: D5–D12 list (Brand screen BUG-009, ExportController BUG-180, RBACService BUG-190, Core graph models, `getChassisNumbers` BUG-153, dead Org views BUG-154, seeder test users, Booking scopes BUG-191); the Booking model's dead `vehicle()` + 9 dashboard helpers; the unused `getAccessoriesList()`, `fetchCbrData()`, `fetchPendBkData()` (booking controller, no callers); `XlSpareMaster` relations + `ProductionRBACSeeder` (BUG-221); unused `resources/views/admin/pricing/{hold,tcs}/index.blade.php`; branch `backup/dev-admin-before-rewrite-30-09` | yes to all | |

## Phase 3 — booking code to project level
| # | Decision | Rec. | Your answer |
|---|---|---|---|
| 7 | **D23** — 5 booking reports 500 (tables do not exist, BUG-122): rewrite them on the current tables, or retire them? | rewrite, if the team uses them | |
| 8 | **D13** — 52 dead menu links (BUG-056 / 062): remove, or keep as "coming soon"? | remove | |
| 9 | **D16** — hard-coded user-id lists `[5, 23, 123]` in booking (BUG-095) → a permission | yes | |
| 10 | **BUG-223 / 224 / 225 / 226** (finance view / payout-edit without finance, missing `invoiced-show` view, `refund-view` crash, enquiry view crash with CRE follow-ups): may the agent fix them as logged BT changes? | yes | |
| 11 | **BUG-219** — should a `Dummy` booking still need name, mobile, branch, vehicle, sale type? | | |
| 12 | **D21** — "BEV / Personal → order 3 when DMS SO missing": still a business rule, or drop the dead branch? (BUG-101) | | |
| 13 | Booking controller: convert each query **and** move it into its `Booking*Service` in one change (SL6 with W15), each step numbered and revertable | yes | |

## Phase 4 — merge
| # | Decision | Rec. | Your answer |
|---|---|---|---|
| 14 | **N2** — after phase 3: full suite + smoke, merge `dev/admin` → `stage`, push, send `docs/booking-team-changes.md` to the booking team | yes | |

## Phase 5 — data foundations
| # | Decision | Rec. | Your answer |
|---|---|---|---|
| 15 | **BUG-206** — `person_code` holds PAN / Aadhaar for 211 of 215 people: move to a generated person code, keep PAN / Aadhaar only as masked fields (largest data change; 14 tables) | yes | |
| 16 | **D25 / BUG-173** — variant codes without the colour suffix (booking) vs with it (pricing): keep codes **with** the suffix (DEC-051), then purge + re-import vehicle masters (V7 / DA2) | yes | |
| 17 | **F2 / F3** — the 5 format questions in `tech-guides/architecture/data-dictionary.md`, then sign off the data dictionary | | |
| 18 | **HR data** — map 36 employees on unknown designation codes (D24 / BUG-183); fill missing branch / location / department (BUG-218, 24–39 employees); who of the 34 disabled users gets access back (DA4) | HR to provide | |
| 19 | **D26** — mask Aadhaar / PAN in old KYC rows | yes | |
| 20 | **DEC-090** — users workbook `ALL` = unrestricted (also covers codes added later). Keep, or "today's codes only"? | keep | |
| 21 | **DEC-093** defaults — `DB::transaction` stays allowed; migrations exempt from the Eloquent-only rule | confirm | |

## Phase 6 — pricing sign-off
| # | Decision | Rec. | Your answer |
|---|---|---|---|
| 22 | **V1** — approve the numbers of a full run on real data (agent hand-checks 3 vehicles) | | |
| 23 | **N1** — include COD in on-road (`pricing.dealer_charges.include_cod`) | | |
| 24 | **V2** — reference-sheet fixes: CNG RTO rows (20 vehicles), Passenger insurance above 7 seats (12) | provide sheets | |
| 25 | **V9** — business review of the CSD / TZU / electric price-list columns | | |
| 26 | **D20** — RTO sheet id (BUG-029) | | |
| 27 | **V5** — which designations get the `PRC_*` pricing permissions; upload the site logo | | |

## Phase 7 — access and security policy
| # | Decision | Rec. | Your answer |
|---|---|---|---|
| 28 | **N4** — idle-logout minutes, lockout, password expiry / history, self-service e-mail / mobile change rules | | |
| 29 | **S6** — 2FA for admins: approve a package (e.g. `pragmarx/google2fa`) | yes | |
| 30 | **D14** — permission for the Imports menu / landing page (BUG-177) | `UTL_IMPORT_VIEW` | |
| 31 | **S15** — permission review with the business (who gets what) | schedule | |
| 32 | Grants: `VEH_CONT_VIEW` / `VEH_CONT_EDIT` / `VEH_CMPR_VIEW` now; `UTL_SUPP_ADMIN` / `UTL_SUPP_EXEC` when W16 ships | | |

## Phase 9 — go-live readiness and process
| # | Decision | Rec. | Your answer |
|---|---|---|---|
| 33 | UAT / production infrastructure: Redis for cache + queue (also removes the DB-cache query load), backups, CI gate before deploy, monitoring + error tracking (package), queue worker + cron on the servers | yes, with IT | |
| 34 | Use Playwright (dev-only, already approved for the manual) for end-to-end tests too (Q3) | yes | |
| 35 | Go-live runbook, hypercare roster, QA team start date | | |
| 36 | UAT settings: Settings → Site → Dealership name; load the vehicle sample workbooks (Vehicle Content → Workbooks) | | |
| 37 | **D28** spares module rebuild; **D4** lead lookups — when? | after go-live | |
