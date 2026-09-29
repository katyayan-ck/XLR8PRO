<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\DealerCharge;
use App\Services\Vehicle\Pricing\Addons\DealerChargeService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\WorkbookGroupMaster;

/** Dealer charges by segment / permit / model, the wide heads (DEC-083). */
final class DealerChargesMaster extends WorkbookGroupMaster
{
    public function key(): string
    {
        return 'dealer-charges';
    }

    public function label(): string
    {
        return 'Dealer Charges';
    }

    public function permission(): string
    {
        return 'PRC_DLRC';
    }

    public function icon(): string
    {
        return 'la-receipt';
    }

    public function group(): string
    {
        return 'DEALER_CHARGES';
    }

    public function model(): string
    {
        return DealerCharge::class;
    }

    public function service(): EntityService
    {
        return app(DealerChargeService::class);
    }

    public function description(): string
    {
        return 'Incidental, FASTag, TRC, RTO tape, COD and Kazam per segment / permit / model; the most specific row wins.';
    }

    public function formFields(): array
    {
        return ['segment', 'permit', 'model_code', 'incidental', 'fastag', 'trc', 'rto_tape', 'cod', 'kazam', 'wef_date'];
    }

    public function columns(): array
    {
        return [
            ['field' => 'segment', 'label' => 'Segment', 'pinned' => 'left', 'width' => 110],
            ['field' => 'permit', 'label' => 'Permit', 'width' => 110],
            ['field' => 'model_code', 'label' => 'Model', 'width' => 150],
            ['field' => 'incidental', 'label' => 'Incidental Charges', 'type' => 'number'],
            ['field' => 'fastag', 'label' => 'FASTag', 'type' => 'number'],
            ['field' => 'trc', 'label' => 'TRC', 'type' => 'number'],
            ['field' => 'rto_tape', 'label' => 'RTO Tape', 'type' => 'number'],
            ['field' => 'cod', 'label' => 'COD Charges', 'type' => 'number'],
            ['field' => 'kazam', 'label' => 'Kazam', 'type' => 'number'],
            ['field' => 'wef_date', 'label' => 'WEF', 'type' => 'date', 'width' => 120],
        ];
    }
}
