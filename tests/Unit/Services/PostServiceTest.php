<?php

namespace Tests\Unit\Services;

use App\Exceptions\ApplicationException;
use App\Models\IAM\Post;
use App\Services\IAM\PostService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PostServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PostService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PostService::class);
    }

    public function test_create_post_generates_code_and_saves(): void
    {
        $post = $this->service->create([
            'display_name' => 'Floor Sales Consultant - NKH',
            'branch_code'  => 'NKH',
            'dept_code'    => 'SLS',
            'div_code'     => 'SHW',
            'desig_code'   => 'FSC',
        ]);

        $this->assertNotNull($post->post_code);
        $this->assertStringStartsWith('NKH-SLS-SHW-FSC', $post->post_code);
        $this->assertTrue($post->is_post);
        $this->assertTrue($post->is_active);
    }

    public function test_create_post_with_org_scopes(): void
    {
        $post = $this->service->create([
            'display_name' => 'BM Test',
            'branch_code'  => 'NKH',
            'desig_code'   => 'BM',
            'org_scopes'   => [
                ['scope_type' => 'branch',   'scope_value' => 'NKH'],
                ['scope_type' => 'location', 'scope_value' => null], // wildcard
            ],
        ]);

        $post->load('orgScopes');
        $this->assertCount(2, $post->orgScopes);
        $this->assertNull($post->getOrgScopeFor('location')); // wildcard
        $this->assertEquals(['NKH'], $post->getOrgScopeFor('branch'));
    }

    public function test_deactivate_fails_if_occupied(): void
    {
        $post = Post::factory()->create(['max_occupants' => 1]);

        // Simulate occupied post
        \App\Models\Admin\EmpPostAssignment::factory()->create([
            'post_code' => $post->post_code,
            'to_date'   => null,
        ]);

        $this->expectException(ApplicationException::class);
        $this->service->deactivate($post->post_code);
    }

    public function test_find_by_code_throws_if_not_found(): void
    {
        $this->expectException(ApplicationException::class);
        $this->service->findByCode('NONEXISTENT-001');
    }
}