<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\Module\Finance\XFinance;
use App\Models\User;
use App\Services\Sales\Booking\BookingFinanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookingFinanceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingFinanceService::class);

        $user = User::create([
            'username' => 'finance_test_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
        $this->app['auth']->guard('backpack')->setUser($user);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'pending_remark' => '',
            'booking_amount' => 10000,
        ], $overrides));
    }

    public function test_apply_creates_a_new_finance_record(): void
    {
        $booking = $this->makeBooking();

        $finance = $this->service->apply($booking, [
            'fin_mode' => 'In-house',
            'verification_status' => 2,
            'case_status' => 2,
            'instrument_type' => 1,
            'instrument_ref_no' => 'REF123',
            'loan_amount' => 50000,
            'margin_money' => 5000,
            'file_charge' => 500,
            'remark' => 'initial',
        ], false, false);

        $this->assertSame($booking->id, $finance->bid);
        $this->assertSame('In-house', $finance->fin_mode);
        $this->assertSame(2, $finance->status);
    }

    public function test_apply_clears_mode_specific_fields_for_cash_mode(): void
    {
        $booking = $this->makeBooking();

        $finance = $this->service->apply($booking, [
            'fin_mode' => 'Cash',
            'verification_status' => 1,
            'case_status' => 1,
            'remark' => 'cash purchase',
        ], false, false);

        $this->assertNull($finance->loan_amount);
        $this->assertSame(1, $finance->case_lost_reason);
    }

    public function test_apply_auto_verifies_a_new_retail_record_with_default_remark(): void
    {
        $booking = $this->makeBooking();

        $finance = $this->service->apply($booking, [
            'fin_mode' => 'In-house',
            'verification_status' => 1,
            'remark' => '',
            'retail' => 1,
        ], false, false);

        $this->assertSame(2, $finance->verification_status);
        $this->assertSame(2, $finance->case_status);
        $this->assertSame(1, $booking->fresh()->retail);
    }

    public function test_apply_updates_an_existing_finance_record_without_resetting_created_by(): void
    {
        $booking = $this->makeBooking();
        $existing = XFinance::create([
            'bid' => $booking->id,
            'fin_mode' => 'In-house',
            'verification_status' => 1,
            'case_status' => 1,
            'created_by' => 999,
        ]);

        $finance = $this->service->apply($booking, [
            'fin_mode' => 'In-house',
            'verification_status' => 2,
            'case_status' => 2,
            'remark' => 'updated',
        ], false, false);

        $this->assertSame($existing->id, $finance->id);
        $this->assertSame(999, $finance->created_by);
    }

    public function test_apply_payout_saves_payout_category_1_fields_and_marks_booking(): void
    {
        $booking = $this->makeBooking();
        $finance = XFinance::create([
            'bid' => $booking->id,
            'fin_mode' => 'In-house',
            'verification_status' => 2,
            'case_status' => 2,
        ]);

        $updated = $this->service->applyPayout($booking, $finance, [
            'payout_category' => 1,
            'loan_amount' => 40000,
            'do_number' => 'DO123',
            'expected_payout_pct' => 2.5,
            'gst_included' => 1,
            'inv1_no' => 'INV1',
            'inv1_name' => 'Invoice One',
            'inv1_prov_gst' => 1000,
            'consideration_no_gst' => 38000,
            'difference_no_gst' => 2000,
            'payout_remarks' => 'payout done',
        ]);

        $this->assertSame(1, $updated->payout_category);
        $this->assertSame(3, $updated->status);
        $this->assertSame(2, $booking->fresh()->payout);
    }

    public function test_apply_payout_nulls_payout_fields_for_no_payout_category(): void
    {
        $booking = $this->makeBooking();
        $finance = XFinance::create([
            'bid' => $booking->id,
            'fin_mode' => 'In-house',
            'verification_status' => 2,
            'case_status' => 2,
            'loan_amount' => 10000,
        ]);

        $updated = $this->service->applyPayout($booking, $finance, [
            'payout_category' => 2,
            'no_payout_reason' => 3,
            'payout_remarks' => 'no payout',
        ]);

        $this->assertNull($updated->loan_amount);
        $this->assertSame(2, $updated->status);
    }

    public function test_resolve_fin_edit_data_returns_expected_shape(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->resolveFinEditData($booking);

        $this->assertEqualsCanonicalizing(['finance', 'data', 'dsaname'], array_keys($result));
        $this->assertNull($result['finance']);
    }

    public function test_resolve_finance_view_data_returns_expected_shape(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->resolveFinanceViewData($booking);

        $this->assertEqualsCanonicalizing(['finance', 'data'], array_keys($result));
        $this->assertArrayHasKey('financiers', $result['data']);
    }
}
