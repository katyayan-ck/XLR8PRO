<?php

namespace App\Models\Module\Finance;

use App\Models\BaseModel;
use App\Models\Module\Booking\Booking;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class XFinance extends BaseModel implements HasMedia
{
    use HasDocuments;
    use SoftDeletes;

    /** Proof files live in Docs (DEC-069) and follow booking access: SLS_BKNG_VIEW. */
    public function chatCanView(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user !== null && ($user->can('SLS_BKNG_VIEW'));
    }

    protected $table = 'xlr8_booking_finance';

    protected $fillable = [];

    protected $guarded = ['id'];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'bid', 'id');   // BUG-193
    }

    public static function getVerifiedCounts($type, $timeFrame = null)
    {
        $query = self::where('verification_status', 1)->where('status', 2)
            ->whereHas('booking', function ($q) use ($type, $timeFrame) {
                $q->where('finance', $type)->whereNull('deleted_at');
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
            ->whereHas('booking', function ($q) use ($type) {
                $q->where('finance', $type)->whereNull('deleted_at');
            })->count();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('instrument_proof')
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('thumb250')
                    ->width(250)
                    ->height(250)
                    ->quality(70);

                $this->addMediaConversion('thumb100')
                    ->width(100)
                    ->height(100)
                    ->quality(70);
            });
    }
}
