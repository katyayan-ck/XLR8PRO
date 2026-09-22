<?php

namespace Tests\Unit\Rules;

use App\Rules\ChassisNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ChassisNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        return Validator::make(['v' => $value], ['v' => [new ChassisNumber]])->fails();
    }

    public function test_accepts_real_s_prefixed_stock_shape(): void
    {
        $this->assertFalse($this->fails('S1A17817'));
    }

    public function test_accepts_real_r_prefixed_stock_shape(): void
    {
        // Confirmed against live X_Vh_Stock data during the audit - a
        // previous regex here only accepted 'S', which would have rejected
        // 16 of 895 real chassis records.
        $this->assertFalse($this->fails('R6A10154'));
    }

    public function test_rejects_a_prefix_other_than_s_or_r(): void
    {
        $this->assertTrue($this->fails('T1A17817'));
    }

    public function test_rejects_too_short(): void
    {
        $this->assertTrue($this->fails('S123'));
    }
}
