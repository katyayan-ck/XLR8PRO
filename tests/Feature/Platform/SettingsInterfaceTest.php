<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The categorised Settings interface (DEC-091, W13 Phase 1): settings managers see every tab, pricing managers only the
 * Pricing tab, anyone else nothing; a section saves only its changed values through SettingsService, rejects invalid
 * input, and a blank secret keeps the stored one.
 */
class SettingsInterfaceTest extends TestCase
{
    use DatabaseTransactions;

    private function userWith(string ...$permissions): User
    {
        $user = User::create(['username' => 'set_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user->fresh();
    }

    public function test_a_settings_manager_sees_every_tab(): void
    {
        $this->actingAs($this->userWith('UTL_SETTINGS_MANAGE'), 'backpack');

        $page = $this->get('/admin/utils/settings')->assertOk();
        foreach (['site', 'communication', 'pricing', 'users', 'security', 'modules'] as $tab) {
            $page->assertSee('data-tab="'.$tab.'"', false);
        }
    }

    public function test_a_pricing_manager_sees_and_saves_only_pricing(): void
    {
        $this->actingAs($this->userWith('PRC_WKFL_MANAGE'), 'backpack');

        $this->get('/admin/utils/settings')->assertOk()->assertSee('data-tab="pricing"', false)->assertDontSee('data-tab="site"', false);
        $this->put('/admin/utils/settings/pricing/onroad', ['settings' => ['pricing__rto__round_up_to' => '500', 'pricing__dealer_charges__include_cod' => '1']])
            ->assertRedirect(route('utils.settings.index', ['tab' => 'pricing']));
        $this->assertSame(500, app(SettingsService::class)->get('pricing.rto.round_up_to'));

        $this->put('/admin/utils/settings/site/dealership', ['settings' => ['dealership__name' => 'X']])->assertForbidden();
        $this->put('/admin/utils/settings', ['key' => 'dealership.name', 'value' => 'X'])->assertForbidden();
    }

    public function test_others_cannot_open_settings(): void
    {
        $this->actingAs($this->userWith(), 'backpack');

        $this->get('/admin/utils/settings')->assertForbidden();
    }

    public function test_a_section_saves_changed_values_and_rejects_invalid_ones(): void
    {
        $this->actingAs($this->userWith('UTL_SETTINGS_MANAGE'), 'backpack');

        $this->put('/admin/utils/settings/site/dealership', ['settings' => ['dealership__name' => 'Bikaner Motors Test', 'dealership__email' => 'sales@example.com']])
            ->assertSessionHasNoErrors();
        $this->assertSame('Bikaner Motors Test', app(SettingsService::class)->get('dealership.name'));

        $this->put('/admin/utils/settings/site/dealership', ['settings' => ['dealership__email' => 'not-an-email', 'dealership__url' => 'nope']])
            ->assertSessionHasErrors(['settings.dealership__email', 'settings.dealership__url']);
        $this->assertSame('sales@example.com', app(SettingsService::class)->get('dealership.email'));
    }

    public function test_a_blank_secret_keeps_the_stored_one_and_secrets_are_encrypted(): void
    {
        $this->actingAs($this->userWith('UTL_SETTINGS_MANAGE'), 'backpack');

        $this->put('/admin/utils/settings/communication/smtp', ['settings' => ['mail__smtp__password' => 's3cret-pass', 'mail__smtp__port' => '587']]);
        $this->put('/admin/utils/settings/communication/smtp', ['settings' => ['mail__smtp__password' => '', 'mail__smtp__port' => '465']]);

        $this->assertSame('s3cret-pass', app(SettingsService::class)->get('mail.smtp.password'));
        $this->assertSame(465, app(SettingsService::class)->get('mail.smtp.port'));
        $this->assertStringNotContainsString('s3cret-pass', (string) DB::table('xlr8_utils_system_setting')->where('key', 'mail.smtp.password')->value('value'));
        $this->get('/admin/utils/settings?tab=communication')->assertDontSee('s3cret-pass');
    }
}
