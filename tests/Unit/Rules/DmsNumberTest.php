<?php

namespace Tests\Unit\Rules;

use App\Rules\DmsNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DmsNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new DmsNumber]])->fails();
    }

    public function test_accepts_the_canonical_shape(): void
    {
        $this->assertFalse($this->fails('B-12345678'));
    }

    public function test_rejects_wrong_digit_count(): void
    {
        $this->assertTrue($this->fails('B-1234567'));
    }
}
