# 7. Approvals

Get an extra-commercial ask (discount amount, percentage, or a yes/no flag) granted by the right authority.
Service `App\Services\Platform\Approval\ApprovalService` · facade `Approval`.

## Concepts (FRS §7 — read this before using it)
- **Authority is frozen** on the request when it opens: topic + the matched rule + its levels (who may grant how
  much). Rule edits later never change an open request.
- **Visibility is live:** holders of a level's designation whose branch fits the rule scope (or who are scoped to
  it, or unscoped), plus the requester and approval admins.
- **Decision = highest level wins** among counters on the **current ask revision**; at the same level the latest
  counter wins. There is no "reject": an approver counters `0`.
- The **requester** revises the ask (older counters become stale), **accepts** the effective grant or **withdraws**.
- **LINEAR** topics only let the current level act (baton), all others are **OPEN_TO_ALL** (default).
- Asks within the requester's own power auto-accept when `approval.auto_accept_own_power` is on (still snapshotted).
- Two topics on one quote are two independent requests.

## API
| Call | Returns / codes |
|---|---|
| `Approval::open($source, 'DISCOUNT.EXTRA', 'extra_disc', ['asked' => 8000, 'value_type' => 'AMOUNT', 'scope' => ['model' => 'NEXON', 'branch' => 'JPR'], 'remark' => '…'], $actorId = null)` | Result `{id, status, auto_accepted}` — `UNAUTHORISED`, `UNKNOWN_TOPIC`, `NO_RULE`, `INVALID_ASK` |
| `Approval::counter($requestId, $actorId, $value, $remark = null)` | Result `{effective}` — `NOT_FOUND`, `CLOSED`, `NO_AUTHORITY`, `NOT_YOUR_TURN`, `INVALID_VALUE`, `EXCEEDS_POWER` |
| `Approval::effective($requestId)` | `{level, actor, value, basis, counter_id}` or null |
| `Approval::reviseAsk($requestId, $actorId, $newAsk, $remark)` | Result `{ask_revision}` — requester only |
| `Approval::close($requestId, $actorId, 'ACCEPTED'|'WITHDRAWN', $remark)` | Result `{status, effective}` — `NO_GRANT` (nobody countered this revision), `FORBIDDEN` |
| `Approval::authorize($userId, 'extra_disc', $scope, 2000)` | bool — could this person grant it without a request? |
| `Approval::preview($userId, 'extra_disc', $scope, $ask)` | who is visible at each level, their max, the user's level, would it auto-pass |
| `Approval::visibleTo($userId)` / `inbox($userId, 'TO_ACT'|'RAISED'|'TEAM'|'CLOSED')` | lists |
| `Approval::panel($request, $viewerId)` | data for `<x-approval.panel>` |

`$source` is the model (e.g. the Quote) or `['type' => 'QUOTE', 'id' => 8821]`. Scope keys: `company`, `zone`,
`state`, `branch`, `desk`, `segment`, `model`, `variant`, `permit`, `channel` (branch defaults to the requester's).
`value_type`: `AMOUNT`, `PERCENTAGE` (0–100), `FLAG` (counter 0/1). Defaults to the topic's value type.

## Use cases

**1. FSC asks for an extra discount on a quote**
```php
$r = Approval::open($quote, 'DISCOUNT.EXTRA', 'extra_disc', [
    'asked' => 8000, 'scope' => ['model' => $quote->model_code, 'variant' => $quote->variant_code],
    'remark' => 'Competitor offer',
]);
if ($r->code === 'NO_RULE') { /* no authority configured for this vehicle — tell the user */ }
$quote->update(['approval_request_id' => $r->get('id')]);   // your module keeps the request id (column of your own)
```

**2. Skip the request when the user can grant it themselves**
```php
if (Approval::authorize(backpack_user()->id, 'extra_disc', $scope, $discount)) { /* apply directly */ }
```

**3. Approver counters** (from the panel, or code)
```php
Approval::counter($requestId, $gmUserId, 6000, 'Max for this month');
```

**4. Read the grant when saving the quote**
```php
$grant = Approval::effective($quote->approval_request_id);      // ['level' => 4, 'value' => 6000.0, …]
```

**5. Requester accepts or revises**
```php
Approval::reviseAsk($requestId, $fscId, 6500, 'Customer agreed to 6.5k');   // approvers must re-counter
Approval::close($requestId, $fscId, 'ACCEPTED');
```

**6. "Is the quote fully approved?"** — every mandatory topic's request ACCEPTED:
```php
$ok = ApprovalRequest::where('source_type', 'QUOTE')->where('source_id', $quote->id)
    ->whereIn('topic_code', $mandatoryTopics)->where('status', 'ACCEPTED')->count() === count($mandatoryTopics);
```

**7. UI** — drop the panel on the source screen:
```blade
<x-approval.panel :request="$approvalRequest" />
```
Shows the ask, levels with max, counters (stale ones struck through), the winning grant, and the counter / revise /
accept / withdraw actions allowed for the viewer.

## Screens & permissions
**Utilities → Approvals** `/admin/utils/approvals` (To act · Raised by me · Team · Closed), `/approvals/create`
(raise by hand), `/approvals/{id}`. Everyone `UTL_APPR_VIEW` / `UTL_APPR_REQUEST`; `UTL_APPR_ADMIN` may close any
request and manages topics / rules (guide 8); `UTL_APPR_REPORT` for the report.

## Gotchas
- Callers never compute "who is next" and never store an `assigned_to` baton.
- Authority comes from the power sheet (guide 8), never from code.
- Events: `ApprovalChanged` (OPENED, COUNTERED, REVISED, ACCEPTED, WITHDRAWN) — listen instead of polling.
