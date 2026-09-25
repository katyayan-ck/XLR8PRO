<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * DMS OTF (Order To Factory) reference number. No external standard
 * governs this - it is an internal DMS/OEM-specific code. Kept identical
 * to the shape already used identically in both of its two existing
 * validated call sites before this consolidation.
 */
class OtfNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^OTF\d{2}[A-Z]\d{6}$/', $value)) {
            $fail('The :attribute must be a valid OTF number (e.g. OTF00A123456).');
        }
    }
}
