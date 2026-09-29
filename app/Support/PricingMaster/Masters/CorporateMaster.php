<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

/** Corporate conditional discount (DEC-083). */
final class CorporateMaster extends ConditionalDiscountMaster
{
    public function key(): string
    {
        return 'corporate';
    }

    public function label(): string
    {
        return 'Corporate';
    }

    public function permission(): string
    {
        return 'PRC_CORP';
    }

    public function icon(): string
    {
        return 'la-building';
    }

    public function group(): string
    {
        return 'CORPORATE';
    }
}
