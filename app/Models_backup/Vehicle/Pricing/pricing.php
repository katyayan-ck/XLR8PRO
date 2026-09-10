<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Pricing extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing';

    protected $fillable = [
        'import_session_id',
        'model_code',
        'variant_code',
        'channel',
        'wef_date',
        'expired_on',
        'is_active',
        'assessable_value_with_freight',
        'gst_percent',
        'gst_amount',
        'mm_invoice_amount',
        'dealer_margin',
        'ex_showroom_price',
        'curr_oem_scheme',
        'curr_dealer_cont',
        'curr_cash_discount',
        'curr_acc_discount',
        'curr_shield_discount',
        'curr_acc_elg',
        'curr_shield_elg',
        'old_oem_scheme',
        'old_dealer_cont',
        'old_cash_discount',
        'old_acc_discount',
        'old_shield_discount',
        'old_acc_elg',
        'old_shield_elg',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'wef_date'                      => 'date',
            'expired_on'                    => 'date',
            'is_active'                     => 'boolean',
            'assessable_value_with_freight' => 'decimal:2',
            'gst_percent'                   => 'decimal:2',
            'gst_amount'                    => 'decimal:2',
            'mm_invoice_amount'             => 'decimal:2',
            'dealer_margin'                 => 'decimal:2',
            'ex_showroom_price'             => 'decimal:2',
            'curr_oem_scheme'               => 'decimal:2',
            'curr_dealer_cont'              => 'decimal:2',
            'curr_cash_discount'            => 'decimal:2',
            'curr_acc_discount'             => 'decimal:2',
            'curr_shield_discount'          => 'decimal:2',
            'curr_acc_elg'                  => 'decimal:4',
            'curr_shield_elg'               => 'decimal:4',
            'old_oem_scheme'                => 'decimal:2',
            'old_dealer_cont'               => 'decimal:2',
            'old_cash_discount'             => 'decimal:2',
            'old_acc_discount'              => 'decimal:2',
            'old_shield_discount'           => 'decimal:2',
            'old_acc_elg'                   => 'decimal:4',
            'old_shield_elg'                => 'decimal:4',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expired_on')
                  ->orWhere('expired_on', '>=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('wef_date')
                  ->orWhere('wef_date', '<=', now()->toDateString());
            });
    }

    public function scopeForModel(Builder $query, string $modelCode): Builder
    {
        return $query->where('model_code', $modelCode);
    }

    public function history()
    {
        return $this->hasMany(PricingHistory::class, 'pricing_id');
    }

    public static function getActive(string $modelCode, string $channel = 'normal'): ?self
    {
        return self::query()
            ->active()
            ->where('model_code', $modelCode)
            ->where('channel', $channel)
            ->orderByDesc('wef_date')
            ->first();
    }
}
