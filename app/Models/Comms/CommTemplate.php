<?php

namespace App\Models\Comms;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A template family: one code × channel × locale (FRS §12.3). Copy lives in versions.
 * Written only by TemplateService.
 *
 * @property int $id
 * @property string $code
 * @property string $channel
 * @property string $locale
 * @property string $brand
 * @property string $category
 * @property string $name
 * @property ?string $description
 * @property bool $is_system
 * @property-read ?CommTemplateVersion $active
 */
class CommTemplate extends BaseModel
{
    public const CHANNELS = ['EMAIL', 'SMS', 'WHATSAPP', 'PUSH', 'PRINT'];

    public const CATEGORIES = ['TRANSACTIONAL', 'OTP', 'OPERATIONAL', 'PROMOTIONAL'];

    protected $table = 'xlr8_comm_template';

    protected $fillable = ['code', 'channel', 'locale', 'brand', 'category', 'name', 'description', 'deprecated_at', 'replaced_by', 'is_system'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_system' => 'boolean', 'deprecated_at' => 'date']);
    }

    /** @return HasMany<CommTemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CommTemplateVersion::class, 'template_id')->orderByDesc('version');
    }

    /** @return HasOne<CommTemplateVersion, $this> */
    public function active(): HasOne
    {
        return $this->hasOne(CommTemplateVersion::class, 'template_id')->where('status', 'ACTIVE');
    }
}
