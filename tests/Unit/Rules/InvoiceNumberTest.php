<?php

namespace Tests\Unit\Rules;

use App\Rules\InvoiceNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InvoiceNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new InvoiceNumber]])->fails();
    }

    public function test_accepts_the_canonical_shape(): void
    {
        $this->assertFalse($this->fails('INV00A123456'));
    }

    public function test_rejects_wrong_shape(): void
    {
        $this->assertTrue($this->fails('INV0A123456'));
    }
}
