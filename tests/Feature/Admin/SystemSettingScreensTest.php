<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Utilities\Settings\SystemSetting;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    /** DEC-091: settings are shown only on Utilities → Settings; the legacy pages lead there. */
    public function test_legacy_pages_lead_to_the_settings_screen(): void
    {
        $id = SystemSetting::query()->value('id') ?? 1;

        foreach (['/admin/utils/system-setting', '/admin/utils/system-setting/create', "/admin/utils/system-setting/{$id}/edit", "/admin/utils/system-setting/{$id}/show"] as $url) {
            $this->get($url)->assertRedirect(route('utils.settings.index'));
        }
    }

    /** BUG-207: the legacy search never returns secrets. */
    public function test_list_search_leaves_out_secrets(): void
    {
        app(SettingsService::class)->set('mail.smtp.password', 'hidden-one');

        $keys = collect($this->post('/admin/utils/system-setting/search', ['draw' => 1, 'start' => 0, 'length' => 500])->json('data'))
            ->map(fn ($row) => strip_tags((string) ($row[0] ?? '').(string) ($row[1] ?? '')))->implode(' ');

        $this->assertStringNotContainsString('mail.smtp.password', $keys);
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
