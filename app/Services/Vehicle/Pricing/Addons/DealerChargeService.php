<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Addons;

use App\Models\Vehicle\Pricing\DealerCharge;
use App\Services\Vehicle\Pricing\Rules\Concerns\ExpiresActiveRows;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Dealer charges (xlr8_vehicle_pricing_dealer_charges) — their only write path (DEC-050/057).
 *
 * Scope: ANY / blank = all — `segment` is NOT NULL, so "all" is stored as ANY (the engine treats
 * ANY, blank and null alike). Amounts are NOT NULL with default 0; a row whose charges are all
 * zero is refused (Machine Spec pitfall: never seed zero-value rows).
 *
 * @extends EntityService<DealerCharge>
 */
final class DealerChargeService extends EntityService
{
    use ExpiresActiveRows;

    private const AMOUNTS = ['incidental', 'fastag', 'trc', 'rto_tape', 'cod', 'kazam', 'amount'];

    protected function model(): string
    {
        return DealerCharge::class;
    }

    public function fields(): array
    {
        return [
            Field::make('import_session_id')->rules('integer'),
            Field::scope('segment', 20, 'Segment', anyIsBlank: true)->label('Segment')->default('ANY'),
            Field::scope('permit', 30, 'Permit', anyIsBlank: true)->label('Permit'),
            Field::scope('model_code', 40, anyIsBlank: true)->label('Model'),
            Field::text('charge_code', 40),
            Field::text('charge_name', 80),
            Field::number('amount')->label('Amount'),
            Field::number('incidental')->label('Incidental Charges')->default(0),
            Field::number('fastag')->label('FASTag')->default(0),
            Field::number('trc')->label('TRC')->default(0),
            Field::number('rto_tape')->label('RTO Tape')->default(0),
            Field::number('cod')->label('COD Charges')->default(0),
            Field::number('kazam')->label('Kazam')->default(0),
            Field::json('extra_json'),
            Field::date('wef_date')->label('WEF'),
            Field::date('expired_on')->label('Expired On')->rules('after_or_equal:wef_date'),
            Field::flag('is_active', true),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        $total = 0.0;
        foreach (self::AMOUNTS as $column) {
            $total += abs((float) ($data[$column] ?? 0));
        }
        if ($total == 0.0) {
            $this->fail('incidental', 'A dealer charge row needs at least one non-zero amount (zero rows override every scope).');
        }
    }
}
