<?php

declare(strict_types=1);

namespace App\Services\Person;

use App\Models\Admin\PersonContact;
use App\Services\IdentifierService;
use App\Services\Person\Concerns\TypedSlots;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

/**
 * Person contacts (xlr8_admin_person_contacts) — their only write path (DEC-050/053).
 * One row per (person, data type, contact type); see TypedSlots for the Primary rules.
 * contact_detail is formatted by data type: Mobile → 10 digits (+91 / leading 0 removed),
 * Email → lower-case address, Landline/Fax → digits and + ( ) - kept.
 *
 * @extends EntityService<PersonContact>
 */
final class PersonContactService extends EntityService
{
    use TypedSlots;

    protected function model(): string
    {
        return PersonContact::class;
    }

    protected function naturalKey(): array
    {
        return ['person_code', 'data_type', 'contact_type'];
    }

    public function fields(): array
    {
        return [
            Field::reference('person_code', 'xlr8_admin_person', 20, 'person_code')->label(__('org.fields.person_code'))->required()->immutable(),
            Field::choice('data_type', PersonContact::DATA_TYPES)->label(__('org.fields.data_type'))->required(),
            Field::choice('contact_type', PersonContact::CONTACT_TYPES)->label(__('org.fields.contact_type'))
                ->format('One of: '.implode(', ', PersonContact::CONTACT_TYPES).'; blank = Primary if free, else the next free type')
                ->unique(['person_code', 'data_type'], includeTrashed: true),
            Field::make('contact_detail')->label(__('org.fields.contact_detail'))
                ->format('Mobile: 10 digits; Email: lower-case; Landline/Fax: digits')
                ->rules('string', 'max:100',
                    Rule::when(fn (Fluent $in) => $in->data_type === 'Mobile', ['digits:10']),
                    Rule::when(fn (Fluent $in) => $in->data_type === 'Email', ['email']),
                    Rule::when(fn (Fluent $in) => in_array($in->data_type, ['Landline', 'Fax'], true), ['regex:/^\+?[0-9()\- ]{6,20}$/']),
                )->required(),
            Field::flag('is_primary', false)->virtual(),
        ];
    }

    /**
     * @param  PersonContact  $model
     * @param  array<string, mixed>  $input
     */
    public function update(Model $model, array $input): Model
    {
        // contact_detail is formatted by data type, so the stored type applies when none is sent.
        $input += ['data_type' => $model->data_type];

        return parent::update($model, $input);
    }

    protected function derive(array $data, array $input): array
    {
        $detail = $data['contact_detail'] ?? null;
        if (is_string($detail)) {
            $data['contact_detail'] = match ($data['data_type'] ?? null) {
                'Mobile' => app(IdentifierService::class)->cleanMobile($detail) ?? $detail,
                'Email' => mb_strtolower($detail),
                default => preg_replace('/\s+/', ' ', $detail),
            };
        }

        return $data;
    }

    protected function slotField(): string
    {
        return 'contact_type';
    }

    protected function slotGroup(): array
    {
        return ['person_code', 'data_type'];
    }

    protected function slotTypes(): array
    {
        return PersonContact::CONTACT_TYPES;
    }

    /** @param  PersonContact  $model */
    protected function promote(Model $model): void
    {
        $model->makesPrimary();
    }
}
