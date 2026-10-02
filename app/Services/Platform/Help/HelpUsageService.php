<?php

namespace App\Services\Platform\Help;

use App\Models\Utilities\Help\HelpUsage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Help-usage log and report (W16f, DEC-094; FRS help-and-support §7): which articles are opened, which screens have no
 * article, what people search for (and find nothing for), which tours are finished and how many support requests are
 * sent. Search text is masked (`DiagnosticsService::mask`) and cut to 100 characters. Logging never breaks a request.
 *
 * Example:
 *
 *   $usage->record('OPEN', 'sales/booking/edit', $userId);
 *   $usage->report(30);   // ['days' => 30, 'totals' => [...], 'articles' => [...], 'empty_searches' => [...], ...]
 */
class HelpUsageService
{
    /** OPEN article · MISSING screen without help · SEARCH / SEARCH_EMPTY · TOUR_DONE · SUPPORT request sent */
    public const EVENTS = ['OPEN', 'MISSING', 'SEARCH', 'SEARCH_EMPTY', 'TOUR_DONE', 'SUPPORT'];

    public function record(string $event, ?string $ref, ?int $userId): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }
        if (in_array($event, ['SEARCH', 'SEARCH_EMPTY'], true) && $ref !== null) {
            $ref = mb_strtolower(trim(DiagnosticsService::mask($ref)));
        }
        try {
            HelpUsage::query()->create(['user_id' => $userId, 'event' => $event, 'ref' => $ref !== null ? mb_substr($ref, 0, 100) : null]);
        } catch (Throwable $e) {
            Log::warning('Help usage not recorded', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Usage over the last N days.
     *
     * @return array{days: int, totals: array<string, int>, articles: array<string, int>, missing: array<string, int>, empty_searches: array<string, int>, searches: array<string, int>}
     */
    public function report(int $days = 30): array
    {
        $since = now()->subDays(max(1, $days));
        $base = fn () => HelpUsage::query()->where('created_at', '>=', $since);
        $top = fn (string $event) => $base()->where('event', $event)->whereNotNull('ref')->selectRaw('ref, COUNT(*) AS n')
            ->groupBy('ref')->orderByDesc('n')->limit(10)->pluck('n', 'ref')->map(fn ($n) => (int) $n)->all();

        return [
            'days' => $days,
            'totals' => $base()->selectRaw('event, COUNT(*) AS n')->groupBy('event')->pluck('n', 'event')->map(fn ($n) => (int) $n)->all(),
            'articles' => $top('OPEN'),
            'missing' => $top('MISSING'),
            'empty_searches' => $top('SEARCH_EMPTY'),
            'searches' => $top('SEARCH'),
        ];
    }

    /** Deletes events older than `help.usage_retention_days` (default 180). @return int rows removed */
    public function purge(): int
    {
        $days = max(1, (int) setting('help.usage_retention_days', 180));

        return HelpUsage::query()->where('created_at', '<', now()->subDays($days))->delete();
    }
}
