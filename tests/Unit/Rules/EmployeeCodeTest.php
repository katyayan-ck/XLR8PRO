<?php

namespace Tests\Unit\Rules;

use App\Rules\EmployeeCode;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmployeeCodeTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new EmployeeCode]])->fails();
    }

    public function test_accepts_the_generator_shape(): void
    {
        $this->assertFalse($this->fails('BMPL-0001'));
    }

    public function test_rejects_wrong_digit_count(): void
    {
        $this->assertTrue($this->fails('BMPL-001'));
        $this->assertTrue($this->fails('BMPL-00001'));
    }

    public function test_rejects_a_different_prefix(): void
    {
        $this->assertTrue($this->fails('EMP-0001'));
    }
}
