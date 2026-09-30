# Execution order to go-live — dependency-ordered (01-10-2026)

> **Status (01-10-2026): 📝 proposed to the owner.** Built from a full review of open bugs (34), pending to-do rows,
> owner decisions (D1–D29, N1–N4) and clean-up items. The rows stay in `docs/todo.md`; this file fixes their **order**
> and says **why**, so nothing is done twice. Replaces `docs/todo.md` §12 "Suggested order".

## Dependencies that drive the order
1. **Dead code before conversion.** The Booking model's 9 dead helpers (27 `DB::` uses), the Booking scopes (BUG-191),
   `getChassisNumbers` (BUG-153), `getAccessoriesList`, Brand (BUG-009), ExportController (BUG-180), RBACService
   (BUG-190), the spare master / RBAC seeder (BUG-221). Converting them to Eloquent (W15) and then deleting them is wasted
   work. **Delete first (D5–D12), then convert.**
2. **Booking reports (BUG-122, D23).** 5 reports + their lists read tables that do not exist. Converting them is
   pointless until D23 says "rewrite on current tables" or "retire". Rewrite them once, directly on models.
3. **Booking controller: convert and carve out in one touch.** W15 (DEC-093) rewrites each query, and SL6 later moves
   the same code into `Booking*Service`s. Doing both per method group touches each block once. The numbered BT log
   keeps every step revertable. The user-id whitelists (BUG-095 / D16) become permission checks in the same touch.
4. **Menus and routes must settle before help (W16) and tests.** The dead menu links (D13) and the booking carve-out
   decide the final routes. The F1 help maps route → article, and E2E tests (Q3) click through menus.
5. **Keys before data migration.** `person_code` = PAN / Aadhaar (BUG-206) and the variant-code split (BUG-173 / V7 /
   DA2) change business keys across 14+ tables. Settle both before the vehicle re-import, the pricing sign-off run (V1)
   and the production data migration (DA1). Otherwise all three are repeated.
6. **Formats before importers.** The data dictionary / formats (F2 → F3 sign-off → F4) define the entity-service field
   rules. Rewrite the importers (`ImportEnquiriesJob`, `SalesImportController`) on entity services after F4, not before.
7. **HR data before approvals and scoping.** Designation mapping (BUG-183 / D24), missing primaries (BUG-218) and the 34
   disabled users (DA4) must be right before sales flows use approvals / notifications (SL3). The same goes for the
   permission review (S15) and granting new permissions (PRC_*, VEH_*, UTL_SUPP_*).
8. **Stable code before QA; QA before user docs.** Bugs fixed → QA vets → user manual + help texts (W17, owner 01-10).

## Phases
| Phase | Content | Needs from the owner | Unblocks |
|---|---|---|---|
| **0 — Decisions** (now) | Answer the decision list (below). | everything marked ⚑ | all phases |
| **1 — Security quick fixes** (≈1 day) | D2 `random_int` OTP (BUG-188); D3 model-class allowlist for `docs/upload` / `history` (BUG-182); D1 OTP login user fields (BUG-187); BUG-207 remainder; BUG-209; D29 key rotation | ⚑ approvals D1–D3; you rotate the key | mobile app login, safe API |
| **2 — Dead-code removal** (≈½ day) | D5–D12 + Booking dead helpers + BUG-221 remainder + unused pricing views; W15 baseline drops | ⚑ deletion list OK | less W15 work |
| **3 — Booking to project level** (≈3–4 days) | BT-series: convert + carve out per method group (grid, lookups, receipts, finance, RTO, delivery, refund, OTF); BUG-223/224/225; D16 whitelists → permissions; D13 dead links; D23 reports rewrite / retire; D21 BEV rule; BUG-219 dummy bookings; Sales tests grow per group (Q1). Then `QuotationCrudController`, `EnquiryCrudController` the same way | ⚑ D13, D16, D21, D23, BUG-219, carve-out approach | N2 merge, W16, Q3 |
| **4 — Merge + hand-over** (½ day) | full suite + smoke; merge `dev/admin` → `stage` (N2); send `docs/booking-team-changes.md` to the booking team | ⚑ merge OK, push | team works on the new base |
| **5 — Data foundations** (≈3–5 days, partly HR) | F2 answers → F3 sign-off → F4 central SSOT + F5 permission plan + F7 guard rails; BUG-206 person surrogate key; BUG-173 / V7 / DA2 variant codes + vehicle purge / re-import; D24 / BUG-183 / BUG-218 / DA4 HR data; DA5 / DA6 clean-up; D26 KYC masking; importers on entity services (W15 rest) | ⚑ F2 answers, BUG-206, D25, D26; HR fills data | V1, DA1, SL3 |
| **6 — Pricing sign-off** (≈2 days + your review) | V2 sheet fixes → V6 accessory re-import → V1 full run on real data + 3 hand checks; N1 COD; V9 business review; V10 retire old calculators; V5 grants; V8 app delta sync | ⚑ V1 numbers, N1, V9, D20 | quotations / bookings on real prices |
| **7 — Platform in sales + security features** (≈4–5 days) | SL3 (Notify / approvals / templates in sales flows); S3 token expiry, S4 persistent lock, S5 expiry / history, S7 email / mobile change (N4), S9 sessions page, S14 audit viewer, S6 2FA | ⚑ N4 values, 2FA package | UAT feature-complete |
| **8 — Help & support mechanism** (W16, ≈4 days) | engine + F1 pane, tours, diagnostics, support requests; developer guide | ⚑ grant UTL_SUPP_* later | support during UAT |
| **9 — Go-live readiness** (≈3–5 days, with IT) | O1 CI gate, O2 safer deploy, O3 backups, O4 cron, V4 queue worker, O7 Redis (also fixes the DB-cache query load), O5 / O6 monitoring + error tracking, S11 prod config, Q3 E2E with Playwright (same dev tool as the manual), O10 load test, DA1 migration rehearsal, O9 runbook + hypercare | ⚑ infra choices, packages, roster | UAT → go-live |
| **10 — QA sign-off, then user docs** | QA cycle on UAT; fixes; then W17 manual + help texts (§13) | QA team schedule | training |

Parallel, any time: remaining W15 in tests / console (no behaviour change); Q5 PHPStan baseline shrinks as Phase 3
lands; X-series extras only after Phase 9 (X5 is W16).

## Owner decisions and involvement (⚑), in the order they unblock work
See the chat hand-over of 01-10 and `docs/todo.md` §3. Each item has the agent's recommendation.
