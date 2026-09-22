<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Sales\Booking\BookingInsuranceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BookingInsuranceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingInsuranceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingInsuranceService::class);

        $user = User::create([
            'username' => 'ins_test_'.uniqid(),
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

    private function validPayload(): array
    {
        return [
            'insurance_category' => 1,
            'insurance_company' => 2,
            'policy_no' => 'abc1234567',
            'hidden_policy_date' => '2026-01-01',
            'policy_type' => 1,
        ];
    }

    public function test_apply_creates_insurance_with_status_1_when_no_policy_copy(): void
    {
        $booking = $this->makeBooking();

        $insurance = $this->service->apply($booking->id, $this->validPayload(), null);

        $this->assertSame($booking->id, $insurance->bid);
        $this->assertSame('ABC1234567', $insurance->pol_no);
        $this->assertSame(1, $insurance->status);
    }

    public function test_apply_sets_status_2_when_policy_copy_is_present(): void
    {
        $booking = $this->makeBooking();
        $file = UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf');

        $insurance = $this->service->apply($booking->id, $this->validPayload(), $file);

        $this->assertSame(2, $insurance->status);
        $this->assertTrue($insurance->getMedia('policy_copy')->isNotEmpty());
    }

    public function test_apply_updates_the_same_row_on_a_second_call(): void
    {
        $booking = $this->makeBooking();

        $first = $this->service->apply($booking->id, $this->validPayload(), null);
        $second = $this->service->apply($booking->id, array_merge($this->validPayload(), [
            'policy_no' => 'xyz9876543',
        ]), null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('XYZ9876543', $second->pol_no);
    }

    public function test_resolve_edit_data_returns_expected_shape(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->resolveEditData($booking);

        $this->assertEqualsCanonicalizing(['insurance', 'data', 'dsaname'], array_keys($result));
        $this->assertNull($result['insurance']);
        $this->assertSame('N/A', $result['dsaname']);
    }
}
