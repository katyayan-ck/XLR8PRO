<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\AccessoryScope;
use App\Services\Vehicle\Accessories\AccessoryScopeService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\MasterDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Where each accessory applies: part no × segment / model / variant / permit, blank = all (DEC-083).
 *
 * @extends MasterDefinition<AccessoryScope>
 */
final class AccessoryScopesMaster extends MasterDefinition
{
    public function key(): string
    {
        return 'accessory-scopes';
    }

    public function label(): string
    {
        return 'Accessory Scopes';
    }

    public function permission(): string
    {
        return 'PRC_ACCS';
    }

    public function icon(): string
    {
        return 'la-sitemap';
    }

    public function recalculates(): bool
    {
        return false;
    }

    public function model(): string
    {
        return AccessoryScope::class;
    }

    public function service(): EntityService
    {
        return app(AccessoryScopeService::class);
    }

    public function formFields(): array
    {
        return ['part_no', 'segment_code', 'model_code', 'variant_code', 'permit', 'status'];
    }

    /** @return Builder<AccessoryScope> */
    public function query(bool $history = false): Builder
    {
        return AccessoryScope::query()->when(! $history, fn ($q) => $q->where('status', 1))->orderBy('part_no');
    }

    public function columns(): array
    {
        return [
            ['field' => 'part_no', 'label' => 'Part No.', 'pinned' => 'left', 'width' => 140],
            ['field' => 'segment_code', 'label' => 'Segment', 'width' => 110],
            ['field' => 'model_code', 'label' => 'Model', 'width' => 150],
            ['field' => 'variant_code', 'label' => 'Variant', 'width' => 190],
            ['field' => 'permit', 'label' => 'Permit', 'width' => 110],
            ['field' => 'status', 'label' => 'Status', 'width' => 100],
        ];
    }

    public function remove(Model $model): void
    {
        $this->service()->update($model, ['status' => '0']);
    }
}
