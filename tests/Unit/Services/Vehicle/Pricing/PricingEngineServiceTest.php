<?php

namespace Tests\Unit\Services\Vehicle\Pricing;

use App\Services\Vehicle\Pricing\PricingEngineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PricingEngineServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function callKv(mixed $id): ?string
    {
        $service = app(PricingEngineService::class);
        $method = new ReflectionMethod($service, 'kv');
        $method->setAccessible(true);

        return $method->invoke($service, $id);
    }

    /**
     * Regression for BUG-132: kv() queried 2 nonexistent table names
     * (xlr8_utilities_keyvalues, xlr8_keyvalues) instead of the real
     * xlr8_utils_keyvalue, silently returning null for every permit/fuel
     * lookup, for every vehicle, unconditionally.
     */
    public function test_kv_resolves_a_real_keyvalue_row_by_id(): void
    {
        $id = DB::table('xlr8_utils_keyvalue')->insertGetId([
            'keyword_code' => 'TEST_PERMIT',
            'key' => 'GOODS',
            'code' => 'GOODS',
            'value' => 'Goods',
            'status' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('Goods', $this->callKv($id));
    }

    public function test_kv_returns_null_for_a_falsy_id(): void
    {
        $this->assertNull($this->callKv(null));
        $this->assertNull($this->callKv(0));
    }

    public function test_kv_returns_null_for_an_id_that_does_not_exist(): void
    {
        $this->assertNull($this->callKv(999999999));
    }
}
