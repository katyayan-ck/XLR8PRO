# Plans — design plans, kept current

Each approved implementation plan, with a **status header** at the top that is updated whenever work on it moves
(`.ai/guidelines/10-workflow.md`). A plan is the design record (context, decisions, phases, verification); what is left
from it also appears as rows in `docs/todo.md`.

| Plan | Decision | Status (29-09-2026) | Open items |
|---|---|---|---|
| [Automatic user data scoping](2026-09-28-data-scoping-DEC-071.md) | DEC-071 | ✅ shipped (on `stage`) | — |
| [My Account + dynamic dashboard](2026-09-28-my-account-and-dashboard-DEC-072.md) | DEC-072 | ✅ shipped (`dev/admin`) | email / mobile self-service (N4) |
| [Pricing process redesign](2026-09-28-pricing-redesign-DEC-073.md) | DEC-073 … 082 | ✅ shipped (`dev/admin`) | sheet fixes, quotation picker browser check, COD |
| [Pricing masters, recalculation, sync stamp, logo](2026-09-29-pricing-masters-DEC-083.md) | DEC-083 | ✅ shipped (`dev/admin`) | `PRC_*` grants, accessory re-import, prod queue worker |
| [Users bulk workbook + screen, org rules](2026-09-30-users-bulk-and-org-rules-DEC-089.md) | DEC-089 | ✅ done 30-09 (A org rules, B workbook, C bulk screen) | phases A–C |
| [One categorised Settings interface](2026-09-30-settings-interface-DEC-091.md) | DEC-091 | ✅ done 30-09 (Phases 1–6 + W13g) | phases 1–6 |
| [Vehicle specifications, features, galleries, compare](2026-09-30-vehicle-content-compare-DEC-092.md) | DEC-092 | ✅ done 01-10 (Phases 1–5) | phases 1–5 |
| [Help (F1), tours, support requests, user manual](2026-10-01-help-and-support-DEC-094.md) | DEC-094 | 🟡 in progress — W16b ✅ 03-10; next W16c | W16b–f, W17a–d |
| [Execution order to go-live (dependency-ordered)](2026-10-01-execution-order.md) | — | 📝 proposed 01-10 | phases 0–10 |
| Go-live to-do (the programme plan) | — | 🟡 live | lives in `docs/todo.md` Part 1 |

**New plan:** save it here as `YYYY-MM-DD-{topic}-DEC-NNN.md` (the plan-mode file in `~/.claude/plans/` is only a
working copy) with a status header, add a row above, and link it from the DEC entry and the to-do row.
