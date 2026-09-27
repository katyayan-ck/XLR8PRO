<?php

namespace Tests\Unit\Services\IAM;

use App\Models\Admin\UserScope;
use App\Models\User;
use App\Services\IAM\DataScope\ScopeResolver;
use App\Services\IAM\UserScopeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DEC-071: a user's scope rows resolve into the codes they may see at every level — no rows = everything, a parent
 * covers all children unless restricted lower down, and a child restriction applies within its assigned ancestor.
 */
class ScopeResolverTest extends TestCase
{
    use DatabaseTransactions;

    private ScopeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ScopeResolver::class);
        $this->resolver->flushMasters();
    }

    private function user(): User
    {
        return User::create([
            'username' => 'scope_test_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
    }

    private function grant(User $user, string $type, string $code): void
    {
        app(UserScopeService::class)->grant($user->id, $type, $code);
        $this->resolver->flush($user->id);
    }

    /** @return list<string> */
    private function codes(string $table, array $where = []): array
    {
        return DB::table($table)->whereNull('deleted_at')->where($where)->pluck('code')
            ->map(fn ($c) => strtoupper((string) $c))->unique()->sort()->values()->all();
    }

    /** @param list<string>|null $actual */
    private function assertSameCodes(array $expected, ?array $actual): void
    {
        $this->assertNotNull($actual);
        sort($expected);
        $actual = array_values(array_unique($actual));
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    private function modelIn(string $segment): string
    {
        $model = DB::table('xlr8_vehicle_model')->whereNull('deleted_at')->where('segment_code', $segment)
            ->whereExists(fn ($q) => $q->from('xlr8_vehicle_variant as v')->whereColumn('v.model_code', 'xlr8_vehicle_model.code')->whereNull('v.deleted_at'))
            ->value('code');
        if (! $model) {
            $this->markTestSkipped("Needs a {$segment} model with variants in xlrm_testing.");
        }

        return strtoupper((string) $model);
    }

    public function test_a_user_without_scope_rows_is_unrestricted(): void
    {
        $scope = $this->resolver->for($this->user());

        $this->assertTrue($scope->isUnrestricted());
        $this->assertNull($scope->allowed('variant'));
        $this->assertNull($scope->allowed('branch'));
    }

    public function test_a_segment_covers_every_model_and_variant_under_it(): void
    {
        $user = $this->user();
        $this->grant($user, 'segment', 'PV');

        $scope = $this->resolver->for($user);

        $this->assertSame(['PV'], $scope->allowed('segment'));
        $this->assertSameCodes($this->codes('xlr8_vehicle_model', ['segment_code' => 'PV']), $scope->allowed('model'));
        $this->assertSameCodes($this->codes('xlr8_vehicle_variant', ['segment_code' => 'PV']), $scope->allowed('variant'));
        $this->assertNull($scope->allowed('branch'), 'other trees stay unrestricted');
    }

    public function test_a_model_under_a_segment_narrows_to_that_models_variants(): void
    {
        $user = $this->user();
        $model = $this->modelIn('PV');
        $this->grant($user, 'segment', 'PV');
        $this->grant($user, 'model', $model);

        $scope = $this->resolver->for($user);

        $this->assertSame([$model], $scope->allowed('model'));
        $this->assertSameCodes($this->codes('xlr8_vehicle_variant', ['model_code' => $model]), $scope->allowed('variant'));
    }

    public function test_a_model_restriction_only_narrows_its_own_segment(): void
    {
        $user = $this->user();
        $model = $this->modelIn('PV');
        $this->grant($user, 'segment', 'PV');
        $this->grant($user, 'segment', 'CV');
        $this->grant($user, 'model', $model);

        $allowedModels = $this->resolver->for($user)->allowed('model');

        $this->assertSameCodes(array_merge([$model], $this->codes('xlr8_vehicle_model', ['segment_code' => 'CV'])), $allowedModels);
    }

    public function test_a_branch_covers_its_locations_unless_one_is_assigned(): void
    {
        $user = $this->user();
        $this->grant($user, 'branch', 'BKN');
        $bknLocations = $this->codes('xlr8_admin_location', ['branch_code' => 'BKN']);
        if (count($bknLocations) < 2) {
            $this->markTestSkipped('Needs a branch with two locations.');
        }

        $this->assertSameCodes($bknLocations, $this->resolver->for($user)->allowed('location'));

        $this->grant($user, 'location', $bknLocations[0]);
        $this->assertSame([$bknLocations[0]], $this->resolver->for($user)->allowed('location'));
    }

    public function test_expired_inactive_and_wildcard_rows_do_not_restrict(): void
    {
        $user = $this->user();
        $this->grant($user, 'segment', 'PV');
        UserScope::where('user_id', $user->id)->update(['to_date' => now()->subDay()->toDateString()]);
        $this->grant($user, 'branch', 'BKN');
        UserScope::where('user_id', $user->id)->where('scope_type', 'branch')->update(['is_active' => false]);
        DB::table('xlr8_admin_user_scopes')->insert(['user_id' => $user->id, 'scope_type' => 'vertical', 'scope_code' => 'ALL', 'is_active' => 1, 'created_at' => now()]);
        $this->resolver->flush($user->id);

        $this->assertTrue($this->resolver->for($user)->isUnrestricted());
    }

    public function test_a_superadmin_is_unrestricted_whatever_their_rows(): void
    {
        $admin = User::role('superadmin')->firstOrFail();

        $this->assertTrue($this->resolver->for($admin)->isUnrestricted());
    }
}
