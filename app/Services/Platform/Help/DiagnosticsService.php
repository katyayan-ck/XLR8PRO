<?php

namespace App\Services\Platform\Help;

use Illuminate\Support\Facades\Cache;

/**
 * Diagnostics for support requests (DEC-094, W16d; FRS help-and-support §5.2). Keeps each user's last admin requests in
 * a short-lived server trail (cache, no table) and masks personal data before anything leaves the app. The browser side
 * (`public/js/xl-diag.js`) keeps the matching action / network / error ring buffer; the support request (W16e) puts
 * both into the ticket's zip.
 *
 * Example:
 *
 *   $diag->record($userId, ['method' => 'GET', 'route' => 'sales.booking.index', 'status' => 200, 'ms' => 84]);
 *   $diag->trail($userId);                      // newest first, at most 50, kept 2 hours
 *   DiagnosticsService::mask('PAN ABCDE1234F'); // "PAN XXXXXX234F"
 */
class DiagnosticsService
{
    public const TRAIL_SIZE = 50;

    public const TRAIL_MINUTES = 120;

    /** Query parameters whose values are never kept. */
    private const SECRET_PARAMS = ['token', '_token', 'password', 'otp', 'signature', 'code', 'key', 'secret', 'api_key', 'access_token'];

    /**
     * Add one request to the user's trail (newest first, capped at TRAIL_SIZE).
     *
     * @param  array<string, mixed>  $entry
     */
    public function record(int $userId, array $entry): void
    {
        $key = $this->key($userId);
        $trail = Cache::get($key, []);
        array_unshift($trail, $entry + ['at' => now()->toIso8601String()]);
        Cache::put($key, array_slice($trail, 0, self::TRAIL_SIZE), now()->addMinutes(self::TRAIL_MINUTES));
    }

    /** @return list<array<string, mixed>> the user's recent requests, newest first */
    public function trail(int $userId): array
    {
        return array_values(Cache::get($this->key($userId), []));
    }

    /**
     * Masks personal data in free text: Aadhaar (`XXXXXXXX1234`), PAN (`XXXXXX234F`), mobile numbers (`XXXXXX3210`), e-mail
     * addresses (`r***@example.com`) and long tokens (`[token]`).
     */
    public static function mask(string $text): string
    {
        $text = (string) preg_replace_callback('/(?<![\w-])\d{4}[\s-]?\d{4}[\s-]?\d{4}(?![\w-])/', fn ($m) => 'XXXXXXXX'.substr(preg_replace('/\D/', '', $m[0]), -4), $text);
        $text = (string) preg_replace_callback('/\b[A-Z]{5}\d{4}[A-Z]\b/', fn ($m) => 'XXXXXX'.substr($m[0], -4), $text);
        $text = (string) preg_replace_callback('/(?<![\w])(?:\+?91[\s-]?)?[6-9]\d{9}(?![\w])/', fn ($m) => 'XXXXXX'.substr($m[0], -4), $text);
        $text = (string) preg_replace_callback('/\b([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/', fn ($m) => $m[1].'***@'.$m[2], $text);

        return (string) preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', '[token]', $text);
    }

    /** A URL with secret query values removed and the rest masked. */
    public static function cleanUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return self::mask($url);
        }
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        foreach ($query as $name => $value) {
            if (in_array(strtolower((string) $name), self::SECRET_PARAMS, true)) {
                $query[$name] = '[removed]';
            }
        }
        $path = ($parts['path'] ?? '').($query !== [] ? '?'.urldecode(http_build_query($query)) : '');

        return self::mask($path);
    }

    private function key(int $userId): string
    {
        return "help.trail.{$userId}";
    }
}
