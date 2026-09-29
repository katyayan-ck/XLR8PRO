<?php

namespace Tests\Feature\Utils;

use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-083: the site logo is an image setting (branding.logo) uploaded in Settings; the admin header in both layouts
 * shows it and always links to the dashboard, the login page shows it; unset, the built-in images stay.
 */
class SiteLogoTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::create(['username' => 'logo_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        foreach (['UTL_SETTINGS_VIEW', 'UTL_SETTINGS_MANAGE'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(SettingsService::class)->reset('branding.logo');
    }

    public function test_the_uploaded_logo_is_shown_and_links_to_the_dashboard(): void
    {
        $this->actingAs($this->user, backpack_guard_name())->get(route('utils.settings.index', ['q' => 'branding']))
            ->assertOk()->assertSee('branding.logo')->assertSee('Not set');
        $this->assertStringEndsWith('images/Logo-108x75.png', site_logo_url(), 'unset: the built-in logo');

        $this->post(route('utils.settings.image'), ['key' => 'branding.logo', 'file' => UploadedFile::fake()->image('logo.png', 200, 60)])->assertRedirect();
        $url = (string) setting('branding.logo');
        $this->assertStringContainsString('logo.png', $url);
        $this->assertSame($url, site_logo_url('images/bikaner_logo.png'), 'prints use the configured logo too');

        $page = $this->get(route('utils.settings.index', ['q' => 'branding']))->assertOk()->getContent();
        $this->assertStringContainsString('src="'.$url.'"', $page);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(backpack_url('dashboard'), '#').'"[^>]*>\s*<img src="'.preg_quote($url, '#').'"#', $page, 'the header logo opens the dashboard');
    }

    public function test_only_image_settings_take_an_upload_and_reset_restores_the_built_in_logo(): void
    {
        $this->actingAs($this->user, backpack_guard_name());
        $this->post(route('utils.settings.image'), ['key' => 'pricing.rto.round_up_to', 'file' => UploadedFile::fake()->image('x.png')])->assertRedirect();
        $this->assertSame(1000, (int) setting('pricing.rto.round_up_to'), 'not an image setting — refused');
        $this->post(route('utils.settings.image'), ['key' => 'branding.logo', 'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');

        $this->post(route('utils.settings.image'), ['key' => 'branding.logo', 'file' => UploadedFile::fake()->image('logo.png')]);
        $this->post(route('utils.settings.reset'), ['key' => 'branding.logo'])->assertRedirect();
        $this->assertStringEndsWith('images/Logo-108x75.png', site_logo_url());
    }

    public function test_the_login_page_shows_the_site_logo(): void
    {
        $this->get(backpack_url('login'))->assertOk()->assertSee('xl-site-logo', false)->assertSee(site_logo_url(), false);
    }
}
