<?php

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Admin\Employee;
use App\Models\Admin\Location;
use App\Services\Org\Concerns\OrgEntityConcerns;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Location — the only write path (DEC-050/052).
 *
 * @extends EntityService<Location>
 */
class LocationService extends EntityService
{
    use OrgEntityConcerns;

    private const DEPENDENTS = [
        [Employee::class, 'primary_loc_code', 'employee', 'employment_status', 'active'],
    ];

    protected function model(): string
    {
        return Location::class;
    }

    public function fields(): array
    {
        return [
            Field::reference('branch_code', 'xlr8_admin_branch', 10)->label('Branch')->required(),
            Field::code('code', 100)->label('Location Code')->required()->unique()->immutable(),
            Field::name('name')->label('Location Name')->required(),
            Field::text('description', 5000)->label('Description'),
            Field::phone()->label('Phone'),
            Field::email()->label('Email'),
            Field::text('address', 5000)->label('Address'),
            Field::name('city', 100)->label('City'),
            Field::name('state', 100)->label('State'),
            Field::pincode()->label('Pincode'),
            Field::coordinate('latitude', 90)->label('Latitude'),
            Field::coordinate('longitude', 180)->label('Longitude'),
            Field::flag('is_active')->label('Active'),
            Field::flag('is_sales_location', false)->label('Sales Location'),
            Field::flag('is_workshop', false)->label('Workshop'),
            Field::flag('is_parts_location', false)->label('Parts Location'),
            Field::flag('is_stock_location', false)->label('Stock Location'),
            Field::flag('is_office_only', false)->label('Office Only'),
            Field::flag('is_mwh', false)->label('Mother Warehouse'),
            Field::flag('is_lmmws', false)->label('LMM Workshop'),
            ...$this->mediaFields('location_image'),
        ];
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->guardDisabling($model, $data, self::DEPENDENTS, 'location');
    }

    protected function afterSave(Model $model, array $input, bool $created): void
    {
        $this->syncMedia($model, $input, 'location_image');
    }
}
