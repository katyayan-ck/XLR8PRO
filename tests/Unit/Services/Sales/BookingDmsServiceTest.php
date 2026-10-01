<?php

namespace Tests\Unit\Services\Sales;

use App\Models\CRM\Enquiry;
use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Models\Vehicle\VehicleModel;
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
        $booking = $this->makeBooking(['order' => 1, 'segment_code' => 'CV']);

        $updated = $this->service->apply($booking, $this->dmsInput(''), false);

        $this->assertSame(2, $updated->order);
    }

    /** BT-013 / BUG-101 (DEC-095 #12): a BEV / Personal booking submitted without an SO goes to order 3. */
    public function test_apply_sends_a_bev_or_personal_booking_without_an_so_to_order_3(): void
    {
        foreach (['BEV', 'PV'] as $segment) {
            $updated = $this->service->apply($this->makeBooking(['order' => 1, 'segment_code' => $segment]), $this->dmsInput(''), false);
            $this->assertSame(3, $updated->order, $segment);
        }

        $withSo = $this->service->apply($this->makeBooking(['order' => 2, 'segment_code' => 'BEV']), $this->dmsInput('1234567890'), true);
        $this->assertSame(2, $withSo->order);
    }

    /** BT-013 / BUG-101: the booking row rarely holds a segment — it is taken from the linked enquiry, else the model. */
    public function test_apply_resolves_the_segment_from_the_enquiry_or_the_model(): void
    {
        $enquiry = Enquiry::query()->withoutGlobalScopes()->whereNotNull('enquiry_no')->where('enquiry_no', '!=', '')->first()
            ?? $this->markTestSkipped('No enquiry with a number in the test copy.');
        $enquiry->forceFill(['segment_code' => 'BEV'])->saveQuietly();
        $fromEnquiry = $this->service->apply($this->makeBooking(['order' => 1, 'enq_no' => $enquiry->enquiry_no]), $this->dmsInput(''), false);
        $this->assertSame(3, $fromEnquiry->order);

        $pvModel = VehicleModel::query()->where('segment_code', 'PV')->value('code') ?? $this->markTestSkipped('No PV model in the test copy.');
        $fromModel = $this->service->apply($this->makeBooking(['order' => 1, 'model_code' => $pvModel]), $this->dmsInput(''), false);
        $this->assertSame(3, $fromModel->order);
    }

    /** @return array{dms_no: string, dms_otf: string, otf_date: string, dms_so: string} */
    private function dmsInput(string $so): array
    {
        return ['dms_no' => 'B-12345678', 'dms_otf' => 'OTF00A123456', 'otf_date' => '2026-01-01', 'dms_so' => $so];
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
