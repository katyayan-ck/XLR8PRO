<?php

namespace Tests\Feature\Org;

use App\Models\Admin\Division;
use App\Models\Admin\Location;
use App\Models\Vehicle\SubSegment;
use App\Services\Org\BranchService;
use App\Services\Org\DepartmentService;
use App\Services\Vehicle\SegmentService;
use App\Services\Vehicle\SubSegmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * DEC-089 (owner rule 30-09): every Branch / Department / Segment has a Location / Division / Sub-segment with the same
 * code and name, created by the parent's entity service.
 */
class SameCodeChildTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_new_branch_department_and_segment_each_get_their_same_code_child(): void
    {
        $tag = strtoupper(substr(uniqid(), -3));

        app(BranchService::class)->create(['code' => "ZB{$tag}", 'name' => 'Zeta Branch', 'is_active' => true]);
        app(DepartmentService::class)->create(['code' => "ZD{$tag}", 'name' => 'Zeta Department', 'is_active' => true]);
        app(SegmentService::class)->create(['code' => "Z{$tag}", 'name' => 'Zeta Segment', 'is_active' => true]);

        $this->assertSame(["ZB{$tag}", 'Zeta Branch'], [Location::where('code', "ZB{$tag}")->value('branch_code'), Location::where('code', "ZB{$tag}")->value('name')]);
        $this->assertSame(["ZD{$tag}", 'Zeta Department'], [Division::where('code', "ZD{$tag}")->value('dept_code'), Division::where('code', "ZD{$tag}")->value('name')]);
        $this->assertSame(["Z{$tag}", 'Zeta Segment'], [SubSegment::where('code', "Z{$tag}")->value('segment_code'), SubSegment::where('code', "Z{$tag}")->value('name')]);
    }

    public function test_a_parent_is_still_created_when_its_code_is_already_used_by_another_child(): void
    {
        $tag = strtoupper(substr(uniqid(), -3));
        app(SegmentService::class)->create(['code' => "Y{$tag}", 'name' => 'Yeti', 'is_active' => true]);
        app(SubSegmentService::class)->create(['segment_code' => "Y{$tag}", 'code' => "W{$tag}", 'name' => 'Taken', 'is_active' => true]);

        $segment = app(SegmentService::class)->create(['code' => "W{$tag}", 'name' => 'Wolf', 'is_active' => true]);

        $this->assertSame("W{$tag}", $segment->code);
        $this->assertSame("Y{$tag}", SubSegment::where('code', "W{$tag}")->value('segment_code'), 'the existing child is left alone');
    }
}
