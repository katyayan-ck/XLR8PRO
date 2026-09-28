<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Session;

/**
 * The stages of a pricing process (DEC-073), in order. A session moves forward through them; `completed` and
 * `discarded` are terminal. Discard is allowed only before anything is published (see PricingSessionService).
 */
enum PricingStage: string
{
    case Started = 'started';
    case Detecting = 'detecting';
    case VehicleInfo = 'vehicle_info';
    case Prices = 'prices';
    case Addons = 'addons';
    case Rules = 'rules';
    case Impact = 'impact';
    case HoldCheck = 'hold_check';
    case Calculating = 'calculating';
    case Summary = 'summary';
    case Completed = 'completed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Started => 'Started',
            self::Detecting => 'Detecting vehicles',
            self::VehicleInfo => 'Vehicle info',
            self::Prices => 'Price import',
            self::Addons => 'Add-ons & discounts',
            self::Rules => 'Insurance & RTO',
            self::Impact => 'Impact summary',
            self::HoldCheck => 'Hold check',
            self::Calculating => 'Calculate & publish',
            self::Summary => 'Process summary',
            self::Completed => 'Completed',
            self::Discarded => 'Discarded',
        };
    }

    /** Position in the flow (for "step n of m" and forward-only moves). */
    public function order(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Discarded;
    }

    /** The user-facing steps shown in the stepper (1 … 10). @return list<self> */
    public static function steps(): array
    {
        return [self::Started, self::VehicleInfo, self::Prices, self::Addons, self::Rules, self::Impact, self::HoldCheck, self::Calculating, self::Summary];
    }

    /** Map legacy stage strings of sessions created before DEC-073. */
    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? match ((string) $value) {
            'idle' => self::Started,
            'awaiting_vehicle' => self::VehicleInfo,
            'importing_prices' => self::Prices,
            'awaiting_addons', 'importing_addons' => self::Addons,
            'awaiting_rules' => self::Rules,
            'cancelled' => self::Discarded,
            default => self::Started,
        };
    }
}
