<?php

namespace App\Services;

/**
 * SSOT for the 'XENQ-{id}' enquiry reference format used to cross-reference
 * an Enquiry from Booking/Receipt/JournalVoucher records that only store a
 * free-text reference string rather than a real foreign key. This is a
 * purely internal display/lookup convention - no external standards body
 * governs it.
 *
 * Previously reimplemented independently (build and/or parse) in
 * EnquiryCrudController, OrgService, ReceiptCrudController,
 * JournalVoucherCrudController and a raw-SQL CONCAT() in Enquiry.php - see
 * docs/refactor/ai-findings-22-09-2026.md.
 *
 * Registered as a singleton in AppServiceProvider. Inject via constructor
 * property promotion, e.g. public function __construct(private
 * EnquiryReferenceService $enquiryRef) {}
 */
class EnquiryReferenceService
{
    private const PREFIX = 'XENQ-';

    public function toReference(int $enquiryId): string
    {
        return self::PREFIX.$enquiryId;
    }

    /**
     * Parses 'XENQ-123', 'xenq-123', or a bare numeric string back to the
     * integer enquiry id. Returns null for anything that isn't one of
     * those shapes.
     */
    public function fromReference(string|int|null $reference): ?int
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        $ref = trim((string) $reference);

        if (preg_match('/^'.self::PREFIX.'(\d+)$/i', $ref, $matches)) {
            return (int) $matches[1];
        }

        return ctype_digit($ref) ? (int) $ref : null;
    }
}
