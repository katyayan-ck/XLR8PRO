<?php

namespace Tests\Unit\Models;

use App\Models\Admin\EmpPostAssignment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EmpPostAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_current_scope_returns_only_active_assignments(): void
    {
        // Active
        EmpPostAssignment::factory()->create(['to_date' => null]);
        // Closed
        EmpPostAssignment::factory()->create(['to_date' => now()->subDays(10)]);

        $this->assertEquals(1, EmpPostAssignment::current()->count());
    }

    public function test_on_date_scope_with_open_ended_assignment(): void
    {
        EmpPostAssignment::factory()->create([
            'from_date' => '2024-01-01',
            'to_date'   => null,
        ]);
        $this->assertEquals(1, EmpPostAssignment::onDate('2024-09-12')->count());
        $this->assertEquals(0, EmpPostAssignment::onDate('2023-12-31')->count());
    }

    public function test_on_date_scope_with_closed_assignment(): void
    {
        EmpPostAssignment::factory()->create([
            'from_date' => '2024-01-01',
            'to_date'   => '2024-06-30',
        ]);
        $this->assertEquals(1, EmpPostAssignment::onDate('2024-03-15')->count());
        $this->assertEquals(0, EmpPostAssignment::onDate('2024-07-01')->count());
    }

    public function test_primary_scope_filters_correctly(): void
    {
        EmpPostAssignment::factory()->create(['assignment_type' => 'primary']);
        EmpPostAssignment::factory()->create(['assignment_type' => 'additional']);

        $this->assertEquals(1, EmpPostAssignment::primary()->count());
    }

    public function test_chronological_scope_orders_by_from_date(): void
    {
        EmpPostAssignment::factory()->create(['from_date' => '2024-06-01']);
        EmpPostAssignment::factory()->create(['from_date' => '2024-01-01']);

        $first = EmpPostAssignment::chronological()->first();
        $this->assertEquals('2024-01-01', $first->from_date->toDateString());
    }

    public function test_is_active_returns_true_when_to_date_is_null(): void
    {
        $a = EmpPostAssignment::factory()->create(['to_date' => null]);
        $this->assertTrue($a->isActive());
    }

    public function test_is_active_returns_false_when_to_date_is_set(): void
    {
        $a = EmpPostAssignment::factory()->create(['to_date' => now()->subDay()]);
        $this->assertFalse($a->isActive());
    }
}
