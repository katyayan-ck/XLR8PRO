<?php

namespace App\Models\Module\Booking;

use Illuminate\Database\Eloquent\SoftDeletes;
use DataTables, Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Traits\HasHashedMediaTrait;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use App\Models\BaseModel;

class XlDelivery extends BaseModel  implements HasMedia
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    use SoftDeletes;
    use InteractsWithMedia;
    protected $table = 'xlr8_booking_delivered';

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
        $collections = [
            'delivery_ceremony_with_customer',
            'bonnet',
            'windshield_glass',
            'vehicle_driver_side',
            'vehicle_co_driver_side',
            'vehicle_rear_side',
            'tire_front_driver_side',
            'tire_front_co_driver_side',
            'tire_rear_driver_side',
            'tire_rear_co_driver_side',
            'stepney',
            'foot_rest_driver_side',
            'foot_rest_co_driver_side',
            'tool_kit',
            'vehicle_chassis_no_photo',
            'chassis_no_screenshot_invoice',
            'chassis_no_screenshot_insurance'
        ];
        foreach ($collections as $collection) {
            $this->addMediaCollection($collection)
                ->singleFile()
                ->registerMediaConversions(function (Media $media = null) {
                    $this->addMediaConversion('thumb250')
                        ->width(250)
                        ->height(250);
                    $this->addMediaConversion('thumb100')
                        ->width(100)
                        ->height(100);
                });
        }
    }
}
