<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Services\Person\PersonRecordService;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Settings → User behaviour applied (DEC-091, W13 Phase 5): users change only the personal details switched on (the
 * rest of a request is ignored), with the person / employee field rules; the Appearance panel can be hidden.
 */
class UserBehaviourSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function userWithPerson(): User
    {
        $person = app(PersonRecordService::class)->create(['display_name' => 'Behaviour Test', 'gender' => 'Male', 'pan_no' => 'ABCPE1234F']);

        return User::create(['username' => 'ub_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1,
            'person_code' => $person->person_code])->fresh();
    }

    public function test_nothing_is_editable_until_a_switch_is_on(): void
    {
        $user = $this->userWithPerson();
        $this->actingAs($user, 'backpack');

        $this->get(route('backpack.account.info'))->assertOk()->assertDontSee('Save personal details');
        $this->post(route('backpack.account.personal'), ['gender' => 'Female'])->assertForbidden();
    }

    public function test_only_switched_on_fields_are_saved_and_the_field_rules_apply(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('account.can_change_gender', true);
        $settings->set('account.can_change_email', true);
        $user = $this->userWithPerson();
        $this->actingAs($user, 'backpack');

        $this->get(route('backpack.account.info'))->assertSee('Save personal details');
        $this->post(route('backpack.account.personal'), ['gender' => 'Female', 'email' => 'me@example.com', 'pan_no' => 'ZZZPZ9999Z'])
            ->assertRedirect(route('backpack.account.info'))->assertSessionHasNoErrors();

        $person = DB::table('xlr8_admin_person')->where('person_code', $user->person_code)->first();
        $this->assertSame(['Female', 'ABCPE1234F'], [$person->gender, $person->pan_no], 'PAN is not switched on, so it is ignored');
        $this->assertTrue(DB::table('xlr8_admin_person_contacts')->where('person_code', $user->person_code)
            ->where('data_type', 'Email')->where('contact_type', 'Primary')->where('contact_detail', 'me@example.com')->whereNull('deleted_at')->exists());

        $this->post(route('backpack.account.personal'), ['gender' => 'Robot'])->assertSessionHasErrorsIn('personal', 'gender');
    }

    public function test_the_appearance_panel_can_be_hidden(): void
    {
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
        $this->get('/admin/dashboard')->assertSee('aria-controls="xl-theme-settings"', false);

        app(SettingsService::class)->set('ui.appearance_enabled', false);

        $this->get('/admin/dashboard')->assertDontSee('aria-controls="xl-theme-settings"', false);
    }
}
