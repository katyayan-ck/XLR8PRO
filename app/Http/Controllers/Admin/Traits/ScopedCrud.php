<?php

namespace App\Http\Controllers\Admin\Traits;

use App\Services\IAM\DataScopeService;

/**
 * Restricts a Backpack CRUD list to the rows the user's data scope allows.
 *
 * The using controller returns a DataScopeService::TYPE_MODELS key from
 * getScopeType() ('' disables scoping). Direct scope ids filter the CRUD's
 * own `id`; otherwise $hierarchies lets a parent scope (e.g. branch for a
 * location list) filter via a foreign key. No resolvable scope → no rows.
 *
 * Only runs through this trait's setupListOperation() — a controller that
 * overrides setupListOperation() must call $this->applyDataScope() itself.
 */
trait ScopedCrud
{
    protected function setupListOperation()
    {
        parent::setupListOperation();
        $this->applyDataScope();
    }

    protected function applyDataScope(): void
    {
        $scopeType = $this->getScopeType();

        if ($scopeType === '') {
            return;
        }

        $service = app(DataScopeService::class);
        $ids = $service->getAccessibleIds(backpack_user(), $scopeType);

        if ($ids === null) {
            return;
        }

        if ($ids !== []) {
            $this->crud->addClause('whereIn', 'id', $ids);

            return;
        }

        if ($this->hasHierarchy($scopeType) && $this->applyHierarchyFilters($scopeType, $service)) {
            return;
        }

        $this->crud->addClause('whereRaw', '1 = 0');
    }

    abstract protected function getScopeType(): string;

    protected function hasHierarchy(string $scopeType): bool
    {
        return isset($this->hierarchies[$scopeType]);
    }

    /**
     * @return bool whether any parent-scope filter was applied
     */
    protected function applyHierarchyFilters(string $scopeType, DataScopeService $service): bool
    {
        $user = backpack_user();
        $applied = false;

        foreach ($this->hierarchies[$scopeType] ?? [] as $hierarchy) {
            $parentIds = $service->getAccessibleIds($user, $hierarchy['parent_type']);

            if (! empty($parentIds)) {
                $this->crud->addClause('whereIn', $hierarchy['foreign_key'], $parentIds);
                $applied = true;
            }
        }

        return $applied;
    }

    protected $hierarchies = [
        'location' => [
            ['parent_type' => 'branch', 'foreign_key' => 'branch_id'],
        ],
        'department' => [
            ['parent_type' => 'vertical', 'foreign_key' => 'vertical_id'],
        ],
        'sub_segment' => [
            ['parent_type' => 'segment', 'foreign_key' => 'segment_id'],
        ],
        'vehicle_model' => [
            ['parent_type' => 'segment', 'foreign_key' => 'segment_id'],
            ['parent_type' => 'sub_segment', 'foreign_key' => 'sub_segment_id'],
        ],
    ];
}
