<?php

namespace Tests\Feature\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\Module\Finance\XFinance;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Booking-screen fixes of DEC-095 #10 (W18c, booking-team change log BT-008 onward). Each test failed before its fix.
 */
class BookingBugFixesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::role('superadmin')->firstOrFail(), 'backpack');
    }

    private function bookingWithoutFinance(): Booking
    {
        return Booking::query()->withoutGlobalScopes()->whereNotIn('id', XFinance::query()->withoutGlobalScopes()->select('bid'))->first()
            ?? $this->markTestSkipped('Every booking in the test copy has a finance record.');
    }

    /** BT-008 / BUG-223: the finance pages of a booking without finance details go back to the list with a message. */
    public function test_finance_pages_without_a_finance_record_return_to_the_list(): void
    {
        $booking = $this->bookingWithoutFinance();

        foreach (['view', 'payout-edit'] as $page) {
            $this->get("/admin/sales/booking/finance/{$booking->id}/{$page}")
                ->assertRedirect(backpack_url('sales/booking/finance'))
                ->assertSessionHas('error', __('booking.flash.finance_record_missing'));
        }
    }

    /** BT-009 / BUG-224: the "View" link of the Invoiced list opens the booking (its view was missing). */
    public function test_an_invoiced_booking_opens_from_the_invoiced_list(): void
    {
        $booking = Booking::query()->withoutGlobalScopes()->where('status', 2)->first() ?? $this->markTestSkipped('No invoiced booking in the test copy.');

        $this->get("/admin/sales/booking/{$booking->id}/invoiced-show")->assertOk();
    }
}
