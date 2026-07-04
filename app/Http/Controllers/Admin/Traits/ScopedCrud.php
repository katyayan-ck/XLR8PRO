<?php

namespace App\Http\Controllers\Admin\Traits;

use Illuminate\Support\Facades\Log;

trait ScopedCrud
{
    protected function setupListOperation()
    {
        parent::setupListOperation();
        $this->applyDataScope();
    }

    protected function applyDataScope()
    {
        $user = backpack_user();

        if ($user->isSuperAdmin()) {
            return;
        }

        $scopeType = $this->getScopeType();

        if (!$scopeType) {
            return;
        }

        $scopes = $user->userDataScopes()->byType($scopeType)->active()->get();

        if ($scopes->isEmpty()) {
            return; // all
        }

        $hasWildcard = $scopes->contains(function ($scope) {
            return $scope->isWildcard();
        });

        if ($hasWildcard) {
            return;
        }

        $accessibleIds = $scopes->pluck('scope_value')->filter()->unique()->toArray();

        if (empty($accessibleIds) && $this->hasHierarchy($scopeType)) {
            $this->applyHierarchyFilters($scopeType);
            return;
        }

        if (!empty($accessibleIds)) {
            $this->crud->addClause('whereIn', 'id', $accessibleIds);
        } else {
            $this->crud->addClause('whereRaw', '1=0'); // deny if no access
        }
    }

    abstract protected function getScopeType(): string;

    protected function hasHierarchy(string $scopeType): bool
    {
        return isset($this->hierarchies[$scopeType]);
    }

    protected function applyHierarchyFilters(string $scopeType)
    {
        $user = backpack_user();
        $hierarchies = $this->hierarchies[$scopeType] ?? [];
        $applied = false;

        foreach ($hierarchies as $hierarchy) {
            $parentType = $hierarchy['parent_type'];
            $foreignKey = $hierarchy['foreign_key'];
            $parentIds = $user->getScopedIds($parentType);

            if (!empty($parentIds)) {
                $this->crud->addClause('whereIn', $foreignKey, $parentIds);
                $applied = true;
            }
        }

        if (!$applied) {
            // No parent filters, allow all
        }
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
            ['parent_type' => 'brand', 'foreign_key' => 'brand_id'],
            ['parent_type' => 'segment', 'foreign_key' => 'segment_id'],
            ['parent_type' => 'sub_segment', 'foreign_key' => 'sub_segment_id'],
        ],
        'variant' => [
            ['parent_type' => 'vehicle_model', 'foreign_key' => 'vehicle_model_id'],
        ],
        // Add for color if needed, e.g., if colors per variant
        // 'color' => [],
        // 'division' => [],
    ];
}
