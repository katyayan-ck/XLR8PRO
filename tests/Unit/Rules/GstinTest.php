<?php

namespace Tests\Unit\Rules;

use App\Rules\Gstin;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GstinTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new Gstin]])->fails();
    }

    public function test_accepts_a_structurally_valid_gstin(): void
    {
        $this->assertFalse($this->fails('27ABCDE1234F1Z5'));
    }

    public function test_rejects_wrong_length(): void
    {
        $this->assertTrue($this->fails('27ABCDE1234F1Z'));
    }

    public function test_rejects_missing_literal_z(): void
    {
        $this->assertTrue($this->fails('27ABCDE1234F1A5'));
    }

    public function test_rejects_lowercase(): void
    {
        // Rule expects uppercase - IdentifierService::normalizeGstin() uppercases
        // before validation.
        $this->assertTrue($this->fails('27abcde1234f1z5'));
    }
}
