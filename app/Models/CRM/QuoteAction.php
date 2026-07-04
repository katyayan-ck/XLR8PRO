<?php

namespace App\Models\CRM;

use App\Models\BaseModel;
use App\Models\User;
use App\Models\Traits\HasColumnTransformations;

class QuoteAction extends BaseModel
{
    use HasColumnTransformations;

    protected $table = 'xlr8_crm_quote_actions';

    protected $fillable = [
        'quotation_no',
        'action_by',
        'action',
        'revision',
        'requested',
        'onroad',
        'status',
        'remarks',
    ];

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge($this->casts, [
            'requested' => 'array',
            'onroad'    => 'decimal:2',
            'revision'  => 'integer',
        ]);
    }

    protected array $columnTransformations = [
        'action' => 'trim',
        'status' => 'trim',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_no', 'quotation_no');
    }

    public function actionBy()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    // public function getActionLabelAttribute(): string
    // {
    //     return app(\App\Services\KeywordValueService::class)->getEnum('QUOTE_ACTION', $this->action)
    //         ?? ucfirst(str_replace('_', ' ', $this->action));
    // }
}
