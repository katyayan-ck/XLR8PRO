<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\Dev\UiKitController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * DEC-067: the dev-only UI kit is hidden unless enabled, and the Appearance panel's layout cookie picks the admin
 * layout (only whitelisted layouts are honoured).
 */
class UiKitAndLayoutTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
    }

    public function test_every_ui_kit_page_renders_when_enabled(): void
    {
        config(['platform.dev_ui_kit' => true]);

        foreach (array_keys(UiKitController::PAGES) as $page) {
            $this->get('/admin/dev/ui/'.$page)->assertOk()->assertSee('Developer reference');
        }
    }

    public function test_ui_kit_is_not_found_when_disabled(): void
    {
        config(['platform.dev_ui_kit' => false]);

        $this->get('/admin/dev/ui')->assertNotFound();
        $this->get('/admin/dev/ui/forms')->assertNotFound();
    }

    public function test_ui_kit_needs_an_admin_login(): void
    {
        config(['platform.dev_ui_kit' => true]);
        $this->app['auth']->guard('backpack')->logout();

        $this->get('/admin/dev/ui')->assertRedirect();
    }

    public function test_layout_cookie_switches_to_a_whitelisted_layout(): void
    {
        config(['platform.dev_ui_kit' => true]);

        $this->withUnencryptedCookie('xl_layout', 'vertical_dark')->get('/admin/dev/ui')
            ->assertOk()->assertSee('bp-layout="vertical-dark"', false)->assertSee('data-bs-theme="dark"', false);
    }

    public function test_unknown_layout_cookie_keeps_the_default_layout(): void
    {
        config(['platform.dev_ui_kit' => true, 'backpack.theme-tabler.layout' => 'horizontal']);

        $this->withUnencryptedCookie('xl_layout', 'plain')->get('/admin/dev/ui')
            ->assertOk()->assertSee('bp-layout="horizontal"', false);
    }
}
