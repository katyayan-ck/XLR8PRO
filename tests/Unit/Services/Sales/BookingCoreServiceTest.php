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

    public function test_update_saves_sale_type(): void
    {
        // Regression for BUG-104: sale_type (a required edit-form field) was not a
        // real column, so every edit crashed. Migration 2026_09_26_120000 adds it.
        $booking = $this->makeBooking();

        $this->service->update($booking, [
            'sale_type' => '1',
            'customer_type' => 'Active',
            'col_type' => '1',
        ]);

        $this->assertSame(1, $booking->fresh()->sale_type); // unsigned tinyint since DEC-041
    }
}
