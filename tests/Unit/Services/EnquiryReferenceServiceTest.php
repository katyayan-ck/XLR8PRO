<?php

namespace Tests\Unit\Services;

use App\Services\EnquiryReferenceService;
use Tests\TestCase;

class EnquiryReferenceServiceTest extends TestCase
{
    private EnquiryReferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnquiryReferenceService::class);
    }

    public function test_to_reference_builds_the_xenq_format(): void
    {
        $this->assertSame('XENQ-123', $this->service->toReference(123));
    }

    public function test_from_reference_parses_uppercase_prefix(): void
    {
        $this->assertSame(123, $this->service->fromReference('XENQ-123'));
    }

    public function test_from_reference_parses_lowercase_prefix(): void
    {
        $this->assertSame(123, $this->service->fromReference('xenq-123'));
    }

    public function test_from_reference_parses_a_bare_numeric_string(): void
    {
        $this->assertSame(123, $this->service->fromReference('123'));
    }

    public function test_from_reference_returns_null_for_garbage(): void
    {
        $this->assertNull($this->service->fromReference('not-a-reference'));
        $this->assertNull($this->service->fromReference(null));
        $this->assertNull($this->service->fromReference(''));
    }
}
