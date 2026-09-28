<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Xl_Refunds;
use App\Models\User;
use App\Services\Sales\Booking\BookingRefundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BookingRefundServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingRefundService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingRefundService::class);

        $user = User::create([
            'username' => 'refund_test_'.uniqid(),
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
            'booking_amount' => 100000,
        ], $overrides));
    }

    private function requestPayload(array $overrides = []): array
    {
        return array_merge([
            'bank_name' => 'test bank',
            'branch_name' => 'Main Branch',
            'account_type' => 'savings',
            'account_number' => '123456789012',
            'holder_name' => 'John Doe',
            'ifsc_code' => 'hdfc0001234',
            'deduction_reason' => 'cancellation charges',
            'deduction' => 5000,
            'remaining_amount' => 95000,
        ], $overrides);
    }

    public function test_apply_creates_a_refund_request_and_queues_the_booking(): void
    {
        $booking = $this->makeBooking();

        $refund = $this->service->apply($booking, $this->requestPayload(), []);

        $this->assertSame('booking', $refund->entity_type);
        $this->assertEquals($booking->id, $refund->entity_id);
        $this->assertSame('TEST BANK', $refund->bank_name);
        $this->assertSame('HDFC0001234', $refund->ifsc_code);
        $this->assertEquals(95000, $refund->amount);
        $this->assertEquals(4, $booking->fresh()->status);
    }

    public function test_apply_attaches_provided_documents(): void
    {
        $booking = $this->makeBooking();
        $accProof = UploadedFile::fake()->create('acc.pdf', 100, 'application/pdf');

        $refund = $this->service->apply($booking, $this->requestPayload(), [
            'acc_proof' => $accProof,
            'aadhar' => null,
            'pan' => null,
        ]);

        $this->assertTrue($refund->getMedia('acc-proof')->isNotEmpty());
        $this->assertTrue($refund->getMedia('aadhar')->isEmpty());
    }

    public function test_apply_records_requested_again_when_the_booking_was_previously_rejected(): void
    {
        // Regression for BUG-103: the check used to run after the status had
        // already been overwritten to 4, so it never fired.
        $booking = $this->makeBooking(['status' => 7]);

        $this->service->apply($booking, $this->requestPayload(), []);

        $history = $booking->commMaster()->first()?->rootThreads ?? collect();
        $titles = $history->pluck('title')->all();

        $this->assertContains('Refund Requested Again', $titles);
        $this->assertContains('Refund Requested', $titles);
    }

    public function test_apply_does_not_record_requested_again_for_a_first_refund_request(): void
    {
        $booking = $this->makeBooking(['status' => 3]);

        $this->service->apply($booking, $this->requestPayload(), []);

        $history = $booking->commMaster()->first()?->rootThreads ?? collect();
        $titles = $history->pluck('title')->all();

        $this->assertNotContains('Refund Requested Again', $titles);
        $this->assertContains('Refund Requested', $titles);
    }

    public function test_resolve_refund_display_data_returns_defaults_when_no_refund_exists(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->resolveRefundDisplayData($booking);

        $this->assertNull($result['refund']);
        $this->assertNull($result['refundDetails']);
        $this->assertEquals(100000, $result['amount']);
        $this->assertSame(0, $result['deduction']);
    }

    public function test_apply_refund_update_marks_booking_refunded(): void
    {
        $booking = $this->makeBooking(['status' => 4]);
        $refund = Xl_Refunds::create([
            'entity_type' => 'booking',
            'entity_id' => $booking->id,
            'bank_name' => 'TEST BANK',
            'branch_name' => 'Main',
            'account_type' => 'savings',
            'account_number' => '123456789012',
            'holder_name' => 'John Doe',
            'ifsc_code' => 'HDFC0001234',
            'req_date' => now()->format('Y-m-d'),
            'req_by' => 1,
            'amount' => 95000,
        ]);

        $updated = $this->service->applyRefundUpdate($booking, $refund, [
            'ref_date' => now()->format('Y-m-d'),
            'mode' => 'Online',
            'transaction_details' => 'TXN123',
            'remark' => 'paid out',
        ], null);

        $this->assertSame('Online', $updated->mode);
        $this->assertEquals(5, $booking->fresh()->status);
    }

    public function test_apply_refunded_update_logs_changes_without_touching_booking_status(): void
    {
        $booking = $this->makeBooking(['status' => 5]);
        $refund = Xl_Refunds::create([
            'entity_type' => 'booking',
            'entity_id' => $booking->id,
            'bank_name' => 'TEST BANK',
            'branch_name' => 'Main',
            'account_type' => 'savings',
            'account_number' => '123456789012',
            'holder_name' => 'John Doe',
            'ifsc_code' => 'HDFC0001234',
            'req_date' => now()->format('Y-m-d'),
            'req_by' => 1,
            'amount' => 95000,
            'mode' => 'Cash',
        ]);

        $updated = $this->service->applyRefundedUpdate($booking, $refund, [
            'ref_date' => now()->format('Y-m-d'),
            'mode' => 'Cheque',
            'transaction_details' => 'CHQ001',
            'remark' => 'corrected mode',
        ], null);

        $this->assertSame('Cheque', $updated->mode);
        $this->assertEquals(5, $booking->fresh()->status);
    }

    public function test_apply_refund_details_edit_updates_bank_fields_without_touching_status(): void
    {
        $booking = $this->makeBooking(['status' => 7]);
        $refund = $this->service->apply($booking, $this->requestPayload(), []);
        $booking->update(['status' => 7]);
        $historyBefore = $booking->commMaster?->threads()->count() ?? 0;

        $edited = $this->service->applyRefundDetailsEdit($booking, $refund, $this->editPayload([
            'bank_name' => 'new bank',
            'ifsc_code' => 'icic0004321',
            'account_type' => 'Current',
            'remaining_amount' => 90000,
            'details' => 'revised charges',
        ]));

        $this->assertSame('NEW BANK', $edited->bank_name);
        $this->assertSame('ICIC0004321', $edited->ifsc_code);
        $this->assertSame('current', $edited->account_type);
        $this->assertEquals(90000, $edited->amount);
        $this->assertSame('revised charges', $edited->details);
        $this->assertEquals(7, $booking->fresh()->status);
        $this->assertSame($historyBefore + 1, $booking->fresh()->commMaster->threads()->count());
    }

    public function test_apply_refund_details_edit_records_nothing_when_unchanged(): void
    {
        $booking = $this->makeBooking(['status' => 7]);
        $refund = $this->service->apply($booking, $this->requestPayload(), []);
        $historyBefore = $booking->fresh()->commMaster->threads()->count();

        $this->service->applyRefundDetailsEdit($booking, $refund->fresh(), $this->editPayload([
            'details' => $refund->details,
        ]));

        $this->assertSame($historyBefore, $booking->fresh()->commMaster->threads()->count());
    }

    private function editPayload(array $overrides = []): array
    {
        return array_merge([
            'bank_name' => 'test bank',
            'branch_name' => 'Main Branch',
            'account_type' => 'savings',
            'account_number' => '123456789012',
            'holder_name' => 'John Doe',
            'ifsc_code' => 'hdfc0001234',
            'deduction' => 5000,
            'remaining_amount' => 95000,
            'details' => 'cancellation charges',
        ], $overrides);
    }
}
