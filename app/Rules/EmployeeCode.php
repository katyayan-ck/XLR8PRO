<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Employee code. No external standard governs this - it is purely an
 * internal, company-specific format. Kept identical to the shape the
 * only existing generator (UserCrudController::generateEmployeeCode())
 * produces, and confirmed to match all 200 real employee codes currently
 * in the database.
 */
class EmployeeCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^BMPL-\d{4}$/', $value)) {
            $fail('The :attribute must be a valid employee code (e.g. BMPL-0001).');
        }
    }
}
