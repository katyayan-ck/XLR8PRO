<?php

namespace App\Models\Module\Booking;

use App\Models\BaseModel;
use App\Models\Traits\HasDataScope;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Bookingamount extends BaseModel implements HasMedia
{
    use HasDataScope;   // DEC-071: filtered by the signed-in user's data scope (config/data_scope.php)
    use HasDocuments;
    use InteractsWithMedia, SoftDeletes;

    /** Proof files live in Docs (DEC-069) and follow booking access: SLS_BKNG_VIEW, ACC_RCPT_VIEW. */
    public function chatCanView(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user !== null && ($user->can('SLS_BKNG_VIEW') || $user->can('ACC_RCPT_VIEW'));
    }

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
