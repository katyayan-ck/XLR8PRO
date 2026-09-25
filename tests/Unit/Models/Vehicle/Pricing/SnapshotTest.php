<?php

namespace Tests\Unit\Models\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\Snapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SnapshotTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Regression for BUG-135: redeclaring `protected $casts;` with no
     * default shadowed BaseModel's array default with null, crashing
     * Eloquent's own internals the instant the model was instantiated —
     * before the model's constructor override ever ran.
     */
    public function test_model_can_be_instantiated_without_crashing(): void
    {
        $snapshot = new Snapshot;

        $this->assertInstanceOf(Snapshot::class, $snapshot);
    }

    public function test_inherited_audit_casts_and_own_casts_are_both_present(): void
    {
        $casts = (new Snapshot)->getCasts();

        $this->assertSame('datetime', $casts['created_at']);
        $this->assertSame('array', $casts['payload']);
        $this->assertSame('boolean', $casts['is_active']);
        $this->assertSame('date', $casts['wef_date']);
    }

    public function test_payload_persists_and_reads_back_as_an_array(): void
    {
        $snapshot = Snapshot::query()->create([
            'model_code' => 'TESTCODE',
            'channel' => 'normal',
            'vin_type' => 'nv',
            'wef_date' => now()->toDateString(),
            'payload' => ['ex_showroom' => 500000, 'on_road' => 550000],
            'is_active' => true,
        ]);

        $fresh = $snapshot->fresh();
        $this->assertIsArray($fresh->payload);
        $this->assertSame(500000, $fresh->payload['ex_showroom']);
    }
}
