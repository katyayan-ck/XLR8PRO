<?php

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Location;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Org\DivisionService;
use App\Services\Org\LocationService;
use App\Services\Vehicle\SubSegmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * DEC-089 (owner decision 30-09): every Branch / Department / Segment has a Location / Division / Sub-segment with the
 * same code and name. New parents get it from their entity service (`afterSave`); this fills the gaps in existing data.
 * Fail-safe: only parents without a same-code child are touched, a code already used elsewhere is skipped (logged), and
 * everything goes through the entity services. `down()` removes only what this migration created.
 */
return new class extends Migration
{
    private const MARK = 'Auto-created: same code as its parent (DEC-089)';

    public function up(): void
    {
        if (Schema::hasTable('xlr8_admin_branch') && Schema::hasTable('xlr8_admin_location')) {
            foreach (Branch::query()->get() as $branch) {
                if (Location::withTrashed()->where('code', $branch->code)->exists()) {
                    continue;
                }
                $this->safely('location', $branch->code, fn () => app(LocationService::class)->create(['branch_code' => $branch->code,
                    'code' => $branch->code, 'name' => $branch->name, 'city' => $branch->city, 'state' => $branch->state,
                    'description' => self::MARK, 'is_active' => true]));
            }
        }

        if (Schema::hasTable('xlr8_admin_department') && Schema::hasTable('xlr8_admin_division')) {
            foreach (Department::query()->get() as $department) {
                if (Division::withTrashed()->where('code', $department->code)->exists()) {
                    continue;
                }
                $this->safely('division', $department->code, fn () => app(DivisionService::class)->create(['dept_code' => $department->code,
                    'code' => $department->code, 'name' => $department->name, 'description' => self::MARK, 'is_active' => true]));
            }
        }

        if (Schema::hasTable('xlr8_vehicle_segment') && Schema::hasTable('xlr8_vehicle_subsegment')) {
            foreach (Segment::query()->get() as $segment) {
                $taken = SubSegment::withTrashed()->where('code', $segment->code)->first();
                if ($taken) {
                    if ($taken->segment_code !== $segment->code) {
                        Log::warning('DEC-089: sub-segment code already used by another segment', ['segment' => $segment->code, 'owner' => $taken->segment_code]);
                    }

                    continue;
                }
                $this->safely('sub-segment', $segment->code, fn () => app(SubSegmentService::class)->create(['segment_code' => $segment->code,
                    'code' => $segment->code, 'name' => $segment->name, 'is_active' => true]));
            }
        }
    }

    /** A parent whose code breaks the child's field rules (e.g. legacy over-long codes) is skipped and logged, never fatal. */
    private function safely(string $child, string $code, callable $create): void
    {
        try {
            $create();
        } catch (ValidationException $e) {
            Log::warning('DEC-089: same-code child skipped', ['child' => $child, 'code' => $code, 'errors' => $e->errors()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_admin_location')) {
            Location::withTrashed()->where('description', self::MARK)->forceDelete();
        }
        if (Schema::hasTable('xlr8_admin_division')) {
            Division::withTrashed()->where('description', self::MARK)->forceDelete();
        }
        // sub-segments carry no description: remove a same-code child only while nothing uses it
        if (Schema::hasTable('xlr8_vehicle_subsegment')) {
            foreach (Segment::query()->pluck('code') as $code) {
                $used = VehicleModel::query()->where('sub_segment_code', $code)->exists() || Variant::query()->where('sub_segment_code', $code)->exists();
                if (! $used) {
                    SubSegment::withTrashed()->where('segment_code', $code)->where('code', $code)->where('created_at', '>=', '2026-09-30')->forceDelete();
                }
            }
        }
    }
};
