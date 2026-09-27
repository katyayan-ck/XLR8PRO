<?php

namespace Tests\Feature\IAM;

use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\XExchange;
use App\Models\User;
use App\Services\IAM\DataScope\DataScopeManager;
use App\Services\IAM\DataScope\ScopeResolver;
use App\Services\IAM\UserScopeService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Facades\DataScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DEC-071: business models are filtered by the signed-in user's scope automatically; rows without a code stay
 * visible while scope.unassigned_rows = visible; withoutDataScope(), DataScope::off() and the master switch lift it.
 */
class DataScopeFilterTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScopeResolver::class)->flushMasters();
        $this->user = User::create([
            'username' => 'scope_filter_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
    }

    private function actAsScopedUser(string $type, string $code): void
    {
        app(UserScopeService::class)->grant($this->user->id, $type, $code);
        app(ScopeResolver::class)->flush();
        $this->app['auth']->guard('backpack')->setUser($this->user);
    }

    private function booking(array $codes = []): Booking
    {
        return Booking::withoutDataScope()->create(array_merge(['pending_remark' => '', 'booking_amount' => 10000], $codes));
    }

    public function test_a_branch_scoped_user_sees_their_branch_and_unassigned_rows_only(): void
    {
        $bkn = $this->booking(['branch_code' => 'BKN']);
        $chr = $this->booking(['branch_code' => 'CHR']);
        $none = $this->booking();
        $this->actAsScopedUser('branch', 'BKN');

        $visible = Booking::whereIn('id', [$bkn->id, $chr->id, $none->id])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$bkn->id, $none->id], $visible);
    }

    public function test_a_location_inside_the_branch_decides_when_the_row_has_one(): void
    {
        $locations = DB::table('xlr8_admin_location')->whereNull('deleted_at')->where('branch_code', 'BKN')->limit(2)->pluck('code')->all();
        if (count($locations) < 2) {
            $this->markTestSkipped('Needs two BKN locations.');
        }
        $mine = $this->booking(['branch_code' => 'BKN', 'location_code' => $locations[0]]);
        $other = $this->booking(['branch_code' => 'BKN', 'location_code' => $locations[1]]);
        $branchOnly = $this->booking(['branch_code' => 'BKN']);
        $this->actAsScopedUser('branch', 'BKN');
        app(UserScopeService::class)->grant($this->user->id, 'location', $locations[0]);
        app(ScopeResolver::class)->flush();

        $visible = Booking::whereIn('id', [$mine->id, $other->id, $branchOnly->id])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$mine->id, $branchOnly->id], $visible, 'an empty location falls back to the branch');
    }

    public function test_unassigned_rows_disappear_when_the_setting_is_hidden(): void
    {
        $bkn = $this->booking(['branch_code' => 'BKN']);
        $none = $this->booking();
        $this->actAsScopedUser('branch', 'BKN');
        app(SettingsService::class)->set('scope.unassigned_rows', 'hidden');
        app(DataScopeManager::class)->refreshSettings();

        $this->assertSame([$bkn->id], Booking::whereIn('id', [$bkn->id, $none->id])->pluck('id')->all());
    }

    public function test_satellites_follow_their_booking(): void
    {
        $chr = $this->booking(['branch_code' => 'CHR']);
        $bkn = $this->booking(['branch_code' => 'BKN']);
        $hidden = XExchange::seedForBooking($chr->id, 'Exchange Buy');
        $shown = XExchange::seedForBooking($bkn->id, 'Exchange Buy');
        $this->actAsScopedUser('branch', 'BKN');

        $this->assertSame([$shown->id], XExchange::whereIn('id', [$hidden->id, $shown->id])->pluck('id')->all());
    }

    public function test_a_segment_scope_filters_by_the_most_specific_vehicle_code(): void
    {
        $pvModel = DB::table('xlr8_vehicle_model')->whereNull('deleted_at')->where('segment_code', 'PV')->value('code');
        $cvModel = DB::table('xlr8_vehicle_model')->whereNull('deleted_at')->where('segment_code', 'CV')->value('code');
        if (! $pvModel || ! $cvModel) {
            $this->markTestSkipped('Needs PV and CV models.');
        }
        $pv = $this->booking(['model_code' => $pvModel]);
        $cv = $this->booking(['model_code' => $cvModel]);
        $segmentOnly = $this->booking(['segment_code' => 'PV']);
        $wrongSegment = $this->booking(['segment_code' => 'CV']);
        $this->actAsScopedUser('segment', 'PV');

        $visible = Booking::whereIn('id', [$pv->id, $cv->id, $segmentOnly->id, $wrongSegment->id])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pv->id, $segmentOnly->id], $visible, 'model decides; an empty model falls back to the segment');
    }

    public function test_the_opt_outs_see_every_row(): void
    {
        $chr = $this->booking(['branch_code' => 'CHR']);
        $this->actAsScopedUser('branch', 'BKN');

        $this->assertFalse(Booking::whereKey($chr->id)->exists());
        $this->assertTrue(Booking::withoutDataScope()->whereKey($chr->id)->exists());
        $this->assertTrue(DataScope::off(fn () => Booking::whereKey($chr->id)->exists(), 'test'));
        $this->assertFalse(Booking::whereKey($chr->id)->exists(), 'scoping is back after the closure');

        app(DataScopeManager::class)->offForRequest('test route');
        $this->assertTrue(Booking::whereKey($chr->id)->exists());
    }

    public function test_the_master_switch_turns_scoping_off(): void
    {
        $chr = $this->booking(['branch_code' => 'CHR']);
        $this->actAsScopedUser('branch', 'BKN');
        app(SettingsService::class)->set('scope.enabled', false);
        app(DataScopeManager::class)->refreshSettings();

        $this->assertTrue(Booking::whereKey($chr->id)->exists());
    }

    public function test_without_a_signed_in_user_nothing_is_filtered(): void
    {
        $chr = $this->booking(['branch_code' => 'CHR']);
        app(UserScopeService::class)->grant($this->user->id, 'branch', 'BKN');

        $this->assertTrue(Booking::whereKey($chr->id)->exists(), 'jobs, console and imports run unscoped');
    }

    public function test_raw_queries_can_take_the_same_filter(): void
    {
        $chr = $this->booking(['branch_code' => 'CHR']);
        $bkn = $this->booking(['branch_code' => 'BKN']);
        $this->actAsScopedUser('branch', 'BKN');

        $query = DB::table('xlr8_booking_master as b')->whereIn('b.id', [$chr->id, $bkn->id]);
        DataScope::apply($query, Booking::class, 'b');

        $this->assertSame([$bkn->id], $query->pluck('b.id')->all());
    }
}
