<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * DMS booking number. No external standard governs this - it is an
 * internal DMS/OEM-specific code. Kept identical to the shape already
 * used identically in both of its two existing validated call sites
 * before this consolidation.
 */
class DmsNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^B-\d{8}$/', $value)) {
            $fail('The :attribute must be a valid DMS number (e.g. B-12345678).');
        }
    }
}
