<?php

namespace App\Models\Module\Finance;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Helpers\XCommonHelper;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Traits\HasHashedMediaTrait;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class XFinance extends BaseModel  implements HasMedia
{
    use SoftDeletes;

    protected $table = 'xlr8_booking_finance';
    protected $fillable = [];
    protected $guarded = ['id'];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
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
