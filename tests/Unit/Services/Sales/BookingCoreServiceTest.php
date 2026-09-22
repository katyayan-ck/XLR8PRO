<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Sales\Booking\BookingCoreService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookingCoreServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingCoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingCoreService::class);

        $user = User::create([
            'username' => 'core_test_'.uniqid(),
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

    // store()'s crash path (BUG-104) is NOT covered by a test here: the
    // original code wraps $booking->save() in a try/catch that calls
    // dd($e->getMessage(), ...) on failure - preserved exactly in
    // BookingCoreService::store(). dd() halts the PHP process outright
    // (it's not an exception), which kills the PHPUnit test runner
    // itself rather than producing a catchable failure - confirmed by
    // attempting expectException(QueryException::class) here, which
    // instead terminated the whole test run with dd()'s raw dump.
    // update() has no such wrapping try/catch, so its crash IS a normal,
    // catchable QueryException - covered below.

    public function test_update_always_crashes_because_sale_type_is_not_a_real_column(): void
    {
        // Same root cause as above, reproduced via update()'s path.
        // sale_type is a required field on the edit form, so this is not
        // a theoretical edge case - every real edit submission hits this.
        $booking = $this->makeBooking();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches("/Unknown column 'sale_type'/");

        $this->service->update($booking, [
            'sale_type' => '1',
            'customer_type' => 'Active',
        ]);
    }
}
