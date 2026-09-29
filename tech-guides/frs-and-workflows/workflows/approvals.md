# Workflow — approvals (the approval engine)

**Detail:** `platform/07-approvals.md` (API, use cases), `platform/08-approval-topics-rules-reports.md` (topics, rules,
the power sheet import, reports). **Spec:** `frs/platform-utilities-frs.md` §7–8 (the spec — DEC-001 plan).
**Facade:** `App\Support\Facades\Approval` (+ `Topics`, `Rules`); every call returns `App\Support\Result`.

```
Requester asks (topic, amount / % / yes-no, on a record)
   │  rule matched by topic + scope → levels frozen on the request (authority snapshot)
   ▼
Approvers at each level see it (designation + branch scope, live) ──► counter-offer (0 = no)
   │  decision = the HIGHEST level's counter on the current ask revision (same level: latest wins)
   ▼
Requester: accept the effective grant · revise the ask (older counters go stale) · withdraw
```

## Concepts
- **Topic:** what is being asked (e.g. a quotation discount). Two topics on one record are two independent requests.
- **Rule:** who may grant how much for a topic in a scope; edits later never change an open request.
- **Levels:** OPEN_TO_ALL (default, any level may counter) or LINEAR (only the current level acts, baton passes).
- **No reject:** an approver counters `0`.
- **Own power:** an ask within the requester's own power auto-accepts when `approval.auto_accept_own_power` is on.
- **Power sheet:** designations × topics × limits, imported from a workbook (`08-…` guide).

## Where it is used
- Built and tested as a platform utility (DEC-060…065); **no Sales flow calls it yet** — quotations still use the legacy
  `QuoteAction` approval (`sales-lifecycle.md`).
- Any new module: follow `platform/14-cookbook.md` to wire a topic, a rule, notifications and the timeline.

## Rules that bite
- Approvers never write the requester's record; the requester accepts, and the owning service applies the grant.
- Designation codes missing from the master (BUG-183: 36 employees) cannot be reached by any rule.
