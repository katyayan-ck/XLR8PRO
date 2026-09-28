<?php

namespace Tests\Unit\Services\Vehicle\Pricing;

use App\Services\Vehicle\Pricing\Rules\RuleFormula;
use App\Services\Vehicle\Pricing\Rules\RuleRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** DEC-078: the range and formula text the RTO / insurance sheets use. */
class RuleRangeAndFormulaTest extends TestCase
{
    public static function ranges(): array
    {
        return [
            'band' => ['1001-1500', [1001 => true, 1500 => true, 1000 => false, 1501 => false]],
            'spaced band' => ['3001 - 16500', [3001 => true, 16501 => false]],
            'to' => ['1 to 7', [7 => true, 8 => false]],
            'units' => ['30 - 65 KW', [30 => true, 66 => false]],
            'greater' => ['>1500', [1500 => false, 1501 => true]],
            'above' => ['Above 2000000', [2000000 => false, 2000001 => true]],
            'less' => ['< 30KW', [29 => true, 30 => false]],
            'exact' => ['4', [4 => true, 3 => false]],
            'any' => ['ANY', [1 => true, 99999 => true]],
            'blank' => ['', [0 => true]],
        ];
    }

    /** @param array<int, bool> $cases */
    #[DataProvider('ranges')]
    public function test_ranges_parse_and_match(string $text, array $cases): void
    {
        $range = RuleRange::parse($text);
        foreach ($cases as $value => $expected) {
            $this->assertSame($expected, $range->contains($value), "{$text} contains {$value}");
        }
    }

    public function test_an_inverted_range_is_flagged_and_garbage_is_rejected(): void
    {
        $this->assertTrue(RuleRange::parse('1000000 - 200000')->isInverted());
        $this->expectException(\InvalidArgumentException::class);
        RuleRange::parse('lots');
    }

    public function test_formulas_from_the_sheets_evaluate(): void
    {
        $this->assertEqualsWithDelta(0.016667, RuleFormula::evaluate('(10% * 1.25 * 2) / 15'), 0.000001);
        $this->assertSame(1250.0, RuleFormula::evaluate('12.5% of Tax', ['tax' => 10000]));
        $this->assertSame(600.0, RuleFormula::evaluate('5% x OD', ['OD' => 12000]));
        $this->assertSame(1800.0, RuleFormula::evaluate('15% x (OD + LPG)', ['OD' => 11400, 'LPG' => 600]));
        $this->assertSame(6972.0, RuleFormula::evaluate('1162 x (Setat -1)', ['SEAT' => 7]));
        $this->assertSame(1050.0, RuleFormula::evaluate('150 Per Seat', ['SEAT' => 7]));
        $this->assertSame(6521.0, RuleFormula::evaluate('6521'));
        $this->assertSame(['SEAT'], RuleFormula::variables('75 x (Seat -1)'));
    }

    public function test_bad_formulas_are_rejected_with_a_reason(): void
    {
        foreach (['5% x Engine', '(1 + 2', '3 / 0', 'rm -rf', ''] as $bad) {
            try {
                RuleFormula::evaluate($bad, ['OD' => 1]);
                $this->fail("\"{$bad}\" should be rejected");
            } catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }
}
