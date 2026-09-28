<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use Carbon\CarbonImmutable;

/**
 * The period a dashboard widget counts over (DEC-072): today, this week (Mon–Sun), this month, this quarter or this
 * Indian financial year (Apr–Mar). Dates are compared as local calendar days.
 */
final class DashboardPeriod
{
    public const KEYS = ['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'quarter' => 'This quarter', 'fy' => 'This FY'];

    public const DEFAULT = 'month';

    private function __construct(public readonly string $key, public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    public static function make(?string $key, ?CarbonImmutable $now = null): self
    {
        $key = array_key_exists((string) $key, self::KEYS) ? (string) $key : self::DEFAULT;
        $now ??= CarbonImmutable::now();

        [$from, $to] = match ($key) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'week' => [$now->startOfWeek(), $now->endOfWeek()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            'quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'fy' => self::financialYear($now),
        };

        return new self($key, $from, $to);
    }

    public function label(): string
    {
        return self::KEYS[$this->key];
    }

    /** @return array{0: string, 1: string} date strings for whereBetween on date / datetime columns */
    public function between(): array
    {
        return [$this->from->toDateTimeString(), $this->to->toDateTimeString()];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function financialYear(CarbonImmutable $now): array
    {
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $from = CarbonImmutable::create($startYear, 4, 1)->startOfDay();

        return [$from, $from->addYear()->subDay()->endOfDay()];
    }
}
