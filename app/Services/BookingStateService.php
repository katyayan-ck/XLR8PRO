<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\EntityHistory; // Assuming your Entity History system uses this model
use Exception;
use Illuminate\Support\Facades\DB;

class BookingStateService
{
    // Define strict valid transitions
    protected const ALLOWED_TRANSITIONS = [
        'Draft'            => ['Pending', 'Cancelled'],
        'Pending'          => ['Live', 'Cancelled'],
        'Live'             => ['Invoiced', 'Cancelled', 'Refund Requested'],
        'Refund Requested' => ['Cancelled', 'Live'], // Live if refund is rejected/restored
        'Invoiced'         => [], // Terminal state, requires credit note logic to reverse
        'Cancelled'        => ['Draft'], // Can only be restored to draft
    ];

    /**
     * Transition a booking to a new state with strict enforcement and audit logging.
     */
    public function transitionTo(Booking $booking, string $newState, string $remarks = null): bool
    {
        $currentState = $booking->status_kw ?? 'Draft';

        if (!in_array($newState, self::ALLOWED_TRANSITIONS[$currentState] ?? [])) {
            throw new Exception("Illegal state transition from [{$currentState}] to [{$newState}].");
        }

        DB::beginTransaction();
        try {
            // 1. Update the Booking
            $booking->status_kw = $newState;
            $booking->save();

            // 2. Log to your Custom Entity History System
            EntityHistory::create([
                'entity_type' => Booking::class,
                'entity_id'   => $booking->id,
                'action'      => 'STATE_CHANGE',
                'old_value'   => $currentState,
                'new_value'   => $newState,
                'remarks'     => $remarks,
                'created_by'  => backpack_user()->id,
            ]);

            // 3. Trigger Notifications (Using your existing Notification logic)
            // app(NotificationService::class)->notifyBookingStateChange($booking);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
