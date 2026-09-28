<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Admin\Employee;
use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Sales\Booking\BookingOtfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookingOtfServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingOtfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingOtfService::class);

        $user = User::create([
            'username' => 'otf_test_'.uniqid(),
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

    private function makeQuotation(array $standardData = []): Quotation
    {
        return Quotation::create([
            'standard_data' => array_merge([
                'ex_showroom_price' => 500000,
            ], $standardData),
        ]);
    }

    public function test_resolve_otf_form_data_returns_null_when_quotation_not_found(): void
    {
        $booking = $this->makeBooking(['quotation_id' => 999999]);

        $result = $this->service->resolveOtfFormData($booking);

        $this->assertNull($result);
    }

    public function test_resolve_otf_form_data_merges_quotation_and_final_data(): void
    {
        // final_data can't be persisted through makeBooking() (BUG-104:
        // it isn't a real column - see the dedicated apply() test below),
        // so it's set as a direct, unsaved in-memory attribute here purely
        // to exercise resolveOtfFormData()'s read-side merge logic.
        $quotation = $this->makeQuotation(['loan_amount' => 100000]);
        $booking = $this->makeBooking(['quotation_id' => $quotation->id]);
        $booking->final_data = json_encode(['ex_showroom_price' => 550000]);

        $result = $this->service->resolveOtfFormData($booking);

        $this->assertNotNull($result);
        // final_data (550000) overrides quotation's ex_showroom_price (500000)
        $this->assertEquals(550000, $result['otfData']['ex_showroom_price']);
        $this->assertArrayHasKey('net_settlement_amount', $result['otfData']);
    }

    public function test_apply_saves_final_data(): void
    {
        // Regression for BUG-104: final_data was not a real column, so every OTF
        // save crashed. Migration 2026_09_26_120000 adds it.
        $booking = $this->makeBooking();

        $saved = $this->service->apply($booking, ['pan_no' => 'ABCDE1234F'], null);

        $this->assertIsArray(json_decode((string) $saved->fresh()->final_data, true));
    }

    public function test_generate_votf_number_uses_the_linked_enquiry_branch(): void
    {
        // DEC-027: bookings have no branch column; the branch comes from the
        // linked enquiry, as it does for display.
        $enquiry = Enquiry::query()->whereNotNull('dealer_branch')->where('dealer_branch', '!=', '')->first();
        if (! $enquiry) {
            $this->markTestSkipped('No enquiry with a dealer_branch in the test database.');
        }

        $booking = $this->makeBooking(['enq_no' => $enquiry->id]);

        $votf = $this->service->generateVotfNumber($booking);

        $this->assertStringContainsString(strtoupper($enquiry->dealer_branch), $votf);
    }

    public function test_generate_votf_number_falls_back_to_the_consultant_branch(): void
    {
        // DEC-029: no enquiry branch, so use the FSC's primary branch.
        $employee = Employee::query()->whereNotNull('primary_branch_code')->where('primary_branch_code', '!=', '')->first();
        if (! $employee) {
            $this->markTestSkipped('No employee with a primary branch in the test database.');
        }

        $booking = $this->makeBooking(['consultant' => $employee->person_code]);

        $this->assertStringContainsString(strtoupper($employee->primary_branch_code), $this->service->generateVotfNumber($booking));
    }

    public function test_generate_votf_number_throws_when_branch_code_missing(): void
    {
        // No branch on the booking and no linked enquiry to take it from.
        $booking = $this->makeBooking(['branch_code' => 'TESTBR']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->generateVotfNumber($booking);
    }
}
