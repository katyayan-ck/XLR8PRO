<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

/** Loyalty conditional discount (DEC-083). */
final class LoyaltyMaster extends ConditionalDiscountMaster
{
    public function key(): string
    {
        return 'loyalty';
    }

    public function label(): string
    {
        return 'Loyalty';
    }

    public function permission(): string
    {
        return 'PRC_LYLT';
    }

    public function icon(): string
    {
        return 'la-heart';
    }

    public function group(): string
    {
        return 'LOYALTY';
    }
}
