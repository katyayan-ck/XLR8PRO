<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Screen density (go-live to-do U3): the site default text size and spacing scale, set in Settings
 * (`ui.density.text`, `ui.density.space`). The render-blocking bootstrap (`inc/theme_styles.blade.php`) puts them on
 * `<html data-xl-text data-xl-space>` before the first paint; a user's own choice from the Appearance panel
 * (`public/js/xl-theme.js`, saved per browser) wins over them. The CSS lives in `public/css/xl-ui.css`.
 *
 *   UiDensity::defaults();   // ['text' => 'sm', 'space' => 'compact']
 */
final class UiDensity
{
    /** Text sizes, smallest first; `md` is the Tabler standard. */
    public const TEXT = ['xs', 'sm', 'md', 'lg'];

    /** Spacing scales, tightest first; `comfortable` is the Tabler standard. */
    public const SPACE = ['compact', 'cozy', 'comfortable'];

    /**
     * The site defaults; an unknown or unreadable setting falls back to the Tabler standard, so a typo in Settings (or
     * a database that is down) never breaks the page.
     *
     * @return array{text: string, space: string}
     */
    public static function defaults(): array
    {
        $text = (string) rescue(fn () => setting('ui.density.text', 'md'), 'md', false);
        $space = (string) rescue(fn () => setting('ui.density.space', 'comfortable'), 'comfortable', false);

        return [
            'text' => in_array($text, self::TEXT, true) ? $text : 'md',
            'space' => in_array($space, self::SPACE, true) ? $space : 'comfortable',
        ];
    }
}
