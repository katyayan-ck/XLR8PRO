<?php

namespace App\Models\Utilities\Settings;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use App\Models\BaseModel;

/**
 * SystemSetting Model
 * 
 * Manages application-wide configuration settings with:
 * - Topic/Category based organization
 * - Type casting and validation
 * - Redis caching
 * - Audit trail (inherited from BaseModel via AuditableTrait)
 */
class SystemSetting extends BaseModel
{
    use CrudTrait, HasFactory, SoftDeletes;

    protected $table = 'xlr8_utils_system_setting';

    protected $fillable = [
        'key',
        'label',
        'value',
        'type',
        'description',
        'iseditable',
        'isvisible',
    ];

    protected $casts = [
        'type' => 'string',
        'description' => 'string',
        'iseditable' => 'boolean',
        'isvisible' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Clear cache when setting is created/updated/deleted
        static::saved(function (self $model) {
            static::flushCache($model->key);
        });

        static::deleted(function (self $model) {
            static::flushCache($model->key);
        });
    }

    /**
     * Scope: Get only editable settings
     */
    public function scopeEditable($query)
    {
        return $query->where('iseditable', true);
    }

    /**
     * Scope: Get only visible settings
     */
    public function scopeVisible($query)
    {
        return $query->where('isvisible', true);
    }

    /**
     * Flush cache for single setting
     */
    public static function flushCache(string $key): void
    {
        Cache::forget('setting.' . $key);
    }

    /**
     * Flush all settings cache
     */
    public static function flushAllCache(): void
    {
        Cache::tags('settings')->flush();
    }

    /**
     * Ensure a setting exists or create it if it doesn't
     *
     * @param string $key Setting key
     * @param mixed $value Value to set
     * @param string $type Data type (string, integer, boolean, json, etc.)
     * @param string|null $label Human-readable label
     * @param string|null $description Description text
     * @return self
     */
    public static function ensure(
        string $key,
        $value,
        string $type = 'string',
        ?string $label = null,
        ?string $description = null
    ): self {
        // Prepare value - keep as string for database
        $settingValue = is_string($value) ? $value : json_encode($value);

        // Use firstOrCreate to get existing or create new
        return static::firstOrCreate(
            ['key' => $key],
            [
                'value' => $settingValue,
                'type' => $type,
                'label' => $label ?? str_replace('.', ' ', $key),
                'description' => $description ?? str_replace('.', ' ', $key),
                'is_visible' => true,
                'iseditable' => true,
            ]
        );
    }

    /**
     * Set a setting value
     *
     * @param string $key Setting key
     * @param mixed $value Value to set
     * @return self
     */
    public static function set(string $key, $value): self
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            throw new \Exception("Setting '{$key}' not found. Use ensure() to create it first.");
        }

        $setting->value = is_string($value) ? $value : json_encode($value);

        if (auth()->check()) {
            $setting->updatedby = auth()->id();
        }

        $setting->saveQuietly();

        return $setting;
    }

    /**
     * Check if setting exists
     */
    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }

    /**
     * Get a setting value with type casting
     *
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return static::getValue($key, $default);
    }

    /**
     * Get value with caching and type casting
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        $cacheKey = 'setting.' . $key;

        return Cache::rememberForever($cacheKey, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            return static::castValue($setting);
        });
    }

    /**
     * Cast setting value based on type
     *
     * @param self $setting
     * @return mixed
     */
    protected static function castValue(self $setting)
    {
        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'array' => json_decode($setting->value, true) ?? [],
            'json' => json_decode($setting->value, true) ?? [],
            'file', 'image' => $setting->value,
            default => $setting->value,
        };
    }

    /**
     * Get all settings as key-value array
     *
     * @return array
     */
    public static function getAllAsArray(): array
    {
        return static::where('isvisible', true)
            ->get()
            ->mapWithKeys(fn($row) => [$row->key => static::castValue($row)])
            ->toArray();
    }

    /**
     * Get all settings for export
     *
     * @return array
     */
    public static function allForExport(): array
    {
        return static::orderBy('key')
            ->get()
            ->map(fn($row) => [
                'key' => $row->key,
                'label' => $row->label,
                'value' => $row->value,
                'type' => $row->type,
                'description' => $row->description,
            ])
            ->toArray();
    }
}
