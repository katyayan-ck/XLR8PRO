<?php

namespace Tests\Unit\Services\Vehicle\Pricing;

use App\Services\Vehicle\Pricing\PriceListVehicleDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PriceListVehicleDetectorTest extends TestCase
{
    public static function titleProvider(): array
    {
        return [
            'Price List PV exact' => ['Price List PV', 'PRICE_LIST_PV'],
            'Price List CV exact' => ['Price List CV', 'PRICE_LIST_CV'],
            'Price List CSD exact' => ['Price List CSD', 'PRICE_LIST_CSD'],
            'bare CSD' => ['CSD', 'PRICE_LIST_CSD'],
            'bare LMM' => ['LMM', 'PRICE_LIST_LMM'],
            'LMM TZU' => ['LMM TZU', 'PRICE_LIST_LMM_TZU'],
            // Regression: "CSD Index Codes" is a lookup/index sheet, not a
            // price list, but contains "CSD" — the fallback substring match
            // used to false-positive on it (BUG-131).
            'CSD Index Codes is not a price list' => ['CSD Index Codes', null],
            'any Index-titled sheet is excluded' => ['LMM Index', null],
            'unrelated sheet' => ['Insurance Co.', null],
        ];
    }

    #[DataProvider('titleProvider')]
    public function test_sheet_code_from_title_maps_correctly(string $title, ?string $expected): void
    {
        $this->assertSame($expected, PriceListVehicleDetector::sheetCodeFromTitle($title));
    }
}
