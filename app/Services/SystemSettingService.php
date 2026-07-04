<?php

namespace App\Services;

use App\Models\Utilities\Settings\SystemSetting;
use Illuminate\Support\Facades\Validator;

/**
 * SystemSettingService
 * 
 * Centralized service for system settings with validation,
 * formatting, and business logic
 */
class SystemSettingService
{
    /**
     * Get a single setting value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return SystemSetting::get($key, $default);
    }

    /**
     * Get all settings as flat array
     */
    public function all(): array
    {
        return SystemSetting::getAllAsArray();
    }

    /**
     * Get all settings grouped by topic
     */
    public function allByTopic(): array
    {
        return SystemSetting::allByTopic();
    }

    /**
     * Get settings for specific topic
     */
    public function topic(string $topic): array
    {
        return SystemSetting::getByTopic($topic);
    }

    /**
     * Set a setting value with validation
     */
    public function set(string $key, mixed $value, ?string $topic = null): SystemSetting
    {
        $setting = SystemSetting::where('key', $key)->first();

        if (!$setting) {
            throw new \InvalidArgumentException("Setting '{$key}' does not exist");
        }

        // Validate value if rules exist
        if ($setting->validation_rules) {
            $rules = [
                'value' => $setting->validation_rules
            ];

            $validator = Validator::make(['value' => $value], $rules);

            if ($validator->fails()) {
                throw new \InvalidArgumentException(
                    "Validation failed for '{$key}': " . implode(', ', $validator->errors()->all())
                );
            }
        }

        return SystemSetting::set($key, $value, $topic);
    }

    /**
     * Get grouped settings for admin UI
     */
    public function getForAdmin(string $topic = null): array
    {
        $query = SystemSetting::where('is_visible', true)
            ->orderBy('topic')
            ->orderBy('group')
            ->orderBy('sort_order');

        if ($topic) {
            $query->where('topic', $topic);
        }

        return $query->get()
            ->groupBy('topic')
            ->map(fn($topicSettings) => $topicSettings->groupBy('group'))
            ->toArray();
    }

    /**
     * Get all distinct topics
     */
    public function getTopics(): array
    {
        return SystemSetting::select('topic')
            ->distinct()
            ->where('is_visible', true)
            ->orderBy('topic')
            ->get()
            ->pluck('topic')
            ->toArray();
    }

    /**
     * Get settings for specific topic and group
     */
    public function getByGroup(string $topic, string $group): array
    {
        return SystemSetting::byTopic($topic)
            ->byGroup($group)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn($row) => [$row->key => $row->value])
            ->toArray();
    }

    /**
     * Bulk set multiple settings
     */
    public function setMultiple(array $settings): array
    {
        $saved = [];

        foreach ($settings as $key => $value) {
            try {
                $saved[$key] = $this->set($key, $value);
            } catch (\Exception $e) {
                \Log::warning("Failed to set setting '{$key}': {$e->getMessage()}");
            }
        }

        return $saved;
    }

    /**
     * Get settings as key-value for environment variables
     */
    public function getAsEnv(): array
    {
        $env = [];

        foreach (SystemSetting::all() as $setting) {
            // Convert key format: site.name -> SITE_NAME
            $envKey = strtoupper(str_replace('.', '_', $setting->key));
            $env[$envKey] = $setting->value;
        }

        return $env;
    }

    /**
     * Get site-specific settings
     */
    public function getSiteSettings(): array
    {
        return SystemSetting::getByTopic('site');
    }

    /**
     * Get dealership-specific settings
     */
    public function getDealershipSettings(): array
    {
        return SystemSetting::getByTopic('dealership');
    }

    /**
     * Get pricing-specific settings
     */
    public function getPricingSettings(): array
    {
        return SystemSetting::getByTopic('pricing');
    }

    /**
     * Helper: Get site name
     */
    public function getSiteName(): string
    {
        return (string) $this->get('site.name', config('app.name', 'MyApp'));
    }

    /**
     * Helper: Get site URL
     */
    public function getSiteUrl(): string
    {
        return (string) $this->get('site.url', config('app.url', 'http://localhost'));
    }

    /**
     * Helper: Get site slogan
     */
    public function getSiteSlogan(): string
    {
        return (string) $this->get('site.slogan', '');
    }

    /**
     * Helper: Get site logo paths
     */
    public function getSiteLogos(): array
    {
        return [
            'header' => $this->get('site.logo.header', 'images/logo-header.png'),
            'footer' => $this->get('site.logo.footer', 'images/logo-footer.png'),
            'login' => $this->get('site.logo.login', 'images/logo-login.png'),
            'favicon' => $this->get('site.logo.favicon', 'favicon.ico'),
        ];
    }

    /**
     * Helper: Get logo URL
     */
    public function getLogoUrl(string $type = 'header'): string
    {
        $path = $this->get("site.logo.{$type}", "images/logo-{$type}.png");
        return asset($path);
    }

    /**
     * Helper: Get footer text
     */
    public function getFooterText(): string
    {
        return (string) $this->get('site.footer.text', '&copy; ' . date('Y'));
    }

    /**
     * Helper: Get theme
     */
    public function getTheme(): string
    {
        return (string) $this->get('site.theme', 'light');
    }

    /**
     * Helper: Get skin/color scheme
     */
    public function getSkin(): string
    {
        return (string) $this->get('site.skin', 'default');
    }

    /**
     * Helper: Get dealership name
     */
    public function getDealershipName(): string
    {
        return (string) $this->get('dealership.name', '');
    }

    /**
     * Helper: Get dealership details
     */
    public function getDealershipDetails(): array
    {
        return [
            'name' => $this->get('dealership.name', ''),
            'address' => $this->get('dealership.address', ''),
            'phone' => $this->get('dealership.phone', ''),
            'email' => $this->get('dealership.email', ''),
            'website' => $this->get('dealership.website', ''),
            'pan' => $this->get('dealership.pan', ''),
            'gstin' => $this->get('dealership.gstin', ''),
        ];
    }

    /**
     * Helper: Get tax rates
     */
    public function getTaxRates(): array
    {
        return [
            'gst' => (float) $this->get('pricing.gst_rate', 18),
            'tds' => (float) $this->get('pricing.tds_rate', 1),
        ];
    }

    /**
     * Helper: Get GST rate
     */
    public function getGSTRate(): float
    {
        return (float) $this->get('pricing.gst_rate', 18);
    }

    /**
     * Helper: Get TDS rate
     */
    public function getTDSRate(): float
    {
        return (float) $this->get('pricing.tds_rate', 1);
    }

    /**
     * Get setting with all metadata
     */
    public function getSetting(string $key): ?SystemSetting
    {
        return SystemSetting::where('key', $key)->first();
    }

    /**
     * Get all settings with metadata
     */
    public function getAllWithMetadata(): array
    {
        return SystemSetting::orderBy('topic')
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }
}
