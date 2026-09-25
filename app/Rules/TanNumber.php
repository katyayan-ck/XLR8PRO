<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * TAN (Tax Deduction and Collection Account Number), per the Income Tax
 * Department (CBDT) structure: 4 letters, 5 digits, 1 letter. Case-
 * insensitive at input time; normalize to uppercase before storing.
 */
class TanNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Za-z]{4}[0-9]{5}[A-Za-z]$/', $value)) {
            $fail('The :attribute must be a valid TAN number (e.g. ABCD12345E).');
        }
    }
}
