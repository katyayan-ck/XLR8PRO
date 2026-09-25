<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * OEM dealer invoice number. No external standard governs this - it is an
 * internal DMS/OEM-specific code. Kept identical to the shape already used
 * at its one existing validated call site before this consolidation.
 */
class DealerInvoiceNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Z]{3}\d{2}[A-Z]\d{6}$/', $value)) {
            $fail('The :attribute must be a valid dealer invoice number (e.g. ABC12K555555).');
        }
    }
}
