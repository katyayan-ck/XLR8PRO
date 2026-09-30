<?php

namespace Tests\Feature\Sales;

use App\Http\Controllers\Admin\Sales\Booking\BookingCrudController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * W7 (N+1 review): the booking list batches its per-row lookups (consultant name, latest refund, live-order count per
 * model / variant / colour). Every mapped row must be identical to the original per-row queries.
 */
class BookingGridLookupsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_batched_lookups_give_the_same_rows_as_the_per_row_queries(): void
    {
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
        $controller = app(BookingCrudController::class);
        $call = function (string $method, mixed ...$args) use ($controller) {
            $m = new ReflectionMethod($controller, $method);

            return $m->invoke($controller, ...$args);
        };

        $bookings = $call('getBaseQuery')->whereIn('bookings.status', [1, 8])->orderBy('bookings.id', 'desc')->limit(50)->get();
        if ($bookings->isEmpty()) {
            $this->markTestSkipped('No live bookings in the test copy.');
        }
        $lookups = $call('preloadGridLookups', $bookings);

        foreach ($bookings as $booking) {
            $this->assertEquals($call('mapBookingForGrid', $booking, []), $call('mapBookingForGrid', $booking, $lookups), "booking #{$booking->id}");
        }
    }
}
