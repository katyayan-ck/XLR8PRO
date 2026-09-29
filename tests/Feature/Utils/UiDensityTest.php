<?php

namespace Tests\Feature\Utils;

use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * To-do U3: the site default density (Settings ui.density.text / ui.density.space) reaches every admin page before the
 * first paint, an unknown value falls back to the Tabler standard, and the Appearance panel offers the per-user choice.
 */
class UiDensityTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['username' => 'density_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('UTL_SETTINGS_VIEW', 'web'));
    }

    public function test_the_site_default_density_is_applied_before_paint_and_the_panel_offers_a_choice(): void
    {
        app(SettingsService::class)->set('ui.density.text', 'lg');
        app(SettingsService::class)->set('ui.density.space', 'cozy');

        $page = $this->actingAs($this->user, backpack_guard_name())->get(route('utils.settings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('XL.densityDefaults = {"text":"lg","space":"cozy"}', $page);
        $this->assertStringContainsString('data-xl-theme="text"', $page);
        $this->assertStringContainsString('data-xl-theme="space"', $page);
    }

    public function test_an_unknown_setting_value_falls_back_to_the_tabler_standard(): void
    {
        app(SettingsService::class)->set('ui.density.text', 'huge');
        app(SettingsService::class)->set('ui.density.space', '');

        $page = $this->actingAs($this->user, backpack_guard_name())->get(route('utils.settings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('XL.densityDefaults = {"text":"md","space":"comfortable"}', $page);
    }
}
