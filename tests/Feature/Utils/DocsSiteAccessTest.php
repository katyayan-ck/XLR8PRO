<?php

namespace Tests\Feature\Utils;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BUG-211 (DEC-086): the Laradocs site (/docs) serves the internal developer guides (tech-guides/), so it needs an admin
 * login; it never serves the project records in docs/ (bug tracker, decision log).
 */
class DocsSiteAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/docs')->assertRedirect(backpack_url('login'));
    }

    public function test_a_signed_in_user_reads_the_guides_and_never_the_records(): void
    {
        $user = User::create(['username' => 'docs_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);

        $this->actingAs($user, backpack_guard_name())->get('/docs')->assertOk();
        $this->assertSame(base_path('tech-guides'), config('laradocs.docs.path'));
    }
}
