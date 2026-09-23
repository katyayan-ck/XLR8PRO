<?php

namespace Tests\Unit\Models\Vehicle;

use App\Models\Vehicle\Accessory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccessoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_type_constants_match_the_type_columns_documented_enum(): void
    {
        $this->assertSame('Accessory', Accessory::TYPE_ACCESSORY);
        $this->assertSame('Ceramic', Accessory::TYPE_CERAMIC);
        $this->assertSame('PPF', Accessory::TYPE_PPF);
        $this->assertSame('Maxicare', Accessory::TYPE_MAXICARE);
        $this->assertSame('GPS_VLTD', Accessory::TYPE_GPS_VLTD);
        $this->assertSame('RTO_Tape', Accessory::TYPE_RTO_TAPE);
        $this->assertSame('Kazam', Accessory::TYPE_KAZAM);
    }

    public function test_all_types_contains_every_type_constant(): void
    {
        $this->assertEqualsCanonicalizing([
            Accessory::TYPE_ACCESSORY,
            Accessory::TYPE_CERAMIC,
            Accessory::TYPE_PPF,
            Accessory::TYPE_MAXICARE,
            Accessory::TYPE_GPS_VLTD,
            Accessory::TYPE_RTO_TAPE,
            Accessory::TYPE_KAZAM,
        ], Accessory::ALL_TYPES);
    }

    public function test_bundle_types_excludes_rto_tape_and_kazam(): void
    {
        $this->assertNotContains(Accessory::TYPE_RTO_TAPE, Accessory::BUNDLE_TYPES);
        $this->assertNotContains(Accessory::TYPE_KAZAM, Accessory::BUNDLE_TYPES);
        $this->assertContains(Accessory::TYPE_ACCESSORY, Accessory::BUNDLE_TYPES);
        $this->assertContains(Accessory::TYPE_CERAMIC, Accessory::BUNDLE_TYPES);
    }

    public function test_type_and_set_qty_are_mass_assignable(): void
    {
        // Mirrors AccessoryService's real import write path
        // (Accessory::updateOrCreate with type/item/set_qty), which
        // silently dropped both before $fillable included them.
        $accessory = Accessory::query()->create([
            'part_no' => 'TEST-'.uniqid(),
            'type' => Accessory::TYPE_CERAMIC,
            'display_name' => 'Test Accessory',
            'item' => 'Test Item',
            'set_qty' => 2,
            'status' => 1,
        ]);

        $fresh = $accessory->fresh();
        $this->assertSame(Accessory::TYPE_CERAMIC, $fresh->type);
        $this->assertSame(2, $fresh->set_qty);
    }
}
