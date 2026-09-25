<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Booking invoice number. No external standard governs this - it is an
 * internal DMS/OEM-specific code. Kept identical to the shape already
 * used identically at its two existing validated call sites before this
 * consolidation.
 */
class InvoiceNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^INV\d{2}[A-Z]\d{6}$/', $value)) {
            $fail('The :attribute must be a valid invoice number (e.g. INV00A123456).');
        }
    }
}
