<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

/** Exchange conditional discount (DEC-083). */
final class ExchangeMaster extends ConditionalDiscountMaster
{
    public function key(): string
    {
        return 'exchange';
    }

    public function label(): string
    {
        return 'Exchange';
    }

    public function permission(): string
    {
        return 'PRC_EXCH';
    }

    public function icon(): string
    {
        return 'la-exchange-alt';
    }

    public function group(): string
    {
        return 'EXCHANGE';
    }
}
