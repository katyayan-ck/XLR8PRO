<?php

namespace Tests\Unit\Rules;

use App\Rules\OtfNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class OtfNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new OtfNumber]])->fails();
    }

    public function test_accepts_the_canonical_shape(): void
    {
        $this->assertFalse($this->fails('OTF00A123456'));
    }

    public function test_rejects_wrong_shape(): void
    {
        $this->assertTrue($this->fails('OTF0A123456'));
        $this->assertTrue($this->fails('OT00A123456'));
    }
}
