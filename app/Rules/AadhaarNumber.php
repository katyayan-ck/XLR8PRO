<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aadhaar number, per UIDAI numbering rules: 12 digits, first digit 2-9
 * (UIDAI never issues a number starting with 0 or 1). Spaces or dashes
 * between the 4-4-4 digit groups are accepted at input time; normalize
 * separately via IdentifierService::normalizeAadhaar() before storing.
 */
class AadhaarNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[2-9]\d{3}[ -]?\d{4}[ -]?\d{4}$/', $value)) {
            $fail('The :attribute must be a valid 12-digit Aadhaar number.');
        }
    }
}
