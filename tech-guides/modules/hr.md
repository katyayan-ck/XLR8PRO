# HR — employee journey (history over time)

Where an employee worked, in which role, with which scopes and permissions, **over time**. The live state is the
`Employee` row ([org.md](org.md)); the history is `EmployeeHistory` (`xlr8_admin_employee_history`), written only by
`EmployeeJourneyService`.

## EmployeeJourneyService (`App\Services\HR\EmployeeJourneyService`) — use this
| Method | Returns |
|---|---|
| `recordChange(Employee $e, array $primary, string $reason, $effectiveFrom, ?$notes, ?$addonScopes, ?$permissionsSnapshot, ?$actorId)` | the new `EmployeeHistory` row; closes the open row (`effective_to = effectiveFrom − 1 day`). `$primary` keys: `designation_code`, `primary_branch_code`, `primary_loc_code`, `primary_dept_code`, `primary_div_code`, `vertical_code`, `segment_code`, `sub_segment_code`, `reporting_manager_code`; `$addonScopes` e.g. `['branch' => ['CHR', 'SUJ']]`; `$permissionsSnapshot` `['role' => …, 'added' => [...], 'removed' => [...]]` |
| `recordPermissionChange(Employee $e, array $snapshot, $reason = 'permission_change', $effectiveFrom = null, ?$notes, ?$actorId)` | history row carrying the current org fields + the new permission snapshot |
| `currentState($empCode)` | the open row (`effective_to` null) |
| `stateOn($empCode, $date)` / `roleOn($empCode, $date)` | the row / designation in effect on a date (null when uncovered) |
| `whoWas(array $criteria, $date = null)` | people matching any of `designation_code`, `branch_code`, `location_code`, `department_code`, `division_code`, `vertical_code`, `segment_code`, `sub_segment_code` on a date (primary or add-on) |
| `journey($empCode)` / `journeyByName($fragment)` | chronological rows (by name / username fragment via Person) |
| `tenureInDesignation($empCode, $designationCode)` | days, summed across stints |
| `transferHistory($empCode)` | rows where branch or location changed |
| `promotionHistory($empCode)` | rows where the designation rank improved (A = 1 highest … E = 5) |
| `teamAt($branchCode, $date = null)` | everyone on a branch (primary or add-on) on a date |

Called by the User screen when designation / org / scopes / permissions change.

```php
$svc = app(EmployeeJourneyService::class);
$svc->recordChange($employee, ['designation_code' => 'SLS_MGR', 'primary_branch_code' => 'JPR'] + $employee->only([...]),
    'promotion', '2026-10-01', 'Promoted after Q2 review');
$svc->roleOn('BMPL-0282', '2026-06-15');           // "SLS_CONS"
$svc->whoWas(['designation_code' => 'SLS_MGR', 'branch_code' => 'JPR'], '2026-06-15');
```

## Legacy (registered, not used by any screen)
- `HRJourneyService` — `onboard($empCode, $desig, $from, $remarks)`, `transfer($empCode, $newDesig, $date, $remarks)`,
  `getCurrentDesignation($empCode)`, `getJourney($empCode)`; throws `App\Exceptions\DomainException`. Superseded by
  `EmployeeJourneyService`.
- `UserReportingService` — topic-based reporting lines on `UserReporting` (`getReportingManagerForTopic($user, $topic,
  $context)`, `getDefaultReportingManager($user)`, `getMaxLevelsForTopic($topic)`, `getDirectReports($manager, $topic)`,
  `getFullDownline($manager, $topic, $maxDepth = 8)`). No callers; approvals use the power sheet
  (tech-guides/platform/08) and the org hierarchy uses `OrgService::getUpline()` / `getDownline()`.

## Gotchas
- Never write `EmployeeHistory` directly; the "close the open row" step keeps dates contiguous.
- `EmployeeHistory` extends plain `Model` (the table has no `deleted_at`).
