<?php

namespace Tests\Unit\Models;

use App\Models\IAM\Post;
use App\Models\IAM\PostOrgScope;
use App\Models\IAM\PostVehicleScope;
use App\Models\Admin\EmpPostAssignment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PostModelTest extends TestCase
{
    use DatabaseTransactions;

    // ── Code Generation ───────────────────────────────────────────────────

    public function test_generates_post_code_correctly(): void
    {
        $code = Post::generateCode('NKH', 'SLS', 'SHW', 'FSC');
        $this->assertEquals('NKH-SLS-SHW-FSC-001', $code);
    }

    public function test_generates_sequential_post_codes(): void
    {
        Post::generateCode('NKH', 'SLS', 'SHW', 'FSC'); // 001 would be first
        // Create a dummy post to occupy 001 slot
        Post::create([
            'post_code'   => 'NKH-SLS-SHW-FSC-001',
            'display_name'=> 'Test Post',
            'branch_code' => 'NKH',
            'dept_code'   => 'SLS',
            'div_code'    => 'SHW',
            'desig_code'  => 'FSC',
        ]);
        $code = Post::generateCode('NKH', 'SLS', 'SHW', 'FSC');
        $this->assertEquals('NKH-SLS-SHW-FSC-002', $code);
    }

    public function test_generates_code_without_division(): void
    {
        $code = Post::generateCode('BKN', 'SLS', null, 'BM');
        $this->assertEquals('BKN-SLS-BM-001', $code);
    }

    // ── Discriminator ────────────────────────────────────────────────────

    public function test_post_is_always_created_with_is_post_true(): void
    {
        $post = Post::create([
            'display_name' => 'Test',
            'branch_code'  => 'NKH',
            'desig_code'   => 'FSC',
        ]);
        $this->assertTrue($post->is_post);
    }

    public function test_post_name_equals_post_code(): void
    {
        $post = Post::create([
            'display_name' => 'Test',
            'loc_code'     => 'NKH',
            'dept_code'    => 'SLS',
            'desig_code'   => 'FSC',
        ]);
        $this->assertEquals($post->post_code, $post->name);
    }

    public function test_global_scope_excludes_system_roles(): void
    {
        // Create a system role directly in xlr8_iam_roles
        \Illuminate\Support\Facades\DB::table('xlr8_iam_roles')->insert([
            'name'       => 'system-admin',
            'guard_name' => 'web',
            'is_post'    => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEquals(0, Post::count());
    }

    // ── Vacancy Logic ─────────────────────────────────────────────────────

    public function test_post_is_vacant_when_no_assignments(): void
    {
        $post = Post::factory()->create(['max_occupants' => 1]);
        $this->assertTrue($post->isVacant());
        $this->assertEquals(1, $post->vacancyCount());
    }

    // ── Org Scope ─────────────────────────────────────────────────────────

    public function test_wildcard_org_scope_returns_null(): void
    {
        $post = Post::factory()->create();
        PostOrgScope::create([
            'post_code'   => $post->post_code,
            'scope_type'  => 'branch',
            'scope_value' => null, // wildcard
        ]);
        $post->load('orgScopes');
        $this->assertNull($post->getOrgScopeFor('branch'));
    }

    public function test_specific_org_scope_returns_array_of_codes(): void
    {
        $post = Post::factory()->create();
        PostOrgScope::create(['post_code'=>$post->post_code,'scope_type'=>'branch','scope_value'=>'NKH']);
        PostOrgScope::create(['post_code'=>$post->post_code,'scope_type'=>'branch','scope_value'=>'BKN']);
        $post->load('orgScopes');
        $scopes = $post->getOrgScopeFor('branch');
        $this->assertContains('NKH', $scopes);
        $this->assertContains('BKN', $scopes);
    }

    public function test_missing_org_scope_returns_empty_array(): void
    {
        $post = Post::factory()->create();
        $post->load('orgScopes');
        $this->assertEquals([], $post->getOrgScopeFor('branch'));
    }
}
