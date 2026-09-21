<?php

namespace App\Services\HR;

use App\Models\Admin\Designation;
use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeHistory;
use App\Models\Admin\Person;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for an employee's organisational + vehicle-scope +
 * permission "journey" over time. Every org/vehicle/permission change for an
 * Employee should go through record*() here rather than writing to
 * EmployeeHistory directly, so the "close the previous open record" rule
 * (see recordChange()'s docblock) can never be forgotten at a call site.
 *
 * Storage shape: one EmployeeHistory row per effective period. The primary
 * org/vehicle columns (designation_code, primary_*_code, vertical_code,
 * segment_code, sub_segment_code) mirror Employee's own primary columns at
 * that point in time. Anything that isn't a single "primary" value — addon
 * branches/locations/departments/divisions/segments/sub_segments, and a
 * permission snapshot (role + per-user overrides) — lives in the `scopes`
 * JSON column as:
 *   {
 *     "addons": {"branch": [...], "location": [...], ...},
 *     "permissions": {"role": "Sales Manager", "added": [...], "removed": [...]}
 *   }
 */
class EmployeeJourneyService
{
    /**
     * Record a new effective-dated state for an employee — closes whatever
     * period was previously open (effective_to = day before the new
     * effective_from) and inserts a new open-ended row capturing the
     * complete post-change state. Idempotent-ish: always call this with the
     * employee's FULL current state (not just the changed fields), since a
     * history row is a point-in-time snapshot, not a diff.
     *
     * @param  array<string, mixed>  $primary  designation_code, primary_branch_code, primary_loc_code,
     *                                         primary_dept_code, primary_div_code, vertical_code,
     *                                         segment_code, sub_segment_code, reporting_manager_code
     * @param  array<string, array<int, string>>|null  $addonScopes  e.g. ['branch' => ['CHR','SUJ'], 'location' => [...]]
     * @param  array{role?: ?string, added?: array<int,string>, removed?: array<int,string>}|null  $permissionsSnapshot
     */
    public function recordChange(
        Employee $employee,
        array $primary,
        string $changeReason,
        Carbon|string $effectiveFrom,
        ?string $notes = null,
        ?array $addonScopes = null,
        ?array $permissionsSnapshot = null,
        ?int $actorId = null,
    ): EmployeeHistory {
        $effectiveFrom = Carbon::parse($effectiveFrom)->startOfDay();

        EmployeeHistory::where('emp_code', $employee->code)
            ->whereNull('effective_to')
            ->update(['effective_to' => $effectiveFrom->copy()->subDay()]);

        $scopes = array_filter([
            'addons' => $addonScopes,
            'permissions' => $permissionsSnapshot,
        ]);

        return EmployeeHistory::create([
            'emp_code' => $employee->code,
            'person_code' => $employee->person_code,
            'designation_code' => $primary['designation_code'] ?? null,
            'primary_branch_code' => $primary['primary_branch_code'] ?? null,
            'primary_loc_code' => $primary['primary_loc_code'] ?? null,
            'primary_dept_code' => $primary['primary_dept_code'] ?? null,
            'primary_div_code' => $primary['primary_div_code'] ?? null,
            'vertical_code' => $primary['vertical_code'] ?? null,
            'segment_code' => $primary['segment_code'] ?? null,
            'sub_segment_code' => $primary['sub_segment_code'] ?? null,
            'reporting_manager_code' => $primary['reporting_manager_code'] ?? null,
            'scopes' => $scopes ?: null,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'change_reason' => $changeReason,
            'notes' => $notes,
            'created_by' => $actorId ?? auth()->id(),
        ]);
    }

    /**
     * Convenience wrapper for a permission-only change (role reassignment or
     * an override add/remove) that doesn't touch org/vehicle info — carries
     * the employee's current primary fields and addon scopes forward
     * unchanged, only replacing the permissions snapshot.
     */
    public function recordPermissionChange(
        Employee $employee,
        array $permissionsSnapshot,
        string $changeReason = 'permission_change',
        Carbon|string|null $effectiveFrom = null,
        ?string $notes = null,
        ?int $actorId = null,
    ): EmployeeHistory {
        $current = $this->currentState($employee->code);

        return $this->recordChange(
            employee: $employee,
            primary: [
                'designation_code' => $employee->designation_code,
                'primary_branch_code' => $employee->primary_branch_code,
                'primary_loc_code' => $employee->primary_loc_code,
                'primary_dept_code' => $employee->primary_dept_code,
                'primary_div_code' => $employee->primary_div_code,
                'vertical_code' => $employee->vertical_code,
                'segment_code' => $employee->segment_code,
                'sub_segment_code' => $employee->sub_segment_code,
                'reporting_manager_code' => $employee->reporting_manager_code,
            ],
            changeReason: $changeReason,
            effectiveFrom: $effectiveFrom ?? now(),
            notes: $notes,
            addonScopes: $current?->scopes['addons'] ?? null,
            permissionsSnapshot: $permissionsSnapshot,
            actorId: $actorId,
        );
    }

    /** The currently-open (effective_to is null) history row for an employee. */
    public function currentState(string $empCode): ?EmployeeHistory
    {
        return EmployeeHistory::where('emp_code', $empCode)
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->first();
    }

    /** The history row that was in effect for an employee on a given date. */
    public function stateOn(string $empCode, Carbon|string $date): ?EmployeeHistory
    {
        $date = Carbon::parse($date)->toDateString();

        return EmployeeHistory::where('emp_code', $empCode)
            ->activeOn($date)
            ->latest('effective_from')
            ->first();
    }

    /**
     * "Where was <empCode> working on which role during <date>" — returns
     * just the designation code/name for readability, or null if no history
     * row covers that date (e.g. before they joined, or a gap in records).
     */
    public function roleOn(string $empCode, Carbon|string $date): ?string
    {
        return $this->stateOn($empCode, $date)?->designation_code;
    }

    /**
     * "Which person was working as <designation> on <branch> for <segment>"
     * (and any other combination of primary org/vehicle fields) — matches
     * against BOTH the primary column and that type's addon scopes, since an
     * employee assigned a branch as an addon (not their primary) was still
     * genuinely working there.
     *
     * @param  array<string, string>  $criteria  Any of: designation_code, branch_code, location_code,
     *                                           department_code, division_code, vertical_code,
     *                                           segment_code, sub_segment_code
     */
    public function whoWas(array $criteria, Carbon|string|null $date = null): Collection
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        $primaryColumnFor = [
            'branch_code' => 'primary_branch_code',
            'location_code' => 'primary_loc_code',
            'department_code' => 'primary_dept_code',
            'division_code' => 'primary_div_code',
            'vertical_code' => 'vertical_code',
            'segment_code' => 'segment_code',
            'sub_segment_code' => 'sub_segment_code',
        ];

        $addonTypeFor = [
            'branch_code' => 'branch',
            'location_code' => 'location',
            'department_code' => 'department',
            'division_code' => 'division',
            'segment_code' => 'segment',
            'sub_segment_code' => 'sub_segment',
        ];

        $query = EmployeeHistory::activeOn($date);

        if (! empty($criteria['designation_code'])) {
            $query->where('designation_code', $criteria['designation_code']);
        }

        foreach ($primaryColumnFor as $key => $column) {
            if (empty($criteria[$key])) {
                continue;
            }

            $code = $criteria[$key];
            $addonType = $addonTypeFor[$key] ?? null;

            $query->where(function ($q) use ($column, $code, $addonType) {
                $q->where($column, $code);

                if ($addonType) {
                    $q->orWhereJsonContains('scopes->addons->'.$addonType, $code);
                }
            });
        }

        return $query->get();
    }

    /** Full chronological employment journey for one employee. */
    public function journey(string $empCode): Collection
    {
        return EmployeeHistory::where('emp_code', $empCode)
            ->orderBy('effective_from')
            ->get();
    }

    /**
     * Resolve a name/username fragment to employee(s) via their Person
     * record, then return each match's full journey — e.g. "keshav's
     * journey" without knowing their employee code up front.
     *
     * @return Collection<string, Collection> emp_code => journey
     */
    public function journeyByName(string $searchTerm): Collection
    {
        $personCodes = Person::search($searchTerm)->pluck('person_code');

        return Employee::whereIn('person_code', $personCodes)
            ->pluck('code')
            ->mapWithKeys(fn ($empCode) => [$empCode => $this->journey($empCode)]);
    }

    /**
     * Total number of days an employee has spent in a given designation,
     * summed across every non-contiguous stint (e.g. promoted away and
     * later moved back into the same role).
     */
    public function tenureInDesignation(string $empCode, string $designationCode): int
    {
        return EmployeeHistory::where('emp_code', $empCode)
            ->where('designation_code', $designationCode)
            ->get()
            ->sum(function (EmployeeHistory $row) {
                $end = $row->effective_to ?? now();

                return (int) $row->effective_from->diffInDays($end) + 1;
            });
    }

    /**
     * History entries where the primary branch or location changed from the
     * immediately preceding entry — i.e. actual physical transfers, as
     * opposed to a designation/permission-only change at the same location.
     */
    public function transferHistory(string $empCode): Collection
    {
        $journey = $this->journey($empCode)->values();

        return $journey->filter(function (EmployeeHistory $row, int $index) use ($journey) {
            if ($index === 0) {
                return false;
            }

            $previous = $journey[$index - 1];

            return $row->primary_branch_code !== $previous->primary_branch_code
                || $row->primary_loc_code !== $previous->primary_loc_code;
        })->values();
    }

    /**
     * History entries where the designation's rank improved (numerically
     * lower rank = higher seniority, per Designation's own A(1)-E(5) scale —
     * see DesignationService::validateReportsTo()) compared to the
     * immediately preceding entry.
     */
    public function promotionHistory(string $empCode): Collection
    {
        $journey = $this->journey($empCode)->values();
        $ranks = Designation::pluck('rank', 'code');

        return $journey->filter(function (EmployeeHistory $row, int $index) use ($journey, $ranks) {
            if ($index === 0 || ! $row->designation_code) {
                return false;
            }

            $previous = $journey[$index - 1];
            if (! $previous->designation_code || $previous->designation_code === $row->designation_code) {
                return false;
            }

            $newRank = $ranks->get($row->designation_code);
            $oldRank = $ranks->get($previous->designation_code);

            return $newRank !== null && $oldRank !== null && $newRank < $oldRank;
        })->values();
    }

    /**
     * Everyone whose primary or addon branch was (or currently is) the given
     * branch on a date — "who was on my team at this branch" — regardless of
     * designation.
     */
    public function teamAt(string $branchCode, Carbon|string|null $date = null): Collection
    {
        return $this->whoWas(['branch_code' => $branchCode], $date);
    }
}
