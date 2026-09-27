<?php

namespace Tests\Unit\Models;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BUG-184 (DEC-070): the audit helpers name the acting user (users have no `name` column; `display_name` is the
 * accessor), and fall back to "System" only when nobody is recorded.
 */
class BaseModelAuditDetailsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creation_details_name_the_user_who_created_the_record(): void
    {
        $user = User::role('superadmin')->firstOrFail();
        $this->app['auth']->guard('backpack')->setUser($user);

        $booking = Booking::create(['pending_remark' => '', 'booking_amount' => 10000]);

        $details = $booking->fresh()->getCreationDetails();
        $this->assertSame($user->id, (int) $details['created_by_id']);
        $this->assertSame($user->display_name, $details['created_by_name']);
    }

    public function test_a_record_without_an_actor_reads_system(): void
    {
        $booking = new Booking(['pending_remark' => '']);

        $this->assertNull($booking->created_by);
        $this->assertSame('System', $booking->getCreationDetails()['created_by_name']);
    }
}
