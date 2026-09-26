<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Sales\Booking\BookingDmsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookingDmsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingDmsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingDmsService::class);

        $user = User::create([
            'username' => 'dms_test_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
        $this->app['auth']->guard('backpack')->setUser($user);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'pending_remark' => 'placeholder',
            'booking_amount' => 10000,
        ], $overrides));
    }

    public function test_apply_saves_dms_fields_when_dms_so_applies(): void
    {
        // Includes an unrelated pending item that won't be cleared, so
        // pending_remark stays non-empty here - see
        // test_apply_clearing_every_pending_item_stores_an_empty_pending_remark()
        // for the all-clear case.
        $booking = $this->makeBooking([
            'order' => 2,
            'pending_remark' => 'Unrelated item, DMS Booking no needs to be updated',
        ]);

        $updated = $this->service->apply($booking, [
            'dms_no' => 'B-12345678',
            'dms_otf' => 'OTF00A123456',
            'otf_date' => '2026-01-01',
            'dms_so' => '1234567890',
        ], true);

        $this->assertSame('B-12345678', $updated->dms_no);
        $this->assertSame('OTF00A123456', $updated->dms_otf);
        $this->assertSame('1234567890', $updated->dms_so);
    }

    public function test_apply_does_not_persist_dms_so_when_it_does_not_apply(): void
    {
        $booking = $this->makeBooking(['order' => 1, 'dms_so' => null]);

        $updated = $this->service->apply($booking, [
            'dms_no' => 'B-12345678',
            'dms_otf' => 'OTF00A123456',
            'otf_date' => '2026-01-01',
            'dms_so' => '1234567890',
        ], false);

        $this->assertNull($updated->dms_so);
    }

    public function test_apply_removes_satisfied_pending_items_and_keeps_unrelated_ones(): void
    {
        $booking = $this->makeBooking([
            'pending_remark' => 'Old unrelated item, DMS Booking no needs to be updated',
            'order' => 2,
            'dms_no' => null,
        ]);

        $updated = $this->service->apply($booking, [
            'dms_no' => 'B-12345678',
            'dms_otf' => 'OTF00A123456',
            'otf_date' => '2026-01-01',
            'dms_so' => '',
        ], true);

        $this->assertSame(2, $updated->pending);
        $this->assertStringContainsString('Old unrelated item', $updated->pending_remark);
        $this->assertStringNotContainsString('DMS Booking no needs to be updated', $updated->pending_remark);
        $this->assertStringContainsString('DMS SO number needs to be updated', $updated->pending_remark);
    }

    public function test_apply_clearing_every_pending_item_stores_an_empty_pending_remark(): void
    {
        // Regression for BUG-100: pending_remark is NOT NULL, so clearing the
        // last pending item used to throw instead of saving.
        $booking = $this->makeBooking([
            'pending_remark' => 'DMS OTF Date needs to be updated',
            'status' => 8,
            'order' => 2,
            'dms_no' => 'B-00000000',
            'dms_otf' => 'OTF00A000000',
            'dms_so' => '0000000000',
        ]);

        $updated = $this->service->apply($booking, [
            'dms_no' => 'B-12345678',
            'dms_otf' => 'OTF00A123456',
            'otf_date' => '2026-01-01',
            'dms_so' => '1234567890',
        ], true);

        $this->assertSame('', $updated->fresh()->pending_remark);
        $this->assertSame(0, (int) $updated->fresh()->pending);
    }

    public function test_apply_order_stays_2_when_booking_is_not_bev_or_personal_segment(): void
    {
        // xlr8_booking_master has no segment_code column of its own - it's
        // always null on a freshly-loaded Booking, so the "BEV/Personal ->
        // order 3" branch is unreachable in this flow (matches the
        // original inline behavior exactly, see BUG-101 in
        // known-bugs-report.md for why this was already dead code before
        // this extraction).
        $booking = $this->makeBooking(['order' => 1]);

        $updated = $this->service->apply($booking, [
            'dms_no' => 'B-12345678',
            'dms_otf' => 'OTF00A123456',
            'otf_date' => '2026-01-01',
            'dms_so' => '',
        ], false);

        $this->assertSame(2, $updated->order);
    }

    public function test_resolve_edit_data_returns_expected_shape(): void
    {
        $booking = $this->makeBooking();

        $data = $this->service->resolveEditData($booking, true);

        $this->assertEqualsCanonicalizing([
            'branch', 'location', 'collector_name', 'accessories',
            'total_amount', 'is_bev_or_personal', 'from_pending', 'so_required',
        ], array_keys($data));

        $this->assertTrue($data['from_pending']);
    }
}
