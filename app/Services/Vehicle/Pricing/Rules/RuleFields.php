<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Support\Entity\Field;

/** Field definitions shared by several pricing rule services (DEC-056). */
final class RuleFields
{
    /**
     * `wheels` is a tinyint scope: ANY / ALL / * / blank mean "all vehicles" and are stored as
     * null (the Machine Spec scope rule); otherwise a whole number of wheels.
     */
    public static function wheels(): Field
    {
        return Field::make('wheels')->label('Wheels')->format('Whole number, or ANY / blank = all')
            ->transform(fn (string $v) => in_array(strtoupper(trim($v)), ['ANY', 'ALL', '*'], true) ? '' : $v)
            ->rules('integer', 'between:2,255');
    }
}
