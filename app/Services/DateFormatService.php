<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for frontend date display formatting, per the
 * project-wide UI/UX standard (see .ai/rules/conventions.md section 13):
 * every date shown on screen uses one site-settings-driven format
 * ("display.date_format", default "d-M-Y" -> 23-Sep-2026), changeable
 * from one place instead of being hardcoded per view.
 */
class DateFormatService
{
    private const SETTING_KEY = 'display.date_format';

    private const DEFAULT_FORMAT = 'd-M-Y';

    public function __construct(private SystemSettingService $settings) {}

    /**
     * The raw PHP/Carbon format token currently configured, e.g. "d-M-Y".
     */
    public function phpFormat(): string
    {
        $format = $this->settings->get(self::SETTING_KEY, self::DEFAULT_FORMAT);

        return is_string($format) && $format !== '' ? $format : self::DEFAULT_FORMAT;
    }

    /**
     * Formats a date value using the site's configured date format.
     * Accepts anything Carbon::parse() accepts (string, Carbon, DateTime),
     * plus null. Returns $fallback ('N/A' by default) for empty/
     * unparseable input rather than throwing, since this is a display
     * helper used directly in Blade views.
     */
    public function format(mixed $date, string $fallback = 'N/A'): string
    {
        if (empty($date)) {
            return $fallback;
        }

        if ($date instanceof CarbonInterface) {
            return $date->format($this->phpFormat());
        }

        try {
            return Carbon::parse($date)->format($this->phpFormat());
        } catch (\Throwable $e) {
            Log::warning('DateFormatService: unparseable date value', [
                'value' => is_scalar($date) ? $date : gettype($date),
                'message' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }
}
