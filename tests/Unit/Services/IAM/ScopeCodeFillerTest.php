<?php

namespace Tests\Unit\Services\IAM;

use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DEC-071: a booking fills its empty scope codes on save — vehicle codes from its quotation snapshot (a variant gives
 * its model / sub-segment / segment) and the branch from its location — without overwriting codes already set.
 */
class ScopeCodeFillerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_booking_takes_vehicle_codes_from_its_quotation_variant(): void
    {
        $variant = DB::table('xlr8_vehicle_variant')->whereNull('deleted_at')->whereNotNull('model_code')->first(['code', 'model_code', 'segment_code', 'sub_segment_code']);
        if (! $variant) {
            $this->markTestSkipped('Needs a vehicle variant in xlrm_testing.');
        }
        $quotation = Quotation::withoutDataScope()->create(['standard_data' => ['variant_code' => $variant->code]]);

        $booking = Booking::create(['pending_remark' => '', 'booking_amount' => 10000, 'quotation_id' => $quotation->id]);

        $this->assertSame(strtoupper($variant->code), $booking->variant_code);
        $this->assertSame(strtoupper($variant->model_code), $booking->model_code);
        $this->assertSame(strtoupper($variant->segment_code), $booking->segment_code);
    }

    public function test_a_location_gives_the_branch_and_set_codes_are_kept(): void
    {
        $location = DB::table('xlr8_admin_location')->whereNull('deleted_at')->whereNotNull('branch_code')->first(['code', 'branch_code']);
        if (! $location) {
            $this->markTestSkipped('Needs a location in xlrm_testing.');
        }

        $booking = Booking::create(['pending_remark' => '', 'booking_amount' => 10000, 'location_code' => $location->code, 'segment_code' => 'KEEP']);

        $this->assertSame(strtoupper($location->branch_code), $booking->branch_code);
        $this->assertSame('KEEP', $booking->segment_code);
    }
}
