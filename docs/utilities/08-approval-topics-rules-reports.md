# 8. Approval topics, rules & reports

Master data behind approvals (who may grant what) and the reports leadership uses.
Services: `TopicService` (`Topics` facade), `RuleService` (`Rules` facade), writers
`Entities\ApprovalTopicService` / `Entities\ApprovalRuleService` (DEC-050), `PowerSheetImportService`,
`ApprovalReportService`.

## Topic tree
```
Main topic (DISCOUNT)
  └── Sub / Item (DISCOUNT.EXTRA, item_key extra_disc)   ← requests attach to items
```
- `code` is a stable dotted string; `item_key` is lower_snake. Snapshots store code + title, so renames are safe.
- `mode` is inherited downward (blank = inherit, default `OPEN_TO_ALL`); `LINEAR` = one level at a time;
  `VARIABLE` without a resolver falls back to OPEN_TO_ALL (logged).
- Seeded: DISCOUNT.EXTRA `extra_disc`, ACCESSORIES.PACK `apack_disc`, INSURANCE.WAIVER `ins_waiver`,
  RTO.EXEMPT `rto_exempt` (FLAG), DOCS.APPROVAL, COMMS.TEMPLATE (templates go live through it — guide 9).

```php
Topics::resolve('extra_disc');   // ['node' => ApprovalTopic, 'chain' => [DISCOUNT, DISCOUNT.EXTRA], 'mode' => 'OPEN_TO_ALL', 'value_type' => 'AMOUNT']
app(ApprovalTopicService::class)->create(['code' => 'ACCESSORIES.POWER', 'parent_id' => $accId, 'title' => 'Power accessories']);
```

## Rules (authority)
A rule = topic + scope tuple (blank = ANY) + ordered levels (designation, value type, std / min / max), optional
`valid_from` / `valid_to`.

**Matching (locked):** deepest topic node that has a valid rule → most specific scope
(variant > model > segment > permit > desk > branch > zone > state > channel > company) → latest id. A set dimension
must equal the request's value.
```php
Rules::match(Topics::resolve('extra_disc'), ['model' => 'NEXON', 'branch' => 'JPR']);   // ?ApprovalRule
app(ApprovalRuleService::class)->create([
    'topic_id' => $topicId, 'model_code' => 'NEXON', 'valid_from' => '2026-10-01',
    'levels' => [
        ['level_no' => 1, 'designation_code' => 'SLS_CONS', 'value_type' => 'AMOUNT', 'max_value' => 3000],
        ['level_no' => 2, 'designation_code' => 'SLS_MGR',  'value_type' => 'AMOUNT', 'max_value' => 8000],
    ],
]);   // throws ValidationException on unknown designation, duplicate level, min > max …
```

## Power-sheet import (the normal way to load authority)
Excel / CSV, **one row per level**. Columns (header spellings are forgiving): `Topic` (code or item key), `Level`,
`Designation`, `Value Type`, `Std`, `Min`, `Max`, `Valid From`, `Valid To`, `Company`, `Zone`, `State`, `Branch`,
`Desk`, `Segment`, `Model`, `Variant`, `Permit`, `Channel` (blank / ANY = any). Rows with the same topic + scope +
validity form one rule.

- **Dry run** reports rules / levels / problems without writing.
- **Apply** purges and replaces **all rules of each topic in the sheet**; a topic with any bad row is skipped whole;
  problems download as an error sheet. Open requests keep their snapshot.
```php
$r = app(PowerSheetImportService::class)->import($path, apply: false);   // data: topics, rules, levels, errors[], skipped_topics
```
Screen: **Approvals → Topics & rules → Import power sheet** (template download there).

## Simulation
"User U on vehicle V asking ₹X on item K — who sees it, what is each max, would it auto-pass?"
`Approval::preview($userId, 'extra_disc', ['model' => 'NEXON'], 5000)` or `/admin/utils/approvals/admin/simulate`.

## Reports
Read from the request projection + counters (never re-matched).
```php
app(ApprovalReportService::class)->summary(['fy' => '26-27', 'branch' => 'JPR', 'topic' => 'DISCOUNT'], 'effective_level');
// totals: opened, accepted, withdrawn, open, asked_accepted, granted, grant_ratio %, auto_share %, avg_hours_to_close
// rows grouped by topic_code | branch_code | effective_level | item_key | source_type | fy; by_level: counters per level
app(ApprovalReportService::class)->rows($filters);   // flat rows (xlsx export)
```
Screen `/admin/utils/approvals/report` with xlsx export. Filters: FY, branch, topic prefix, item, winning level,
actor, source.

## Permissions
`UTL_APPR_ADMIN` — topics, rules, import, simulation. `UTL_APPR_REPORT` — report.

## Gotchas
- Designations must exist in the designation master (BUG-183 lists employees on unknown codes).
- Company / zone / state / desk / channel match only when callers pass them in the scope.
