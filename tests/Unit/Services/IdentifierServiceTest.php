<?php

namespace Tests\Unit\Services;

use App\Services\IdentifierService;
use Tests\TestCase;

class IdentifierServiceTest extends TestCase
{
    private IdentifierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IdentifierService::class);
    }

    public function test_clean_mobile_strips_a_real_91_country_code_prefix(): void
    {
        $this->assertSame('9876543210', $this->service->cleanMobile('919876543210'));
        $this->assertSame('9876543210', $this->service->cleanMobile('+91 98765 43210'));
    }

    public function test_clean_mobile_does_not_mangle_a_real_10_digit_number_starting_with_9_or_1(): void
    {
        // This is the exact bug found live in EmployeeSheetImport/UsersImportSheet:
        // ltrim($v, '91') treats '91' as a character mask, not a prefix, so it
        // strips leading 9s/1s from ANY number, not just a real country-code
        // prefix. 9198765432 is a genuine 10-digit mobile number (starts '91'
        // but is NOT 12 digits, so it must be returned unchanged).
        $this->assertSame('9198765432', $this->service->cleanMobile('9198765432'));
    }

    public function test_clean_mobile_strips_a_single_leading_zero(): void
    {
        $this->assertSame('9876543210', $this->service->cleanMobile('09876543210'));
    }

    public function test_clean_mobile_returns_null_for_unrecoverable_input(): void
    {
        $this->assertNull($this->service->cleanMobile('12345'));
        $this->assertNull($this->service->cleanMobile(null));
        $this->assertNull($this->service->cleanMobile(''));
    }

    public function test_normalize_pan_trims_and_uppercases(): void
    {
        $this->assertSame('ABCDE1234F', $this->service->normalizePan(' abcde1234f '));
    }

    public function test_normalize_aadhaar_strips_separators(): void
    {
        $this->assertSame('234567890123', $this->service->normalizeAadhaar('2345 6789-0123'));
    }

    public function test_normalize_gstin_trims_and_uppercases(): void
    {
        $this->assertSame('27ABCDE1234F1Z5', $this->service->normalizeGstin(' 27abcde1234f1z5 '));
    }

    public function test_normalize_chassis_trims_and_uppercases(): void
    {
        $this->assertSame('S1A17817', $this->service->normalizeChassis(' s1a17817 '));
    }
}
