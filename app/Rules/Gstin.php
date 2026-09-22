<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * GSTIN (GST Identification Number), per the CBIC/GST law structure:
 * 2-digit state code + 10-char PAN + 1-digit entity code + literal 'Z'
 * + 1 checksum character. Structural validation only - the checksum
 * character's position is validated, not its computed value. Expects
 * uppercase input - normalize separately via
 * IdentifierService::normalizeGstin() before validating/storing.
 */
class Gstin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $value)) {
            $fail('The :attribute must be a valid 15-character GSTIN.');
        }
    }
}
