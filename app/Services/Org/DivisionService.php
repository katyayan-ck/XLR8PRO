<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use App\Services\Org\Concerns\OrgEntityConcerns;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Division — the only write path (DEC-050/052). An active division needs an active department.
 *
 * @extends EntityService<Division>
 */
class DivisionService extends EntityService
{
    use OrgEntityConcerns;

    private const DEPENDENTS = [
        [Employee::class, 'primary_div_code', 'employee', 'employment_status', 'active'],
    ];

    protected function model(): string
    {
        return Division::class;
    }

    public function fields(): array
    {
        return [
            Field::reference('dept_code', 'xlr8_admin_department', 10)->label('Department')->required(),
            Field::code('code', 10)->label('Division Code')->rules('min:2')->required()->unique()->immutable(),
            Field::name('name')->label('Division Name')->required(),
            Field::text('description', 5000)->label('Description'),
            Field::flag('is_active')->label('Active'),
            ...$this->mediaFields('division_image'),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        $this->assertDepartmentActive($data);
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->assertDepartmentActive(array_merge($model->only(['dept_code', 'is_active']), $data));
        $this->guardDisabling($model, $data, self::DEPENDENTS, 'division');
    }

    protected function afterSave(Model $model, array $input, bool $created): void
    {
        $this->syncMedia($model, $input, 'division_image');
    }

    /** @param array<string, mixed> $data */
    private function assertDepartmentActive(array $data): void
    {
        if (empty($data['dept_code']) || ! ($data['is_active'] ?? true)) {
            return;
        }
        $department = Department::where('code', $data['dept_code'])->first();
        if ($department && ! $department->is_active) {
            $this->fail('is_active', 'Division cannot be activated because its Department is inactive.');
        }
    }
}
