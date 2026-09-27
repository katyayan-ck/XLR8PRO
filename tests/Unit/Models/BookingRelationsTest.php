<?php

namespace Tests\Unit\Models;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\XExchange;
use App\Models\Module\Finance\XFinance;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BUG-192 / BUG-193 (DEC-070): the booking satellites key on `bid`, and quotations store the enquiry id in
 * `enquiry_no` — the relations must join on those columns.
 */
class BookingRelationsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_booking_reaches_its_finance_and_exchange_rows_and_back(): void
    {
        $booking = Booking::create(['pending_remark' => '', 'booking_amount' => 10000]);
        $finance = XFinance::create(['bid' => $booking->id, 'verification_status' => 0, 'case_status' => 1]);
        $exchange = XExchange::seedForBooking($booking->id, 'Exchange Buy');

        $this->assertSame([$finance->id], $booking->finances()->pluck('id')->all());
        $this->assertSame([$exchange->id], $booking->exchanges()->pluck('id')->all());
        $this->assertSame($booking->id, $finance->booking->id);
        $this->assertSame($booking->id, $exchange->booking->id);
    }

    public function test_an_enquiry_lists_the_quotations_raised_on_it(): void
    {
        $enquiry = Enquiry::query()->first();
        if (! $enquiry) {
            $this->markTestSkipped('Needs an enquiry in xlrm_testing.');
        }
        $quotation = Quotation::create(['enquiry_no' => $enquiry->id, 'standard_data' => ['ex_showroom_price' => 500000]]);

        $this->assertContains($quotation->id, $enquiry->quotations()->pluck('id')->all());
    }
}
