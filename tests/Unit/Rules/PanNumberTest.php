<?php

namespace Tests\Unit\Rules;

use App\Rules\PanNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PanNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new PanNumber]])->fails();
    }

    public function test_accepts_a_valid_uppercase_pan(): void
    {
        $this->assertFalse($this->fails('ABCDE1234F'));
    }

    public function test_accepts_lowercase_input(): void
    {
        // Case-insensitive at the Rule level - IdentifierService::normalizePan()
        // is responsible for uppercasing before storage.
        $this->assertFalse($this->fails('abcde1234f'));
    }

    public function test_rejects_wrong_shape(): void
    {
        $this->assertTrue($this->fails('ABCD1234F'));
        $this->assertTrue($this->fails('ABCDE12345'));
        $this->assertTrue($this->fails('12345ABCDE'));
    }
}
