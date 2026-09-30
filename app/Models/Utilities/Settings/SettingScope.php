<?php

namespace App\Models\Utilities\Settings;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A scoped override of a setting (COMPANY / BRANCH / DESK + code) — `xlr8_utils_setting_scope`. Written only by
 * `SettingsService` (`set($key, $value, $scopeType, $scopeCode)`, `clearScope()`), DEC-093.
 *
 * @property int $id
 * @property string $setting_key
 * @property string $scope_type
 * @property string $scope_code
 * @property ?string $value
 * @property ?int $created_by
 * @property ?int $updated_by
 */
class SettingScope extends Model
{
    protected $table = 'xlr8_utils_setting_scope';

    protected $fillable = ['setting_key', 'scope_type', 'scope_code', 'value', 'created_by', 'updated_by'];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFor(Builder $query, string $key, string $scopeType, string $scopeCode): Builder
    {
        return $query->where('setting_key', $key)->where('scope_type', $scopeType)->where('scope_code', $scopeCode);
    }
}
