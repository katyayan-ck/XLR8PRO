<?php

namespace App\Models\Module\Booking;

use \App\Models\Traits\ScopedQuery;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Helpers\XCommonHelper;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Traits\HasHashedMediaTrait;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\BaseModel;
use App\Models\Traits\HasCommunications;


class Booking extends BaseModel  implements HasMedia
{
    use CrudTrait;
    use SoftDeletes;
    use InteractsWithMedia;
    use HasCommunications;
    //protected Carpdates = ['booking_date'];
    protected $table = 'xlr8_booking_master';
    protected $fillable = [];
    protected $guarded = ['id'];

    public string $scopeType   = 'branch';
    public string $scopeColumn = 'branchid';   // existing column name on xlr8_booking_master
    public string $scopeGroup  = 'org';



    public function segment()
    {
        return $this->belongsTo(\App\Models\EnumMaster::class, 'segment_id', 'id');
    }


    /**
     * Relationships
     */
    public function bookingAmounts()
    {
        return $this->hasMany(Bookingamount::class, 'bid', 'id');
    }



public function location()
{
    return $this->belongsTo(\App\Models\Admin\Location::class, 'location_code', 'code');
    // or 'code' depending on your foreign key logic
}
public function branch()
{
    return $this->belongsTo(\App\Models\Admin\Branch::class, 'branch_code', 'code');
    // or 'code' depending on your foreign key logic
}

    public function finances()
    {
        return $this->hasMany(XFinance::class, 'booking_id', 'id');
    }

    public function exchanges()
    {
        return $this->hasMany(XExchange::class, 'booking_id', 'id');
    }

    public function vehicle()
    {
        return $this->belongsTo(XVehicleMaster::class, 'vh_id', 'id')
            ->orWhere('model_code', $this->model_code);
    }

    /**
     * Calculate total received amount
     */
    public function totalReceivedAmount()
    {
        return $this->bookingAmounts()->sum('amount');
    }

    /**
     * Scopes for Booking Conditions
     */
    public function scopeLiveAll($query)
    {
        return $query->whereIn('status', [1, 8]);
    }

    public function scopeLive($query)
    {
        return $query->whereIn('status', [1, 8])->where('b_type', 'Active');
    }

    public function scopePendingDataAll($query)
    {
        return $query->where('status', 8);
    }

    public function scopePendingData($query)
    {
        return $query->where('status', 8)->where('b_type', 'Active');
    }

    public function scopeActiveBooking($query)
    {
        return $query->where('b_type', 'Active')->whereIn('status', [1, 8]);
    }

    public function scopeDummyBooking($query)
    {
        return $query->where('b_type', 'Dummy')->whereIn('status', [1, 8]);
    }

    public function scopeOnHold($query)
    {
        return $query->where('status', 6);
    }

    public function scopePendingPayment($query)
    {
        return $query->whereIn('status', [1, 8])
            ->where('col_type', 2)
            ->whereRaw('(SELECT SUM(amount) FROM xcelr8_booking_amount ba WHERE ba.bid = xcelr8_booking_master.id AND ba.deleted_at IS NULL) < xcelr8_booking_master.booking_amount');
    }

    public function scopeInvoiced($query)
    {
        return $query->where('status', 2);
    }

