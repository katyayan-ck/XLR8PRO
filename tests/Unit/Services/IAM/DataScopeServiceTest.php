<?php

namespace Tests\Unit\Services\IAM;

use App\Http\Scopes\DataScopeFilter;
use App\Models\Admin\UserScope;
use App\Models\Module\Booking\Stock;
use App\Models\User;
use App\Services\IAM\DataScopeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataScopeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DataScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataScopeService::class);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'scope_test_'.uniqid(),
            'password' => bcrypt('password'),
            'is_active' => 1,
        ], $overrides));
    }

    private function makeLocation(string $code): int
    {
        return DB::table('xlr8_admin_location')->insertGetId([
            'branch_code' => 'TSTB',
            'code' => $code,
            'name' => 'Test '.$code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantScope(User $user, string $type, string $code, bool $active = true): void
    {
        UserScope::create([
            'user_id' => $user->id,
            'scope_type' => $type,
            'scope_code' => $code,
            'is_active' => $active,
        ]);
    }

    public function test_user_with_bypass_flag_is_unrestricted(): void
    {
        $user = $this->makeUser(['bypass_data_scoping' => true]);

        $this->assertNull($this->service->getAccessibleIds($user, 'location'));
    }

    public function test_scope_codes_resolve_to_the_entity_ids(): void
    {
        $user = $this->makeUser();
        $idA = $this->makeLocation('TSTLA');
        $idB = $this->makeLocation('TSTLB');
        $this->makeLocation('TSTLC');
        $this->grantScope($user, 'location', 'TSTLA');
        $this->grantScope($user, 'location', 'TSTLB');

        $this->assertEqualsCanonicalizing([$idA, $idB], $this->service->getAccessibleIds($user, 'location'));
    }

    public function test_user_with_no_scope_rows_of_that_type_sees_nothing(): void
    {
        $user = $this->makeUser();
        $this->makeLocation('TSTLD');
        $this->grantScope($user, 'location', 'TSTLD');

        $this->assertSame([], $this->service->getAccessibleIds($user, 'branch'));
    }

    public function test_inactive_scope_rows_are_ignored(): void
    {
        $user = $this->makeUser();
        $this->makeLocation('TSTLE');
        $this->grantScope($user, 'location', 'TSTLE', active: false);

        $this->assertSame([], $this->service->getAccessibleIds($user, 'location'));
    }

    public function test_unknown_scope_type_fails_closed(): void
    {
        $user = $this->makeUser();

        $this->assertSame([], $this->service->getAccessibleIds($user, 'brand'));
    }

    public function test_data_scope_filter_restricts_query_to_scoped_ids(): void
    {
        $user = $this->makeUser();
        $id = $this->makeLocation('TSTLF');
        $this->grantScope($user, 'location', 'TSTLF');
        Auth::login($user);

        $query = Stock::query();
        (new DataScopeFilter)->apply($query, new Stock);

        $this->assertStringContainsString('`location_id` in (?)', $query->toSql());
        $this->assertSame([$id], $query->getBindings());
    }

    public function test_data_scope_filter_matches_nothing_when_user_has_no_scope(): void
    {
        Auth::login($this->makeUser());

        $query = Stock::query();
        (new DataScopeFilter)->apply($query, new Stock);

        $this->assertStringContainsString('1 = 0', $query->toSql());
    }
}
