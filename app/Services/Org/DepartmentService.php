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
 * Department — the only write path (DEC-050/052). A new department gets a same-coded default
 * division, created through DivisionService.
 *
 * @extends EntityService<Department>
 */
class DepartmentService extends EntityService
{
    use OrgEntityConcerns;

    private const DEPENDENTS = [
        [Division::class, 'dept_code', 'division'],
        [Employee::class, 'primary_dept_code', 'employee', 'employment_status', 'active'],
    ];

    protected function model(): string
    {
        return Department::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 10)->label('Department Code')->rules('min:2')->required()->unique()->immutable(),
            Field::name('name')->label('Department Name')->required(),
            Field::text('description', 5000)->label('Description'),
            Field::flag('is_active')->label('Active'),
            ...$this->mediaFields('department_image'),
        ];
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->guardDisabling($model, $data, self::DEPENDENTS, 'department');
    }

    protected function afterSave(Model $model, array $input, bool $created): void
    {
        $this->syncMedia($model, $input, 'department_image');

        if ($created && ! Division::withTrashed()->where('code', $model->code)->exists()) {
            app(DivisionService::class)->create([
                'dept_code' => $model->code,
                'code' => $model->code,
                'name' => $model->name,
                'is_active' => true,
            ]);
        }
    }
}
