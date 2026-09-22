<?php

namespace App\Services;

/**
 * SSOT for normalizing the business identifiers catalogued in
 * docs/refactor/ai-findings-22-09-2026.md ("Identifier Validation/Generation
 * Duplication" investigation). Format VALIDATION lives in App\Rules\*
 * (one ValidationRule class per identifier); this service only normalizes
 * raw input into the canonical storage shape - it never rejects input,
 * callers combine a normalize*() call with the matching Rule class.
 *
 * Registered as a singleton in AppServiceProvider. Inject via constructor
 * property promotion, e.g. public function __construct(private
 * IdentifierService $identifiers) {}
 */
class IdentifierService
{
    /**
     * Strips an Indian mobile number down to its bare 10 digits.
     *
     * This is the single correct implementation - it previously existed
     * independently (and divergently) in PersonService, StandaloneUsersImport,
     * EmployeeRowDTO, EmployeeSheetImport and UsersImportSheet. The last two
     * used ltrim($v, '91')/ltrim($v, '+91'), which treats the second argument
     * as a character mask, not a prefix - it strips any leading run of the
     * characters '9'/'1'/'+' regardless of whether they represent a real
     * country-code prefix, silently mangling real 10-digit numbers that
     * happen to start with 9 or 1 (e.g. 9198765432). Ported from
     * PersonService::cleanPhone(), which already carried this exact fix.
     */
    public function cleanMobile(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? $digits : null;
    }

    /**
     * Trims and uppercases a PAN for storage/comparison. Does not validate
     * shape - pair with App\Rules\PanNumber.
     */
    public function normalizePan(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        return $raw === '' ? null : strtoupper($raw);
    }

    /**
     * Trims and uppercases a TAN for storage/comparison. Does not validate
     * shape - pair with App\Rules\TanNumber.
     */
    public function normalizeTan(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        return $raw === '' ? null : strtoupper($raw);
    }

    /**
     * Strips spaces/dashes from an Aadhaar number, leaving 12 plain digits
     * for storage. Does not validate shape - pair with App\Rules\AadhaarNumber.
     */
    public function normalizeAadhaar(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        return $digits === '' ? null : $digits;
    }

    /**
     * Trims and uppercases a GSTIN for storage/comparison. Does not
     * validate shape - pair with App\Rules\Gstin.
     */
    public function normalizeGstin(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        return $raw === '' ? null : strtoupper($raw);
    }

    /**
     * Trims and uppercases a chassis/stock code for storage/comparison.
     * Does not validate shape - pair with App\Rules\ChassisNumber.
     */
    public function normalizeChassis(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        return $raw === '' ? null : strtoupper($raw);
    }
}
