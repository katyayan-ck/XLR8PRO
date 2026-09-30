<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Site / dealership settings applied across the interface (DEC-091, W13 Phase 2 + owner feedback 30-09): the browser
 * title is "<dealership> | <application>", the footer says "Made for <dealership>" linked to its website with the
 * tagline on hover, the menu shows the logo or the name as text, an uploaded favicon replaces the built-in icons, the
 * legal name on documents comes from its setting, and the old site name / slogan are no longer on the Settings screen.
 */
class SiteSettingsApplyTest extends TestCase
{
    use DatabaseTransactions;

    private function superadmin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail();
    }

    public function test_the_title_footer_and_menu_follow_the_dealership_settings(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('dealership.name', 'Zeta Motors');
        $settings->set('dealership.url', 'https://zeta.example.com');
        $settings->set('dealership.tagline', 'Driven by trust');
        $app = (string) config('backpack.ui.project_name');

        $this->get('/admin/login')->assertOk()->assertSee('<title>', false)->assertSee('Zeta Motors | '.$app);

        $this->actingAs($this->superadmin(), 'backpack');
        $page = $this->get('/admin/dashboard')->assertOk();
        $page->assertSee('<meta name="application-name" content="Zeta Motors | '.e($app).'">', false);
        $page->assertSee('<a href="https://zeta.example.com" rel="noopener" target="_blank"  title="Driven by trust" >Zeta Motors</a>', false);
        $page->assertSee('class="xl-site-logo"', false);

        $settings->set('branding.menu_logo', 'text');
        $this->get('/admin/dashboard')->assertSee('<span class="xl-site-brand-text">Zeta Motors</span>', false);
    }

    public function test_an_uploaded_favicon_replaces_the_built_in_icons(): void
    {
        $this->get('/admin/login')->assertSee('favicon-32x32.png', false);

        app(SettingsService::class)->set('dealership.favicon', 'https://cdn.example.com/fav.png');

        $this->get('/admin/login')->assertSee('<link rel="icon" href="https://cdn.example.com/fav.png">', false)->assertDontSee('favicon-32x32.png', false);
    }

    public function test_the_legal_name_comes_from_the_setting(): void
    {
        $this->assertSame('Bikaner Motors Private Limited', dealership('legal_name'));

        app(SettingsService::class)->set('dealership.legal_name', 'Zeta Motors Pvt Ltd');

        $this->assertSame('Zeta Motors Pvt Ltd', dealership('legal_name'));
    }

    public function test_the_settings_screen_shows_current_images_and_no_site_name_or_slogan(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');

        $this->get('/admin/utils/settings')->assertOk()
            ->assertSee('images/Logo-108x75.png', false)->assertSee('Built-in')
            ->assertSee('<code>dealership.tagline</code>', false)->assertSee('<code>branding.menu_logo</code>', false)
            ->assertDontSee('<code>site.name</code>', false)->assertDontSee('<code>site.slogan</code>', false);
    }
}
