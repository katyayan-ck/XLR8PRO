<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Models\Utilities\CommHistory\CommThread;
use App\Services\Sales\Booking\BookingKycService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookingKycServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingKycService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingKycService::class);

        // addHistory() records the acting backpack_user() as the actor -
        // needs a real authenticated user, same pattern as this session's
        // other tests (see BUG-079 in docs/bugs/closed.md for why
        // actingAs($user, 'backpack') can't be used instead).
        $user = User::create([
            'username' => 'kyc_test_'.uniqid(),
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

    public function test_apply_normalizes_and_saves_pan_aadhaar_and_gst(): void
    {
        $booking = $this->makeBooking();

        $updated = $this->service->apply($booking, [
            'pan_no' => 'abcde1234f',
            'adhar_no' => '2345 6789 0123',
            'gst_no' => '27abcde1234f1z5',
        ], false);

        $this->assertSame('ABCDE1234F', $updated->pan_no);
        $this->assertSame('234567890123', $updated->adhar_no);
        $this->assertSame('27ABCDE1234F1Z5', $updated->gstn);
    }

    public function test_the_kyc_history_entry_masks_pan_and_aadhaar(): void
    {
        // BUG-195: the booking timeline is visible to every booking viewer and the mobile app.
        $booking = $this->makeBooking();

        $this->service->apply($booking, [
            'pan_no' => 'ABCDE1234F',
            'adhar_no' => '234567890123',
            'gst_no' => null,
        ], true);

        $meta = CommThread::where('comm_master_id', $booking->getOrCreateCommMaster()->id)
            ->latest('id')->firstOrFail()->extra_data;
        $stored = json_encode($meta);

        $this->assertStringNotContainsString('ABCDE1234F', $stored);
        $this->assertStringNotContainsString('234567890123', $stored);
        $this->assertStringContainsString('XXXXXX234F', $stored);
        $this->assertStringContainsString('XXXXXXXX0123', $stored);
    }

    public function test_apply_sets_gstn_to_zero_when_gst_not_required(): void
    {
        $booking = $this->makeBooking();

        $updated = $this->service->apply($booking, [
            'pan_no' => 'ABCDE1234F',
            'adhar_no' => '234567890123',
            'gst_no' => null,
        ], true);

        $this->assertSame('0', $updated->gstn);
    }

    public function test_apply_keeps_existing_gstn_when_no_new_value_and_not_marked_not_required(): void
    {
        $booking = $this->makeBooking(['gstn' => '27ABCDE1234F1Z5']);

        $updated = $this->service->apply($booking, [
            'pan_no' => 'ABCDE1234F',
            'adhar_no' => '234567890123',
            'gst_no' => null,
        ], false);

        $this->assertSame('27ABCDE1234F1Z5', $updated->gstn);
    }

    public function test_resolve_edit_data_returns_expected_shape(): void
    {
        $booking = $this->makeBooking();

        $data = $this->service->resolveEditData($booking);

        $this->assertEqualsCanonicalizing([
            'branches', 'locations', 'segments', 'saleConsultants',
            'customer_name', 'branch_name', 'location_name',
            'model_name', 'variant_name', 'color_name',
        ], array_keys($data));
    }

    public function test_resolve_edit_data_falls_back_to_em_dash_when_nothing_resolvable(): void
    {
        // xlr8_booking_master has no 'name' column of its own - customer_name
        // is only ever resolved via a linked Enquiry, or this fallback.
        $booking = $this->makeBooking();

        $data = $this->service->resolveEditData($booking);

        $this->assertSame('—', $data['customer_name']);
        $this->assertSame('—', $data['branch_name']);
    }
}
