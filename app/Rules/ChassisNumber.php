<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Vehicle stock chassis/allocation code. No external standards body
 * governs this (it is an internal OEM/dealer stock code, not a full
 * 17-character VIN) - the pattern below is derived from real data in
 * X_Vh_Stock, which uses both 'S' and 'R' prefixes. Expects uppercase
 * input - normalize separately via IdentifierService::normalizeChassis()
 * before validating/storing.
 */
class ChassisNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[SR][A-Z0-9]{4,10}$/', $value)) {
            $fail('The :attribute must be a valid chassis number (starting with S or R).');
        }
    }
}
