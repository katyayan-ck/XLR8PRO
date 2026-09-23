<?php

namespace Tests\Unit\Models\Vehicle;

use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubSegmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_name_is_mass_assignable_and_persists(): void
    {
        Segment::query()->firstOrCreate(['code' => 'TESTSEG'], ['name' => 'Test Segment', 'is_active' => true]);

        $sub = SubSegment::query()->create([
            'segment_code' => 'TESTSEG',
            'code' => 'TESTSUB',
            'name' => 'Test Sub Segment',
            'is_active' => true,
        ]);

        $this->assertSame('Test Sub Segment', $sub->fresh()->name);
    }

    public function test_fillable_does_not_reference_nonexistent_columns(): void
    {
        $sub = new SubSegment;
        $realColumns = Schema::getColumnListing($sub->getTable());

        foreach ($sub->getFillable() as $field) {
            $this->assertContains($field, $realColumns, "Fillable field '{$field}' has no matching column on {$sub->getTable()}");
        }
    }
}
