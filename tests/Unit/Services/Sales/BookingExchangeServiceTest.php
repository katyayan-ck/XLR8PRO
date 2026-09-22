<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\XExchange;
use App\Models\User;
use App\Services\Sales\Booking\BookingExchangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookingExchangeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingExchangeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingExchangeService::class);

        $user = User::create([
            'username' => 'exchange_test_'.uniqid(),
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

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'buyer_type' => 'Exchange Buy',
            'enum_master1' => 5,
            'vehicle_details' => 'Old Sedan',
            'enum_master2' => null,
            'vehicle_details2' => null,
            'registration_no' => 'MH12AB1234',
            'manufacturing_year' => 2018,
            'odometer_reading' => 45000,
            'expected_price' => 300000,
            'offered_price' => 280000,
            'exchange_bonus' => 10000,
            'update' => 1,
            'case_status' => 1,
            'remark' => 'first submission',
        ], $overrides);
    }

    public function test_apply_creates_a_new_exchange_record(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->apply($booking, $this->basePayload());

        $this->assertSame($booking->id, $result['exchange']->bid);
        $this->assertSame('Exchange Buy', $result['exchange']->purchase_type);
        $this->assertSame(5, $result['exchange']->vh_id);
        $this->assertStringContainsString('New exchange entry created', implode(' ', $result['changes']));
    }

    public function test_apply_defaults_vh_id_to_zero_when_enum_master1_absent(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->apply($booking, $this->basePayload([
            'buyer_type' => 'Scrappage',
            'enum_master1' => null,
        ]));

        $this->assertSame(0, $result['exchange']->vh_id);
        $this->assertSame('Scrappage', $result['exchange']->purchase_type);
    }

    public function test_apply_logs_verification_and_case_status_changes_on_update(): void
    {
        $booking = $this->makeBooking();

        $this->service->apply($booking, $this->basePayload(['update' => 1, 'case_status' => 1]));

        $result = $this->service->apply($booking, $this->basePayload(['update' => 2, 'case_status' => 2]));

        $changesText = implode(' | ', $result['changes']);
        $this->assertStringContainsString('Verification Status', $changesText);
        $this->assertStringContainsString('Case Status', $changesText);
        $this->assertSame(2, $result['exchange']->case_status);
    }

    public function test_apply_reuses_the_existing_row_on_a_second_call(): void
    {
        $booking = $this->makeBooking();

        $first = $this->service->apply($booking, $this->basePayload());
        $second = $this->service->apply($booking, $this->basePayload(['update' => 2]));

        $this->assertSame($first['exchange']->id, $second['exchange']->id);
        $this->assertSame(2, $second['exchange']->verification_status);
        $this->assertSame(1, XExchange::where('bid', $booking->id)->count());
    }

    public function test_apply_does_not_persist_the_detail_fields_xlr8_booking_exchange_has_no_columns_for(): void
    {
        // BUG-102 (known-bugs-report.md): xlr8_booking_exchange has no
        // offered_price/registration_no/etc. columns at all - the payload
        // silently drops them via HasColumnTransformations. Not a data
        // loss (the same fields correctly persist onto the linked
        // Enquiry), but the extraction must preserve this exact
        // pre-existing behavior, not silently "fix" it.
        $booking = $this->makeBooking();

        $result = $this->service->apply($booking, $this->basePayload(['offered_price' => 290000]));

        $this->assertArrayNotHasKey('offered_price', $result['exchange']->getAttributes());
    }

    public function test_resolve_edit_data_returns_expected_shape(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->resolveEditData($booking);

        $this->assertEqualsCanonicalizing(
            ['exchange', 'data', 'dsaname', 'uid', 'bookingHistory'],
            array_keys($result)
        );
        $this->assertNull($result['exchange']);
    }
}
