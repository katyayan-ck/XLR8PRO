<?php

namespace Tests\Feature\Dashboard;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Dashboard\DashboardPeriod;
use App\Services\IAM\DataScope\ScopeResolver;
use App\Services\IAM\UserScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * DEC-072: the dashboard shows only the widgets the user's permissions allow, each widget endpoint re-checks that
 * permission, and the numbers are counted inside the user's data scope.
 */
class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    private function userWith(array $permissions): User
    {
        $user = User::create(['username' => 'dash_test_'.uniqid(), 'password' => bcrypt('x'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo(array_merge(['admin.dashboard'], $permissions));

        return $user;
    }

    public function test_the_page_shows_only_permitted_widgets(): void
    {
        $user = $this->userWith(['SLS_BKNG_VIEW']);

        $this->actingAs($user, 'backpack')->get(route('backpack.dashboard'))
            ->assertOk()
            ->assertSee('data-widget="bookings_live"', false)
            ->assertDontSee('data-widget="receipts"', false)
            ->assertDontSee('data-widget="enquiries"', false);
    }

    public function test_a_widget_endpoint_rechecks_its_permission(): void
    {
        $user = $this->userWith(['SLS_BKNG_VIEW']);
        $this->actingAs($user, 'backpack');

        $this->getJson(route('dashboard.widget', ['key' => 'receipts']))->assertForbidden();
        $this->getJson(route('dashboard.widget', ['key' => 'no_such_widget']))->assertNotFound();
        $this->getJson(route('dashboard.widget', ['key' => 'bookings_live', 'period' => 'fy']))
            ->assertOk()->assertJsonPath('type', 'kpi')->assertJsonStructure(['data' => ['value', 'lines', 'hint']]);
    }

    public function test_counts_stay_inside_the_users_data_scope(): void
    {
        $user = $this->userWith(['SLS_BKNG_VIEW']);
        app(UserScopeService::class)->grant($user->id, 'branch', 'BKN');
        app(ScopeResolver::class)->flush();
        $this->actingAs($user, 'backpack');
        config(['dashboard.cache_seconds' => 0]);   // fresh numbers on every call
        $count = function (): int {
            return (int) $this->getJson(route('dashboard.widget', ['key' => 'bookings_new', 'period' => 'today']))->json('data.value');
        };

        $before = $count();
        Booking::withoutDataScope()->create(['pending_remark' => '', 'booking_amount' => 1, 'booking_date' => now()->toDateString(), 'branch_code' => 'CHR']);
        $this->assertSame($before, $count(), 'another branch is not counted');

        Booking::withoutDataScope()->create(['pending_remark' => '', 'booking_amount' => 1, 'booking_date' => now()->toDateString(), 'branch_code' => 'BKN']);
        $this->assertSame($before + 1, $count(), 'the user\'s own branch is counted');
    }

    public function test_the_financial_year_runs_april_to_march(): void
    {
        $p = DashboardPeriod::make('fy', CarbonImmutable::create(2027, 2, 10));

        $this->assertSame('2026-04-01', $p->from->toDateString());
        $this->assertSame('2027-03-31', $p->to->toDateString());
        $this->assertSame('month', DashboardPeriod::make('bogus')->key);
    }
}