    public function scopePendingInvoice($query)
    {
        return $query->where('status', 2)->where('dealer_status', 1);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 3);
    }

    public function scopeRefundQueued($query)
    {
        return $query->where('status', 4);
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 5);
    }

    public function scopeRefundRejected($query)
    {
        return $query->where('status', 7);
    }

    public function scopeRequestOrder($query)
    {
        return $query->where('order', 1);
    }

    public function scopeVerifiedOrder($query)
    {
        return $query->where('order', 2);
    }

    public function scopeOrdered($query)
    {
        return $query->where('order', 3);
    }

    public function scopeHotEnquiries($query)
    {
        return $query->where('b_type', 'Active')->whereIn('status', [1, 8]);
    }

    public function scopeOlderThan($query, $days)
    {
        return $query->whereRaw('DATEDIFF(CURDATE(), booking_date) > ?', [$days]);
    }


    /**
     * Scope for Pending KYC
     */
    public function scopePendingKYC($query)
    {
        return $query->whereIn('status', [1, 8])
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('pan_no')->orWhere('pan_no', '');
                })->where(function ($q) {
                    $q->whereNull('adhar_no')->orWhere('adhar_no', '');
                })->where(function ($q) {
                    $q->whereNull('gstn')->orWhere('gstn', '');
                });
            });
    }
    public function scopependingDO($query)
    {
        return $query
            ->from('xcelr8_booking_master as bookings')
            ->join('xcelr8_finance as xf', 'bookings.id', '=', 'xf.bid')
            ->where('bookings.status', 2)
            ->where('bookings.fin_mode', 'In-house')
            ->where('xf.status', 1)
            ->where('xf.verification_status', '!=', 3)
            ->whereNotNull('xf.bid')
            ->withTrashed();
    }

    /**
     * Scope for Pending DMS
     */
    public function scopePendingDMS($query)
    {
        $excludedOrderIds = self::whereIn('order', [1, 2, 3])
            ->pluck('id')
            ->toArray();

        return $query->whereIn('status', [1, 8])
            ->whereNotIn('id', $excludedOrderIds)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('dms_no')->orWhere('dms_no', '');
                })->orWhere(function ($q) {
                    $q->whereNull('dms_otf')->orWhere('dms_otf', '');
                })->orWhere(function ($q) {
                    $q->whereNull('otf_date')->orWhere('otf_date', '');
                });
            });
    }

    /**
     * Scope for Pending Insurance
     */
    public function scopePendingInsurance($query)
    {
        return $query->where('status', 2)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('xcelr8_booking_insurance')
                    ->whereColumn('xcelr8_booking_insurance.bid', 'xcelr8_booking_master.id')
                    ->where('xcelr8_booking_insurance.status', 2);
            });
    }

    /**
     * Scope for Pending RTO
     */
    public function scopePendingRTO($query)
    {
        return $query->where('status', 2)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('xcelr8_booking_rto')
                    ->whereColumn('xcelr8_booking_rto.bid', 'xcelr8_booking_master.id')
                    ->where('xcelr8_booking_rto.status', 2);
            });
    }

    /**
     * Scope for Pending Deliveries
     */
    public function scopePendingDeliveries($query)
    {
        return $query->where('status', 2)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('xcelr8_booking_delivered')
                    ->whereColumn('xcelr8_booking_delivered.bid', 'xcelr8_booking_master.id')
                    ->where('xcelr8_booking_delivered.status', 1);
            })
            ->whereNotIn('id', function ($query) {
                $query->select('bid')
                    ->from('xcelr8_booking_delivered');
            });
    }

    /**
     * Scope for Pending Registration Number
     */
    public function scopePendingRegNo($query)
    {
        return $query->join('xcelr8_booking_rto', function ($join) {
            $join->on('xcelr8_booking_rto.bid', '=', 'xcelr8_booking_master.id')
                ->where('xcelr8_booking_rto.status', 1)
                ->whereNull('xcelr8_booking_rto.vh_rgn_no');
        });
    }


    /**
     * Dynamic Aggregation Methods
     */
    public static function getDynamicBookingCounts($condition = null)
    {
        $query = self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at');

        if ($condition) {
            $query->$condition();
        }

        $branches = Branches::whereNull('deleted_at')->pluck('abbr', 'id')->toArray();
        $locations = Location::whereNull('deleted_at')->pluck('abbr', 'id')->toArray();

        $branchColumns = [];
        foreach ($branches as $id => $abbr) {
            $sanitizedAbbr = str_replace([' ', '-'], '_', strtolower($abbr));
            $branchColumns["bookings_branch_$sanitizedAbbr"] = DB::raw("COUNT(DISTINCT CASE WHEN xcelr8_booking_master.branch_id = $id THEN xcelr8_booking_master.id END)");
        }

        $locationColumns = [];
        foreach ($locations as $id => $abbr) {
            $sanitizedAbbr = str_replace([' ', '-'], '_', strtolower($abbr));
            $locationColumns[" booking_location_$sanitizedAbbr"] = DB::raw("COUNT(DISTINCT CASE WHEN xcelr8_booking_master.location_id = $id THEN xcelr8_booking_master.id END)");
        }

        return $query->select([
            DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
            'xcelr8_vehicle_master.custom_model as model',
            'xcelr8_vehicle_master.custom_variant as variant',
            'xcelr8_vehicle_master.color',
            DB::raw('COUNT(DISTINCT xcelr8_booking_master.id) as bookings_total'),
            DB::raw('COUNT(DISTINCT CASE WHEN xcelr8_booking_master.location_id IS NULL OR xcelr8_booking_master.location_id = 0 THEN xcelr8_booking_master.id END) as bookings_other'),
            DB::raw('MAX(DATEDIFF(CURDATE(), xcelr8_booking_master.booking_date)) as tst_max_age'),
            ...$branchColumns,
            ...$locationColumns
        ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }

    public static function getFinanceCounts($type, $timeFrame = null)
    {
        $query = self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at')
            ->whereHas('finances', function ($q) use ($type) {
                $q->where('verification_status', 1)->where('status', 2);
            })
            ->where('finance', $type);

        if ($timeFrame === 'mtd') {
            $query->where('booking_date', '>=', now()->subDays(30));
        } elseif ($timeFrame === 'ytd') {
            $query->where('booking_date', '>=', now()->subDays(365));
        }

        return $query->select([
            DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
            'xcelr8_vehicle_master.custom_model as model',
            'xcelr8_vehicle_master.custom_variant as variant',
            'xcelr8_vehicle_master.color',
            DB::raw("COUNT(DISTINCT xcelr8_finance.id) as finance_" . strtolower(str_replace(' ', '_', $type))),
            DB::raw("(COUNT(DISTINCT xcelr8_finance.id) / NULLIF(COUNT(DISTINCT CASE WHEN xcelr8_booking_master.status = 2 AND xcelr8_booking_master.finance NOT IN ('Cash OOT', 'Customer self OOT') THEN xcelr8_booking_master.id END), 0)) * 100 as finance_" . strtolower(str_replace(' ', '_', $type)) . "_percent")
        ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->join('xcelr8_finance', 'xcelr8_booking_master.id', '=', 'xcelr8_finance.booking_id')
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }

    public static function getFinanceMTDPercent($type)
    {
        $query = self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at')
            ->whereHas('finances', function ($q) use ($type) {
                $q->where('verification_status', 1)->where('status', 2);
            })
            ->where('finance', $type)
            ->where('booking_date', '>=', now()->subDays(30));

        return $query->select([
            DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
            'xcelr8_vehicle_master.custom_model as model',
            'xcelr8_vehicle_master.custom_variant as variant',
            'xcelr8_vehicle_master.color',
            DB::raw("(COUNT(DISTINCT xcelr8_finance.id) / NULLIF(COUNT(DISTINCT CASE WHEN xcelr8_booking_master.status = 2 AND xcelr8_booking_master.finance NOT IN ('Cash OOT', 'Customer self OOT') AND xcelr8_booking_master.booking_date >= CURDATE() - INTERVAL 30 DAY THEN xcelr8_booking_master.id END), 0)) * 100 as finance_" . strtolower(str_replace(' ', '_', $type)) . "_mtd_percent")
        ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->join('xcelr8_finance', 'xcelr8_booking_master.id', '=', 'xcelr8_finance.booking_id')
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }

    public static function getFinanceYTDPercent($type)
    {
        $query = self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at')
            ->whereHas('finances', function ($q) use ($type) {
                $q->where('verification_status', 1)->where('status', 2);
            })
            ->where('finance', $type)
            ->where('booking_date', '>=', now()->subDays(365));

        return $query->select([
            DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
            'xcelr8_vehicle_master.custom_model as model',
            'xcelr8_vehicle_master.custom_variant as variant',
            'xcelr8_vehicle_master.color',
            DB::raw("(COUNT(DISTINCT xcelr8_finance.id) / NULLIF(COUNT(DISTINCT CASE WHEN xcelr8_booking_master.status = 2 AND xcelr8_booking_master.finance NOT IN ('Cash OOT', 'Customer self OOT') AND xcelr8_booking_master.booking_date >= CURDATE() - INTERVAL 365 DAY THEN xcelr8_booking_master.id END), 0)) * 100 as finance_" . strtolower(str_replace(' ', '_', $type)) . "_ytd_percent")
        ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->join('xcelr8_finance', 'xcelr8_booking_master.id', '=', 'xcelr8_finance.booking_id')
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }

    public static function getExchangeScrappageCounts($type, $timeFrame = null)
    {
        $query = self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at')
            ->whereHas('exchanges', function ($q) use ($type) {
                $q->where('verification_status', 1)->where('status', 2)->where('purchase_type', $type);
            });

        if ($timeFrame === 'mtd') {
            $query->where('booking_date', '>=', now()->subDays(30));
        } elseif ($timeFrame === 'ytd') {
            $query->where('booking_date', '>=', now()->subDays(365));
        }

        return $query->select([
            DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
            'xcelr8_vehicle_master.custom_model as model',
            'xcelr8_vehicle_master.custom_variant as variant',
            'xcelr8_vehicle_master.color',
            DB::raw("COUNT(DISTINCT xcelr8_exchange.id) as " . strtolower(str_replace(' ', '_', $type)) . "_inhouse"),
            DB::raw("(COUNT(DISTINCT xcelr8_exchange.id) / NULLIF(COUNT(DISTINCT CASE WHEN xcelr8_booking_master.status = 2 AND xcelr8_booking_master.finance NOT IN ('Cash OOT', 'Customer self OOT') THEN xcelr8_booking_master.id END), 0)) * 100 as " . strtolower(str_replace(' ', '_', $type)) . "_inhouse_percent")
        ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->join('xcelr8_exchange', 'xcelr8_booking_master.id', '=', 'xcelr8_exchange.booking_id')
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }

    public static function getExchangeScrappageMTDPercent($type)
    {
        $query = self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at')
            ->whereHas('exchanges', function ($q) use ($type) {
                $q->where('verification_status', 1)->where('status', 2)->where('purchase_type', $type);
            })
            ->where('booking_date', '>=', now()->subDays(30));

        return $query->select([
            DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
            'xcelr8_vehicle_master.custom_model as model',
            'xcelr8_vehicle_master.custom_variant as variant',
            'xcelr8_vehicle_master.color',
            DB::raw("(COUNT(DISTINCT xcelr8_exchange.id) / NULLIF(COUNT(DISTINCT CASE WHEN xcelr8_booking_master.status = 2 AND xcelr8_booking_master.finance NOT IN ('Cash OOT', 'Customer self OOT') AND xcelr8_booking_master.booking_date >= CURDATE() - INTERVAL 30 DAY THEN xcelr8_booking_master.id END), 0)) * 100 as " . strtolower(str_replace(' ', '_', $type)) . "_mtd_percent")
        ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->join('xcelr8_exchange', 'xcelr8_booking_master.id', '=', 'xcelr8_exchange.booking_id')
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }

    public static function getExchangeScrappageYTDPercent($type)
    {
        $query = self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at')
            ->whereHas('exchanges', function ($q) use ($type) {
                $q->where('verification_status', 1)->where('status', 2)->where('purchase_type', $type);
            })
            ->where('booking_date', '>=', now()->subDays(365));

        return $query->select([
            DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
            'xcelr8_vehicle_master.custom_model as model',
            'xcelr8_vehicle_master.custom_variant as variant',
            'xcelr8_vehicle_master.color',
            DB::raw("(COUNT(DISTINCT xcelr8_exchange.id) / NULLIF(COUNT(DISTINCT CASE WHEN xcelr8_booking_master.status = 2 AND xcelr8_booking_master.finance NOT IN ('Cash OOT', 'Customer self OOT') AND xcelr8_booking_master.booking_date >= CURDATE() - INTERVAL 365 DAY THEN xcelr8_booking_master.id END), 0)) * 100 as " . strtolower(str_replace(' ', '_', $type)) . "_ytd_percent")
        ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->join('xcelr8_exchange', 'xcelr8_booking_master.id', '=', 'xcelr8_exchange.booking_id')
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }

    /**
     * Age-Based Metrics
     */
    public static function getTSTMaxAge()
    {
        return self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at')
            ->select([
                DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
                'xcelr8_vehicle_master.custom_model as model',
                'xcelr8_vehicle_master.custom_variant as variant',
                'xcelr8_vehicle_master.color',
                DB::raw('MAX(DATEDIFF(CURDATE(), xcelr8_booking_master.booking_date)) as tst_max_age')
            ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }

    public static function getBookingsOlderThan($days)
    {
        return self::query()->whereHas('vehicle', function ($q) {
            $q->whereNull('deleted_at')->where('status', 1);
        })->whereNull('deleted_at')
            ->whereRaw('DATEDIFF(CURDATE(), booking_date) > ?', [$days])
            ->select([
                DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
                'xcelr8_vehicle_master.custom_model as model',
                'xcelr8_vehicle_master.custom_variant as variant',
                'xcelr8_vehicle_master.color',
                DB::raw('COUNT(DISTINCT xcelr8_booking_master.id) as bookings_older_than_' . $days . '_days')
            ])
            ->join('xcelr8_vehicle_master', function ($join) {
                $join->on('xcelr8_vehicle_master.id', '=', 'xcelr8_booking_master.vh_id')
                    ->orOn('xcelr8_vehicle_master.code', '=', 'xcelr8_booking_master.model_code');
            })
            ->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
            ->get();
    }
}
