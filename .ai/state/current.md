# Current state (keep ≤ 50 lines; update at every checkpoint)

**Branch:** `dev/admin` (working branch). **`stage`** = `6ccaf2a` (dev/admin merged and pushed 27-09-2026, deploys to dev.xceler8.in).
**Updated:** 28-09-2026 — dev/admin is 10 commits ahead of stage (DEC-059…067), not pushed.

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

**UI / design (DEC-066, DEC-067), paused until the Sales merge:** the shared UI layer, the Appearance panel (mode / colour / font /
radius / layout), AG-Grid themed via the global hook, and the dev UI kit `/admin/dev/ui`. The resume list and how to verify are in
`docs/refactor/ui-design-progress.md` (pin AG-Grid in about 86 views, convert the Sales views, remove hex, real dashboard).

**Waiting on the user (left as they are, 27-09):**
- BUG-173: variant code convention; a fresh vehicle import is pending and needs `gscreds.json`.
- BUG-177: which permission gates the Imports menu / `imports/admin`.
- BUG-178: the engine ignores WIDE dealer charges and the model-scope column (price-changing fix).
- BUG-179: which accessory importer is authoritative. The accessories entity services wait on this.
- `ProductionRBACSeeder` test users are broken (DEC-059).
- Data scoping switch-on (BUG-083).
- Google service-account key rotation (history purge needs approval).

**For the booking team:** BUG-168 (routes skipping permission checks), about 37 menu links with no routes, BUG-153 (chassis status rule), BUG-030 (XCommonHelper).

**Local data:** vehicle master tables in `xlrm` purged (DEC-051), awaiting a fresh import. Keep `xlrm_testing` as is until then; both have the DEC-055 keyword masters migrated.

**Track B:** paused after B0 (DEC-033); resume from xceler8 `d9009db`. **Deferred until after UAT:** Laravel 13, Excel 4, Permission 8, Firebase 8, PHPUnit 12/13, Swagger 11.

**Verification cadence:** targeted smoke per change; full suite periodically (338 passed, 2 skipped on 28-09); `--group=smoke` sweep before merges.

**Environment:** Laragon, PHP 8.4.26 (+redis), MySQL 8.4.3. After a migration, run `DB_DATABASE=xlrm_testing php artisan migrate`. Do not `testing:refresh-db` until the vehicle import is in (DEC-051); it copies the empty vehicle tables over the test data.
