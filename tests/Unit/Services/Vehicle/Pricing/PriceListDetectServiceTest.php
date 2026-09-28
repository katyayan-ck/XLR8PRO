<?php

namespace Tests\Unit\Services\Vehicle\Pricing;

use App\Services\Vehicle\Pricing\Import\PriceListDetectService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Sheet titles → price-list codes (moved from the retired PriceListVehicleDetector; BUG-131 regression kept). */
class PriceListDetectServiceTest extends TestCase
{
    public static function titleProvider(): array
    {
        return [
            'Price List PV' => ['Price List PV', 'PRICE_LIST_PV'],
            'Price List CSD' => ['Price List CSD', 'PRICE_LIST_CSD'],
            'Price List LMM TZU (extra spaces, case)' => ['  price list  LMM   tzu ', 'PRICE_LIST_LMM_TZU'],
            // BUG-131: "CSD Index Codes" is a lookup sheet that contains "CSD" — never a price list
            'CSD Index Codes is not a price list' => ['CSD Index Codes', null],
            'the retired PV Vehicle sheet is not read' => ['PV Vehicle', null],
            'an unknown list' => ['Price List XYZ', null],
            'unrelated sheet' => ['Insurance Co.', null],
        ];
    }

    #[DataProvider('titleProvider')]
    public function test_sheet_code_maps_price_list_titles_only(string $title, ?string $expected): void
    {
        $this->assertSame($expected, PriceListDetectService::sheetCode($title));
    }

    public function test_match_sheets_reports_the_lists_a_workbook_lacks(): void
    {
        $match = PriceListDetectService::matchSheets(['Price List PV', 'CSD Index Codes', 'Price List CSD'], ['PV', 'csd', 'BEV']);

        $this->assertSame(['PV' => 'Price List PV', 'CSD' => 'Price List CSD'], $match['found']);
        $this->assertSame(['BEV'], $match['missing']);
    }
}
