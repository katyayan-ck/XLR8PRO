<?php

namespace Tests\Unit\Rules;

use App\Rules\IndianMobileNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IndianMobileNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new IndianMobileNumber]])->fails();
    }

    public function test_accepts_a_valid_10_digit_number_starting_6_to_9(): void
    {
        $this->assertFalse($this->fails('9876543210'));
        $this->assertFalse($this->fails('6123456789'));
    }

    public function test_rejects_a_number_starting_0_to_5(): void
    {
        // TRAI numbering plan: mobile numbers always start 6-9.
        $this->assertTrue($this->fails('5876543210'));
        $this->assertTrue($this->fails('0876543210'));
    }

    public function test_rejects_wrong_length(): void
    {
        $this->assertTrue($this->fails('987654321'));
        $this->assertTrue($this->fails('98765432101'));
    }

    public function test_rejects_a_number_with_a_country_code_prefix_still_attached(): void
    {
        // This Rule expects pre-cleaned input - IdentifierService::cleanMobile()
        // is responsible for stripping +91/91/leading 0 before validation.
        $this->assertTrue($this->fails('919876543210'));
    }
}
