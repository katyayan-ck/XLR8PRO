<?php

namespace App\Models\Module\Booking;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class XExchange extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_booking_exchange';

    protected $fillable = [];

    protected $guarded = ['id'];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    /**
     * Creates the initial XExchange row for a booking flagged as an
     * exchange purchase, with the default verification/case status new
     * exchange entries always start at. SSOT for a pattern previously
     * duplicated independently in BookingCrudController's store() and
     * update() - see docs/refactor/ai-changelogs-22-09-2026.md.
     *
     * BUG-098: store()'s copy additionally tried to set a
     * 'vehicle_oem_code' column that doesn't exist on
     * xlr8_booking_exchange, so every save there silently failed (caught
     * by its own try/catch) - fixed by dropping that field here.
     *
     * BUG-099: neither copy set 'vh_id', which is NOT NULL with no
     * database default - store()'s save silently failed the same way as
     * BUG-098 (same try/catch), but update()'s copy has no try/catch, so
     * it would have thrown a hard 500 whenever a booking was edited to
     * "Exchange Buy" for the first time. Fixed by defaulting to 0 here,
     * matching the sentinel value already used for "no vehicle chosen
     * yet" elsewhere in this table (exchangeUpdate() defaults the same
     * column to 0 via `$request->enum_master1 ?? 0`).
     */
    public static function seedForBooking(int $bookingId, ?string $purchaseType): self
    {
        return static::create([
            'bid' => $bookingId,
            'vh_id' => 0,
            'verification_status' => 1,
            'case_status' => 1,
            'purchase_type' => $purchaseType,
        ]);
    }

    public static function getVerifiedCounts($type, $timeFrame = null)
    {
        $query = self::where('verification_status', 1)->where('status', 2)
            ->where('purchase_type', $type)
            ->whereHas('booking', function ($q) use ($timeFrame) {
                $q->whereNull('deleted_at');
                if ($timeFrame === 'mtd') {
                    $q->where('booking_date', '>=', now()->subDays(30));
                } elseif ($timeFrame === 'ytd') {
                    $q->where('booking_date', '>=', now()->subDays(365));
                }
            });

        return $query->count();
    }

    public static function getPendingCounts($type)
    {
        return self::whereIn('verification_status', [0, null])
            ->where('purchase_type', $type)
            ->whereHas('booking', function ($q) {
                $q->whereNull('deleted_at');
            })->count();
    }
}
