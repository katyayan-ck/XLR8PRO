<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Site / dealership settings applied across the interface (DEC-091, W13 Phase 2): the dealership name becomes the
 * project name on the next request (login page included), an uploaded favicon replaces the built-in icons, and the
 * legal name printed on documents comes from the setting.
 */
class SiteSettingsApplyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_dealership_name_shows_on_the_next_page_including_login(): void
    {
        app(SettingsService::class)->set('dealership.name', 'Zeta Motors Test');

        $this->get('/admin/login')->assertOk()->assertSee('Zeta Motors Test');

        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
        $this->get('/admin/dashboard')->assertOk()->assertSee('<meta name="application-name" content="Zeta Motors Test">', false);
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
}
