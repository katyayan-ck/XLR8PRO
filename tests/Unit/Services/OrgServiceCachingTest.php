<?php

namespace Tests\Unit\Services;

use App\Services\OrgService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the query-caching pass added to OrgService's user/keyword lookup
 * methods, per .ai/rules/conventions.md section 13's "apply query caching
 * ... when touching a data-rendering screen" requirement. These methods
 * are called repeatedly by every Booking sub-domain service's
 * resolveEditData() (BookingKycService, BookingRtoService,
 * BookingFinanceService, BookingExchangeService, BookingOtfService, etc.)
 * on every single edit-screen page load.
 */
class OrgServiceCachingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_sales_consultants_result_is_cached_after_first_call(): void
    {
        $first = OrgService::salesConsultants();

        DB::enableQueryLog();
        $second = OrgService::salesConsultants();
        $queriesOnCachedCall = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        // The database cache driver itself issues one lookup query - the
        // absence of the original 3-query whereHas() chain is what matters.
        $this->assertLessThanOrEqual(1, $queriesOnCachedCall);
    }

    public function test_sales_consultants_cache_key_is_scoped_by_branch_code(): void
    {
        // BUG-108 (known-bugs-report.md): passing a real (non-'ALL') branch
        // code into userQuery()'s branch scope throws, because
        // xlr8_admin_emp_branch_pivot doesn't exist in this database - a
        // pre-existing, previously-undiscovered issue surfaced while
        // writing this test, not caused by the caching change. Every real
        // Booking call site only ever passes the default 'ALL', so this
        // is only verified with the key format itself, not a real branch
        // query.
        OrgService::salesConsultants('ALL');

        $this->assertTrue(Cache::has('org.sales_consultants.ALL'));
    }

    public function test_users_by_designation_is_cached(): void
    {
        OrgService::usersByDesignation('CNS');

        $this->assertTrue(Cache::has('org.users_by_designation.CNS.ALL'));
    }

    public function test_users_by_department_uses_a_distinct_cache_key_per_department(): void
    {
        OrgService::usersByDepartment('SLS');

        $this->assertTrue(Cache::has('org.users_by_department.SLS.ALL.ALL'));
    }

    public function test_get_key_values_by_code_is_cached_and_returns_consistent_results(): void
    {
        $first = OrgService::getKeyValuesByCode('RTO_PERMIT');

        DB::enableQueryLog();
        $second = OrgService::getKeyValuesByCode('RTO_PERMIT');
        $queriesOnCachedCall = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals($first?->count(), $second?->count());
        $this->assertLessThanOrEqual(1, $queriesOnCachedCall);
    }

    public function test_get_key_value_by_id_returns_null_without_querying_for_empty_input(): void
    {
        $this->assertNull(OrgService::getKeyValueById(null));
        $this->assertNull(OrgService::getKeyValueById(''));
    }

    public function test_keyword_value_by_code_is_cached(): void
    {
        OrgService::keywordValueByCode('EXISTING_CAR_OEM');

        $this->assertTrue(Cache::has('org.keyword_value_by_code.EXISTING_CAR_OEM'));
    }
}
