<?php

namespace Tests\Feature\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Xl_Refunds;
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

    /** BT-012 / BUG-219 (DEC-095 #11): a Dummy booking still needs the customer, branch, vehicle and sale type. */
    public function test_a_dummy_booking_without_its_base_fields_is_refused_and_nothing_is_saved(): void
    {
        $count = Booking::query()->withoutGlobalScopes()->count();

        $this->from('/admin/sales/booking/create')->post('/admin/sales/booking', ['customertype' => 'Dummy', 'customercat' => 'Individual'])
            ->assertRedirect('/admin/sales/booking/create')
            ->assertSessionHas('error');

        $this->assertSame($count, Booking::query()->withoutGlobalScopes()->count());
    }

    /** BT-014 / BUG-095 (DEC-095 #9): Order Verification's Accept / Reject follow a permission, not a list of user ids. */
    public function test_order_verification_actions_need_the_approve_permission(): void
    {
        $booking = Booking::query()->withoutGlobalScopes()->where('order', 1)->first() ?? $this->markTestSkipped('No booking awaiting verification.');
        $this->assertStringContainsString('order-update\/', $this->get('/admin/sales/booking/order-verification')->assertOk()->getContent(), 'superadmin sees Accept / Reject');

        $verifier = User::query()->find(40) ?? $this->markTestSkipped('Scoped test user 40 is missing.');   // a non-superadmin who opens the admin
        $verifier->givePermissionTo('SLS_BKNG_ORDER_VERIFY');
        $this->flushSession();   // the superadmin request above left its session-auth marker
        $this->actingAs($verifier, 'backpack');
        $this->get("/admin/sales/booking/order-update/{$booking->id}/1")->assertForbidden();

        $verifier->givePermissionTo('SLS_BKNG_ORDER_APPROVE');
        $this->from('/admin/sales/booking/order-verification')->get("/admin/sales/booking/order-update/{$booking->id}/1")
            ->assertRedirect('/admin/sales/booking/order-verification');
    }

    /** BT-010 / BUG-225: the refund view of a booking with a refund record opens (it read an undefined `$receiptLogs`). */
    public function test_the_refund_view_opens_for_a_booking_with_a_refund(): void
    {
        $refunded = Xl_Refunds::query()->withoutGlobalScopes()->where('entity_type', 'booking')->value('entity_id')
            ?? $this->markTestSkipped('No refund record in the test copy.');

        $this->get("/admin/sales/booking/{$refunded}/refund-view")->assertOk();
    }
}
