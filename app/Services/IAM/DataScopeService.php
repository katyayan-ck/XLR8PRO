<?php

namespace App\Services\IAM;

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Location;
use App\Models\Admin\Vertical;
use App\Models\User;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a user's data scope into the primary-key ids a scoped column
 * stores. Built on the live scope table via User::getScopeCodes()
 * (App\Models\Admin\UserScope, xlr8_admin_user_scopes.scope_code) — scope
 * rows hold codes, scoped business columns hold ids, so this translates.
 *
 * Return contract (shared by DataScopeFilter, ScopedCrud, RBACService):
 *   null  → unrestricted (SuperAdmin or bypass_data_scoping)
 *   []    → no access (fail closed: no scope rows of that type, or an
 *           unknown scope type)
 *   int[] → the ids the user may see
 */
class DataScopeService
{
    /** @var array<string, class-string<Model>> */
    public const TYPE_MODELS = [
        'branch' => Branch::class,
        'location' => Location::class,
        'department' => Department::class,
        'division' => Division::class,
        'vertical' => Vertical::class,
        'segment' => Segment::class,
        'sub_segment' => SubSegment::class,
    ];

    /** @return array<int, int>|null */
    public function getAccessibleIds(User $user, string $scopeType): ?array
    {
        if ($user->isSuperAdmin() || $user->bypassesDataScoping()) {
            return null;
        }

        $modelClass = self::TYPE_MODELS[strtolower(trim($scopeType))] ?? null;
        if ($modelClass === null) {
            return [];
        }

        $codes = $user->getScopeCodes($scopeType);
        if ($codes === []) {
            return [];
        }

        return $modelClass::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<int, int>|null */
    public function getOrgScope(User $user, string $scopeType): ?array
    {
        return $this->getAccessibleIds($user, $scopeType);
    }

    /** @return array<int, int>|null */
    public function getVehicleScope(User $user, string $scopeType): ?array
    {
        return $this->getAccessibleIds($user, $scopeType);
    }
}
