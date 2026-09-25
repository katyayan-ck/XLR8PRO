<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * PAN (Permanent Account Number), per the Income Tax Department (CBDT)
 * structure: 5 letters, 4 digits, 1 letter. Case-insensitive at input
 * time; normalize to uppercase separately via
 * IdentifierService::normalizePan() before storing.
 */
class PanNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/', $value)) {
            $fail('The :attribute must be a valid PAN number (e.g. ABCDE1234F).');
        }
    }
}
