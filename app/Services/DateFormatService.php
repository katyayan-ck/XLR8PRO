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

    /** Date + time format: the site date format followed by `display.time_format` (default "H:i"). */
    public function phpDateTimeFormat(): string
    {
        $time = $this->settings->get('display.time_format', 'H:i');

        return $this->phpFormat().' '.(is_string($time) && $time !== '' ? $time : 'H:i');
    }

    /** Formats a date-time value with the site date format plus time; same fallback rules as format(). */
    public function formatDateTime(mixed $date, string $fallback = 'N/A'): string
    {
        if (empty($date)) {
            return $fallback;
        }
        try {
            return ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->format($this->phpDateTimeFormat());
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * The site format as Carbon isoFormat tokens (what Backpack's date/datetime columns use), so
     * Backpack lists follow the same setting.
     */
    public function isoFormat(bool $withTime = false): string
    {
        $map = ['d' => 'DD', 'j' => 'D', 'D' => 'ddd', 'l' => 'dddd', 'm' => 'MM', 'n' => 'M', 'M' => 'MMM', 'F' => 'MMMM',
            'Y' => 'YYYY', 'y' => 'YY', 'H' => 'HH', 'G' => 'H', 'h' => 'hh', 'g' => 'h', 'i' => 'mm', 's' => 'ss', 'A' => 'A', 'a' => 'a'];

        return strtr($withTime ? $this->phpDateTimeFormat() : $this->phpFormat(), $map);
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
