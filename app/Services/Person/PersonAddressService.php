<?php

declare(strict_types=1);

namespace App\Services\Person;

use App\Models\Admin\PersonAddress;
use App\Services\Person\Concerns\TypedSlots;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Person addresses (xlr8_admin_person_addresses) — their only write path (DEC-050/053).
 * One row per (person, address type); see TypedSlots for the Primary rules.
 *
 * @extends EntityService<PersonAddress>
 */
final class PersonAddressService extends EntityService
{
    use TypedSlots;

    protected function model(): string
    {
        return PersonAddress::class;
    }

    protected function naturalKey(): array
    {
        return ['person_code', 'address_type'];
    }

    public function fields(): array
    {
        return [
            Field::reference('person_code', 'xlr8_admin_person', 20, 'person_code')->label(__('org.fields.person_code'))->required()->immutable(),
            Field::choice('address_type', PersonAddress::ADDRESS_TYPES)->label(__('org.fields.type'))
                ->format('One of: '.implode(', ', PersonAddress::ADDRESS_TYPES).'; blank = Primary if free, else the next free type')
                ->unique(['person_code'], includeTrashed: true),
            Field::text('address_line_1', 150)->label(__('org.fields.address_line_1'))->transform('strip_tags', 'trim_spaces'),
            Field::text('address_line_2', 150)->label(__('org.fields.address_line_2'))->transform('strip_tags', 'trim_spaces'),
            Field::text('landmark', 80)->transform('strip_tags', 'trim_spaces'),
            Field::name('city', 60)->label(__('org.fields.city')),
            Field::name('taluka', 60),
            Field::name('district', 60),
            Field::name('state', 60)->label(__('org.fields.state')),
            Field::name('country', 60)->label(__('org.fields.country'))->default('India'),
            Field::pincode()->label(__('org.fields.pincode')),
            Field::coordinate('latitude', 90)->label(__('org.fields.latitude')),
            Field::coordinate('longitude', 180)->label(__('org.fields.longitude')),
            Field::flag('is_primary', false)->virtual(),
        ];
    }

    protected function slotField(): string
    {
        return 'address_type';
    }

    protected function slotGroup(): array
    {
        return ['person_code'];
    }

    protected function slotTypes(): array
    {
        return PersonAddress::ADDRESS_TYPES;
    }

    /** @param  PersonAddress  $model */
    protected function promote(Model $model): void
    {
        $model->makePrimary();
    }
}
