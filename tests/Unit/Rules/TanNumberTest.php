<?php

namespace Tests\Unit\Rules;

use App\Rules\TanNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TanNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new TanNumber]])->fails();
    }

    public function test_accepts_a_valid_tan(): void
    {
        $this->assertFalse($this->fails('ABCD12345E'));
    }

    public function test_accepts_lowercase_input(): void
    {
        $this->assertFalse($this->fails('abcd12345e'));
    }

    public function test_rejects_wrong_shape(): void
    {
        $this->assertTrue($this->fails('ABC12345E'));
        $this->assertTrue($this->fails('ABCD1234E'));
    }
}
