<?php

namespace Tests\Unit\Models;

use App\Models\IAM\PostReporting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PostReportingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_on_date_scope_matches_active_reporting_line(): void
    {
        PostReporting::factory()->create([
            'from_date' => '2024-01-01',
            'to_date'   => null,
            'topic'     => 'sales',
        ]);
        $this->assertEquals(1, PostReporting::forTopic('sales')->onDate('2024-09-12')->count());
    }

    public function test_on_date_scope_excludes_expired_reporting_line(): void
    {
        PostReporting::factory()->create([
            'from_date' => '2024-01-01',
            'to_date'   => '2024-06-30',
            'topic'     => 'sales',
        ]);
        $this->assertEquals(0, PostReporting::forTopic('sales')->onDate('2024-09-12')->count());
    }

    public function test_with_param_scope_matches_exact_param(): void
    {
        PostReporting::factory()->create([
            'topic'       => 'sales',
            'param_type'  => 'segment',
            'param_value' => 'LMM',
            'to_date'     => null,
            'from_date'   => '2024-01-01',
        ]);
        $result = PostReporting::forTopic('sales')
            ->onDate('2024-09-12')
            ->withParam('segment', 'LMM')
            ->count();
        $this->assertEquals(1, $result);
    }

    public function test_with_param_scope_wildcard_matches_any_value(): void
    {
        PostReporting::factory()->create([
            'topic'       => 'sales',
            'param_type'  => 'segment',
            'param_value' => null, // wildcard
            'to_date'     => null,
            'from_date'   => '2024-01-01',
        ]);
        $result = PostReporting::forTopic('sales')
            ->onDate('2024-09-12')
            ->withParam('segment', 'LMM')
            ->count();
        $this->assertEquals(1, $result);
    }

    public function test_by_priority_orders_highest_first(): void
    {
        PostReporting::factory()->create(['priority' => 1, 'from_date'=>'2024-01-01','to_date'=>null,'topic'=>'sales']);
        PostReporting::factory()->create(['priority' => 5, 'from_date'=>'2024-01-01','to_date'=>null,'topic'=>'sales']);

        $first = PostReporting::byPriority()->first();
        $this->assertEquals(5, $first->priority);
    }
}
