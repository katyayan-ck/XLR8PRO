<?php

namespace App\Models\CRM;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Cache;

class LeadSource extends BaseModel
{

    protected $table = 'xlr8_crm_lead_sources';

    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge($this->casts, [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);
    }

    protected array $columnTransformations = [
        'code' => 'uppercase|trim',
        'name' => 'trim',
    ];

    // ==================== RELATIONSHIPS ====================
    public function leads()
    {
        return $this->hasMany(Lead::class, 'source_code', 'code');
    }

    // Note: scopeActive() is already available from BaseModel
    // No need to redefine it here

    // ==================== CACHING ====================
    public static function getActiveCached(): \Illuminate\Support\Collection
    {
        return Cache::remember('crm_lead_sources_active', 3600, function () {
            return self::active()                    // ← comes from BaseModel
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name']);
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('crm_lead_sources_active');
    }
}