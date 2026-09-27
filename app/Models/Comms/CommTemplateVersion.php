<?php

namespace App\Models\Comms;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of a template's copy (FRS §12.2): DRAFT → IN_REVIEW → APPROVED → ACTIVE → RETIRED.
 * ACTIVE / RETIRED versions are never edited (a change forks a new draft).
 *
 * @property int $id
 * @property int $template_id
 * @property int $version
 * @property string $status
 * @property ?string $subject
 * @property ?string $body_html
 * @property ?string $body_text
 * @property ?array<string, mixed> $wa_components
 * @property ?list<array{name: string, type?: string, required?: bool, sample?: string, pii?: bool}> $variables
 * @property ?array<string, mixed> $sample_vars
 * @property ?string $provider_template_id
 * @property ?string $dlt_entity_id
 * @property ?string $dlt_header
 * @property ?int $approval_request_id
 * @property ?int $approved_by
 * @property ?Carbon $approved_at
 * @property ?Carbon $activated_at
 * @property int $usage_count
 * @property ?Carbon $last_used_at
 * @property-read CommTemplate $template
 */
class CommTemplateVersion extends BaseModel
{
    public const STATUSES = ['DRAFT', 'IN_REVIEW', 'APPROVED', 'ACTIVE', 'RETIRED', 'PENDING_PROVIDER', 'REJECTED'];

    protected $table = 'xlr8_comm_template_version';

    protected $fillable = [
        'template_id', 'version', 'status', 'subject', 'body_html', 'body_text', 'wa_components', 'variables', 'sample_vars',
        'provider_template_id', 'dlt_entity_id', 'dlt_header', 'approval_request_id', 'approved_by', 'approved_at',
        'activated_at', 'retired_at', 'usage_count', 'last_used_at',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'wa_components' => 'array', 'variables' => 'array', 'sample_vars' => 'array',
            'approved_at' => 'datetime', 'activated_at' => 'datetime', 'retired_at' => 'datetime', 'last_used_at' => 'datetime',
        ]);
    }

    /** @return BelongsTo<CommTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommTemplate::class, 'template_id');
    }
}
