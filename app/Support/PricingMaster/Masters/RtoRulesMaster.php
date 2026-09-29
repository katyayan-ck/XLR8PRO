<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\RtoRule;
use App\Services\Vehicle\Pricing\Import\RtoWorkbookService;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\MasterDefinition;

/**
 * RTO rules (DEC-083): CRUD on RtoRuleService; import / export = the Pricing Process RTO workbook (the import replaces
 * every live RTO rule at the WEF).
 */
final class RtoRulesMaster extends MasterDefinition
{
    public function key(): string
    {
        return 'rto-rules';
    }

    public function label(): string
    {
        return 'RTO Rules';
    }

    public function permission(): string
    {
        return 'PRC_RTOR';
    }

    public function icon(): string
    {
        return 'la-id-card';
    }

    public function description(): string
    {
        return 'Tax, surcharge and fees by permit, wheels, registration type, body, GVW, seater, fuel, CC and assessable value; the most specific rule wins.';
    }

    public function model(): string
    {
        return RtoRule::class;
    }

    public function service(): EntityService
    {
        return app(RtoRuleService::class);
    }

    public function importReplacesAll(): bool
    {
        return true;
    }

    public function formFields(): array
    {
        return ['permit', 'wheels', 'reg_type', 'body_type', 'gvw_range', 'seater', 'fuel_type', 'cc_range', 'assessable_range',
            'tax_basis', 'tax_slab', 'tax_factor', 'surcharge', 'surcharge_formula', 'hypothecation', 'green_tax', 'registration_fee',
            'duplicate_tax_card', 'fitness', 'penalty', 'rto_tape', 'wef_date'];
    }

    public function columns(): array
    {
        return [
            ['field' => 'permit', 'label' => 'Permit', 'pinned' => 'left', 'width' => 110],
            ['field' => 'wheels', 'label' => 'Wheels', 'width' => 90],
            ['field' => 'reg_type', 'label' => 'Reg Type', 'width' => 100],
            ['field' => 'body_type', 'label' => 'Body Type', 'width' => 110],
            ['field' => 'gvw_range', 'label' => 'GVW', 'width' => 110],
            ['field' => 'seater', 'label' => 'Seater', 'width' => 100],
            ['field' => 'fuel_type', 'label' => 'Fuel', 'width' => 100],
            ['field' => 'cc_range', 'label' => 'CC', 'width' => 110],
            ['field' => 'assessable_range', 'label' => 'Assessable Value', 'width' => 150],
            ['field' => 'tax_basis', 'label' => 'Tax Basis', 'width' => 170],
            ['field' => 'tax_slab', 'label' => 'Tax Slab', 'width' => 110],
            ['field' => 'surcharge_formula', 'label' => 'Surcharge', 'width' => 140],
            ['field' => 'hypothecation', 'label' => 'Hypothecation', 'type' => 'number'],
            ['field' => 'green_tax', 'label' => 'Green Tax', 'type' => 'number'],
            ['field' => 'registration_fee', 'label' => 'Registration Fee', 'type' => 'number'],
            ['field' => 'duplicate_tax_card', 'label' => 'Tax Card', 'type' => 'number'],
            ['field' => 'fitness', 'label' => 'Fitness', 'type' => 'number'],
            ['field' => 'penalty', 'label' => 'Penalty', 'type' => 'number'],
            ['field' => 'rto_tape', 'label' => 'Outside State TRC', 'type' => 'number', 'width' => 140],
            ['field' => 'wef_date', 'label' => 'WEF', 'type' => 'date', 'width' => 120],
        ];
    }

    public function export(string $path): int
    {
        return app(RtoWorkbookService::class)->export($path);
    }

    public function import(string $path, ?string $wef = null, ?callable $progress = null): array
    {
        $result = app(RtoWorkbookService::class)->import($path, $wef ?? now()->toDateString());
        $result['issues'] = array_map(fn ($i) => ['row' => (int) $i['row'], 'reason' => (string) $i['reason']], array_slice($result['issues'], 0, 500));

        return $result;
    }

    public function sheetTitle(): string
    {
        return RtoWorkbookService::SHEET;
    }
}
