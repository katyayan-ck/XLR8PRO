<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Indian mobile number, per the TRAI numbering plan: exactly 10 digits,
 * first digit 6-9. Expects the number already stripped of any +91/91/0
 * prefix - normalize separately via IdentifierService::cleanMobile()
 * before validating/storing.
 */
class IndianMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[6-9]\d{9}$/', $value)) {
            $fail('The :attribute must be a valid 10-digit Indian mobile number.');
        }
    }
}
