<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Sales\Booking\BookingDeliveryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BookingDeliveryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingDeliveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingDeliveryService::class);

        $user = User::create([
            'username' => 'delivery_test_'.uniqid(),
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

    public function test_apply_creates_delivery_record_with_remarks_and_verification(): void
    {
        $booking = $this->makeBooking();

        $delivery = $this->service->apply($booking->id, 'Delivered fine', true, []);

        $this->assertSame($booking->id, $delivery->bid);
        $this->assertSame('Delivered fine', $delivery->remarks);
        $this->assertTrue($delivery->verification);
        $this->assertSame(1, $delivery->status);
    }

    public function test_apply_attaches_only_the_provided_photos(): void
    {
        $booking = $this->makeBooking();
        $bonnet = UploadedFile::fake()->image('bonnet.jpg');

        $delivery = $this->service->apply($booking->id, 'ok', false, [
            'bonnet' => $bonnet,
            'stepney' => null,
        ]);

        $this->assertCount(1, $delivery->getMedia('bonnet'));
        $this->assertCount(0, $delivery->getMedia('stepney'));
    }

    public function test_apply_updates_the_same_row_on_a_second_call(): void
    {
        $booking = $this->makeBooking();

        $first = $this->service->apply($booking->id, 'first', false, []);
        $second = $this->service->apply($booking->id, 'second', true, []);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('second', $second->remarks);
        $this->assertTrue($second->verification);
    }

    public function test_apply_replaces_an_existing_photo_in_the_same_collection(): void
    {
        $booking = $this->makeBooking();
        $first = UploadedFile::fake()->image('bonnet1.jpg');
        $second = UploadedFile::fake()->image('bonnet2.jpg');

        $this->service->apply($booking->id, 'v1', false, ['bonnet' => $first]);
        $delivery = $this->service->apply($booking->id, 'v2', false, ['bonnet' => $second]);

        $this->assertCount(1, $delivery->getMedia('bonnet'));
        $this->assertSame('bonnet2.jpg', $delivery->getFirstMedia('bonnet')->file_name);
    }

    public function test_resolve_edit_data_returns_expected_shape(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->resolveEditData($booking);

        $this->assertEqualsCanonicalizing(['insurance', 'rto', 'data'], array_keys($result));
        $this->assertNull($result['insurance']);
        $this->assertNull($result['rto']);
        $this->assertEqualsCanonicalizing(
            ['segments', 'saleconsultants', 'branch', 'location', 'financier', 'bchasis'],
            array_keys($result['data'])
        );
    }
}
