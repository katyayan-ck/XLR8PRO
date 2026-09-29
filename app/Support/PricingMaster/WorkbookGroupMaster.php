<?php

declare(strict_types=1);

namespace App\Support\PricingMaster;

use App\Services\Vehicle\Pricing\Import\AddonDiscountWorkbookService;

/**
 * A master whose import / export is one sheet of the Pricing Process workbook Addon-N-Discounts.xlsx (DEC-083), so the
 * same file works in both places. The import replaces the whole group at the WEF (expire + insert, as in the process).
 */
abstract class WorkbookGroupMaster extends MasterDefinition
{
    /** The AddonDiscountWorkbookService group (DEALER_CHARGES, RSA, SHIELD, EXCHANGE, CORPORATE, LOYALTY). */
    abstract public function group(): string;

    public function importReplacesAll(): bool
    {
        return true;
    }

    public function export(string $path): int
    {
        return (int) (app(AddonDiscountWorkbookService::class)->export($path, [$this->group()])[$this->group()] ?? 0);
    }

    public function import(string $path, ?string $wef = null, ?callable $progress = null): array
    {
        $result = app(AddonDiscountWorkbookService::class)->import($path, [$this->group()], $wef ?? now()->toDateString());
        $stats = $result['sheets'][$this->group()] ?? ['rows' => 0, 'written' => 0, 'rejected' => 0];

        return $stats + ['issues' => array_map(fn ($i) => ['row' => (int) $i['row'], 'reason' => (string) $i['reason']], array_slice($result['issues'], 0, 500))];
    }

    public function sheetTitle(): string
    {
        return AddonDiscountWorkbookService::SHEETS[$this->group()];
    }
}
