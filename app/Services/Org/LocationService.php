<?php

namespace App\Services\Org;

use App\Models\Admin\Employee;
use App\Models\Admin\Location;
use Illuminate\Http\Request;

/**
 * Single source of truth for Location business logic (create/update, code
 * immutability, dependency-checked disable, media). The controller only
 * handles HTTP concerns and delegates everything else here.
 */
class LocationService
{
    /**
     * Location is the leaf of the Branch -> Location hierarchy — Employee (via its
     * primary_loc_code column) is its only dependent. `Location::employeeAssignments()`
     * points at xlr8_admin_emp_location_pivot, which doesn't exist (same dead-pivot
     * pattern as Vertical's BUG-081) — using the real, populated employee.primary_loc_code
     * column directly instead.
     */
    private const DEPENDENTS = [
        [Employee::class, 'primary_loc_code', 'employee', 'employment_status', 'active'],
    ];

    public function create(array $validated, Request $request): Location
    {
        $location = Location::create($validated);

        $this->syncMedia($request, $location);

        return $location;
    }

    /**
     * @return array{ok: true, location: Location}|array{ok: false, blockers: array<int, string>}
     */
    public function update(Location $location, array $validated, Request $request): array
    {
        // Code is the real primary key every relation points at by string — never editable.
        unset($validated['code']);

        $wasActive = $location->is_active;
        $willBeActive = (bool) ($validated['is_active'] ?? false);

        $blockers = OrgEntityGuard::blockersForDisabling($wasActive, $willBeActive, $location->code, self::DEPENDENTS);
        if ($blockers) {
            return ['ok' => false, 'blockers' => $blockers];
        }

        $location->update($validated);
        $this->syncMedia($request, $location);

        return ['ok' => true, 'location' => $location->fresh()];
    }

    private function syncMedia(Request $request, Location $location): void
    {
        if ($request->boolean('remove_image')) {
            $location->clearMediaCollection('location_image');
        }

        if ($request->hasFile('location_image')) {
            $location->addMediaFromRequest('location_image')->toMediaCollection('location_image');
        }

        if ($request->hasFile('documents')) {
            foreach ((array) $request->file('documents') as $file) {
                $location->addMedia($file)->toMediaCollection('documents');
            }
        }

        foreach ((array) $request->input('remove_documents', []) as $mediaId) {
            $location->media()->where('id', $mediaId)->where('collection_name', 'documents')->first()?->delete();
        }
    }
}
