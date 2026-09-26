<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * System settings admin screens render: the list's AJAX search and the show page used a
 * `badge` column type that free Backpack 7 does not ship (BUG-167).
 */
class SystemSettingScreensTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
    }

    public function test_list_search_returns_rows(): void
    {
        $this->post('/admin/utils/system-setting/search', ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_show_page_renders(): void
    {
        $id = DB::table('xlr8_utils_system_setting')->whereNull('deleted_at')->value('id');
        if (! $id) {
            $this->markTestSkipped('No system settings in the test database.');
        }

        $this->get("/admin/utils/system-setting/{$id}/show")->assertOk();
    }

    public function test_show_page_needs_the_view_permission(): void
    {
        $id = DB::table('xlr8_utils_system_setting')->whereNull('deleted_at')->value('id');
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('UTL_SETTINGS_VIEW'));
        if (! $id || ! $user) {
            $this->markTestSkipped('Needs a setting and a user without UTL_SETTINGS_VIEW.');
        }

        $this->app['auth']->guard('backpack')->logout();
        $this->actingAs($user, 'backpack');

        $this->get("/admin/utils/system-setting/{$id}/show")->assertForbidden();
    }

    /** Their search/details routes lacked the `operation` key, so the list gate never ran (BUG-167). */
    public function test_key_value_and_keyword_search_need_the_view_permission(): void
    {
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('UTL_SETTINGS_VIEW'));
        if (! $user) {
            $this->markTestSkipped('Needs a user without UTL_SETTINGS_VIEW.');
        }

        $this->app['auth']->guard('backpack')->logout();
        $this->actingAs($user, 'backpack');

        foreach (['utils/key-value', 'utils/keyword-master'] as $uri) {
            $this->post("/admin/{$uri}/search", ['draw' => 1, 'start' => 0, 'length' => 10])->assertForbidden();
        }
    }
}
