<?php

namespace Tests\Unit\Services;

use App\Models\IAM\Post;
use App\Models\IAM\PostReporting;
use App\Services\IAM\ReportingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportingService::class);
    }

    public function test_get_reporting_post_returns_correct_manager(): void
    {
        $subordinate = Post::factory()->create();
        $manager     = Post::factory()->create();

        PostReporting::factory()->create([
            'from_post_code' => $subordinate->post_code,
            'to_post_code'   => $manager->post_code,
            'topic'          => 'sales',
            'from_date'      => '2024-01-01',
            'to_date'        => null,
            'priority'       => 1,
        ]);

        $result = $this->service->getReportingPost($subordinate->post_code, 'sales');
        $this->assertEquals($manager->post_code, $result->post_code);
    }

    public function test_exact_param_wins_over_wildcard(): void
    {
        $sub  = Post::factory()->create();
        $mgr1 = Post::factory()->create(); // wildcard manager
        $mgr2 = Post::factory()->create(); // exact-match manager

        // Wildcard reporting line (priority 1)
        PostReporting::factory()->create([
            'from_post_code' => $sub->post_code,
            'to_post_code'   => $mgr1->post_code,
            'topic'          => 'sales',
            'param_type'     => 'segment',
            'param_value'    => null,
            'from_date'      => '2024-01-01',
            'to_date'        => null,
            'priority'       => 1,
        ]);

        // Exact param line (priority 2)
        PostReporting::factory()->create([
            'from_post_code' => $sub->post_code,
            'to_post_code'   => $mgr2->post_code,
            'topic'          => 'sales',
            'param_type'     => 'segment',
            'param_value'    => 'LMM',
            'from_date'      => '2024-01-01',
            'to_date'        => null,
            'priority'       => 2,
        ]);

        $result = $this->service->getReportingPost($sub->post_code, 'sales', null, 'segment', 'LMM');
        $this->assertEquals($mgr2->post_code, $result->post_code);
    }

    public function test_get_direct_reports_returns_all_reporters(): void
    {
        $manager = Post::factory()->create();
        $sub1    = Post::factory()->create();
        $sub2    = Post::factory()->create();

        PostReporting::factory()->create([
            'from_post_code' => $sub1->post_code,
            'to_post_code'   => $manager->post_code,
            'topic'          => 'sales',
            'from_date'      => '2024-01-01',
            'to_date'        => null,
        ]);
        PostReporting::factory()->create([
            'from_post_code' => $sub2->post_code,
            'to_post_code'   => $manager->post_code,
            'topic'          => 'sales',
            'from_date'      => '2024-01-01',
            'to_date'        => null,
        ]);

        $reports = $this->service->getDirectReports($manager->post_code, 'sales');
        $this->assertCount(2, $reports);
    }

    public function test_set_reporting_line_closes_previous(): void
    {
        $sub  = Post::factory()->create();
        $mgr1 = Post::factory()->create();
        $mgr2 = Post::factory()->create();

        $this->service->setReportingLine([
            'from_post_code' => $sub->post_code,
            'to_post_code'   => $mgr1->post_code,
            'topic'          => 'sales',
            'from_date'      => '2024-01-01',
        ]);

        $this->service->setReportingLine([
            'from_post_code' => $sub->post_code,
            'to_post_code'   => $mgr2->post_code,
            'topic'          => 'sales',
            'from_date'      => '2024-06-01',
        ]);

        $oldLine = PostReporting::where('from_post_code', $sub->post_code)
                                ->where('to_post_code', $mgr1->post_code)
                                ->first();
        $this->assertNotNull($oldLine->to_date);

        $newLine = PostReporting::where('from_post_code', $sub->post_code)
                                ->where('to_post_code', $mgr2->post_code)
                                ->current()
                                ->first();
        $this->assertNotNull($newLine);
    }
}