<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Admin\Designation;
use App\Models\Admin\Employee;
use App\Services\IAM\RolePermissionService;
use App\Services\Org\Concerns\OrgEntityConcerns;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Designation (= Spatie role) — the only write path (DEC-050/052): field rules, immutable code,
 * reports-to rank rule, dependency-checked disable, media; permission sync.
 *
 * @extends EntityService<Designation>
 */
class DesignationService extends EntityService
{
    use OrgEntityConcerns;

    private const DEPENDENTS = [
        [Employee::class, 'designation_code', 'employee', 'employment_status', 'active'],
    ];

    public function __construct(private RolePermissionService $rolePermissions) {}

    protected function model(): string
    {
        return Designation::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 50)->label('Designation Code')->required()->unique()->immutable(),
            Field::name('name')->label('Designation Name')->required(),
            Field::text('description', 5000)->label('Description'),
            Field::make('rank')->label('Rank')->format('0 (none) or 1 (A, highest) … 5 (E, lowest)')->rules('integer', 'between:0,5')->default(0),
            Field::flag('is_top_mgmt', false)->label('Top Management'),
            Field::reference('parent_desig_code', 'xlr8_admin_designation', 50)->label('Reports To'),
            Field::flag('is_active')->label('Active'),
            ...$this->mediaFields('designation_image'),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        $data['guard_name'] = 'web';
        $this->assertReportsTo($data);
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $data['guard_name'] = 'web';
        $this->assertReportsTo(array_merge($model->only(['parent_desig_code', 'rank']), $data));
        $this->guardDisabling($model, $data, self::DEPENDENTS, 'designation');
    }

    protected function afterSave(Model $model, array $input, bool $created): void
    {
        $this->syncMedia($model, $input, 'designation_image');
    }

    public function syncPermissions(Designation $designation, array $permissionCodes): void
    {
        $this->rolePermissions->syncRolePermissions($designation, $permissionCodes);
    }

    public function currentPermissionCodes(Designation $designation): array
    {
        return $this->rolePermissions->currentPermissionCodes($designation);
    }

    /**
     * The designation reported to must be of the same or higher rank (1/A is highest, 5/E lowest).
     *
     * @param  array<string, mixed>  $data
     */
    private function assertReportsTo(array $data): void
    {
        $rank = (int) ($data['rank'] ?? 0);
        if (empty($data['parent_desig_code']) || $rank <= 0) {
            return;
        }
        $parent = Designation::where('code', $data['parent_desig_code'])->first();
        if ($parent && (int) $parent->rank > 0 && (int) $parent->rank > $rank) {
            $this->fail('parent_desig_code', 'The designation reported to must be of the same or higher rank (A is highest, E is lowest).');
        }
    }
}
