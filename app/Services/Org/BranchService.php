<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Admin\Branch;
use App\Models\Admin\Employee;
use App\Models\Admin\Location;
use App\Services\Org\Concerns\OrgEntityConcerns;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Branch — the only write path (DEC-050/052): field rules, immutable code, dependency-checked
 * disable, single head office, media.
 *
 * @extends EntityService<Branch>
 */
class BranchService extends EntityService
{
    use OrgEntityConcerns;

    /** Branch is the parent of Location and (via primary_branch_code) of Employee; both key on `code` (BUG-082). */
    private const DEPENDENTS = [
        [Location::class, 'branch_code', 'location'],
        [Employee::class, 'primary_branch_code', 'employee', 'employment_status', 'active'],
    ];

    protected function model(): string
    {
        return Branch::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 10)->label('Branch Code')->rules('min:3')->required()->unique()->immutable(),
            Field::name('name')->label('Branch Name')->required(),
            Field::text('description', 5000)->label('Description'),
            Field::phone()->label('Phone'),
            Field::email()->label('Email'),
            Field::text('address', 5000)->label('Address'),
            Field::name('city', 100)->label('City'),
            Field::name('state', 100)->label('State'),
            Field::pincode()->label('Pincode'),
            Field::coordinate('latitude', 90)->label('Latitude'),
            Field::coordinate('longitude', 180)->label('Longitude'),
            Field::flag('is_head_office', false)->label('Head Office'),
            Field::flag('is_active')->label('Active'),
            ...$this->mediaFields('branch_image'),
        ];
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->guardDisabling($model, $data, self::DEPENDENTS, 'branch');
    }

    protected function afterSave(Model $model, array $input, bool $created): void
    {
        if ($model->is_head_office) {
            Branch::where('id', '!=', $model->id)->where('is_head_office', true)->update(['is_head_office' => false]);
        }

        $this->syncMedia($model, $input, 'branch_image');

        // DEC-089: every branch has a location with the same code and name (skipped if that code is already taken)
        if ($created && ! Location::withTrashed()->where('code', $model->code)->exists()) {
            app(LocationService::class)->create([
                'branch_code' => $model->code,
                'code' => $model->code,
                'name' => $model->name,
                'city' => $model->city,
                'state' => $model->state,
                'is_active' => true,
            ]);
        }
    }
}
