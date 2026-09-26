<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Admin\Employee;
use App\Models\Admin\Vertical;
use App\Services\Org\Concerns\OrgEntityConcerns;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Vertical — the only write path (DEC-050/052).
 *
 * @extends EntityService<Vertical>
 */
class VerticalService extends EntityService
{
    use OrgEntityConcerns;

    private const DEPENDENTS = [
        [Employee::class, 'vertical_code', 'employee', 'employment_status', 'active'],
    ];

    protected function model(): string
    {
        return Vertical::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 10)->label('Vertical Code')->rules('min:2')->required()->unique()->immutable(),
            Field::name('name')->label('Vertical Name')->required(),
            Field::text('description', 5000)->label('Description'),
            Field::flag('is_active')->label('Active'),
            ...$this->mediaFields('vertical_image'),
        ];
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->guardDisabling($model, $data, self::DEPENDENTS, 'vertical');
    }

    protected function afterSave(Model $model, array $input, bool $created): void
    {
        $this->syncMedia($model, $input, 'vertical_image');
    }
}
