<?php

namespace Tests\Unit\Rules;

use App\Rules\AadhaarNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AadhaarNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new AadhaarNumber]])->fails();
    }

    public function test_accepts_a_plain_12_digit_number_starting_2_to_9(): void
    {
        $this->assertFalse($this->fails('234567890123'));
    }

    public function test_accepts_space_separated_groups(): void
    {
        $this->assertFalse($this->fails('2345 6789 0123'));
    }

    public function test_accepts_dash_separated_groups(): void
    {
        $this->assertFalse($this->fails('2345-6789-0123'));
    }

    public function test_rejects_a_number_starting_with_0_or_1(): void
    {
        // UIDAI never issues an Aadhaar starting with 0 or 1 - this was a
        // real gap in the previous PersonService-only detection regex.
        $this->assertTrue($this->fails('034567890123'));
        $this->assertTrue($this->fails('134567890123'));
    }

    public function test_rejects_wrong_length(): void
    {
        $this->assertTrue($this->fails('23456789012'));
        $this->assertTrue($this->fails('2345678901234'));
    }

    public function test_rejects_non_numeric(): void
    {
        $this->assertTrue($this->fails('23456789ABCD'));
    }
}
