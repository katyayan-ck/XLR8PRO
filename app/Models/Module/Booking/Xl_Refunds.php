<?php

namespace App\Models\Module\Booking;

use App\Models\BaseModel;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Xl_Refunds extends BaseModel implements HasMedia
{
    use HasDocuments;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    use SoftDeletes;

    /** Proof files live in Docs (DEC-069) and follow booking access: SLS_BKNG_VIEW. */
    public function chatCanView(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user !== null && ($user->can('SLS_BKNG_VIEW'));
    }

    use InteractsWithMedia;

    protected $table = 'xlr8_booking_refund';

    /**
     * The attributes to be fillable from the model.
     *
     * A dirty hack to allow fields to be fillable by calling empty fillable array
     *
     * @var array
     */
    protected $fillable = [];

    protected $guarded = ['id'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('acc-proof')

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

        $this->addMediaCollection('pay-proof')

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
