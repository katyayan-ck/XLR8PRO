<?php

namespace App\Models\Module\Booking;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\BaseModel;
class Bookingamount extends BaseModel implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $table = 'xlr8_booking_amount';
    protected $fillable = [];
    protected $guarded = ['id'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('amount-proof')
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

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'bid', 'id');
    }
}
